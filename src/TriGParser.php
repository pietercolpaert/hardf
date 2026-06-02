<?php

declare(strict_types=1);

namespace pietercolpaert\hardf;

/**
 * a clone of the N3Parser class from the N3js code by Ruben Verborgh
 *
 * TriGParser parses Turtle, TriG, N-Quads, N-Triples and N3 to our triple representation (see README.md)
 */
class TriGParser
{
    const RDF_PREFIX = 'http://www.w3.org/1999/02/22-rdf-syntax-ns#';
    const RDF_NIL = self::RDF_PREFIX.'nil';
    const RDF_FIRST = self::RDF_PREFIX.'first';
    const RDF_REST = self::RDF_PREFIX.'rest';
    const RDF_LANG_STRING = self::RDF_PREFIX.'langString';
    const RDF_DIR_LANG_STRING = self::RDF_PREFIX.'dirLangString';
    const RDF_REIFIES = self::RDF_PREFIX.'reifies';
    const QUANTIFIERS_GRAPH = 'urn:n3:quantifiers';

    private $absoluteIRI = '/^[a-z][a-z0-9+.-]*:/i';
    private $schemeAuthority = '/^(?:([a-z][a-z0-9+.-]*:))?(?:\\/\\/[^\\/]*)?/i';
    private $dotSegments = '/(?:^|\\/)\\.\\.?(?:$|[\\/#?])/';

    // The next ID for new blank nodes
    private $blankNodePrefix;
    private $blankNodeCount = 0;

    private $contextStack;
    private $graph;

    private $afterPath;
    private $base;
    private $basePath;
    private $baseRoot;
    private $baseScheme;
    private $callback;
    private $completeLiteral;
    private $error;
    private $explicitQuantifiers;
    private $getContextEndReader;
    private $getPathReader;
    private $inversePredicate;
    private $lexer;
    private $n3Mode;
    private $object;
    private $predicate;
    private $prefix;
    private $prefixes;
    private $prefixCallback;
    private $quantified;
    private $quantifiedPrefix;
    private $readBackwardPath;
    private $readBaseIRI;
    private $readBlankNodeHead;
    private $readBlankNodePunctuation;
    private $readBlankNodeTail;
    private $readDataTypeOrLang;
    private $readDeclarationPunctuation;
    private $readEntity;
    private $readFormulaTail;
    private $readForwardPath;
    private $readGraph;
    private $readListItem;
    private $readListItemDataTypeOrLang;
    private $readNamedGraphLabel;
    private $readNamedGraphBlankLabel;
    private $readObject;
    private $readPath;
    private $readPredicate;
    private $readPredicateAfterBlank;
    private $readPredicateOrNamedGraph;
    private $readPrefix;
    private $readPrefixIRI;
    private $readPunctuation;
    private $readQuadPunctuation;
    private $readQuantifierList;
    private $readQuantifierPunctuation;
    private $readSubject;
    private $readTripleTermSubject;
    private $readTripleTermPredicate;
    private $readTripleTermObject;
    private $readTripleTermObjectDataTypeOrLang;
    private $readTripleTermEnd;
    private $readReifiedTripleSubject;
    private $readReifiedTriplePredicate;
    private $readReifiedTripleObject;
    private $readReifiedTripleObjectDataTypeOrLang;
    private $readReifiedTripleReifierOrEnd;
    private $readReifiedTripleReifier;
    private $readReifiedTripleEnd;
    private $readPredicateAfterReifiedTriple;
    private $readAnnotationPredicate;
    private $readAnnotationObject;
    private $readAnnotationObjectDataTypeOrLang;
    private $readAnnotationPunctuation;
    private $readAnnotationReifier;
    private $readAfterAnnotation;
    private $readVersion;
    private $removeDotSegments;
    private $resolveIRI;
    private $sparqlStyle;
    private $subject;
    private $supportsNamedGraphs;
    private $supportsMessages;
    private $supportsQuads;
    private $supportsReifiedTriples;
    private $triple;
    private $tripleCallback;
    private $tripleTerm;
    private $tripleTermMode;
    private $tripleTermStack;
    private $annotationHadStatement;
    private $annotationReifier;
    private $annotationPendingReifier;
    private $annotationTripleTerm;
    private $annotationGraph;
    private $annotationStack;
    private $messageCounter;

    private $readInTopContext;
    private $readCallback;
    private $blankNodeEndReader;
    private $blankNodeMustBeEmpty;
    private $collectMessages;

    // Constructor
    public function __construct($options = [], $tripleCallback = null, $prefixCallback = null)
    {
        $this->setTripleCallback($tripleCallback);
        $this->setPrefixCallback($prefixCallback);
        $this->contextStack = [];
        $this->graph = null;

        //This will initiate the callback methods
        $this->initReaders();

        // Set the document IRI
        $this->setBase(isset($options['documentIRI']) ? $options['documentIRI'] : null);

        // Set supported features depending on the format
        if (!isset($options['format'])) {
            $options['format'] = '';
        }
        $format = (string) $options['format'];
        $format = strtolower($format);
        $isTurtle = 'turtle' === $format;
        $isTriG = 'trig' === $format;

        $isNTriples = false !== strpos($format, 'triple') ? true : false;
        $isNQuads = false !== strpos($format, 'quad') ? true : false;
        $isN3 = false !== strpos($format, 'n3') ? true : false;
        $this->n3Mode = $isN3;
        $isLineMode = $isNTriples || $isNQuads;
        if (!($this->supportsNamedGraphs = !($isTurtle || $isN3))) {
            $this->readPredicateOrNamedGraph = $this->readPredicate;
        }
        $this->supportsQuads = !($isTurtle || $isTriG || $isNTriples || $isN3);
        $this->supportsReifiedTriples = !$isLineMode;
        $this->supportsMessages = false;
        $this->messageCounter = null;
        $this->collectMessages = !empty($options['messages']);
        // Disable relative IRIs in N-Triples or N-Quads mode
        if ($isLineMode) {
            $this->base = '';
            $this->resolveIRI = function ($token) {
                \call_user_func($this->error, 'Disallowed relative IRI', $token);

                $this->subject = null;

                return $this->callback = function () {};
            };
        }
        $this->blankNodePrefix = null;
        if (isset($options['blankNodePrefix'])) {
            $this->blankNodePrefix = '_:'.preg_replace('/^_:/', '', $options['blankNodePrefix']);
        }

        $this->lexer = isset($options['lexer']) ? $options['lexer'] : new N3Lexer(['lineMode' => $isLineMode, 'n3' => $isN3]);
        // Disable explicit quantifiers by default
        $this->explicitQuantifiers = isset($options['explicitQuantifiers']) ? $options['explicitQuantifiers'] : null;

        // The read callback is the next function to be executed when a token arrives.
        // We start reading in the top context.
        $this->readCallback = $this->readInTopContext;
        $this->sparqlStyle = false;
        $this->prefixes = [];
        $this->prefixes['_'] = isset($this->blankNodePrefix) ? $this->blankNodePrefix : '_:b'.$this->blankNodeCount.'_';
        $this->inversePredicate = false;
        $this->quantified = [];
        $this->tripleTermStack = [];
        $this->annotationStack = [];
    }

    // ## Private class methods
    // ### `_resetBlankNodeIds` restarts blank node identification
    public function _resetBlankNodeIds()
    {
        $this->blankNodeCount = 0;
    }

    private function readLanguageTag($token): ?string
    {
        if (preg_match('/--/', $token['value']) && !preg_match('/--(?:ltr|rtl)$/', $token['value'])) {
            \call_user_func($this->error, 'Detected illegal base direction in language tag', $token);

            return null;
        }

        $language = strtolower($token['value']);
        $languageOnly = preg_replace('/--(?:ltr|rtl)$/', '', $language);
        foreach (explode('-', $languageOnly) as $subtag) {
            if (\strlen($subtag) > 8) {
                \call_user_func($this->error, 'Detected language tag with subtag longer than 8 characters', $token);

                return null;
            }
        }

        return $language;
    }

    // ### `_setBase` sets the base IRI to resolve relative IRIs
    private function setBase($baseIRI = null)
    {
        if (!$baseIRI) {
            $this->base = null;
        } else {
            // Remove fragment if present
            $fragmentPos = strpos($baseIRI, '#');
            if (false !== $fragmentPos) {
                $baseIRI = substr($baseIRI, 0, $fragmentPos);
            }
            // Set base IRI and its components
            $this->base = $baseIRI;
            $this->basePath = false === strpos($baseIRI, '/') ? $baseIRI : preg_replace('/[^\/?]*(?:\?.*)?$/', '', $baseIRI);
            preg_match($this->schemeAuthority, $baseIRI, $matches);
            $this->baseRoot = isset($matches[0]) ? $matches[0] : '';
            $this->baseScheme = isset($matches[1]) ? $matches[1] : '';
        }
    }

