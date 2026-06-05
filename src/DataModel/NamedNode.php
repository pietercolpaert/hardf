<?php

declare(strict_types=1);

namespace pietercolpaert\hardf\DataModel;

use rdfInterface\NamedNodeInterface;
use rdfInterface\TermInterface;

/**
 * An RDF Named Node (an IRI).
 *
 * @see https://rdf.js.org/data-model-spec/#namednode-interface
 * @see https://github.com/sweetrdf/rdfInterface
 */
final readonly class NamedNode implements NamedNodeInterface, Term
{
    public function __construct(
        /** The IRI value of this node. */
        public string $value,
    ) {
    }

    public function termType(): string
    {
        return 'NamedNode';
    }

    /** Returns the IRI string. Convenience alias for {@see getValue()}. */
    public function value(): string
    {
        return $this->value;
    }

    /** {@inheritDoc} */
    public function getValue(): mixed
    {
        return $this->value;
    }

    public function equals(TermInterface $term): bool
    {
        return $term instanceof self && $term->value === $this->value;
    }

    public function __toString(): string
    {
        return '<'.$this->value.'>';
    }
}
