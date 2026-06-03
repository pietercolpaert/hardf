<?php

namespace Tests\hardf;

use PHPUnit\Framework\TestCase;
use pietercolpaert\hardf\TriGParser;
use pietercolpaert\hardf\TriGWriter;

class RDFMessagesTest extends TestCase
{
    /**
     * @return array<int, array<int, array<string, string>>>
     */
    private function parseMessages(string $input, string $format): array
    {
        $parser = new TriGParser(['format' => $format, 'messages' => true]);
        $parser->_resetBlankNodeIds();

        /** @var array<int, array<int, array<string, string>>> $messages */
        $messages = $parser->parse($input);

        return $messages;
    }

    /**
     * @param array<int, array<int, array<string, string>>> $messages
     *
     * @return array<int, array<int, string>>
     */
    private function normalizeMessages(array $messages): array
    {
        $normalized = [];
        foreach ($messages as $messageIndex => $message) {
            $normalized[$messageIndex] = array_map('json_encode', $message);
            sort($normalized[$messageIndex]);
        }

        return $normalized;
    }

    /**
     * @param array<int, array<int, array<string, string>>> $expected
     */
    private function assertParsesMessages(array $expected, string $input, string $format): void
    {
        $this->assertSame(
            $this->normalizeMessages($expected),
            $this->normalizeMessages($this->parseMessages($input, $format))
        );
    }

    /**
     * @param array<int, array<int, array<string, string>>> $messages
     */
    private function roundTripMessages(array $messages, string $format): array
    {
        $writer = new TriGWriter(['format' => $format, 'messages' => true, 'version' => '1.2']);
        foreach ($messages as $message) {
            $writer->addMessage($message);
        }

        return $this->parseMessages((string) $writer->end(), $format);
    }

    public function testSingleMessageWithoutDelimiter(): void
    {
        $this->assertParsesMessages([
            [
                ['subject' => 'http://example.org/s1', 'predicate' => 'http://example.org/p', 'object' => 'http://example.org/o1', 'graph' => ''],
            ],
        ], "VERSION \"1.2-messages\"\n<http://example.org/s1> <http://example.org/p> <http://example.org/o1> .\n", 'N-Triples');
    }

    public function testTwoMessagesWithMessageDelimiter(): void
    {
        $this->assertParsesMessages([
            [
                ['subject' => 'http://example.org/s1', 'predicate' => 'http://example.org/p', 'object' => 'http://example.org/o1', 'graph' => ''],
            ],
            [
                ['subject' => 'http://example.org/s2', 'predicate' => 'http://example.org/p', 'object' => 'http://example.org/o2', 'graph' => ''],
            ],
        ], "VERSION \"1.2-messages\"\n<http://example.org/s1> <http://example.org/p> <http://example.org/o1> .\nMESSAGE\n<http://example.org/s2> <http://example.org/p> <http://example.org/o2> .\n", 'N-Triples');
    }

    public function testTwoMessagesWithAtMessageDelimiter(): void
    {
        $this->assertParsesMessages([
            [
                ['subject' => 'http://example.org/s1', 'predicate' => 'http://example.org/p', 'object' => 'http://example.org/o1', 'graph' => ''],
            ],
            [
                ['subject' => 'http://example.org/s2', 'predicate' => 'http://example.org/p', 'object' => 'http://example.org/o2', 'graph' => ''],
            ],
        ], "@version \"1.2-messages\" .\n@prefix ex: <http://example.org/> .\n\nex:s1 ex:p ex:o1 .\n@message .\nex:s2 ex:p ex:o2 .\n", 'TriG');
    }

    public function testEmptyFirstMessageIsPreserved(): void
    {
        $this->assertParsesMessages([
            [],
            [
                ['subject' => 'http://example.org/s1', 'predicate' => 'http://example.org/p', 'object' => 'http://example.org/o1', 'graph' => ''],
            ],
        ], "VERSION \"1.2-messages\"\nMESSAGE\n<http://example.org/s1> <http://example.org/p> <http://example.org/o1> .\n", 'N-Triples');
    }

    public function testEmptyMessageBetweenNonEmptyMessagesIsPreserved(): void
    {
        $this->assertParsesMessages([
            [
                ['subject' => 'http://example.org/s1', 'predicate' => 'http://example.org/p', 'object' => 'http://example.org/o1', 'graph' => ''],
            ],
            [],
            [
                ['subject' => 'http://example.org/s2', 'predicate' => 'http://example.org/p', 'object' => 'http://example.org/o2', 'graph' => ''],
            ],
        ], "VERSION \"1.2-messages\"\n<http://example.org/s1> <http://example.org/p> <http://example.org/o1> .\nMESSAGE\nMESSAGE\n<http://example.org/s2> <http://example.org/p> <http://example.org/o2> .\n", 'N-Triples');
    }

