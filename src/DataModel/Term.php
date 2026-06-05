<?php

declare(strict_types=1);

namespace pietercolpaert\hardf\DataModel;

use rdfInterface\TermInterface;

/**
 * Represents any RDF term understood by hardf.
 *
 * Extends {@see TermInterface} from sweetrdf/rdfInterface so hardf terms are
 * interoperable with the broader PHP RDF ecosystem.  The additional methods
 * below are hardf-specific extensions.
 *
 * Equality and stringification are inherited from
 * {@see \rdfInterface\TermCompareInterface} (via TermInterface):
 *  – {@see equals()} accepts any rdfInterface TermInterface.
 *  – {@see __toString()} returns a human-readable N-Triples/Turtle serialisation.
 *
 * @see https://rdf.js.org/data-model-spec/#term-interface (RDF-JS inspiration)
 * @see https://github.com/sweetrdf/rdfInterface (rdfInterface)
 */
interface Term extends TermInterface
{
    /**
     * RDF-JS-style string discriminant.
     *
     * Possible values: 'NamedNode' | 'BlankNode' | 'Literal' | 'DefaultGraph' | 'TripleTerm'
     */
    public function termType(): string;
}