    // ### `_saveContext` stores the current parsing context
    // when entering a new scope (list, blank node, formula)
    private function saveContext($type, $graph, $subject, $predicate, $object)
    {
        $n3Mode = $this->n3Mode ?: null;
        array_push($this->contextStack, [
            'subject' => $subject, 'predicate' => $predicate, 'object' => $object,
            'graph' => $graph, 'type' => $type,
            'inverse' => $n3Mode ? $this->inversePredicate : false,
            'blankPrefix' => $n3Mode ? $this->prefixes['_'] : '',
            'quantified' => $n3Mode ? $this->quantified : null,
        ]);
        // The settings below only apply to N3 streams
        if ($n3Mode) {
            // Every new scope resets the predicate direction
            $this->inversePredicate = false;
            // In N3, blank nodes are scoped to a formula
            // (using a dot as separator, as a blank node label cannot start with it)
            $this->prefixes['_'] = $this->graph.'.';
            // Quantifiers are scoped to a formula TODO: is this correct?
            $this->quantified = $this->quantified;
        }
    }

    // ### `_restoreContext` restores the parent context
    // when leaving a scope (list, blank node, formula)
    private function restoreContext()
    {
        $context = array_pop($this->contextStack);
        $n3Mode = $this->n3Mode;
        $this->subject = $context['subject'];
        $this->predicate = $context['predicate'];
        $this->object = $context['object'];
        $this->graph = $context['graph'];
        // The settings below only apply to N3 streams
        if ($n3Mode) {
            $this->inversePredicate = $context['inverse'];
            $this->prefixes['_'] = $context['blankPrefix'];
            $this->quantified = $context['quantified'];
        }
    }

