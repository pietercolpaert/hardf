<?php

namespace pietercolpaert\hardf;
/** a clone of the N3Writer class from the N3js code by Ruben Verborgh **/
/** TriGWriter writes both Turtle and TriG from our triple representation depending on the options */
class TriGWriter
{
    // Matches a literal as represented in memory by the N3 library
    CONST LITERALMATCHER = '/^"(.*)"(?:\\^\\^(.+)|@([\\-a-z]+))?$/is';
    // rdf:type predicate (for 'a' abbreviation)
    CONST RDF_PREFIX = 'http://www.w3.org/1999/02/22-rdf-syntax-ns#';
    CONST RDF_TYPE   = self::RDF_PREFIX . 'type';
    
    // Characters in literals that require escaping
    CONST ESCAPE =    "/[\"\\\\\\t\\n\\r\\b\\f]/";
    //HHVM does not allow this to be a constant
    private $ESCAPEREPLACEMENTS;
    
    // ### `_prefixRegex` matches a prefixed name or IRI that begins with one of the added prefixes
    private $prefixRegex = "/$0^/";
    
    private $subject, $graph, $prefixIRIs, $blocked = false, $string;

    // Replaces a character by its escaped version
    private $characterReplacer;
    
    public function __construct($options = [], $readCallback = null)
    {
        $this->setReadCallback($readCallback);
        $this->ESCAPEREPLACEMENTS = [
            '\\' => '\\\\', '"' => '\\"', "\t" => "\\t",
            "\n" => '\\n', "\r" => "\\r", chr(8) => "\\b", "\f"=> "\\f"
        ];
        $this->initWriter ();
        /* Initialize writer, depending on the format*/
        $this->subject = null;
        if (!isset($options["format"]) || !(preg_match("/triple|quad/i", $options["format"]))) {
            $this->graph = '';
            $this->prefixIRIs = [];
            if (isset($options["prefixes"])) {
                $this->addPrefixes($options["prefixes"]);
            }
        } else {
            $this->writeTriple = $this->writeTripleLine;
        }
        
        $this->characterReplacer = function ($character) {
            // Replace a single character by its escaped version
            $character = $character[0];
            if (strlen($character) > 0 && isset($this->ESCAPEREPLACEMENTS[$character[0]])) {
                return $this->ESCAPEREPLACEMENTS[$character[0]];
            } else {
                return $result; //no escaping necessary, should not happen, or something is wrong in our regex
            }
        };
    }

    public function setReadCallback($readCallback) 
    {
        $this->readCallback = $readCallback;
    }
    

    private function initWriter () 
    {
        // ### `_writeTriple` writes the triple to the output stream
        $this->writeTriple = function($subject, $predicate, $object, $graph, $done = null) {
            try {
                if (isset($graph) && $graph === "") {
                    $graph = null;
                }
                // Write the graph's label if it has changed
                if ($this->graph !== $graph) {
                    // Close the previous graph and start the new one
                    $this->write(($this->subject === null ? '' : ($this->graph ? "\n}\n" : ".\n")) . (isset($graph) ? $this->encodeIriOrBlankNode($graph) . " {\n" : ''));
                    $this->subject = null;
                    // Don't treat identical blank nodes as repeating graphs
                    $this->graph = $graph[0] !== '[' ? $graph : ']';
                }
                // Don't repeat the subject if it's the same
                if ($this->subject === $subject) {
                    // Don't repeat the predicate if it's the same
                    if ($this->predicate === $predicate)
                        $this->write(', ' . $this->encodeObject($object), $done);
                    // Same subject, different predicate
                    else {
                        $this->predicate = $predicate;
                        $this->write(";\n    " . $this->encodePredicate($predicate) . ' ' . $this->encodeObject($object), $done);
                    }
                }
                // Different subject; write the whole triple
                else {
                    $this->write(($this->subject === null ? '' : ".\n") . $this->encodeSubject($this->subject = $subject) . ' ' . $this->encodePredicate($this->predicate = $predicate) . ' ' . $this->encodeObject($object), $done);
                }
            } catch (\Exception $error) {
                if (isset($done)) {
                    $done($error);
                }
            }
        };
    
  
        // ### `_writeTripleLine` writes the triple or quad to the output stream as a single line
        $this->writeTripleLine = function ($subject, $predicate, $object, $graph, $done = null) {
            if (isset($graph) && $graph === "") {
                $graph = null;
            }
            // Don't use prefixes
            unset($this->prefixMatch);
            // Write the triple
            try {
                $this->write($this->encodeIriOrBlankNode($subject) . ' ' .$this->encodeIriOrBlankNode($predicate) . ' ' . $this->encodeObject($object) . (isset($graph) ? ' ' . $this->encodeIriOrBlankNode($graph) . ".\n" : ".\n"), $done);
            } catch (\Exception $error) {
                if (isset($done)) {
                    $done($error);
                }
            }
        };
  
    }
    

