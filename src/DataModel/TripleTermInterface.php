<?php

declare(strict_types=1);

namespace pietercolpaert\hardf\DataModel;

use rdfInterface\NamedNodeInterface;
use rdfInterface\TermInterface;

/**
 * An RDF triple term (also called a quoted triple in RDF-star / RDF 1.2).
 *
 * Triple terms are embedded triples that may appear in subject or object position
 * of an enclosing triple. They have no graph component; the graph is always
 * determined by the enclosing quad.
 *
 * This interface is defined by hardf because rdfInterface does not (yet) include
 * a standardised TripleTermInterface.
 *
 * @see https://www.w3.org/TR/rdf12-concepts/#section-triples (RDF 1.2 triple terms)
 * @see https://rdf.js.org/data-model-spec/#tripleterm-interface (RDF-JS analogue)
 */
interface TripleTermInterface extends TermInterface
{
    /** The subject of the embedded triple – a named node, blank node, or nested triple term. */
    public function getSubject(): TermInterface;

    /** The predicate of the embedded triple – always a named node. */
    public function getPredicate(): NamedNodeInterface;

    /** The object of the embedded triple – a named node, blank node, literal, or nested triple term. */
    public function getObject(): TermInterface;

    /**
     * Returns a copy with the subject replaced.
     *
     * @param TermInterface $subject named node, blank node, or triple term
     */
    public function withSubject(TermInterface $subject): static;

    /**
     * Returns a copy with the predicate replaced.
     */
    public function withPredicate(NamedNodeInterface $predicate): static;

    /**
     * Returns a copy with the object replaced.
     *
     * @param TermInterface $object named node, blank node, literal, or triple term
     */
    public function withObject(TermInterface $object): static;
}
