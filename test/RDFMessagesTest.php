<?php

namespace Tests\hardf;

use PHPUnit\Framework\TestCase;
use pietercolpaert\hardf\DataModel\BlankNode;
use pietercolpaert\hardf\DataModel\DataFactory;
use pietercolpaert\hardf\DataModel\DefaultGraph;
use pietercolpaert\hardf\DataModel\Literal;
use pietercolpaert\hardf\DataModel\NamedNode;
use pietercolpaert\hardf\DataModel\Quad;
use pietercolpaert\hardf\DataModel\TripleTerm;
use pietercolpaert\hardf\DataModel\TripleTermInterface;
use pietercolpaert\hardf\TriGParser;
use pietercolpaert\hardf\TriGWriter;
use rdfInterface\BlankNodeInterface;
use rdfInterface\NamedNodeInterface;
use rdfInterface\TermInterface;

class RDFMessagesTest extends TestCase
{
    /**
     * @return array<int, array<int, array<string, string>>>
     */
    private function parseMessages(string $input, string $format): array
    {
        $parser = new TriGParser(['format' => $format, 'messages' => true]);
        $parser->_resetBlankNodeIds();

        $messages = iterator_to_array($parser->parseMessages($input), false);

        /** @var array<int, array<int, array<string, string>>> $legacy */
        $legacy = [];
        foreach ($messages as $messageIndex => $message) {
            $legacy[$messageIndex] = array_map([$this, 'quadToLegacyArray'], $message);
        }

        return $legacy;
    }

    /**
     * @return array{subject: string, predicate: string, object: string, graph: string}
     */
    private function quadToLegacyArray(Quad $quad): array
    {
        return [
            'subject' => $this->serializeLegacyTerm($quad->subject),
            'predicate' => $this->serializeLegacyTerm($quad->predicate),
            'object' => $this->serializeLegacyTerm($quad->object),
            'graph' => $quad->graph instanceof DefaultGraph ? '' : $this->serializeLegacyTerm($quad->graph),
        ];
    }

    /**
     * @return string|array{type: string, subject: string|array, predicate: string, object: string|array}
     */
    private function serializeLegacyTerm(TermInterface $term)
    {
        if ($term instanceof NamedNodeInterface) {
            return (string) $term->getValue();
        }

        if ($term instanceof BlankNodeInterface) {
            return '_:'.(string) $term->getValue();
        }

        if ($term instanceof TripleTermInterface) {
            return [
                'type' => 'TripleTerm',
                'subject' => $this->serializeLegacyTerm($term->getSubject()),
                'predicate' => $this->serializeLegacyTerm($term->getPredicate()),
                'object' => $this->serializeLegacyTerm($term->getObject()),
            ];
        }

        if ($term instanceof Literal) {
            $escaped = str_replace(['\\', '"'], ['\\\\', '\\"'], (string) $term->getValue());
            $lang = $term->getLang();
            if (null !== $lang) {
                if ('' !== $term->direction) {
                    return '"'.$escaped.'"@'.$lang.'--'.$term->direction;
                }

                return '"'.$escaped.'"@'.$lang;
            }

            $datatype = $term->getDatatype();
            if (Literal::XSD_STRING === $datatype) {
                return '"'.$escaped.'"';
            }

            return '"'.$escaped.'"^^'.$datatype;
        }

        throw new \InvalidArgumentException('Unsupported term in test serializer: '.$term::class);
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
            $typedMessage = [];
            foreach ($message as $quad) {
                $typedMessage[] = DataFactory::quad(
                    $this->parseLegacySubject($quad['subject']),
                    DataFactory::namedNode($quad['predicate']),
                    $this->parseLegacyObject($quad['object']),
                    $this->parseLegacyGraph($quad['graph'])
                );
            }
            $writer->addMessage($typedMessage);
        }

