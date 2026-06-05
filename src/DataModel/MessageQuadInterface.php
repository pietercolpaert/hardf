<?php

declare(strict_types=1);

namespace pietercolpaert\hardf\DataModel;

use rdfInterface\QuadInterface;

/**
 * Quad enriched with RDF message position metadata.
 */
interface MessageQuadInterface extends QuadInterface
{
    /**
     * Zero-based message counter for the message this quad belongs to.
     */
    public function getMessageCounter(): int;
}
