<?php

declare(strict_types=1);

namespace pietercolpaert\hardf;

use pietercolpaert\hardf\DataModel\Quad;
use rdfInterface\QuadIteratorInterface;

/**
 * TrigParser wrapper turning it into a triple/quad generator.
 *
 * Parses the input in chunks and reads the triples in a lazy way which assures
 * both speed and low memory footprint.
 *
 * Can be reused (meaning parse() and parseStream() methods can be run
 * multiple times).
 *
 * Use as follows:
 *
 * ```
 * $parser = new TrigParserIterator();
 * foreach ($parser as $quad) {
 *     ...do something...
 * }
 * ```
 */
class TriGParserIterator implements QuadIteratorInterface
{
    /** @var array<string, mixed> */
    private array $options;

    private $prefixCallback;

    private TriGParser $parser;

    private int $chunkSize;

    private ?\Iterator $quadIterator = null;

    /** @var resource|null */
    private $input;

    private bool $seekableInput = true;

    private bool $started = false;

    private int $position = 0;

    /** @var resource|null */
    private $tmpStream;

    /**
     * Creates a parser object. For documentation of parameters, see the
     * \pietercolpaert\hardf\TrigParser constructor documentation.
     *
     * If you're using this class, you probably don't need the $tripleCallback
     * but $prefixCallback can be still useful.
     *
     * @param callable $prefixCallback
     */
    public function __construct(array $options = [], $prefixCallback = null)
    {
        $this->options = $options;
        $this->prefixCallback = $prefixCallback;
    }

    public function __destruct()
    {
        $this->closeTmpStream();
    }

    /**
     * A thiny wrapper for the parseStream() method turning a string into
     * a stream resource.
     */
    public function parse(string $input): QuadIteratorInterface
    {
        $this->closeTmpStream();
        $this->tmpStream = fopen('php://memory', 'r+');
        fwrite($this->tmpStream, $input);
        rewind($this->tmpStream);

        return $this->parseStream($this->tmpStream);
    }

    /**
     * Parses a given input stream using a given chunk size.
     *
     * @param resource $input
     *
     * @throws \Exception
     */
    public function parseStream($input, int $chunkSize = 8192): QuadIteratorInterface
    {
        if (!\is_resource($input)) {
            throw new \Exception('Input has to be a resource');
        }

        $this->input = $input;
        $this->chunkSize = $chunkSize;
        $this->started = false;
        $this->position = 0;
        $metadata = stream_get_meta_data($input);
        $this->seekableInput = !empty($metadata['seekable']);
        $this->parser = new TriGParser($this->options, null, $this->prefixCallback);
        $this->quadIterator = $this->parser->parseStream($this->input, '', $this->chunkSize);

        return $this;
    }

    public function current(): ?Quad
    {
        if (null === $this->quadIterator) {
            return null;
        }

        $current = $this->quadIterator->current();

        return $current instanceof Quad ? $current : null;
    }

    public function key(): int
    {
        return $this->position;
    }

    public function next(): void
    {
        if (null !== $this->quadIterator) {
            ++$this->position;
            $this->quadIterator->next();
        }
    }

    /**
     * @throws \Exception
     */
    public function rewind(): void
    {
        if (!$this->seekableInput) {
            if ($this->started) {
                throw new \Exception("Can't rewind a non-seekable input stream");
            }
            $this->started = true;
            if (null !== $this->quadIterator) {
                $this->position = 0;
                $this->quadIterator->rewind();
            }

            return;
        }

        $ret = rewind($this->input);
        if (true !== $ret) {
            throw new \Exception("Can't seek in the input stream");
        }
        $this->started = true;
        $this->parser = new TriGParser($this->options, null, $this->prefixCallback);
        $this->quadIterator = $this->parser->parseStream($this->input, '', $this->chunkSize);
        $this->position = 0;
        $this->quadIterator->rewind();
    }

    public function valid(): bool
    {
        return null !== $this->quadIterator && $this->quadIterator->valid();
    }

    private function closeTmpStream(): void
    {
        if (\is_resource($this->tmpStream)) {
            fclose($this->tmpStream);
            $this->tmpStream = null;
        }
    }
}
