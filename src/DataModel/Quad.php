<?php

declare(strict_types=1);

namespace pietercolpaert\hardf\DataModel;

use rdfInterface\BlankNodeInterface;
use rdfInterface\DefaultGraphInterface;
use rdfInterface\NamedNodeInterface;
use rdfInterface\QuadInterface;
use rdfInterface\TermInterface;

/**
 * An RDF Quad (or Triple when the graph is the DefaultGraph).
 *
 * @see https://rdf.js.org/data-model-spec/#quad-interface
 * @see https://github.com/sweetrdf/rdfInterface
 */
final readonly class Quad implements QuadInterface
{
    public function __construct(
        public NamedNode|BlankNode|TripleTerm $subject,
        public NamedNode $predicate,
        public NamedNode|BlankNode|Literal|TripleTerm $object,
        public NamedNode|BlankNode|DefaultGraph $graph,
    ) {
    }

    // ------------------------------------------------------------------
    // rdfInterface\QuadInterface (getter / with* methods)
    // ------------------------------------------------------------------

    public function getSubject(): NamedNode|BlankNode|TripleTerm
    {
        return $this->subject;
    }

    public function getPredicate(): NamedNode
    {
        return $this->predicate;
    }

    public function getObject(): NamedNode|BlankNode|Literal|TripleTerm
    {
        return $this->object;
    }

    public function getGraph(): NamedNode|BlankNode|DefaultGraph
    {
        return $this->graph;
    }

    /**
     * Quads are not directly usable as RDF term values.
     *
     * @throws \BadMethodCallException always
     */
    public function getValue(): mixed
    {
        throw new \BadMethodCallException('getValue() is not supported on '.self::class);
    }

    public function withSubject(TermInterface $subject): static
    {
        if (!($subject instanceof NamedNode || $subject instanceof BlankNode || $subject instanceof TripleTerm)) {
            throw new \InvalidArgumentException('Subject must be a NamedNode, BlankNode, or TripleTerm; got '.$subject::class);
        }

        return new self($subject, $this->predicate, $this->object, $this->graph);
    }

    public function withPredicate(NamedNodeInterface $predicate): static
    {
        if (!($predicate instanceof NamedNode)) {
            throw new \InvalidArgumentException('Predicate must be a '.NamedNode::class.'; got '.$predicate::class);
        }

        return new self($this->subject, $predicate, $this->object, $this->graph);
    }

    public function withObject(TermInterface $object): static
    {
        if (!($object instanceof NamedNode || $object instanceof BlankNode || $object instanceof Literal || $object instanceof TripleTerm)) {
            throw new \InvalidArgumentException('Object must be a NamedNode, BlankNode, Literal, or TripleTerm; got '.$object::class);
        }

        return new self($this->subject, $this->predicate, $object, $this->graph);
    }

    public function withGraph(NamedNodeInterface|BlankNodeInterface|DefaultGraphInterface|null $graph): static
    {
        $resolved = $graph ?? new DefaultGraph();

        if (!($resolved instanceof NamedNode || $resolved instanceof BlankNode || $resolved instanceof DefaultGraph)) {
            throw new \InvalidArgumentException('Graph must be a NamedNode, BlankNode, or DefaultGraph; got '.$resolved::class);
        }

        return new self($this->subject, $this->predicate, $this->object, $resolved);
    }

    // ------------------------------------------------------------------
    // rdfInterface\TermCompareInterface
    // ------------------------------------------------------------------

    public function equals(TermInterface $term): bool
    {
        return $term instanceof self
            && $this->subject->equals($term->subject)
            && $this->predicate->equals($term->predicate)
            && $this->object->equals($term->object)
            && $this->graph->equals($term->graph);
    }

    public function __toString(): string
    {
        return $this->subject.' '.$this->predicate.' '.$this->object
            .($this->graph instanceof DefaultGraph ? '' : ' '.$this->graph).' .';
    }
}