    // ### `_write` writes the argument to the output stream
    private function write ($string) {
        if ($this->blocked) {
            throw new \Exception('Cannot write because the writer has been closed.');
        } else {
            if (isset($this->readCallback)) {
                call_user_func($this->readCallback, $string);
            } else {
                //buffer all
                $this->string .= $string;
            }
        }
    }

    // ### Reads a bit of the string
    public function read ()
    {
        $string = $this->string;
        $this->string = "";
        return $string;
    }

    // ### `_encodeIriOrBlankNode` represents an IRI or blank node
    private function encodeIriOrBlankNode ($entity) {
        // A blank node or list is represented as-is
        $firstChar = substr($entity, 0, 1);
        if ($firstChar === '[' || $firstChar === '(' || $firstChar === '_' && substr($entity, 1, 1) === ':') {
            return $entity;
        }
        // Escape special characters
        if (preg_match(self::ESCAPE, $entity))
            $entity = preg_replace_callback(self::ESCAPE, $this->characterReplacer,$entity);
        
        // Try to represent the IRI as prefixed name
        preg_match($this->prefixRegex, $entity, $prefixMatch);
        if (!isset($prefixMatch[1]) && !isset($prefixMatch[2])) {
            if (preg_match("/(.*?:)/",$entity,$match) && isset($this->prefixIRIs) && in_array($match[1], $this->prefixIRIs)) {
                return $entity;
            } else {
                return '<' . $entity . '>';
            }
        } else {
            return !isset($prefixMatch[1]) ? $entity : $this->prefixIRIs[$prefixMatch[1]] . $prefixMatch[2];
        }
    }

    // ### `_encodeLiteral` represents a literal
    private function encodeLiteral ($value, $type = null, $language = null) {
        // Escape special characters
        if (preg_match(self::ESCAPE, $value))
            $value = preg_replace_callback(self::ESCAPE, $this->characterReplacer,$value);
        $value =  $value ;
        // Write the literal, possibly with type or language
        if (isset($language))
            return '"' . $value . '"@' . $language;
        else if (isset($type))
            return '"' . $value . '"^^' . $this->encodeIriOrBlankNode($type);
        else
            return '"' . $value . '"';
    }
    
    // ### `_encodeSubject` represents a subject
    private function encodeSubject ($subject) {
        if ($subject[0] === '"')
            throw new \Exception('A literal as subject is not allowed: ' . $subject);
        // Don't treat identical blank nodes as repeating subjects
        if ($subject[0] === '[')
            $this->subject = ']';
        return $this->encodeIriOrBlankNode($subject);
    }


    // ### `_encodePredicate` represents a predicate
    private function encodePredicate ($predicate) {
        if ($predicate[0] === '"')
            throw new \Exception('A literal as predicate is not allowed: ' . $predicate);
        return $predicate === self::RDF_TYPE ? 'a' : $this->encodeIriOrBlankNode($predicate);
    }

    // ### `_encodeObject` represents an object
    private function encodeObject ($object) {
        // Represent an IRI or blank node
        if ($object[0] !== '"')
            return $this->encodeIriOrBlankNode($object);
        // Represent a literal
        if (preg_match(self::LITERALMATCHER, $object, $matches)) {
            return $this->encodeLiteral($matches[1], isset($matches[2])?$matches[2]:null, isset($matches[3])?$matches[3]:null);
        }
        else {
            throw new \Exception('Invalid literal: ' . $object);
        }
    }


    // ### `addTriple` adds the triple to the output stream
    public function addTriple ($subject, $predicate = null, $object = null, $graph = null, $done = null) {
        // The triple was given as a triple object, so shift parameters
        if (is_array($subject)) {
            $g = isset($subject["graph"])?$subject["graph"]:null;
            call_user_func($this->writeTriple, $subject["subject"], $subject["predicate"], $subject["object"], $g, $predicate);
        }
        // The optional `graph` parameter was not provided
        else if (!is_string($graph))
            call_user_func($this->writeTriple, $subject, $predicate, $object, '', $graph);
        // The `graph` parameter was provided
        else
            call_user_func($this->writeTriple, $subject, $predicate, $object, $graph, $done);
    }
    