    public function testFinalDelimiterDoesNotCreateExtraEmptyMessage(): void
    {
        $this->assertParsesMessages([
            [
                ['subject' => 'http://example.org/s1', 'predicate' => 'http://example.org/p', 'object' => 'http://example.org/o1', 'graph' => ''],
            ],
        ], "VERSION \"1.2-messages\"\n<http://example.org/s1> <http://example.org/p> <http://example.org/o1> .\nMESSAGE\n", 'N-Triples');
    }

    public function testNQuadsMessagesPreserveGraphNames(): void
    {
        $this->assertParsesMessages([
            [
                ['subject' => 'http://example.org/s1', 'predicate' => 'http://example.org/p', 'object' => 'http://example.org/o1', 'graph' => 'http://example.org/g1'],
            ],
            [
                ['subject' => 'http://example.org/s2', 'predicate' => 'http://example.org/p', 'object' => 'http://example.org/o2', 'graph' => 'http://example.org/g2'],
            ],
        ], "VERSION \"1.2-messages\"\n<http://example.org/s1> <http://example.org/p> <http://example.org/o1> <http://example.org/g1> .\nMESSAGE\n<http://example.org/s2> <http://example.org/p> <http://example.org/o2> <http://example.org/g2> .\n", 'N-Quads');
    }

    public function testMessageWithDefaultAndNamedGraphs(): void
    {
        $this->assertParsesMessages([
            [
                ['subject' => 'http://example.org/s1', 'predicate' => 'http://example.org/p', 'object' => 'http://example.org/o1', 'graph' => ''],
                ['subject' => 'http://example.org/s2', 'predicate' => 'http://example.org/p', 'object' => 'http://example.org/o2', 'graph' => 'http://example.org/g'],
                ['subject' => 'http://example.org/s3', 'predicate' => 'http://example.org/p', 'object' => 'http://example.org/o3', 'graph' => 'http://example.org/g'],
            ],
            [
                ['subject' => 'http://example.org/s4', 'predicate' => 'http://example.org/p', 'object' => 'http://example.org/o4', 'graph' => ''],
            ],
        ], "VERSION \"1.2-messages\"\nPREFIX ex: <http://example.org/>\n\nex:s1 ex:p ex:o1 .\nex:g {\n  ex:s2 ex:p ex:o2 .\n  ex:s3 ex:p ex:o3 .\n}\nMESSAGE\nex:s4 ex:p ex:o4 .\n", 'TriG');
    }

    public function testRepeatedPrefixesAcrossMessages(): void
    {
        $this->assertParsesMessages([
            [
                ['subject' => 'http://example.org/one/s', 'predicate' => 'http://example.org/one/p', 'object' => 'http://example.org/one/o', 'graph' => ''],
            ],
            [
                ['subject' => 'http://example.org/two/s', 'predicate' => 'http://example.org/two/p', 'object' => 'http://example.org/two/o', 'graph' => ''],
            ],
        ], "VERSION \"1.2-messages\"\nPREFIX ex: <http://example.org/one/>\nex:s ex:p ex:o .\nMESSAGE\nPREFIX ex: <http://example.org/two/>\nex:s ex:p ex:o .\n", 'TriG');
    }

    public function testMessageBoundaryAfterGraphBlock(): void
    {
        $this->assertParsesMessages([
            [
                ['subject' => 'http://example.org/a', 'predicate' => 'http://example.org/b', 'object' => 'http://example.org/c', 'graph' => 'http://example.org/g'],
            ],
            [
                ['subject' => 'http://example.org/d', 'predicate' => 'http://example.org/e', 'object' => 'http://example.org/f', 'graph' => ''],
            ],
        ], "VERSION \"1.2-messages\"\n<http://example.org/g> {\n  <http://example.org/a> <http://example.org/b> <http://example.org/c> .\n}\nMESSAGE\n<http://example.org/d> <http://example.org/e> <http://example.org/f> .\n", 'TriG');
    }

    public function testRoundTripSerializationPreservesEmptyMessages(): void
    {
        $messages = [
            [
                ['subject' => 'http://example.org/s1', 'predicate' => 'http://example.org/p', 'object' => 'http://example.org/o1', 'graph' => ''],
            ],
            [],
            [
                ['subject' => 'http://example.org/s2', 'predicate' => 'http://example.org/p', 'object' => 'http://example.org/o2', 'graph' => ''],
            ],
        ];

        $this->assertSame($this->normalizeMessages($messages), $this->normalizeMessages($this->roundTripMessages($messages, 'N-Triples')));
    }