    private function initReaders()
    {
        // ### `_readInTopContext` reads a token when in the top context
        $this->readInTopContext = function ($token) {
            if (!isset($token['type'])) {
                $token['type'] = '';
            }
            switch ($token['type']) {
                // If an EOF token arrives in the top context, signal that we're done
                case 'eof':
                if (null !== $this->graph) {
                    return \call_user_func($this->error, 'Unclosed graph', $token);
                }
                unset($this->prefixes['_']);
                if ($this->callback) {
                    return \call_user_func($this->callback, null, null, $this->prefixes, $this->messageCounter);
                }
                // It could be a prefix declaration
                // no break
                case 'PREFIX':
                $this->sparqlStyle = true;
                // no break
                case '@prefix':
                return $this->readPrefix;
                // It could be a base declaration
                case 'BASE':
                $this->sparqlStyle = true;
                // no break
                case '@base':
                return $this->readBaseIRI;
                case 'VERSION':
                $this->sparqlStyle = true;
                // no break
                case '@version':
                return $this->readVersion;
                case 'MESSAGE':
                case '@message':
                if (!$this->supportsMessages) {
                    return \call_user_func($this->error, 'Unexpected "'.$token['type'].'"', $token);
                }

                if (null === $this->messageCounter) {
                    $this->messageCounter = 1;
                }
                ++$this->messageCounter;
                $this->prefixes['_'] = isset($this->blankNodePrefix) ? $this->blankNodePrefix : '_:b'.$this->blankNodeCount++.'_';

                return 'MESSAGE' === $token['type'] ? $this->readInTopContext : $this->readDeclarationPunctuation;
                // It could be a graph
                case '{':
                if ($this->supportsNamedGraphs) {
                    $this->graph = '';
                    $this->subject = null;

                    return $this->readSubject;
                }
                // no break
                case 'GRAPH':
                if ($this->supportsNamedGraphs) {
                    return $this->readNamedGraphLabel;
                }
                // Otherwise, the next token must be a subject
                // no break
                default:
                return \call_user_func($this->readSubject, $token);
            }
        };

        /*
         * reads an IRI, prefixed name, blank node, or variable
         *
         * @return null|string|object
         */
        $this->readEntity = function ($token, $quantifier = null) {
            $value = null;
            switch ($token['type']) {
                // Read a relative or absolute IRI
                case 'IRI':
                case 'typeIRI':
                    if (null === $this->base || preg_match($this->absoluteIRI, $token['value'])) {
                        $value = $token['value'];
                    } else {
                        $value = \call_user_func($this->resolveIRI, $token);
                    }
                    break;
                    // Read a blank node or prefixed name
                case 'type':
                case 'blank':
                case 'prefixed':
                    if (!isset($this->prefixes[$token['prefix']])) {
                        return \call_user_func($this->error, 'Undefined prefix "'.$token['prefix'].':"', $token);
                    }

                    $prefix = $this->prefixes[$token['prefix']];
                    $value = $prefix.$token['value'];
                    break;
                    // Read a variable
                case 'var':
                    return $token['value'];
                    // Everything else is not an entity
                default:
                    return \call_user_func($this->error, 'Expected entity but got '.$token['type'], $token);
            }
            // In N3 mode, replace the entity if it is quantified
            if (!isset($quantifier) && $this->n3Mode && isset($this->quantified[$value])) {
                $value = $this->quantified[$value];
            }

            return $value;
        };

        // ### `_readSubject` reads a triple's subject
        $this->readSubject = function ($token) {
            $this->predicate = null;
            switch ($token['type']) {
                case '[':
                    // Start a new triple with a new blank node as subject
                    $this->saveContext('blank', $this->graph, $this->subject = '_:b'.$this->blankNodeCount++, null, null);

                    return $this->readBlankNodeHead;
                case '(':;
                    // Start a new list
                    $this->saveContext('list', $this->graph, self::RDF_NIL, null, null);
                    $this->subject = null;

                    return $this->readListItem;
                case '{':
                    // Start a new formula
                    if (!$this->n3Mode) {
                        return \call_user_func($this->error, 'Unexpected graph', $token);
                    }
                    $this->saveContext('formula', $this->graph, $this->graph = '_:b'.$this->blankNodeCount++, null, null);

                    return $this->readSubject;
                case '}':
                    // No subject; the graph in which we are reading is closed instead
                    return \call_user_func($this->readPunctuation, $token);
                case '@forSome':
                    if (!$this->n3Mode) {
                        return \call_user_func($this->error, 'Unexpected "@forSome"', $token);
                    }
                    $this->subject = null;
                    $this->predicate = 'http://www.w3.org/2000/10/swap/reify#forSome';
                    $this->quantifiedPrefix = '_:b';

                    return $this->readQuantifierList;
                case '@forAll':
                    if (!$this->n3Mode) {
                        return \call_user_func($this->error, 'Unexpected "@forAll"', $token);
                    }
                    $this->subject = null;
                    $this->predicate = 'http://www.w3.org/2000/10/swap/reify#forAll';
                    $this->quantifiedPrefix = '?b-';

                    return $this->readQuantifierList;
                case 'tripletermstart':
                    return \call_user_func($this->error, 'Disallowed triple term as subject', $token);
                case 'reifiedtriplestart':
                    if (!$this->supportsReifiedTriples) {
                        return \call_user_func($this->error, 'Disallowed reified triple', $token);
                    }
                    $this->saveContext('reifiedTripleSubject', $this->graph, null, null, null);
                    $this->tripleTermMode = 'reifiedSubject';
                    $this->tripleTerm = ['subject' => null, 'predicate' => null, 'object' => null];

                    return $this->readReifiedTripleSubject;
                default:
                    // Read the subject entity
                    $this->subject = \call_user_func($this->readEntity, $token);
                    if (null == $this->subject) {
                        throw $this->getNoBaseUriException('subject', $token['line']);
                    }
                    // In N3 mode, the subject might be a path
                    if ($this->n3Mode) {
                        return \call_user_func($this->getPathReader, $this->readPredicateOrNamedGraph);
                    }
            }

            // The next token must be a predicate,
            // or, if the subject was actually a graph IRI, a named graph
            return $this->readPredicateOrNamedGraph;
        };

        // ### `_readPredicate` reads a triple's predicate
        $this->readPredicate = function ($token) {
            $type = $token['type'];
            switch ($type) {
                case 'inverse':
                    $this->inversePredicate = true;
                    // no break
                case 'abbreviation':
                    $this->predicate = $token['value'];
                    break;
                case '.':
                case ']':
                case '}':
                    // Expected predicate didn't come, must have been trailing semicolon
                    if (null === $this->predicate) {
                        return \call_user_func($this->error, 'Unexpected '.$type, $token);
                    }
                    $this->subject = null;

                    return ']' === $type ? \call_user_func($this->readBlankNodeTail, $token) : \call_user_func($this->readPunctuation, $token);
                case ';':
                    // Extra semicolons can be safely ignored
                    return $this->readPredicate;
                case 'blank':
                    if (!$this->n3Mode) {
                        return \call_user_func($this->error, 'Disallowed blank node as predicate', $token);
                    }
                        // no break
                default:
                    $this->predicate = \call_user_func($this->readEntity, $token);
                    if (null == $this->predicate) {
                        throw $this->getNoBaseUriException('predicate', $token['line']);
                    }
            }
            // The next token must be an object
            return $this->readObject;
        };

        // ### `_readObject` reads a triple's object
        $this->readObject = function ($token) {
            switch ($token['type']) {
                case 'literal':
                $this->object = $token['value'];

                return $this->readDataTypeOrLang;
                case 'tripletermstart':
                $this->saveContext('tripleTerm', $this->graph, $this->subject, $this->predicate, null);
                $this->tripleTermMode = 'explicitObject';
                $this->tripleTerm = ['subject' => null, 'predicate' => null, 'object' => null];

                return $this->readTripleTermSubject;
                case 'reifiedtriplestart':
                if (!$this->supportsReifiedTriples) {
                    return \call_user_func($this->error, 'Disallowed reified triple', $token);
                }
                $this->saveContext('reifiedTripleObject', $this->graph, $this->subject, $this->predicate, null);
                $this->tripleTermMode = 'reifiedObject';
                $this->tripleTerm = ['subject' => null, 'predicate' => null, 'object' => null];

                return $this->readReifiedTripleSubject;
                case '[':
                // Start a new triple with a new blank node as subject
                $this->saveContext('blank', $this->graph, $this->subject, $this->predicate,
                $this->subject = '_:b'.$this->blankNodeCount++);

                return $this->readBlankNodeHead;
                case '(':
                // Start a new list
                $this->saveContext('list', $this->graph, $this->subject, $this->predicate, self::RDF_NIL);
                $this->subject = null;

                return $this->readListItem;
                case '{':
                // Start a new formula
                if (!$this->n3Mode) {
                    return \call_user_func($this->error, 'Unexpected graph', $token);
                }
                $this->saveContext('formula', $this->graph, $this->subject, $this->predicate,
                $this->graph = '_:b'.$this->blankNodeCount++);

                return $this->readSubject;
                default:
                // Read the object entity
                $this->object = \call_user_func($this->readEntity, $token);
                if (null == $this->object) {
                    throw $this->getNoBaseUriException('object', $token['line']);
                }
                // In N3 mode, the object might be a path
                if ($this->n3Mode) {
                    return \call_user_func($this->getPathReader, \call_user_func($this->getContextEndReader));
                }
            }

            return \call_user_func($this->getContextEndReader);
        };

        // ### `_readPredicateOrNamedGraph` reads a triple's predicate, or a named graph
        $this->readPredicateOrNamedGraph = function ($token) {
            return '{' === $token['type'] ? \call_user_func($this->readGraph, $token) : \call_user_func($this->readPredicate, $token);
        };

        // ### `_readGraph` reads a graph
        $this->readGraph = function ($token) {
            if ('{' !== $token['type']) {
                return \call_user_func($this->error, 'Expected graph but got '.$token['type'], $token);
            }
            // The "subject" we read is actually the GRAPH's label
            $this->graph = $this->subject;
            $this->subject = null;

            return $this->readSubject;
        };

        // ### `_readBlankNodeHead` reads the head of a blank node
        $this->readBlankNodeHead = function ($token) {
            if (']' === $token['type']) {
                $this->subject = null;
                $this->blankNodeMustBeEmpty = null;

                return \call_user_func($this->readBlankNodeTail, $token);
            } else {
                if ($this->blankNodeMustBeEmpty) {
                    $this->blankNodeMustBeEmpty = null;

                    return \call_user_func($this->error, 'Disallowed compound blank node expression', $token);
                }
                $this->predicate = null;

                return \call_user_func($this->readPredicate, $token);
            }
        };

        // ### `_readBlankNodeTail` reads the end of a blank node
        $this->readBlankNodeTail = function ($token) {
            if (']' !== $token['type']) {
                return \call_user_func($this->readBlankNodePunctuation, $token);
            }

            // Store blank node triple
            if (null !== $this->subject) {
                \call_user_func($this->triple, $this->subject, $this->predicate, $this->object, $this->graph);
            }

            // Restore the parent context containing this blank node
            $this->blankNodeMustBeEmpty = null;
            $empty = null === $this->predicate;
            $this->restoreContext();
            if (isset($this->blankNodeEndReader)) {
                $next = $this->blankNodeEndReader;
                $this->blankNodeEndReader = null;

                return \call_user_func($next);
            }
            // If the blank node was the subject, continue reading the predicate
            if (null === $this->object) {
                // If the blank node was empty, it could be a named graph label
                return $empty ? $this->readPredicateOrNamedGraph : $this->readPredicateAfterBlank;
            }
            // If the blank node was the object, restore previous context and read punctuation
            else {
                return \call_user_func($this->getContextEndReader);
            }
        };

        // ### `_readPredicateAfterBlank` reads a predicate after an anonymous blank node
        $this->readPredicateAfterBlank = function ($token) {
            // If a dot follows a blank node in top context, there is no predicate
            if ('.' === $token['type'] && 0 === \count($this->contextStack)) {
                $this->subject = null; // cancel the current triple

                return \call_user_func($this->readPunctuation, $token);
            }

            // Inside a named graph, a sole blank node property list can end right before the closing brace.
            if ('}' === $token['type'] && null !== $this->graph) {
                $this->subject = null;

                return \call_user_func($this->readPunctuation, $token);
            }

            return \call_user_func($this->readPredicate, $token);
        };

        // ### `_readListItem` reads items from a list
        $this->readListItem = function ($token) {
            $item = null;                        // The item of the list
            $list = null;                        // The list itself
            $prevList = $this->subject;          // The previous list that contains this list
            $stack = &$this->contextStack;        // The stack of parent contexts
            $parent = &$stack[\count($stack) - 1]; // The parent containing the current list
            $next = $this->readListItem;         // The next function to execute
            $itemComplete = true;                // Whether the item has been read fully

            switch ($token['type']) {
                case '[':
                    // Stack the current list triple and start a new triple with a blank node as subject
                    $list = '_:b'.$this->blankNodeCount++;
                    $item = '_:b'.$this->blankNodeCount++;
                    $this->subject = $item;
                    $this->saveContext('blank', $this->graph, $list, self::RDF_FIRST, $this->subject);
                    $next = $this->readBlankNodeHead;
                    break;
                case '(':
                    // Stack the current list triple and start a new list
                    $this->saveContext('list', $this->graph, $list = '_:b'.$this->blankNodeCount++, self::RDF_FIRST, self::RDF_NIL);
                    $this->subject = null;
                    break;
                case ')':
                    // Closing the list; restore the parent context
                    $this->restoreContext();
                    // If this list is contained within a parent list, return the membership triple here.
                    // This will be `<parent list element> rdf:first <this list>.`.
                    if (0 !== \count($stack) && 'list' === $stack[\count($stack) - 1]['type']) {
                        \call_user_func($this->triple, $this->subject, $this->predicate, $this->object, $this->graph);
                    }
                    // Was this list the parent's subject?
                    if (null === $this->predicate) {
                        // The next token is the predicate
                        $next = $this->readPredicate;
                        // No list tail if this was an empty list
                        if (self::RDF_NIL === $this->subject) {
                            return $next;
                        }
                    }
                    // The list was in the parent context's object
                    else {
                        $next = \call_user_func($this->getContextEndReader);
                        // No list tail if this was an empty list
                        if (self::RDF_NIL === $this->object) {
                            return $next;
                        }
                    }
                    // Close the list by making the head nil
                    $list = self::RDF_NIL;
                    break;
                case 'literal':
                    $item = $token['value'];
                    $itemComplete = false; // Can still have a datatype or language
                    $next = $this->readListItemDataTypeOrLang;
                    break;
                case 'reifiedtriplestart':
                    if (!$this->supportsReifiedTriples) {
                        return \call_user_func($this->error, 'Disallowed reified triple', $token);
                    }
                    if (null === $list) {
                        $list = '_:b'.$this->blankNodeCount++;
                        $this->subject = $list;
                    }
                    if (null === $prevList) {
                        if (null === $parent['predicate']) {
                            $parent['subject'] = $list;
                        } else {
                            $parent['object'] = $list;
                        }
                    } else {
                        \call_user_func($this->triple, $prevList, self::RDF_REST, $list, $this->graph);
                    }
                    $this->saveContext('reifiedListItem', $this->graph, $list, self::RDF_FIRST, null);
                    $this->tripleTermMode = 'reifiedListItem';
                    $this->tripleTerm = ['subject' => null, 'predicate' => null, 'object' => null];

                    return $this->readReifiedTripleSubject;
                default:
                    $item = \call_user_func($this->readEntity, $token);
                    if (null == $item) {
                        throw $this->getNoBaseUriException('list item', $token['line']);
                    }
            }

            // Create a new blank node if no item head was assigned yet
            if (null === $list) {
                $list = '_:b'.$this->blankNodeCount++;
                $this->subject = $list;
            }
            // Is this the first element of the list?
            if (null === $prevList) {
                // This list is either the subject or the object of its parent
                if (null === $parent['predicate']) {
                    $parent['subject'] = $list;
                } else {
                    $parent['object'] = $list;
                }
            } else {
                // Continue the previous list with the current list
                \call_user_func($this->triple, $prevList, self::RDF_REST, $list, $this->graph);
            }
            // Add the item's value
            if (null !== $item) {
                // In N3 mode, the item might be a path
                if ($this->n3Mode && ('IRI' === $token['type'] || 'prefixed' === $token['type'])) {
                    // Create a new context to add the item's path
                    $this->saveContext('item', $this->graph, $list, self::RDF_FIRST, $item);
                    $this->subject = $item;
                    $this->predicate = null;
                    // _readPath will restore the context and output the item
                    return \call_user_func($this->getPathReader, $this->readListItem);
                }
                // Output the item if it is complete
                if ($itemComplete) {
                    \call_user_func($this->triple, $list, self::RDF_FIRST, $item, $this->graph);
                }
                // Otherwise, save it for completion
                else {
                    $this->object = $item;
                }
            }

            return $next;
        };

        // ### `_readDataTypeOrLang` reads an _optional_ data type or language
        $this->readDataTypeOrLang = function ($token) {
            return \call_user_func($this->completeLiteral, $token, false);
        };

        // ### `_readListItemDataTypeOrLang` reads an _optional_ data type or language in a list
        $this->readListItemDataTypeOrLang = function ($token) {
            return \call_user_func($this->completeLiteral, $token, true);
        };

        // ### `_completeLiteral` completes the object with a data type or language
        $this->completeLiteral = function ($token, $listItem) {
            $suffix = false;
            switch ($token['type']) {
                // Add a "^^type" suffix for types (IRIs and blank nodes)
                case 'type':
                case 'typeIRI':
                    $suffix = true;
                    $type = \call_user_func($this->readEntity, $token);
                    if (self::RDF_LANG_STRING === $type || self::RDF_DIR_LANG_STRING === $type) {
                        return \call_user_func($this->error, 'Detected illegal (directional) languaged-tagged string with explicit datatype', $token);
                    }
                    $this->object .= '^^'.$type;
                    break;
                    // Add an "@lang" suffix for language tags
                case 'langcode':
                    $language = $this->readLanguageTag($token);
                    if (null === $language) {
                        return null;
                    }
                    $suffix = true;
                    $this->object .= '@'.$language;
                    break;
            }
            // If this literal was part of a list, write the item
            // (we could also check the context stack, but passing in a flag is faster)
            if ($listItem) {
                \call_user_func($this->triple, $this->subject, self::RDF_FIRST, $this->object, $this->graph);
            }
            // Continue with the rest of the input
            if ($suffix) {
                return \call_user_func($this->getContextEndReader);
            } else {
                $this->readCallback = \call_user_func($this->getContextEndReader);

                return \call_user_func($this->readCallback, $token);
            }
        };

        $makeTripleTerm = function ($term) {
            return [
                'type' => 'TripleTerm',
                'subject' => $term['subject'],
                'predicate' => $term['predicate'],
                'object' => $term['object'],
            ];
        };

        $completeTripleTerm = function ($token) use ($makeTripleTerm) {
            if ('tripletermend' !== $token['type']) {
                return \call_user_func($this->error, 'Expected triple term end but got '.$token['type'], $token);
            }

            $term = \call_user_func($makeTripleTerm, $this->tripleTerm);

            if (\count($this->tripleTermStack)) {
                $frame = array_pop($this->tripleTermStack);
                $this->tripleTerm = $frame['term'];
                $this->tripleTermMode = $frame['mode'];
                $this->tripleTerm[$frame['position']] = $term;

                return 'subject' === $frame['position'] ? $this->readReifiedTriplePredicate :
                    ('reified' === substr($this->tripleTermMode, 0, 7) ? $this->readReifiedTripleReifierOrEnd : $this->readTripleTermEnd);
            }

            $mode = $this->tripleTermMode;
            $this->restoreContext();
            $this->object = $term;

            return ('annotationExplicitObject' === $mode || 'annotationReifiedObject' === $mode) ?
                $this->readAnnotationPunctuation : \call_user_func($this->getContextEndReader);
        };

        $this->readTripleTermSubject = function ($token) {
            if ('tripletermstart' === $token['type']) {
                return \call_user_func($this->error, 'Disallowed triple term as subject', $token);
            }
            if ('[' === $token['type']) {
                $id = '_:b'.$this->blankNodeCount++;
                $this->blankNodeMustBeEmpty = true;
                $this->blankNodeEndReader = function () use ($id) {
                    $this->tripleTerm['subject'] = $id;

                    return $this->readTripleTermPredicate;
                };
                $this->saveContext('blank', $this->graph, $this->subject, $this->predicate, $this->subject = $id);

                return $this->readBlankNodeHead;
            }
            $this->tripleTerm['subject'] = \call_user_func($this->readEntity, $token);
            if (null == $this->tripleTerm['subject']) {
                throw $this->getNoBaseUriException('triple term subject', $token['line']);
            }

            return $this->readTripleTermPredicate;
        };

        $this->readTripleTermPredicate = function ($token) {
            if ('abbreviation' === $token['type']) {
                $this->tripleTerm['predicate'] = $token['value'];
            } else {
                $this->tripleTerm['predicate'] = \call_user_func($this->readEntity, $token);
                if (null == $this->tripleTerm['predicate']) {
                    throw $this->getNoBaseUriException('triple term predicate', $token['line']);
                }
            }

            return $this->readTripleTermObject;
        };

        $this->readTripleTermObject = function ($token) {
            switch ($token['type']) {
                case 'literal':
                    $this->tripleTerm['object'] = $token['value'];

                    return $this->readTripleTermObjectDataTypeOrLang;
                case 'tripletermstart':
                    $this->tripleTermStack[] = ['term' => $this->tripleTerm, 'mode' => $this->tripleTermMode, 'position' => 'object'];
                    $this->tripleTermMode = 'explicitNested';
                    $this->tripleTerm = ['subject' => null, 'predicate' => null, 'object' => null];

                    return $this->readTripleTermSubject;
                case 'reifiedtriplestart':
                    if (!$this->supportsReifiedTriples) {
                        return \call_user_func($this->error, 'Disallowed reified triple', $token);
                    }
                    $this->tripleTermStack[] = ['term' => $this->tripleTerm, 'mode' => $this->tripleTermMode, 'position' => 'object'];
                    $this->tripleTermMode = 'reifiedNested';
                    $this->tripleTerm = ['subject' => null, 'predicate' => null, 'object' => null];

                    return $this->readReifiedTripleSubject;
                case '[':
                    $id = '_:b'.$this->blankNodeCount++;
                    $this->blankNodeMustBeEmpty = true;
                    $this->blankNodeEndReader = function () use ($id) {
                        $this->tripleTerm['object'] = $id;

                        return $this->readTripleTermEnd;
                    };
                    $this->saveContext('blank', $this->graph, $this->subject, $this->predicate, $this->object = $id);

                    return $this->readBlankNodeHead;
                default:
                    $this->tripleTerm['object'] = \call_user_func($this->readEntity, $token);
                    if (null == $this->tripleTerm['object']) {
                        throw $this->getNoBaseUriException('triple term object', $token['line']);
                    }

                    return $this->readTripleTermEnd;
            }
        };

        $this->readTripleTermObjectDataTypeOrLang = function ($token) use ($completeTripleTerm) {
            switch ($token['type']) {
                case 'type':
                case 'typeIRI':
                    $type = \call_user_func($this->readEntity, $token);
                    if (self::RDF_LANG_STRING === $type || self::RDF_DIR_LANG_STRING === $type) {
                        return \call_user_func($this->error, 'Detected illegal (directional) languaged-tagged string with explicit datatype', $token);
                    }
                    $this->tripleTerm['object'] .= '^^'.$type;

                    return $this->readTripleTermEnd;
                case 'langcode':
                    $language = $this->readLanguageTag($token);
                    if (null === $language) {
                        return null;
                    }
                    $this->tripleTerm['object'] .= '@'.$language;

                    return $this->readTripleTermEnd;
                default:
                    return \call_user_func($completeTripleTerm, $token);
            }
        };

        $this->readTripleTermEnd = function ($token) use ($completeTripleTerm) {
            return \call_user_func($completeTripleTerm, $token);
        };

        $completeReifiedTriple = function ($token, $reifier = null) use ($makeTripleTerm) {
            if ('reifiedtripleend' !== $token['type']) {
                return \call_user_func($this->error, 'Expected >> but got '.$token['type'], $token);
            }
            if (null === $reifier) {
                $reifier = '_:b'.$this->blankNodeCount++;
            }

            $term = \call_user_func($makeTripleTerm, $this->tripleTerm);
            \call_user_func($this->triple, $reifier, self::RDF_REIFIES, $term, $this->graph);

            if (\count($this->tripleTermStack)) {
                $frame = array_pop($this->tripleTermStack);
                $this->tripleTerm = $frame['term'];
                $this->tripleTermMode = $frame['mode'];
                $this->tripleTerm[$frame['position']] = $reifier;

                return 'subject' === $frame['position'] ? $this->readReifiedTriplePredicate :
                    ('reified' === substr($this->tripleTermMode, 0, 7) ? $this->readReifiedTripleReifierOrEnd : $this->readTripleTermEnd);
            }

            $mode = $this->tripleTermMode;
            $this->restoreContext();
            if ('reifiedSubject' === $mode) {
                $this->subject = $reifier;

                return $this->readPredicateAfterReifiedTriple;
            }
            if ('reifiedListItem' === $mode) {
                $this->object = $reifier;
                \call_user_func($this->triple, $this->subject, $this->predicate, $this->object, $this->graph);

                return $this->readListItem;
            }
            if ('annotationReifiedObject' === $mode) {
                $this->object = $reifier;

                return $this->readAnnotationPunctuation;
            }

            $this->object = $reifier;

            return \call_user_func($this->getContextEndReader);
        };

        $this->readReifiedTripleSubject = function ($token) {
            if ('tripletermstart' === $token['type']) {
                return \call_user_func($this->error, 'Disallowed triple term as subject', $token);
            }
            if ('reifiedtriplestart' === $token['type']) {
                $this->tripleTermStack[] = ['term' => $this->tripleTerm, 'mode' => $this->tripleTermMode, 'position' => 'subject'];
                $this->tripleTermMode = 'reifiedNested';
                $this->tripleTerm = ['subject' => null, 'predicate' => null, 'object' => null];

                return $this->readReifiedTripleSubject;
            }
            if ('[' === $token['type']) {
                $id = '_:b'.$this->blankNodeCount++;
                $this->blankNodeMustBeEmpty = true;
                $this->blankNodeEndReader = function () use ($id) {
                    $this->tripleTerm['subject'] = $id;

                    return $this->readReifiedTriplePredicate;
                };
                $this->saveContext('blank', $this->graph, $this->subject, $this->predicate, $this->subject = $id);

                return $this->readBlankNodeHead;
            }

            $this->tripleTerm['subject'] = \call_user_func($this->readEntity, $token);
            if (null == $this->tripleTerm['subject']) {
                throw $this->getNoBaseUriException('reified triple subject', $token['line']);
            }

            return $this->readReifiedTriplePredicate;
        };

        $this->readReifiedTriplePredicate = function ($token) {
            if ('reifiedtriplestart' === $token['type'] || 'tripletermstart' === $token['type']) {
                return \call_user_func($this->error, 'Expected entity but got <<', $token);
            }
            if ('blank' === $token['type']) {
                return \call_user_func($this->error, 'Disallowed blank node as reified triple predicate', $token);
            }
            if ('abbreviation' === $token['type']) {
                $this->tripleTerm['predicate'] = $token['value'];
            } else {
                $this->tripleTerm['predicate'] = \call_user_func($this->readEntity, $token);
                if (null == $this->tripleTerm['predicate']) {
                    throw $this->getNoBaseUriException('reified triple predicate', $token['line']);
                }
            }

            return $this->readReifiedTripleObject;
        };

        $this->readReifiedTripleObject = function ($token) {
            switch ($token['type']) {
                case 'literal':
                    $this->tripleTerm['object'] = $token['value'];

                    return $this->readReifiedTripleObjectDataTypeOrLang;
                case 'tripletermstart':
                    $this->tripleTermStack[] = ['term' => $this->tripleTerm, 'mode' => $this->tripleTermMode, 'position' => 'object'];
                    $this->tripleTermMode = 'explicitNested';
                    $this->tripleTerm = ['subject' => null, 'predicate' => null, 'object' => null];

                    return $this->readTripleTermSubject;
                case 'reifiedtriplestart':
                    if (!$this->supportsReifiedTriples) {
                        return \call_user_func($this->error, 'Disallowed reified triple', $token);
                    }
                    $this->tripleTermStack[] = ['term' => $this->tripleTerm, 'mode' => $this->tripleTermMode, 'position' => 'object'];
                    $this->tripleTermMode = 'reifiedNested';
                    $this->tripleTerm = ['subject' => null, 'predicate' => null, 'object' => null];

                    return $this->readReifiedTripleSubject;
                case '[':
                    $id = '_:b'.$this->blankNodeCount++;
                    $this->blankNodeMustBeEmpty = true;
                    $this->blankNodeEndReader = function () use ($id) {
                        $this->tripleTerm['object'] = $id;

                        return $this->readReifiedTripleReifierOrEnd;
                    };
                    $this->saveContext('blank', $this->graph, $this->subject, $this->predicate, $this->object = $id);

                    return $this->readBlankNodeHead;
                default:
                    $this->tripleTerm['object'] = \call_user_func($this->readEntity, $token);
                    if (null == $this->tripleTerm['object']) {
                        throw $this->getNoBaseUriException('reified triple object', $token['line']);
                    }

                    return $this->readReifiedTripleReifierOrEnd;
            }
        };

        $this->readReifiedTripleObjectDataTypeOrLang = function ($token) use ($completeReifiedTriple) {
            switch ($token['type']) {
                case 'type':
                case 'typeIRI':
                    $type = \call_user_func($this->readEntity, $token);
                    if (self::RDF_LANG_STRING === $type || self::RDF_DIR_LANG_STRING === $type) {
                        return \call_user_func($this->error, 'Detected illegal (directional) languaged-tagged string with explicit datatype', $token);
                    }
                    $this->tripleTerm['object'] .= '^^'.$type;

                    return $this->readReifiedTripleReifierOrEnd;
                case 'langcode':
                    $language = $this->readLanguageTag($token);
                    if (null === $language) {
                        return null;
                    }
                    $this->tripleTerm['object'] .= '@'.$language;

                    return $this->readReifiedTripleReifierOrEnd;
                default:
                    return \call_user_func($completeReifiedTriple, $token);
            }
        };

        $this->readReifiedTripleReifierOrEnd = function ($token) use ($completeReifiedTriple) {
            if ('~' === $token['type']) {
                return $this->readReifiedTripleReifier;
            }

            return \call_user_func($completeReifiedTriple, $token);
        };

        $this->readReifiedTripleReifier = function ($token) {
            if ('reifiedtripleend' === $token['type']) {
                $this->object = null;

                return \call_user_func($this->readReifiedTripleEnd, $token);
            }

            $this->object = \call_user_func($this->readEntity, $token);
            if (null == $this->object) {
                throw $this->getNoBaseUriException('reified triple reifier', $token['line']);
            }

            return $this->readReifiedTripleEnd;
        };

        $this->readReifiedTripleEnd = function ($token) use ($completeReifiedTriple) {
            return \call_user_func($completeReifiedTriple, $token, $this->object);
        };

        $this->readPredicateAfterReifiedTriple = function ($token) {
            if ('.' === $token['type'] && 0 === \count($this->contextStack)) {
                $this->subject = null;

                return $this->readInTopContext;
            }

            return \call_user_func($this->readPredicate, $token);
        };

        $startAnnotation = function ($token, $reifier = null) {
            if ('annotationstart' !== $token['type']) {
                return \call_user_func($this->error, 'Expected annotation syntax opening', $token);
            }
            $this->annotationReifier = $reifier ?: '_:b'.$this->blankNodeCount++;
            $this->annotationHadStatement = false;
            if (!$this->annotationPendingReifier) {
                \call_user_func($this->triple, $this->annotationReifier, self::RDF_REIFIES, $this->annotationTripleTerm, $this->annotationGraph);
            }
            $this->annotationPendingReifier = false;

            return $this->readAnnotationPredicate;
        };

        $resumeParentAnnotation = function ($token) {
            $frame = array_pop($this->annotationStack);
            $this->annotationHadStatement = $frame['hadStatement'];
            $this->annotationReifier = $frame['reifier'];
            $this->annotationPendingReifier = $frame['pendingReifier'];
            $this->annotationTripleTerm = $frame['tripleTerm'];
            $this->annotationGraph = $frame['graph'];

            if (';' === $token['type']) {
                return $this->readAnnotationPredicate;
            }
            if ('annotationend' === $token['type']) {
                return $this->readAfterAnnotation;
            }

            return \call_user_func($this->error, 'Expected annotation punctuation', $token);
        };

        $readCompletedAnnotationStatement = function ($token, $statementTripleTerm) use ($startAnnotation) {
            if (';' === $token['type']) {
                return $this->readAnnotationPredicate;
            }
            if ('annotationend' === $token['type']) {
                return $this->readAfterAnnotation;
            }
            if ('~' === $token['type'] || 'annotationstart' === $token['type']) {
                $this->annotationStack[] = [
                    'hadStatement' => $this->annotationHadStatement,
                    'reifier' => $this->annotationReifier,
                    'pendingReifier' => $this->annotationPendingReifier,
                    'tripleTerm' => $this->annotationTripleTerm,
                    'graph' => $this->annotationGraph,
                ];
                $this->annotationTripleTerm = $statementTripleTerm;
                $this->annotationPendingReifier = false;
                if ('~' === $token['type']) {
                    return $this->readAnnotationReifier;
                }

                return \call_user_func($startAnnotation, $token);
            }

            return \call_user_func($this->error, 'Expected annotation punctuation to follow "'.$this->object.'"', $token);
        };

        $this->readAnnotationPredicate = function ($token) {
            switch ($token['type']) {
                case 'annotationend':
                    if (!$this->annotationHadStatement) {
                        return \call_user_func($this->error, 'Annotation block can not be empty', $token);
                    }

                    return $this->readAfterAnnotation;
                case ';':
                    if (!$this->annotationHadStatement) {
                        return \call_user_func($this->error, 'Expected entity but got '.$token['type'], $token);
                    }

                    return $this->readAnnotationPredicate;
                default:
                    $this->predicate = \call_user_func($this->readEntity, $token);
                    if (null == $this->predicate) {
                        throw $this->getNoBaseUriException('annotation predicate', $token['line']);
                    }

                    return $this->readAnnotationObject;
            }
        };

        $this->readAnnotationObject = function ($token) {
            switch ($token['type']) {
                case 'literal':
                    $this->object = $token['value'];

                    return $this->readAnnotationObjectDataTypeOrLang;
                case 'tripletermstart':
                    $this->saveContext('annotationObject', $this->graph, $this->subject, $this->predicate, null);
                    $this->tripleTermMode = 'annotationExplicitObject';
                    $this->tripleTerm = ['subject' => null, 'predicate' => null, 'object' => null];

                    return $this->readTripleTermSubject;
                case 'reifiedtriplestart':
                    if (!$this->supportsReifiedTriples) {
                        return \call_user_func($this->error, 'Disallowed reified triple', $token);
                    }
                    $this->saveContext('annotationObject', $this->graph, $this->subject, $this->predicate, null);
                    $this->tripleTermMode = 'annotationReifiedObject';
                    $this->tripleTerm = ['subject' => null, 'predicate' => null, 'object' => null];

                    return $this->readReifiedTripleSubject;
                case '[':
                    $id = '_:b'.$this->blankNodeCount++;
                    $this->blankNodeEndReader = function () {
                        return $this->readAnnotationPunctuation;
                    };
                    $this->saveContext('blank', $this->graph, $this->subject, $this->predicate, $this->subject = $id);

                    return $this->readBlankNodeHead;
                default:
                    $this->object = \call_user_func($this->readEntity, $token);
                    if (null == $this->object) {
                        throw $this->getNoBaseUriException('annotation object', $token['line']);
                    }

                    return $this->readAnnotationPunctuation;
            }
        };

        $this->readAnnotationObjectDataTypeOrLang = function ($token) {
            switch ($token['type']) {
                case 'type':
                case 'typeIRI':
                    $type = \call_user_func($this->readEntity, $token);
                    if (self::RDF_LANG_STRING === $type || self::RDF_DIR_LANG_STRING === $type) {
                        return \call_user_func($this->error, 'Detected illegal (directional) languaged-tagged string with explicit datatype', $token);
                    }
                    $this->object .= '^^'.$type;

                    return $this->readAnnotationPunctuation;
                case 'langcode':
                    $language = $this->readLanguageTag($token);
                    if (null === $language) {
                        return null;
                    }
                    $this->object .= '@'.$language;

                    return $this->readAnnotationPunctuation;
                default:
                    $this->readCallback = $this->readAnnotationPunctuation;

                    return \call_user_func($this->readCallback, $token);
            }
        };

        $this->readAnnotationPunctuation = function ($token) use ($makeTripleTerm, $readCompletedAnnotationStatement) {
            \call_user_func($this->triple, $this->annotationReifier, $this->predicate, $this->object, $this->annotationGraph);
            $this->annotationHadStatement = true;
            $statementTripleTerm = \call_user_func($makeTripleTerm, [
                'subject' => $this->annotationReifier,
                'predicate' => $this->predicate,
                'object' => $this->object,
            ]);

            return \call_user_func($readCompletedAnnotationStatement, $token, $statementTripleTerm);
        };

        $this->readAnnotationReifier = function ($token) use ($startAnnotation) {
            if ('reifiedtripleend' === $token['type']) {
                $this->object = null;

                return $this->readReifiedTripleEnd($token);
            }
            if ('.' === $token['type']) {
                $this->annotationReifier = '_:b'.$this->blankNodeCount++;
                \call_user_func($this->triple, $this->annotationReifier, self::RDF_REIFIES, $this->annotationTripleTerm, $this->annotationGraph);
                $this->subject = null;

                return $this->readInTopContext;
            }
            if ('annotationstart' === $token['type']) {
                $this->annotationReifier = '_:b'.$this->blankNodeCount++;
                \call_user_func($this->triple, $this->annotationReifier, self::RDF_REIFIES, $this->annotationTripleTerm, $this->annotationGraph);
                $this->annotationPendingReifier = false;

                return \call_user_func($startAnnotation, $token, $this->annotationReifier);
            }

            $reifier = \call_user_func($this->readEntity, $token);
            if (null == $reifier) {
                throw $this->getNoBaseUriException('annotation reifier', $token['line']);
            }
            $this->annotationReifier = $reifier;
            \call_user_func($this->triple, $this->annotationReifier, self::RDF_REIFIES, $this->annotationTripleTerm, $this->annotationGraph);
            $this->annotationPendingReifier = true;

            return $this->readAfterAnnotation;
        };

        $this->readAfterAnnotation = function ($token) use ($startAnnotation, $resumeParentAnnotation) {
            if (\count($this->annotationStack) && (';' === $token['type'] || 'annotationend' === $token['type'])) {
                return \call_user_func($resumeParentAnnotation, $token);
            }

            switch ($token['type']) {
                case 'annotationstart':
                    return \call_user_func($startAnnotation, $token, $this->annotationPendingReifier ? $this->annotationReifier : null);
                case '~':
                    return $this->readAnnotationReifier;
                case ';':
                    $this->subject = $this->annotationTripleTerm['subject'];

                    return $this->readPredicate;
                case ',':
                    $this->subject = $this->annotationTripleTerm['subject'];
                    $this->predicate = $this->annotationTripleTerm['predicate'];

                    return $this->readObject;
                case '.':
                    $this->subject = null;

                    return \count($this->contextStack) ? $this->readSubject : $this->readInTopContext;
                case '}':
                    $this->subject = null;

                    return \call_user_func($this->readPunctuation, $token);
                default:
                    return \call_user_func($this->error, 'Expected annotation punctuation', $token);
            }
        };

        // ### `_readFormulaTail` reads the end of a formula
        $this->readFormulaTail = function ($token) {
            if ('}' !== $token['type']) {
                return \call_user_func($this->readPunctuation, $token);
            }

            // Store the last triple of the formula
            if (isset($this->subject)) {
                \call_user_func($this->triple, $this->subject, $this->predicate, $this->object, $this->graph);
            }

            // Restore the parent context containing this formula
            $this->restoreContext();
            // If the formula was the subject, continue reading the predicate.
            // If the formula was the object, read punctuation.
            return !isset($this->object) ? $this->readPredicate : \call_user_func($this->getContextEndReader);
        };

        // ### `_readPunctuation` reads punctuation between triples or triple parts
        $this->readPunctuation = function ($token) use ($makeTripleTerm, $startAnnotation) {
            $next = null;
            $subject = isset($this->subject) ? $this->subject : null;
            $graph = $this->graph;
            $inversePredicate = $this->inversePredicate;
            switch ($token['type']) {
                // A closing brace ends a graph
                case '}':
                    if (null === $this->graph) {
                        return \call_user_func($this->error, 'Unexpected graph closing', $token);
                    }
                    if ($this->n3Mode) {
                        return \call_user_func($this->readFormulaTail, $token);
                    }
                    $this->graph = null;
                    // A dot just ends the statement, without sharing anything with the next
                    // no break
                case '.':
                    $this->subject = null;
                    $next = \count($this->contextStack) ? $this->readSubject : $this->readInTopContext;
                    if ($inversePredicate) {
                        $this->inversePredicate = false;
                    } //TODO: What’s this?
                    break;
                    // Semicolon means the subject is shared; predicate and object are different
                case ';':
                    $next = $this->readPredicate;
                    break;
                    // Comma means both the subject and predicate are shared; the object is different
                case ',':
                    $next = $this->readObject;
                    break;
                case '~':
                case 'annotationstart':
                    if (null === $subject) {
                        return \call_user_func($this->error, 'Unexpected annotation syntax', $token);
                    }
                    $predicate = $this->predicate;
                    $object = $this->object;
                    $baseSubject = $inversePredicate ? $object : $subject;
                    $baseObject = $inversePredicate ? $subject : $object;
                    \call_user_func($this->triple, $baseSubject, $predicate, $baseObject, $graph);
                    $this->annotationTripleTerm = \call_user_func($makeTripleTerm, [
                        'subject' => $baseSubject,
                        'predicate' => $predicate,
                        'object' => $baseObject,
                    ]);
                    $this->annotationGraph = $graph;
                    $this->annotationPendingReifier = false;
                    $this->subject = null;
                    if ('~' === $token['type']) {
                        return $this->readAnnotationReifier;
                    }

                    return \call_user_func($startAnnotation, $token);
                default:
                    // An entity means this is a quad (only allowed if not already inside a graph)
                    $graph = \call_user_func($this->readEntity, $token);
                    if ($this->supportsQuads && null === $this->graph && $graph) {
                        $next = $this->readQuadPunctuation;
                        break;
                    }

                    return \call_user_func($this->error, 'Expected punctuation to follow "'.$this->object.'"', $token);
            }
            // A triple has been completed now, so return it
            if (null !== $subject) {
                $predicate = $this->predicate;
                $object = $this->object;
                if (!$inversePredicate) {
                    \call_user_func($this->triple, $subject, $predicate, $object, $graph);
                } else {
                    \call_user_func($this->triple, $object, $predicate, $subject, $graph);
                }
            }

            return $next;
        };

        // ### `_readBlankNodePunctuation` reads punctuation in a blank node
        $this->readBlankNodePunctuation = function ($token) {
            $next = null;
            switch ($token['type']) {
                // Semicolon means the subject is shared; predicate and object are different
                case ';':
                    $next = $this->readPredicate;
                    break;
                    // Comma means both the subject and predicate are shared; the object is different
                case ',':
                    $next = $this->readObject;
                    break;
                default:
                    return \call_user_func($this->error, 'Expected punctuation to follow "'.$this->object.'"', $token);
            }
            // A triple has been completed now, so return it
            \call_user_func($this->triple, $this->subject, $this->predicate, $this->object, $this->graph);

            return $next;
        };

        // ### `_readQuadPunctuation` reads punctuation after a quad
        $this->readQuadPunctuation = function ($token) {
            if ('.' !== $token['type']) {
                return \call_user_func($this->error, 'Expected dot to follow quad', $token);
            }

            return $this->readInTopContext;
        };

        // ### `_readPrefix` reads the prefix of a prefix declaration
        $this->readPrefix = function ($token) {
            if ('prefix' !== $token['type']) {
                return \call_user_func($this->error, 'Expected prefix to follow @prefix', $token);
            }
            $this->prefix = $token['value'];

            return $this->readPrefixIRI;
        };

        // ### `_readPrefixIRI` reads the IRI of a prefix declaration
        $this->readPrefixIRI = function ($token) {
            if ('IRI' !== $token['type']) {
                return \call_user_func($this->error, 'Expected IRI to follow prefix "'.$this->prefix.':"', $token);
            }
            $prefixIRI = \call_user_func($this->readEntity, $token);
            $this->prefixes[$this->prefix] = $prefixIRI;
            \call_user_func($this->prefixCallback, $this->prefix, $prefixIRI);

            return $this->readDeclarationPunctuation;
        };

        // ### `_readBaseIRI` reads the IRI of a base declaration
        $this->readBaseIRI = function ($token) {
            if ('IRI' !== $token['type']) {
                return \call_user_func($this->error, 'Expected IRI to follow base declaration', $token);
            }
            $this->setBase(null === $this->base || preg_match($this->absoluteIRI, $token['value']) ?
            $token['value'] : \call_user_func($this->resolveIRI, $token));

            return $this->readDeclarationPunctuation;
        };

        // ### `_readVersion` reads an RDF version declaration
        $this->readVersion = function ($token) {
            if ('literal' !== $token['type']) {
                return \call_user_func($this->error, 'Expected literal to follow version declaration', $token);
            }
            if (isset($token['quoted']) && 'long' === $token['quoted']) {
                return \call_user_func($this->error, 'Expected simple literal to follow version declaration', $token);
            }
            if (false !== strpos($token['value'], '^^')) {
                return \call_user_func($this->error, 'Expected simple literal to follow version declaration', $token);
            }

            $versionLabel = substr($token['value'], 1, -1);
            if (preg_match('/-messages$/', $versionLabel)) {
                $this->supportsMessages = true;
                if (null === $this->messageCounter) {
                    $this->messageCounter = 1;
                    $this->prefixes['_'] = isset($this->blankNodePrefix) ? $this->blankNodePrefix : '_:b'.$this->blankNodeCount++.'_';
                }
            } else {
                $this->supportsMessages = false;
            }

            return $this->readDeclarationPunctuation;
        };

        // ### `_readNamedGraphLabel` reads the label of a named graph
        $this->readNamedGraphLabel = function ($token) {
            switch ($token['type']) {
                case 'IRI':
                case 'blank':
                case 'prefixed':
                \call_user_func($this->readSubject, $token);

                return $this->readGraph;
                case '[':
                return $this->readNamedGraphBlankLabel;
                default:
                return \call_user_func($this->error, 'Invalid graph label', $token);
            }
        };

        // ### `_readNamedGraphLabel` reads a blank node label of a named graph
        $this->readNamedGraphBlankLabel = function ($token) {
            if (']' !== $token['type']) {
                return \call_user_func($this->error, 'Invalid graph label', $token);
            }
            $this->subject = '_:b'.$this->blankNodeCount++;

            return $this->readGraph;
        };

        // ### `_readDeclarationPunctuation` reads the punctuation of a declaration
        $this->readDeclarationPunctuation = function ($token) {
            // SPARQL-style declarations don't have punctuation
            if ($this->sparqlStyle) {
                $this->sparqlStyle = false;

                return \call_user_func($this->readInTopContext, $token);
            }

            if ('.' !== $token['type']) {
                return \call_user_func($this->error, 'Expected declaration to end with a dot', $token);
            }

            return $this->readInTopContext;
        };

        // Reads a list of quantified symbols from a @forSome or @forAll statement
        $this->readQuantifierList = function ($token) {
            $entity = null;
            switch ($token['type']) {
                case 'IRI':
                case 'prefixed':
                    $entity = \call_user_func($this->readEntity, $token, true);
                    break;
                default:
                    return \call_user_func($this->error, 'Unexpected '.$token['type'], $token);
            }
            // Without explicit quantifiers, map entities to a quantified entity
            if (!$this->explicitQuantifiers) {
                $this->quantified[$entity] = $this->quantifiedPrefix.$this->blankNodeCount++;
            } else {
                // With explicit quantifiers, output the reified quantifier
                // If this is the first item, start a new quantifier list
                if (null === $this->subject) {
                    $this->subject = '_:b'.$this->blankNodeCount++;
                    \call_user_func($this->triple, isset($this->graph) ? $this->graph : '', $this->predicate, $this->subject, self::QUANTIFIERS_GRAPH);
                }
                // Otherwise, continue the previous list
                else {
                    \call_user_func($this->triple,$this->subject, self::RDF_REST,
                    $this->subject = '_:b'.$this->blankNodeCount++, self::QUANTIFIERS_GRAPH);
                }
                // Output the list item
                \call_user_func($this->triple, $this->subject, self::RDF_FIRST, $entity, self::QUANTIFIERS_GRAPH);
            }

            return $this->readQuantifierPunctuation;
        };

        // Reads punctuation from a @forSome or @forAll statement
        $this->readQuantifierPunctuation = function ($token) {
            // Read more quantifiers
            if (',' === $token['type']) {
                return $this->readQuantifierList;
            }
            // End of the quantifier list
            else {
                // With explicit quantifiers, close the quantifier list
                if ($this->explicitQuantifiers) {
                    \call_user_func($this->triple, $this->subject, self::RDF_REST, self::RDF_NIL, self::QUANTIFIERS_GRAPH);
                    $this->subject = null;
                }
                // Read a dot
                $this->readCallback = \call_user_func($this->getContextEndReader);

                return \call_user_func($this->readCallback, $token);
            }
        };

        // ### `_getPathReader` reads a potential path and then resumes with the given function
        $this->getPathReader = function ($afterPath): ?callable {
            $this->afterPath = $afterPath;

            return $this->readPath;
        };

        // ### `_readPath` reads a potential path
        $this->readPath = function ($token): ?callable {
            switch ($token['type']) {
                case '!':
                    // Forward path
                    return $this->readForwardPath;
                case '^':
                    // Backward path
                    return $this->readBackwardPath;
                default:
                    // Not a path; resume reading where we left off
                    $stack = $this->contextStack;
                    $parent = null;
                    if (\is_array($stack) && \count($stack) - 1 > 0 && isset($stack[\count($stack) - 1])) {
                        $parent = $stack[\count($stack) - 1];
                    }
                    // If we were reading a list item, we still need to output it
                    if ($parent && 'item' === $parent['type']) {
                        // The list item is the remaining subejct after reading the path
                        $item = $this->subject;
                        // Switch back to the context of the list
                        $this->restoreContext();
                        // Output the list item
                        \call_user_func($this->triple, $this->subject, self::RDF_FIRST, $item, $this->graph);
                    }

                    return \call_user_func($this->afterPath, $token);
            }
        };

        // ### `_readForwardPath` reads a '!' path
        $this->readForwardPath = function ($token) {
            $subject = null;
            $predicate = null;
            $object = '_:b'.$this->blankNodeCount++;
            // The next token is the predicate
            $predicate = \call_user_func($this->readEntity, $token);
            if (!$predicate) {
                return;
            }
            // If we were reading a subject, replace the subject by the path's object
            if (null === $this->predicate) {
                $subject = $this->subject;
                $this->subject = $object;
            }
            // If we were reading an object, replace the subject by the path's object
            else {
                $subject = $this->object;
                $this->object = $object;
            }
            // Emit the path's current triple and read its next section
            \call_user_func($this->triple, $subject, $predicate, $object, $this->graph);

            return $this->readPath;
        };

        // ### `_readBackwardPath` reads a '^' path
        $this->readBackwardPath = function ($token) {
            $subject = '_:b'.$this->blankNodeCount++;
            $predicate = null;
            $object = null;
            // The next token is the predicate
            $predicate = \call_user_func($this->readEntity, $token);
            if ($predicate) {
                return;
            }
            // If we were reading a subject, replace the subject by the path's subject
            if (null === $this->predicate) {
                $object = $this->subject;
                $this->subject = $subject;
            }
            // If we were reading an object, replace the subject by the path's subject
            else {
                $object = $this->object;
                $this->object = $subject;
            }
            // Emit the path's current triple and read its next section
            \call_user_func($this->triple, $subject, $predicate, $object, $this->graph);

            return $this->readPath;
        };

        // ### `_getContextEndReader` gets the next reader function at the end of a context
        $this->getContextEndReader = function () {
            $contextStack = $this->contextStack;
            if (!\count($contextStack)) {
                return $this->readPunctuation;
            }

            switch ($contextStack[\count($contextStack) - 1]['type']) {
                case 'blank':
                    return $this->readBlankNodeTail;
                case 'annotationObject':
                    return $this->readAnnotationPunctuation;
                case 'list':
                    return $this->readListItem;
                case 'formula':
                    return $this->readFormulaTail;
            }
        };

        // ### `_triple` emits a triple through the callback
        $this->triple = function ($subject, $predicate, $object, $graph) {
            \call_user_func($this->callback, null, ['subject' => $subject, 'predicate' => $predicate, 'object' => $object, 'graph' => isset($graph) ? $graph : ''], null, $this->messageCounter);
        };

        // ### `_error` emits an error message through the callback
        $this->error = function ($message, $token) {
            if ($this->callback) {
                \call_user_func($this->callback, new \Exception($message.' on line '.$token['line'].'.'), null);
            } else {
                throw new \Exception($message.' on line '.$token['line'].'.');
            }
        };

        // ### `_resolveIRI` resolves a relative IRI token against the base path,
        // assuming that a base path has been set and that the IRI is indeed relative
        $this->resolveIRI = function ($token) {
            $iri = $token['value'];

            if (!isset($iri[0])) { // An empty relative IRI indicates the base IRI
                return $this->base;
            }

            switch ($iri[0]) {
                // Resolve relative fragment IRIs against the base IRI
                case '#': return $this->base.$iri;
                // Resolve relative query string IRIs by replacing the query string
                case '?': //should only replace the first occurence
                    return preg_replace('/(?:\?.*)?$/', $iri, $this->base, 1);
                // Resolve root-relative IRIs at the root of the base IRI
                case '/':
                // Resolve scheme-relative IRIs to the scheme
                    return ('/' === $iri[1] ? $this->baseScheme : $this->baseRoot).\call_user_func($this->removeDotSegments, $iri);
                // Resolve all other IRIs at the base IRI's path
                default:
                    return \call_user_func($this->removeDotSegments, $this->basePath.$iri);
            }
        };

        // ### `_removeDotSegments` resolves './' and '../' path segments in an IRI as per RFC3986
        $this->removeDotSegments = function ($iri) {
            // Don't modify the IRI if it does not contain any dot segments
            if (!preg_match($this->dotSegments, $iri)) {
                return $iri;
            }

            // Start with an imaginary slash before the IRI in order to resolve trailing './' and '../'
            $result = '';
            $length = \strlen($iri);
            $i = -1;
            $pathStart = -1;
            $segmentStart = 0;
            $next = '/';

            // a function we will need here to fetch the last occurence
            //search backwards for needle in haystack, and return its position
            $rstrpos = function ($haystack, $needle) {
                $size = \strlen($haystack);
                $pos = strpos(strrev($haystack), $needle);
                if (false === $pos) {
                    return false;
                }

                return $size - $pos - 1;
            };

            while ($i < $length) {
                switch ($next) {
                    // The path starts with the first slash after the authority
                    case ':':
                        if ($pathStart < 0) {
                            // Skip two slashes before the authority
                            if ('/' === $iri[++$i] && '/' === $iri[++$i]) {
                                // Skip to slash after the authority
                                while (($pathStart = $i + 1) < $length && '/' !== $iri[$pathStart]) {
                                    $i = $pathStart;
                                }
                            }
                        }
                        break;
                        // Don't modify a query string or fragment
                    case '?':
                    case '#':
                        $i = $length;
                        break;
                    // Handle '/.' or '/..' path segments
                    case '/':
                        if (isset($iri[$i + 1]) && '.' === $iri[$i + 1]) {
                            if (isset($iri[++$i + 1])) {
                                $next = $iri[$i + 1];
                            } else {
                                $next = null;
                            }
                            switch ($next) {
                                // Remove a '/.' segment
                                case '/':
                                    if (($i - 1 - $segmentStart) > 0) {
                                        $result .= substr($iri, $segmentStart, $i - 1 - $segmentStart);
                                    }
                                    $segmentStart = $i + 1;
                                    break;
                                    // Remove a trailing '/.' segment
                                case null:
                                case '?':
                                case '#':
                                    return $result.substr($iri, $segmentStart, $i - $segmentStart).substr($iri, $i + 1);
                                    // Remove a '/..' segment
                                case '.':
                                    if (isset($iri[++$i + 1])) {
                                        $next = $iri[$i + 1];
                                    } else {
                                        $next = null;
                                    }
                                    if (null === $next || '/' === $next || '?' === $next || '#' === $next) {
                                        if ($i - 2 - $segmentStart > 0) {
                                            $result .= substr($iri, $segmentStart, $i - 2 - $segmentStart);
                                        }
                                        // Try to remove the parent path from result
                                        if (($segmentStart = $rstrpos($result, '/')) >= $pathStart) {
                                            $result = substr($result, 0, $segmentStart);
                                        }
                                        // Remove a trailing '/..' segment
                                        if ('/' !== $next) {
                                            return $result.'/'.substr($iri, $i + 1);
                                        }
                                        $segmentStart = $i + 1;
                                    }
                            }
                        }
                }
                if (++$i < $length) {
                    $next = $iri[$i];
                }
            }

            return $result.substr($iri, $segmentStart);
        };
    }