    // ### `addTriples` adds the triples to the output stream
    public function addTriples ($triples) {
        for ($i = 0; $i < sizeof($triples); $i++)
            $this->addTriple($triples[$i]);
    }

    // ### `addPrefix` adds the prefix to the output stream
    public function addPrefix($prefix, $iri, $done = null)
    {
        $prefixes = [];
        $prefixes[$prefix] = $iri;
        $this->addPrefixes($prefixes, $done);
    }

    // ### `addPrefixes` adds the prefixes to the output stream
    public function addPrefixes ($prefixes, $done = null) {
        // Add all useful prefixes
        $hasPrefixes = false;
        foreach ($prefixes as $prefix => $iri) {
            
            // Verify whether the prefix can be used and does not exist yet
            if (preg_match('/[#\/]$/',$iri) && (!isset($this->prefixIRIs[$iri]) || $this->prefixIRIs[$iri] !== ($prefix . ':'))) {
                $hasPrefixes = true;
                $this->prefixIRIs[$iri] = $prefix . ":";
                // Finish a possible pending triple
                if ($this->subject !== null) {
                    $this->write($this->graph ? "\n}\n" : ".\n");
                    $this->subject = null;
                    $this->graph = '';
                }
                // Write prefix
                $this->write('@prefix ' . $prefix . ': <' . $iri . ">.\n");
            }
        }
        // Recreate the prefix matcher
        if (isset($hasPrefixes)) {
            $IRIlist = '';
            $prefixList = '';
            foreach ($this->prefixIRIs as $prefixIRI => $iri) {
                $IRIlist .= $IRIlist ? '|' . $prefixIRI : $prefixIRI;
                $prefixList .= ($prefixList ? '|' : '') . $iri;
            }            
            $IRIlist = preg_replace("/([\]\/\(\)\*\+\?\.\\\$])/", '${1}', $IRIlist);
            $this->prefixRegex = '%^(?:' . $prefixList . ')[^/]*$|' . '^(' . $IRIlist . ')([a-zA-Z][\\-_a-zA-Z0-9]*)$%';
            
        }
        // End a prefix block with a newline
        $this->write($hasPrefixes ? "\n" : '', $done);
    }

    // ### `blank` creates a blank node with the given content
    public function blank ($predicate = null, $object = null) {
        $children = $predicate;
        $child = "";
        $length="";
        // Empty blank node
        if (!isset($predicate))
            $children = [];
        // Blank node passed as blank("$predicate", "object")
        else if (is_string($predicate))
            $children = [[ "predicate" => $predicate, "object" => $object ]];
        // Blank node passed as blank({ predicate: $predicate, object: $object })
        else if (is_array($predicate) && isset($predicate["predicate"]))
            $children = [$predicate];        
        switch ($length = sizeof($children)) {
            // Generate an empty blank node
            case 0:
                return '[]';
                // Generate a non-nested one-triple blank node
            case 1:
                $child = $children[0];
                if ($child["object"][0] !== '[')
                    return '[ ' . $this->encodePredicate($child["predicate"]) . ' ' .
                        $this->encodeObject($child["object"]) . ' ]';
                // Generate a multi-triple or nested blank node
            default:
                $contents = '[';
                // Write all triples in order
                for ($i = 0; $i < $length; $i++) {
                    $child = $children[$i];
                    // Write only the object is the $predicate is the same as the previous
                    if ($child["predicate"] === $predicate)
                        $contents .= ', ' . $this->encodeObject($child["object"]);
                    // Otherwise, write the $predicate and the object
                    else {
                        $contents .= ($i ? ";\n  " : "\n  ") .
                            $this->encodePredicate($child["predicate"]) . ' ' .
                            $this->encodeObject($child["object"]);
                        $predicate = $child["predicate"];
                    }
                }
                return $contents . "\n]";
        }
    }

    // ### `list` creates a list node with the given content
    public function addList ($elements = null) {
        $length = 0;
        if (isset($elements)) {
            $length = sizeof($elements);
        }
        $contents = [];
        for ($i = 0; $i < $length; $i++) {
            $contents[$i] = $this->encodeObject($elements[$i]);
        }
        return '(' . join($contents, ' ') . ')';
    }

    // ### `end` signals the end of the output stream
    public function end()
    {
        // Finish a possible pending triple
        if ($this->subject !== null) {
            $this->write($this->graph ? "\n}\n" : ".\n");
            $this->subject = null;
        }
        if (isset($this->readCallbacks))
            call_user_func($this->readCallback, $this->string);
        
        // Disallow further writing
        $this->blocked = true;
        if (!isset($this->readCallback))
            return $this->string;
    }
}