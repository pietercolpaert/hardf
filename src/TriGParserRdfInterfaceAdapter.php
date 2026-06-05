<?php

declare(strict_types=1);

namespace pietercolpaert\hardf;

use rdfInterface\DataFactoryInterface;
use rdfInterface\ParserInterface;
use rdfInterface\QuadIteratorInterface;

/**
 * rdfInterface ParserInterface adapter for quickRdfIo-style integrations.
 */
final class TriGParserRdfInterfaceAdapter implements ParserInterface
{
    /** @var array<string, mixed> */
    private array $options;

    /** @var callable|null */
    private $prefixCallback;

    /**
     * @param DataFactoryInterface $dataFactory unused for now because TriGParser currently uses hardf DataFactory internally
     * @param array<string, mixed> $options
     */
    public function __construct(DataFactoryInterface $dataFactory, array $options = [], $prefixCallback = null)
    {
        $this->options = $options;
        $this->prefixCallback = $prefixCallback;
    }

    public function parse(string $input, string $baseUri = ''): QuadIteratorInterface
    {
        $iterator = new TriGParserIterator($this->optionsWithBaseUri($baseUri), $this->prefixCallback);

        return $iterator->parse($input);
    }

    public function parseStream($input, string $baseUri = ''): QuadIteratorInterface
    {
        $iterator = new TriGParserIterator($this->optionsWithBaseUri($baseUri), $this->prefixCallback);

        return $iterator->parseStream($input);
    }

    /**
     * @return array<string, mixed>
     */
    private function optionsWithBaseUri(string $baseUri): array
    {
        if ('' === $baseUri) {
            return $this->options;
        }

        $options = $this->options;
        $options['documentIRI'] = $baseUri;

        return $options;
    }
}
