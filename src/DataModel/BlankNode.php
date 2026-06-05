<?php

declare(strict_types=1);

namespace pietercolpaert\hardf\DataModel;

use rdfInterface\BlankNodeInterface;
use rdfInterface\TermInterface;

/**
 * An RDF Blank Node.
 *
 * @see https://rdf.js.org/data-model-spec/#blanknode-interface
 * @see https://github.com/sweetrdf/rdfInterface
 */
final readonly class BlankNode implements BlankNodeInterface, Term
{
    public function __construct(
        /** The blank node label (without the leading '_:' prefix). */
        public string $value,
    ) {
    }

    public function termType(): string
    {
        return 'BlankNode';
    }

    /** Returns the blank node label. Convenience alias for {@see getValue()}. */
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
        return '_:'.$this->value;
    }
}