    // ## Public methods

    // ### `parse` parses the N3 input and emits each parsed triple through the callback
    public function parse($input, $tripleCallback = null, $prefixCallback = null)
    {
        $this->setTripleCallback($tripleCallback);
        $this->setPrefixCallback($prefixCallback);

        return $this->parseChunk($input, true);
    }

    // ### New method for streaming possibilities: parse only a chunk
    public function parseChunk($input, $finalize = false)
    {
        if (!isset($this->tripleCallback)) {
            $triples = [];
            $messages = [];
            $collectMessages = $this->collectMessages;
            $error = null;
            $this->callback = function ($e, $t = null, $prefixes = null, $messageCounter = null) use (&$triples, &$messages, &$collectMessages, &$error) {
                if (!$e && $t) {
                    if (null !== $messageCounter) {
                        $collectMessages = true;
                    }

                    if ($collectMessages && null !== $messageCounter) {
                        if (!isset($messages[$messageCounter])) {
                            $messages[$messageCounter] = [];
                        }
                        $messages[$messageCounter][] = $t;
                    } else {
                        $triples[] = $t;
                    }
                } elseif (!$e) {
                    //DONE
                } else {
                    $error = $e;
                }
            };
            $tokens = $this->lexer->tokenize($input, $finalize);
            foreach ($tokens as $token) {
                if (isset($this->readCallback)) {
                    $this->readCallback = \call_user_func($this->readCallback, $token);
                }
            }
            if ($error) {
                throw $error;
            }

            if ($collectMessages) {
                if (empty($messages)) {
                    return [];
                }

                ksort($messages);

                return array_values($messages);
            }

            return $triples;
        } else {
            // Parse asynchronously otherwise, executing the read callback when a token arrives
            $this->callback = $this->tripleCallback;
            try {
                $tokens = $this->lexer->tokenize($input, $finalize);
                foreach ($tokens as $token) {
                    if (isset($this->readCallback)) {
                        $this->readCallback = \call_user_func($this->readCallback, $token);
                    } else {
                        //error occured in parser
                        break;
                    }
                }
            } catch (\Exception $e) {
                if ($this->callback) {
                    \call_user_func($this->callback, $e, null);
                } else {
                    throw $e;
                }
                $this->callback = function () {};
            }
        }
    }

    public function setTripleCallback($tripleCallback = null)
    {
        $this->tripleCallback = $tripleCallback;
    }

    public function setPrefixCallback($prefixCallback = null)
    {
        if (isset($prefixCallback)) {
            $this->prefixCallback = $prefixCallback;
        } else {
            $this->prefixCallback = function () {};
        }
    }

    public function end()
    {
        return $this->parseChunk('', true);
    }

    private function getNoBaseUriException($location, $line)
    {
        return new \Exception(
            "$location on line $line can not be parsed without knowing the the document base IRI.\n".
            "Please set the document base IRI using the documentIRI parser configuration option.\n".
            "See https://github.com/pietercolpaert/hardf/#empty-document-base-IRI ."
        );
    }
}
