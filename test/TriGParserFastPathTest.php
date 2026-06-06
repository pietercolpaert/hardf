<?php

declare(strict_types=1);

namespace Tests\hardf;

use PHPUnit\Framework\TestCase;
use pietercolpaert\hardf\TriGParser;

class TriGParserFastPathTest extends TestCase
{
    public function testRelaxedLineModeAcceptsRelativeIrisForTrustedBenchmarks(): void
    {
        $parser = new TriGParser(['format' => 'N-Triples', 'relax' => true]);

        $this->assertSame([
            [
                'subject' => 's',
                'predicate' => 'p',
                'object' => 'o',
                'graph' => '',
            ],
        ], $parser->parse('<s> <p> <o> .'));
    }

    public function testStrictLineModeRejectsRelativeIris(): void
    {
        $parser = new TriGParser(['format' => 'N-Triples']);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Disallowed relative IRI');

        $parser->parse('<s> <p> <o> .');
    }

    public function testFastPathParsesNQuadsWithTripleTermObject(): void
    {
        $parser = new TriGParser(['format' => 'N-Quads', 'relax' => true]);
        $quads = $parser->parse('<http://example.org/s> <http://example.org/p> <<(<http://example.org/a> <http://example.org/b> "c")>> <http://example.org/g> .');

        $this->assertSame('http://example.org/s', $quads[0]['subject']);
        $this->assertSame('http://example.org/p', $quads[0]['predicate']);
        $this->assertSame('http://example.org/g', $quads[0]['graph']);
        $this->assertSame([
            'type' => 'TripleTerm',
            'subject' => 'http://example.org/a',
            'predicate' => 'http://example.org/b',
            'object' => '"c"',
        ], $quads[0]['object']);
    }
}
