<?php

namespace Tests\hardf;

use PHPUnit\Framework\TestCase;
use pietercolpaert\hardf\DataModel\Literal;
use pietercolpaert\hardf\DataModel\NamedNode;
use pietercolpaert\hardf\DataModel\Quad;
use pietercolpaert\hardf\TriGParserIterator;

class TriGParserIteratorTest extends TestCase
{
    public function testStream(): void
    {
        $input = fopen('php://memory', 'w');
        fwrite($input, <<<IN
<http://foo/bar> <http://bar/baz> "foo baz"@en .
<http://foo/bar> <http://bar/baz> "baz foo"@de .
IN
        );
        fseek($input, 0);
        $parser = new TriGParserIterator();
        $iterator = $parser->parseStream($input);
        $this->assertInstanceOf(\Iterator::class, $iterator);
        $values = iterator_to_array($iterator);
        $this->assertCount(2, $values);
        $this->assertContainsOnlyInstancesOf(Quad::class, $values);
        $this->assertSame('http://foo/bar', $values[0]->getSubject()->getValue());
        $this->assertInstanceOf(Literal::class, $values[0]->getObject());
        $this->assertSame('en', $values[0]->getObject()->getLang());
        fclose($input);
    }

    public function testString(): void
    {
        $input = <<<IN
<http://foo/bar> <http://bar/baz> "foo baz"@en .
<http://foo/bar> <http://bar/baz> "baz foo"@de .
IN;
        $parser = new TriGParserIterator();
        $iterator = $parser->parse($input);
        $this->assertInstanceOf(\Iterator::class, $iterator);
        $values = iterator_to_array($iterator);
        $this->assertCount(2, $values);
        $this->assertContainsOnlyInstancesOf(Quad::class, $values);
        $this->assertInstanceOf(NamedNode::class, $values[0]->getSubject());
        $this->assertInstanceOf(Literal::class, $values[0]->getObject());
    }

    public function testRepeat(): void
    {
        $input = <<<IN
<http://foo/bar> <http://bar/baz> "foo baz"@en .
<http://foo/bar> <http://bar/baz> "baz foo"@de .
IN;
        $parser = new TriGParserIterator();

        $iterator = $parser->parse($input);
        $this->assertInstanceOf(\Iterator::class, $iterator);
        $values = iterator_to_array($iterator);
        $this->assertCount(2, $values);

        $input = <<<IN
<http://foo/bar> <http://bar/baz> "foo baz"@en .
<http://foo/bar> <http://bar/baz> "baz foo"@de .
<http://foo/bar> <http://bar/baz> _:genid1 .
IN;
        $iterator = $parser->parse($input);
        $this->assertInstanceOf(\Iterator::class, $iterator);
        $values = iterator_to_array($iterator);
        $this->assertCount(3, $values);
        $this->assertContainsOnlyInstancesOf(Quad::class, $values);
    }

    public function testNonSeekableStream(): void
    {
        $input = popen("printf '<http://foo/bar> <http://bar/baz> \"foo baz\"@en .\\n'", 'r');
        $this->assertNotFalse($input);

        try {
            $parser = new TriGParserIterator();
            $iterator = $parser->parseStream($input);
            $values = iterator_to_array($iterator);

            $this->assertCount(1, $values);
            $this->assertContainsOnlyInstancesOf(Quad::class, $values);
        } finally {
            pclose($input);
        }
    }

    public function testNonSeekableStreamCannotRewindTwice(): void
    {
        $input = popen("printf '<http://foo/bar> <http://bar/baz> \"foo baz\"@en .\\n'", 'r');
        $this->assertNotFalse($input);

        try {
            $parser = new TriGParserIterator();
            $iterator = $parser->parseStream($input);

            // First pass is allowed.
            iterator_to_array($iterator);

            // Second pass attempts rewind on non-seekable input and should fail.
            try {
                iterator_to_array($iterator);
                $this->fail('Expected rewind failure on non-seekable stream.');
            } catch (\Exception $e) {
                $this->assertSame("Can't rewind a non-seekable input stream", $e->getMessage());
            }
        } finally {
            pclose($input);
        }
    }
}