        return $this->parseMessages((string) $writer->end(), $format);
    }

    /**
     * @param string|array{subject: string|array, predicate: string, object: string|array} $term
     */
    private function parseLegacySubject($term): NamedNode|BlankNode|TripleTerm
    {
        if (\is_array($term)) {
            return DataFactory::tripleTerm(
                $this->parseLegacySubject($term['subject']),
                DataFactory::namedNode($term['predicate']),
                $this->parseLegacyObject($term['object'])
            );
        }

        if (str_starts_with($term, '_:')) {
            return DataFactory::blankNode(substr($term, 2));
        }

        return DataFactory::namedNode($term);
    }

    /**
     * @param string|array{subject: string|array, predicate: string, object: string|array} $term
     */
    private function parseLegacyObject($term): NamedNode|BlankNode|Literal|TripleTerm
    {
        if (\is_array($term)) {
            return DataFactory::tripleTerm(
                $this->parseLegacySubject($term['subject']),
                DataFactory::namedNode($term['predicate']),
                $this->parseLegacyObject($term['object'])
            );
        }

        if (str_starts_with($term, '_:')) {
            return DataFactory::blankNode(substr($term, 2));
        }

        if (!str_starts_with($term, '"')) {
            return DataFactory::namedNode($term);
        }

        if (!preg_match('/^"(.*)"(?:\^\^([^\"]+)|@([^@\"]+))?$/s', $term, $match)) {
            throw new \InvalidArgumentException('Invalid legacy literal: '.$term);
        }

        $lexical = $match[1];
        $datatype = $match[2] ?? '';
        $lang = $match[3] ?? '';

        if ('' !== $datatype) {
            return DataFactory::literal($lexical, null, $datatype);
        }

        if ('' !== $lang) {
            if (preg_match('/^(.+)--(ltr|rtl)$/i', $lang, $dirMatch)) {
                return DataFactory::directionalLiteral($lexical, $dirMatch[1], strtolower($dirMatch[2]));
            }

            return DataFactory::literal($lexical, strtolower($lang));
        }

        return DataFactory::literal($lexical);
    }

    private function parseLegacyGraph(string $term): NamedNodeInterface|BlankNodeInterface|null
    {
        if ('' === $term) {
            return null;
        }

        if (str_starts_with($term, '_:')) {
            return DataFactory::blankNode(substr($term, 2));
        }

        return DataFactory::namedNode($term);
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
        iterator_to_array($parser->parse("<http://example.org/s> <http://example.org/p> <http://example.org/o> .\nMESSAGE\n"), false);
    }

    public function testAtMessageWithoutTrailingDotFails(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Expected declaration to end with a dot on line 3.');

        $parser = new TriGParser(['format' => 'TriG']);
        iterator_to_array($parser->parse("VERSION \"1.2-messages\"\n<http://example.org/s> <http://example.org/p> <http://example.org/o> .\n@message <http://example.org/invalid>\n"), false);
    }

    public function testMessageDelimiterInsideOpenGraphBlockFails(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Unexpected "MESSAGE" on line 4.');

        $parser = new TriGParser(['format' => 'TriG']);
        iterator_to_array($parser->parse("VERSION \"1.2-messages\"\n<http://example.org/g> {\n  <http://example.org/a> <http://example.org/b> <http://example.org/c> .\nMESSAGE\n  <http://example.org/d> <http://example.org/e> <http://example.org/f> .\n}\n"), false);
    }

    public function testTypedMessagesIgnoreEmptyTrailingDelimiter(): void
    {
        $parser = new TriGParser(['format' => 'N-Triples', 'messages' => true]);
        $messages = iterator_to_array(
            $parser->parseMessages("VERSION \"1.2-messages\"\n<http://example.org/a> <http://example.org/b> <http://example.org/c> .\nMESSAGE\n"),
            false
        );

        $this->assertCount(1, $messages);
        $this->assertCount(1, $messages[0]);
    }

    public function testTypedMessagesPreserveNonEmptyFinalMessage(): void
    {
        $parser = new TriGParser(['format' => 'N-Triples', 'messages' => true]);
        $messages = iterator_to_array(
            $parser->parseMessages("VERSION \"1.2-messages\"\n<http://example.org/a> <http://example.org/b> <http://example.org/c> .\nMESSAGE\n<http://example.org/d> <http://example.org/e> <http://example.org/f> .\n"),
            false
        );

        $this->assertCount(2, $messages);
        $this->assertCount(1, $messages[0]);
        $this->assertCount(1, $messages[1]);
    }

    public function testTypedMessageStreamingSkipsBoundaryOnlyEvents(): void
    {
        $parser = new TriGParser(['format' => 'N-Triples', 'messages' => true]);
        $input = fopen('php://memory', 'w+');
        $this->assertNotFalse($input);
        fwrite(
            $input,
            "VERSION \"1.2-messages\"\nMESSAGE\n".
            "<http://example.org/a> <http://example.org/b> <http://example.org/c> .\nMESSAGE\n"
        );
        rewind($input);

        $messages = iterator_to_array($parser->parseStreamMessages($input, '', 16), false);
        fclose($input);

        $this->assertCount(1, $messages);
        $this->assertCount(1, $messages[0]);
    }
}
