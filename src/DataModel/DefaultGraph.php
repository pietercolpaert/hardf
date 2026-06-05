<?php

declare(strict_types=1);

namespace pietercolpaert\hardf\DataModel;

use rdfInterface\DefaultGraphInterface;
use rdfInterface\TermInterface;

/**
 * The RDF Default Graph.
 *
 * @see https://rdf.js.org/data-model-spec/#defaultgraph-interface
 * @see https://github.com/sweetrdf/rdfInterface
 */
final readonly class DefaultGraph implements DefaultGraphInterface, Term
{
    public string $value;

    public function __construct()
    {
        $this->value = '';
    }

    public function termType(): string
    {
        return 'DefaultGraph';
    }

    /** Returns an empty string (the DefaultGraph has no lexical value). */
    public function value(): string
    {
        return '';
    }

    /** {@inheritDoc} */
    public function getValue(): mixed
    {
        return '';
    }

    public function equals(TermInterface $term): bool
    {
        return $term instanceof self;
    }

    public function __toString(): string
    {
        return '';
    }
}