    public function testRoundTripSerializationPreservesNamedGraphQuads(): void
    {
        $messages = [
            [
                ['subject' => 'http://example.org/a', 'predicate' => 'http://example.org/b', 'object' => 'http://example.org/c', 'graph' => 'http://example.org/g'],
            ],
            [
                ['subject' => 'http://example.org/d', 'predicate' => 'http://example.org/e', 'object' => 'http://example.org/f', 'graph' => 'http://example.org/g'],
            ],
        ];

        $this->assertSame($this->normalizeMessages($messages), $this->normalizeMessages($this->roundTripMessages($messages, 'N-Quads')));
    }

    public function testMessageDelimiterWithoutMessageSupportFails(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Unexpected "MESSAGE" on line 2.');

        $parser = new TriGParser(['format' => 'N-Triples']);
        $parser->parse("<http://example.org/s> <http://example.org/p> <http://example.org/o> .\nMESSAGE\n");
    }

    public function testAtMessageWithoutTrailingDotFails(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Expected declaration to end with a dot on line 3.');

        $parser = new TriGParser(['format' => 'TriG']);
        $parser->parse("VERSION \"1.2-messages\"\n<http://example.org/s> <http://example.org/p> <http://example.org/o> .\n@message <http://example.org/invalid>\n");
    }

    public function testMessageDelimiterInsideOpenGraphBlockFails(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Unexpected "MESSAGE" on line 4.');

        $parser = new TriGParser(['format' => 'TriG']);
        $parser->parse("VERSION \"1.2-messages\"\n<http://example.org/g> {\n  <http://example.org/a> <http://example.org/b> <http://example.org/c> .\nMESSAGE\n  <http://example.org/d> <http://example.org/e> <http://example.org/f> .\n}\n");
    }

    public function testEmptyTrailingMessageDetectableInStreamingMode(): void
    {
        $parser = new TriGParser(['format' => 'N-Triples']);
        $lastTripleCounter = null;
        $emptyTrailingMessageDetected = false;

        // File ends with MESSAGE\n — no triples follow, so the final message is empty
        $parser->parse(
            "VERSION \"1.2-messages\"\n<http://example.org/a> <http://example.org/b> <http://example.org/c> .\nMESSAGE\n",
            function ($error, $triple = null, $prefixes = null, $messageCounter = null) use (&$lastTripleCounter, &$emptyTrailingMessageDetected): void {
                if ($error) {
                    throw $error;
                }
                if ($triple) {
                    $lastTripleCounter = $messageCounter;
                } elseif (null !== $prefixes) { // end-of-stream
                    if (null !== $messageCounter && $messageCounter !== $lastTripleCounter) {
                        $emptyTrailingMessageDetected = true;
                    }
                }
            }
        );

        $this->assertTrue($emptyTrailingMessageDetected);
    }

    public function testNonEmptyFinalMessageNotFlaggedAsEmpty(): void
    {
        $parser = new TriGParser(['format' => 'N-Triples']);
        $lastTripleCounter = null;
        $emptyTrailingMessageDetected = false;

        // File does NOT end with a MESSAGE delimiter — last message has triples
        $parser->parse(
            "VERSION \"1.2-messages\"\n<http://example.org/a> <http://example.org/b> <http://example.org/c> .\nMESSAGE\n<http://example.org/d> <http://example.org/e> <http://example.org/f> .\n",
            function ($error, $triple = null, $prefixes = null, $messageCounter = null) use (&$lastTripleCounter, &$emptyTrailingMessageDetected): void {
                if ($error) {
                    throw $error;
                }
                if ($triple) {
                    $lastTripleCounter = $messageCounter;
                } elseif (null !== $prefixes) { // end-of-stream
                    if (null !== $messageCounter && $messageCounter !== $lastTripleCounter) {
                        $emptyTrailingMessageDetected = true;
                    }
                }
            }
        );

        $this->assertFalse($emptyTrailingMessageDetected);
    }

    public function testStreamingModeEmitsOnlyTriplesAndSingleEofSignal(): void
    {
        $parser = new TriGParser(['format' => 'N-Triples']);
        $nullTripleEventCount = 0;
        $eofEventCount = 0;
        $boundaryLikeEventCount = 0;

        $parser->parse(
            "VERSION \"1.2-messages\"\nMESSAGE\n<http://example.org/a> <http://example.org/b> <http://example.org/c> .\nMESSAGE\n",
            function ($error, $triple = null, $prefixes = null) use (&$nullTripleEventCount, &$eofEventCount, &$boundaryLikeEventCount): void {
                if ($error) {
                    throw $error;
                }

                if (null === $triple) {
                    ++$nullTripleEventCount;
                    if (null === $prefixes) {
                        ++$boundaryLikeEventCount;
                    } else {
                        ++$eofEventCount;
                    }
                }
            }
        );

        $this->assertSame(1, $nullTripleEventCount);
        $this->assertSame(1, $eofEventCount);
        $this->assertSame(0, $boundaryLikeEventCount);
    }
}
