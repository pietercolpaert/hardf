<?php

declare(strict_types=1);

namespace pietercolpaert\hardf\DataModel;

use rdfInterface\NamedNodeInterface;
use rdfInterface\TermInterface;

/**
 * An RDF Triple Term (also called a quoted triple in RDF 1.2 / RDF-star).
 *
 * Triple terms may appear in subject or object position of an enclosing triple/quad.
 * They have no graph component.
 *
 * @see TripleTermInterface
 * @see https://rdf.js.org/data-model-spec/#tripleterm-interface
 * @see https://www.w3.org/TR/rdf12-concepts/#section-triples
 */
final readonly class TripleTerm implements TripleTermInterface, Term
{
    public function __construct(
        public NamedNode|BlankNode|self $subject,
        public NamedNode $predicate,
        public NamedNode|BlankNode|Literal|self $object,
    ) {
    }

    public function termType(): string
    {
        return 'TripleTerm';
    }

    /**
     * Triple terms have no lexical value; returns an empty string.
     *
     * @see https://rdf.js.org/data-model-spec/#tripleterm-interface (value is '')
     */
    public function getValue(): mixed
    {
        return '';
    }

    // ------------------------------------------------------------------
    // TripleTermInterface (getter / with* methods)
    // ------------------------------------------------------------------

    public function getSubject(): NamedNode|BlankNode|self
    {
        return $this->subject;
    }

    public function getPredicate(): NamedNode
    {
        return $this->predicate;
    }

    public function getObject(): NamedNode|BlankNode|Literal|self
    {
        return $this->object;
    }

    public function withSubject(TermInterface $subject): static
    {
        if (!($subject instanceof NamedNode || $subject instanceof BlankNode || $subject instanceof self)) {
            throw new \InvalidArgumentException('Subject must be a NamedNode, BlankNode, or TripleTerm; got '.$subject::class);
        }

        return new self($subject, $this->predicate, $this->object);
    }

    public function withPredicate(NamedNodeInterface $predicate): static
    {
        if (!($predicate instanceof NamedNode)) {
            throw new \InvalidArgumentException('Predicate must be a '.NamedNode::class.'; got '.$predicate::class);
        }

        return new self($this->subject, $predicate, $this->object);
    }

    public function withObject(TermInterface $object): static
    {
        if (!($object instanceof NamedNode || $object instanceof BlankNode || $object instanceof Literal || $object instanceof self)) {
            throw new \InvalidArgumentException('Object must be a NamedNode, BlankNode, Literal, or TripleTerm; got '.$object::class);
        }

        return new self($this->subject, $this->predicate, $object);
    }

    // ------------------------------------------------------------------
    // rdfInterface\TermCompareInterface
    // ------------------------------------------------------------------

    public function equals(TermInterface $term): bool
    {
        return $term instanceof self
            && $this->subject->equals($term->subject)
            && $this->predicate->equals($term->predicate)
            && $this->object->equals($term->object);
    }

    public function __toString(): string
    {
        return '<<('
            .$this->subject.' '
            .$this->predicate.' '
            .$this->object
            .')>>';
    }
}
