<?php

namespace Tests\hardf;

use PHPUnit\Framework\TestCase;
use pietercolpaert\hardf\DataModel\DataFactory;
use pietercolpaert\hardf\TriGParserRdfInterfaceAdapter;
use rdfInterface\ParserInterface;
use rdfInterface\QuadIteratorInterface;

class TriGParserRdfInterfaceAdapterTest extends TestCase
{
    public function testImplementsParserInterface(): void
    {
        $parser = new TriGParserRdfInterfaceAdapter(new DataFactory());

        $this->assertInstanceOf(ParserInterface::class, $parser);
    }

    public function testParseReturnsQuadIteratorInterface(): void
    {
        $parser = new TriGParserRdfInterfaceAdapter(new DataFactory());
        $iterator = $parser->parse('<a> <b> <c>.');

        $this->assertInstanceOf(QuadIteratorInterface::class, $iterator);

        $quads = iterator_to_array($iterator, false);
        $this->assertCount(1, $quads);
        $this->assertSame('a', $quads[0]->getSubject()->getValue());
    }
}
