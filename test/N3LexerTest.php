<?php

namespace Tests\hardf;

use PHPUnit\Framework\TestCase;
use pietercolpaert\hardf\N3Lexer;

class N3LexerTest extends TestCase
{
    public function testTokenizeTypedLiteral(): void
    {
        $lexer = new N3Lexer(['lineMode' => false, 'n3' => true]);

        $tokens = $lexer->tokenize('<s> <p> "1"^^<x> .');
        $types = array_map(function (array $token): string {
            return $token['type'];
        }, $tokens);

        $this->assertSame(['IRI', 'IRI', 'literal', 'typeIRI', '.', 'eof'], $types);
        $this->assertSame('s', $tokens[0]['value']);
        $this->assertSame('x', $tokens[3]['value']);
    }

    public function testTokenizeChunkWaitsForCompleteLiteral(): void
    {
        $lexer = new N3Lexer(['lineMode' => false, 'n3' => true]);

        $chunk1 = $lexer->tokenizeChunk('<s> <p> "hel');
        $chunk2 = $lexer->tokenizeChunk('lo" .');
        $final = $lexer->tokenize('', true);

        $this->assertCount(2, $chunk1);
        $this->assertSame('IRI', $chunk1[0]['type']);
        $this->assertSame('IRI', $chunk1[1]['type']);

        $this->assertCount(1, $chunk2);
        $this->assertSame('literal', $chunk2[0]['type']);
        $this->assertSame('"hello"', $chunk2[0]['value']);

        $this->assertCount(2, $final);
        $this->assertSame('.', $final[0]['type']);
        $this->assertSame('eof', $final[1]['type']);
    }

    public function testLineModeRejectsUnsupportedTokens(): void
    {
        $lexer = new N3Lexer(['lineMode' => true, 'n3' => false]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Unexpected "{" on line 1.');

        $lexer->tokenize('{');
    }
}
