<?php

declare(strict_types=1);

namespace pietercolpaert\hardf\DataModel;

use rdfInterface\BlankNodeInterface;
use rdfInterface\DefaultGraphInterface;
use rdfInterface\NamedNodeInterface;
use rdfInterface\QuadInterface;
use rdfInterface\TermInterface;

/**
 * Quad wrapper carrying the RDF message counter it was parsed from.
 */
final readonly class MessageQuad implements MessageQuadInterface
{
    public function __construct(
        private QuadInterface $quad,
        private int $messageCounter,
    ) {
    }

    public function getMessageCounter(): int
    {
        return $this->messageCounter;
    }

    public function getSubject(): TermInterface
    {
        return $this->quad->getSubject();
    }

    public function getPredicate(): NamedNodeInterface
    {
        return $this->quad->getPredicate();
    }

    public function getObject(): TermInterface
    {
        return $this->quad->getObject();
    }

    public function getGraph(): NamedNodeInterface|BlankNodeInterface|DefaultGraphInterface
    {
        return $this->quad->getGraph();
    }

    /**
     * Message quads are not directly usable as RDF term values.
     *
     * @throws \BadMethodCallException always
     */
    public function getValue(): mixed
    {
        throw new \BadMethodCallException('getValue() is not supported on '.self::class);
    }

    public function withSubject(TermInterface $subject): static
    {
        return new self($this->quad->withSubject($subject), $this->messageCounter);
    }

    public function withPredicate(NamedNodeInterface $predicate): static
    {
        return new self($this->quad->withPredicate($predicate), $this->messageCounter);
    }

    public function withObject(TermInterface $object): static
    {
        return new self($this->quad->withObject($object), $this->messageCounter);
    }

    public function withGraph(NamedNodeInterface|BlankNodeInterface|DefaultGraphInterface|null $graph): static
    {
        return new self($this->quad->withGraph($graph), $this->messageCounter);
    }

    public function equals(TermInterface $term): bool
    {
        return $term instanceof self
            && $this->messageCounter === $term->messageCounter
            && $this->quad->equals($term->quad);
    }

    public function __toString(): string
    {
        return (string) $this->quad;
    }
}
