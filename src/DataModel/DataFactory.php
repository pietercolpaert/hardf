<?php

declare(strict_types=1);

namespace pietercolpaert\hardf\DataModel;

use rdfInterface\BlankNodeInterface;
use rdfInterface\DataFactoryInterface;
use rdfInterface\DefaultGraphInterface;
use rdfInterface\NamedNodeInterface;
use rdfInterface\QuadNoSubjectInterface;
use rdfInterface\TermInterface;
use Stringable;

/**
 * Factory for creating RDF terms and converting from the N3 internal string representation.
 *
 * Implements {@see DataFactoryInterface} from sweetrdf/rdfInterface so that hardf is
 * interoperable with the broader PHP RDF ecosystem.
 *
 * The N3 internal format (inherited from N3.js) encodes terms as strings:
 *   - IRI:        the IRI string, e.g. 'http://example.org/foo'
 *   - Blank node: '_:label', e.g. '_:b0'
 *   - Literal:    '"value"', '"value"@lang', '"value"^^datatype', '"value"@lang--dir'
 *   - TripleTerm: '<<(subj pred obj)>>'  (nested, space-separated N3 strings)
 *   - Default graph: '' (empty string)
 *
 * @see https://rdf.js.org/data-model-spec/#datafactory-interface
 * @see https://github.com/sweetrdf/rdfInterface
 */
final class DataFactory implements DataFactoryInterface
{
    private static ?DefaultGraph $defaultGraphSingleton = null;

    /** Creates a NamedNode from an IRI string or Stringable. */
    public static function namedNode(string|\Stringable $iri): NamedNode
    {
        return new NamedNode((string) $iri);
    }

    /**
     * Creates a BlankNode.
     *
     * @param string|\Stringable|null $iri The blank-node label (without '_:'). A null or empty
     *                                     value produces a BlankNode with an empty label.
     */
    public static function blankNode(string|\Stringable|null $iri = null): BlankNode
    {
        return new BlankNode(null !== $iri ? (string) $iri : '');
    }

    /**
     * Creates a Literal following rdfInterface semantics.
     *
     * - If $lang is a non-empty string: datatype is set to rdf:langString.
     * - If $lang is null/empty and $datatype is given: uses $datatype.
     * - If neither is given: datatype is inferred from the PHP type of $value:
     *   bool → xsd:boolean, int → xsd:integer, float → xsd:decimal, string → xsd:string.
     *
     * For directional language-tagged literals (rdf:dirLangString), use
     * {@see self::directionalLiteral()} instead.
     */
    public static function literal(
        int|float|string|bool|\Stringable $value,
        string|\Stringable|null $lang = null,
        string|\Stringable|null $datatype = null,
    ): Literal {
        $lexical = \is_bool($value) ? ($value ? 'true' : 'false') : (string) $value;
        $langStr = null !== $lang ? (string) $lang : null;
        $datatypeStr = null !== $datatype ? (string) $datatype : null;

        if (null !== $langStr && '' !== $langStr) {
            return new Literal($lexical, new NamedNode(Literal::RDF_LANG_STRING), strtolower($langStr), '');
        }

        if (null !== $datatypeStr && '' !== $datatypeStr) {
            return new Literal($lexical, new NamedNode($datatypeStr), '', '');
        }

        $inferredType = match (true) {
            \is_bool($value) => 'http://www.w3.org/2001/XMLSchema#boolean',
            \is_int($value) => 'http://www.w3.org/2001/XMLSchema#integer',
            \is_float($value) => 'http://www.w3.org/2001/XMLSchema#decimal',
            default => Literal::XSD_STRING,
        };

        return new Literal($lexical, new NamedNode($inferredType), '', '');
    }

    /**
     * hardf extension: creates a directional language-tagged literal (rdf:dirLangString).
     *
     * This is the RDF 1.2 base-direction feature not yet present in DataFactoryInterface.
     *
     * @param string|\Stringable $direction 'ltr' or 'rtl'
     */
    public static function directionalLiteral(
        string|\Stringable $value,
        string|\Stringable $lang,
        string|\Stringable $direction,
    ): Literal {
        return new Literal(
            (string) $value,
            new NamedNode(Literal::RDF_DIR_LANG_STRING),
            strtolower((string) $lang),
            strtolower((string) $direction),
        );
    }

    /** Returns the singleton DefaultGraph instance. */
    public static function defaultGraph(): DefaultGraph
    {
        return self::$defaultGraphSingleton ??= new DefaultGraph();
    }

    /**
     * hardf extension: creates a TripleTerm (RDF-star / RDF 1.2 quoted triple).
     */
    public static function tripleTerm(
        NamedNode|BlankNode|TripleTerm $subject,
        NamedNode $predicate,
        NamedNode|BlankNode|Literal|TripleTerm $object,
    ): TripleTerm {
        return new TripleTerm($subject, $predicate, $object);
    }

    /**
     * Creates a Quad.
     *
     * Accepts any rdfInterface TermInterface but validates at runtime that the subject
     * and object are hardf-supported types (NamedNode, BlankNode, Literal, TripleTerm).
     *
     * @throws \InvalidArgumentException when subject, predicate, object, or graph are
     *                                   not hardf-supported term types
     */
    public static function quad(
        TermInterface $subject,
        NamedNodeInterface $predicate,
        TermInterface $object,
        NamedNodeInterface|BlankNodeInterface|DefaultGraphInterface|null $graph = null,
    ): Quad {
        if (!($subject instanceof NamedNode || $subject instanceof BlankNode || $subject instanceof TripleTerm)) {
            throw new \InvalidArgumentException('Subject must be a NamedNode, BlankNode, or TripleTerm; got '.$subject::class);
        }
        if (!($predicate instanceof NamedNode)) {
            throw new \InvalidArgumentException('Predicate must be a hardf NamedNode; got '.$predicate::class);
        }
        if (!($object instanceof NamedNode || $object instanceof BlankNode || $object instanceof Literal || $object instanceof TripleTerm)) {
            throw new \InvalidArgumentException('Object must be a NamedNode, BlankNode, Literal, or TripleTerm; got '.$object::class);
        }

        $resolvedGraph = null !== $graph ? self::asHardfGraph($graph) : self::defaultGraph();

        return new Quad($subject, $predicate, $object, $resolvedGraph);
    }

    /**
     * Not supported by hardf.
     *
     * @throws \BadMethodCallException always
     */
    public static function quadNoSubject(
        NamedNodeInterface $predicate,
        TermInterface $object,
        NamedNodeInterface|BlankNodeInterface|DefaultGraphInterface|null $graph = null,
    ): QuadNoSubjectInterface {
        throw new \BadMethodCallException('hardf does not support QuadNoSubject');
    }

    /**
     * Converts a generic rdfInterface graph term to a hardf graph term.
     *
     * @throws \InvalidArgumentException when the provided graph is not a hardf term
     */
    private static function asHardfGraph(
        NamedNodeInterface|BlankNodeInterface|DefaultGraphInterface $graph,
    ): NamedNode|BlankNode|DefaultGraph {
        if (!($graph instanceof NamedNode || $graph instanceof BlankNode || $graph instanceof DefaultGraph)) {
            throw new \InvalidArgumentException('Graph must be a hardf NamedNode, BlankNode, or DefaultGraph; got '.$graph::class);
        }

        return $graph;
    }
}
