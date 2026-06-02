<?php

namespace Tests\hardf;

use PHPUnit\Framework\TestCase;
use pietercolpaert\hardf\TriGParser;
use pietercolpaert\hardf\TriGWriter;
use pietercolpaert\hardf\Util;

class N3JsCompatibilityTest extends TestCase
{
    const RDF_REIFIES = 'http://www.w3.org/1999/02/22-rdf-syntax-ns#reifies';

    /**
     * @param string|array<string, mixed> $object
     *
     * @return array<string, mixed>
     */
    private function tripleTerm($subject, $predicate, $object, $graph = null): array
    {
        $term = ['type' => 'TripleTerm', 'subject' => $subject, 'predicate' => $predicate, 'object' => $object];
        if (null !== $graph) {
            $term['graph'] = $graph;
        }

        return $term;
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function parse(string $input, ?TriGParser $parser = null): array
    {
        $parser = $parser ?: new TriGParser();
        $parser->_resetBlankNodeIds();
        $results = [];

        $parser->parse($input, function ($error, $triple = null) use (&$results): void {
            if ($error) {
                throw $error;
            }
            if ($triple) {
                $results[] = $triple;
            }
        });

        return $results;
    }

    /**
     * @param array<int, array<string, mixed>> $expected
     */
    private function assertParsesTriples(array $expected, string $input): void
    {
        $expectedJson = array_map('json_encode', $expected);
        $actualJson = array_map('json_encode', $this->parse($input));
        sort($expectedJson);
        sort($actualJson);

        $this->assertEquals($expectedJson, $actualJson);
    }

    public function testParserIgnoresInlineComments(): void
    {
        $this->assertEquals([
            ['subject' => 'a', 'predicate' => 'b', 'object' => 'c', 'graph' => ''],
            ['subject' => 'd', 'predicate' => 'e', 'object' => 'f', 'graph' => ''],
            ['subject' => 'g', 'predicate' => 'h', 'object' => 'i', 'graph' => ''],
        ], $this->parse("<a> <b> #comment2\n <c> . \n<d> <e> <f>.\n<g> <h> <i>."));
    }

    public function testParserAcceptsLanguageTagsWithDigitSubtags(): void
    {
        $this->assertEquals([
            ['subject' => 'a', 'predicate' => 'b', 'object' => '"Hello"@es-419', 'graph' => ''],
        ], $this->parse('<a> <b> "Hello"@es-419.'));
    }

    public function testParserAcceptsDirectionalLanguageTags(): void
    {
        $this->assertEquals([
            ['subject' => 'a', 'predicate' => 'b', 'object' => '"Hello"@en--rtl', 'graph' => ''],
        ], $this->parse('<a> <b> "Hello"@EN--rtl.'));
    }

    public function testParserRejectsUppercaseBaseDirection(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Detected illegal base direction in language tag on line 1.');

        $this->parse('<a> <b> "Hello"@en--RTL.');
    }

    public function testParserRejectsLangStringDatatypeWithoutLanguageTag(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Detected illegal (directional) languaged-tagged string with explicit datatype on line 1.');

        $this->parse('<a> <b> "Hello"^^<http://www.w3.org/1999/02/22-rdf-syntax-ns#langString>.');
    }

    public function testParserRejectsOversizedLanguageSubtag(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Detected language tag with subtag longer than 8 characters on line 1.');

        $this->parse('<a> <b> "Hello"@cantbethislong.');
    }

    public function testParserAcceptsVersionDeclarations(): void
    {
        $this->assertEquals([
            ['subject' => 'ex:a', 'predicate' => 'ex:b', 'object' => 'ex:c', 'graph' => ''],
        ], $this->parse("VERSION \"1.2\"\nversion \"1.2\"\n@version \"1.2\" .\n<ex:a> <ex:b> <ex:c> ."));
    }

    public function testParserAcceptsTripleSingleQuotedLongLiterals(): void
    {
        $triples = $this->parse("<a> <b> '''line 1\nline 2'''.");

        $this->assertCount(1, $triples);
        $this->assertSame('a', $triples[0]['subject']);
        $this->assertSame('b', $triples[0]['predicate']);
        $this->assertSame("\"line 1\nline 2\"", $triples[0]['object']);
        $this->assertSame('', $triples[0]['graph']);
    }

    public function testParserAcceptsNegativeExponentNumbersWithoutLeadingZero(): void
    {
        $this->assertEquals([
            ['subject' => 'a', 'predicate' => 'b', 'object' => '"-.2e3"^^http://www.w3.org/2001/XMLSchema#double', 'graph' => ''],
        ], $this->parse('<a> <b> -.2e3.'));
    }

    public function testParserAcceptsEscapedPrefixedLocalNames(): void
    {
        $this->assertEquals([
            ['subject' => 'http://www.w3.org/2013/TurtleTests/s', 'predicate' => 'http://www.w3.org/2013/TurtleTests/p', 'object' => 'http://www.w3.org/2013/TurtleTests/~.-!$&\'()*+,;=/?#@_%AA', 'graph' => ''],
        ], $this->parse("@prefix : <http://www.w3.org/2013/TurtleTests/> .\n:s :p :\\~\\.\\-\\!\\$\\&\\'\\(\\)\\*\\+\\,\\;\\=\\/\\?\\#\\@\\_\\%AA .", new TriGParser(['format' => 'Turtle'])));
    }

    public function testParserTreatsStatementDotSeparatelyFromIntegerLiteral(): void
    {
        $this->assertEquals([
            ['subject' => 's', 'predicate' => 'p', 'object' => '"123"^^http://www.w3.org/2001/XMLSchema#integer', 'graph' => ''],
        ], $this->parse('<s> <p> 123.', new TriGParser(['format' => 'Turtle'])));
    }

    public function testParserRejectsTrailingDecimalDotInAnonymousNode(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Expected punctuation to follow ""27"^^http://www.w3.org/2001/XMLSchema#integer" on line 5.');

        $this->parse("@prefix : <http://www.w3.org/2013/TurtleTests/> .\n\n:s\n  :p [\n    :p1 27.\n  ] .", new TriGParser(['format' => 'Turtle']));
    }

    public function testParserAcceptsPrefixDeclarationWithoutSpaceBeforeColon(): void
    {
        $this->assertEquals([
            ['subject' => 'http://example/c/s', 'predicate' => 'http://example/c/p', 'object' => 'http://example/c/o', 'graph' => ''],
        ], $this->parse("@prefix:<http://example/c/>.\n:s :p :o .", new TriGParser(['format' => 'TriG'])));
    }

    public function testParserAcceptsSoleBlankNodePropertyListInsideNamedGraphWithoutDot(): void
    {
        $this->assertEquals([
            ['subject' => '_:b0', 'predicate' => 'http://a.example/p', 'object' => 'http://a.example/o', 'graph' => 'http://example/graph'],
        ], $this->parse('<http://example/graph> { [ <http://a.example/p> <http://a.example/o> ] }', new TriGParser(['format' => 'TriG'])));
    }

    public function testParserRejectsN3QuantifiersInTurtleMode(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Unexpected "@forSome" on line 1.');

        $this->parse('@forSome :x .', new TriGParser(['format' => 'Turtle']));
    }

    public function testParserAcceptsTripleTermsAsObjects(): void
    {
        $this->assertEquals([
            ['subject' => 'a', 'predicate' => 'b', 'object' => $this->tripleTerm('a', 'b', 'c'), 'graph' => ''],
        ], $this->parse('<a> <b> <<(<a> <b> <c>)>>.'));
    }

    public function testParserAcceptsTripleTermsWithLiteralObjects(): void
    {
        $this->assertEquals([
            ['subject' => 'a', 'predicate' => 'b', 'object' => $this->tripleTerm('_:b0_a', 'b', '"c"^^d'), 'graph' => ''],
        ], $this->parse('<a> <b> <<(_:a <b> "c"^^<d>)>>.'));
    }

    public function testParserRejectsTripleTermsAsSubjects(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Disallowed triple term as subject on line 1.');

        $this->parse('<<(<a> <b> <c>)>> <b> <c>.');
    }

    public function testParserAcceptsStandaloneReifiedTriple(): void
    {
        $this->assertParsesTriples([
            ['subject' => '_:b0', 'predicate' => self::RDF_REIFIES, 'object' => $this->tripleTerm('a', 'b', 'c'), 'graph' => ''],
        ], '<<<a> <b> <c>>>.');
    }

    public function testParserAcceptsTripleAndStandaloneReifiedTriple(): void
    {
        $this->assertParsesTriples([
            ['subject' => 'a', 'predicate' => 'b', 'object' => 'c', 'graph' => ''],
            ['subject' => '_:b0', 'predicate' => self::RDF_REIFIES, 'object' => $this->tripleTerm('a', 'b', 'c'), 'graph' => ''],
        ], '<a> <b> <c> . <<<a> <b> <c>>> .');
    }

    public function testParserAcceptsStandaloneReifiedTripleAndTriple(): void
    {
        $this->assertParsesTriples([
            ['subject' => '_:b0', 'predicate' => self::RDF_REIFIES, 'object' => $this->tripleTerm('a', 'b', 'c'), 'graph' => ''],
            ['subject' => 'a', 'predicate' => 'b', 'object' => 'c', 'graph' => ''],
        ], '<<<a> <b> <c>>>. <a> <b> <c>.');
    }

    public function testParserAcceptsReifiedTripleAsSubject(): void
    {
        $this->assertParsesTriples([
            ['subject' => '_:b0', 'predicate' => 'b', 'object' => 'c', 'graph' => ''],
            ['subject' => '_:b0', 'predicate' => self::RDF_REIFIES, 'object' => $this->tripleTerm('a', 'b', 'c'), 'graph' => ''],
        ], '<<<a> <b> <c>>> <b> <c>.');
    }

    public function testParserAcceptsReifiedTripleWithBlankNodes(): void
    {
        $this->assertParsesTriples([
            ['subject' => '_:b0', 'predicate' => 'b', 'object' => 'c', 'graph' => ''],
            ['subject' => '_:b0', 'predicate' => self::RDF_REIFIES, 'object' => $this->tripleTerm('_:b0_a', 'b', '_:b0_c'), 'graph' => ''],
        ], '<<_:a <b> _:c>> <b> <c>.');
    }

    public function testParserRejectsBlankNodePredicateInReifiedTriple(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Disallowed blank node as reified triple predicate on line 1.');

        $this->parse('<<<a> _:b <c>>> <b> <c>.');
    }

    public function testParserAcceptsReifiedTripleWithLiteralObject(): void
    {
        $this->assertParsesTriples([
            ['subject' => '_:b0', 'predicate' => 'b', 'object' => 'c', 'graph' => ''],
            ['subject' => '_:b0', 'predicate' => self::RDF_REIFIES, 'object' => $this->tripleTerm('_:b0_a', 'b', '"c"^^d'), 'graph' => ''],
        ], '<<_:a <b> "c"^^<d>>> <b> <c>.');
    }

    public function testParserAcceptsExplicitIriReifierInSubject(): void
    {
        $this->assertParsesTriples([
            ['subject' => 'iri', 'predicate' => 'b', 'object' => 'c', 'graph' => ''],
            ['subject' => 'iri', 'predicate' => self::RDF_REIFIES, 'object' => $this->tripleTerm('a', 'b', 'c'), 'graph' => ''],
        ], '<<<a> <b> <c> ~ <iri>>> <b> <c>.');
    }

    public function testParserAcceptsExplicitBlankNodeReifierInSubject(): void
    {
        $this->assertParsesTriples([
            ['subject' => '_:b0_b1', 'predicate' => 'b', 'object' => 'c', 'graph' => ''],
            ['subject' => '_:b0_b1', 'predicate' => self::RDF_REIFIES, 'object' => $this->tripleTerm('a', 'b', 'c'), 'graph' => ''],
        ], '<<<a> <b> <c> ~ _:b1>> <b> <c>.');
    }

    public function testParserAcceptsReifiedTripleAsObject(): void
    {
        $this->assertParsesTriples([
            ['subject' => 'a', 'predicate' => 'b', 'object' => '_:b0', 'graph' => ''],
            ['subject' => '_:b0', 'predicate' => self::RDF_REIFIES, 'object' => $this->tripleTerm('a', 'b', 'c'), 'graph' => ''],
        ], '<a> <b> <<<a> <b> <c>>>.');
    }

    public function testParserAcceptsExplicitIriReifierInObject(): void
    {
        $this->assertParsesTriples([
            ['subject' => 'a', 'predicate' => 'b', 'object' => 'iri', 'graph' => ''],
            ['subject' => 'iri', 'predicate' => self::RDF_REIFIES, 'object' => $this->tripleTerm('a', 'b', 'c'), 'graph' => ''],
        ], '<a> <b> <<<a> <b> <c> ~ <iri>>>.');
    }

    public function testParserAcceptsExplicitBlankNodeReifierInObject(): void
    {
        $this->assertParsesTriples([
            ['subject' => 'a', 'predicate' => 'b', 'object' => '_:b0_b1', 'graph' => ''],
            ['subject' => '_:b0_b1', 'predicate' => self::RDF_REIFIES, 'object' => $this->tripleTerm('a', 'b', 'c'), 'graph' => ''],
        ], '<a> <b> <<<a> <b> <c> ~ _:b1>>.');
    }

    public function testParserAcceptsNestedTripleTermsInObject(): void
    {
        $this->assertParsesTriples([
            ['subject' => 'd', 'predicate' => 'e', 'object' => $this->tripleTerm('f', 'g', $this->tripleTerm('a', 'b', 'c')), 'graph' => ''],
        ], '<d> <e> <<(<f> <g> <<(<a> <b> <c>)>>)>>.');
    }

    public function testParserAcceptsNestedReifiedTriplesInObject(): void
    {
        $this->assertParsesTriples([
            ['subject' => 'd', 'predicate' => 'e', 'object' => '_:b1', 'graph' => ''],
            ['subject' => '_:b0', 'predicate' => self::RDF_REIFIES, 'object' => $this->tripleTerm('a', 'b', 'c'), 'graph' => ''],
            ['subject' => '_:b1', 'predicate' => self::RDF_REIFIES, 'object' => $this->tripleTerm('f', 'g', '_:b0'), 'graph' => ''],
        ], '<d> <e> <<<f> <g> <<<a> <b> <c>>>>>.');
    }

    public function testParserAcceptsNestedReifiedTriplesInSubject(): void
    {
        $this->assertParsesTriples([
            ['subject' => '_:b1', 'predicate' => 'd', 'object' => 'e', 'graph' => ''],
            ['subject' => '_:b0', 'predicate' => self::RDF_REIFIES, 'object' => $this->tripleTerm('a', 'b', 'c'), 'graph' => ''],
            ['subject' => '_:b1', 'predicate' => self::RDF_REIFIES, 'object' => $this->tripleTerm('_:b0', 'f', 'g'), 'graph' => ''],
        ], '<<<<<a> <b> <c>>> <f> <g>>> <d> <e>.');
    }

    public function testParserAcceptsNestedReifiedTriplesInSubjectAndObject(): void
    {
        $this->assertParsesTriples([
            ['subject' => '_:b1', 'predicate' => 'd', 'object' => 'e', 'graph' => ''],
            ['subject' => '_:b0', 'predicate' => self::RDF_REIFIES, 'object' => $this->tripleTerm('a', 'b', 'c'), 'graph' => ''],
            ['subject' => '_:b1', 'predicate' => self::RDF_REIFIES, 'object' => $this->tripleTerm('f', 'g', '_:b0'), 'graph' => ''],
        ], '<<<f> <g> <<<a> <b> <c>>>>> <d> <e>.');
    }

    public function testParserAcceptsNestedReifiedTriplesInObjectAndSubject(): void
    {
        $this->assertParsesTriples([
            ['subject' => 'd', 'predicate' => 'e', 'object' => '_:b1', 'graph' => ''],
            ['subject' => '_:b0', 'predicate' => self::RDF_REIFIES, 'object' => $this->tripleTerm('a', 'b', 'c'), 'graph' => ''],
            ['subject' => '_:b1', 'predicate' => self::RDF_REIFIES, 'object' => $this->tripleTerm('_:b0', 'f', 'g'), 'graph' => ''],
        ], '<d> <e> <<<<<a> <b> <c>>> <f> <g>>>.');
    }

    public function testParserAcceptsSharedReifiedTripleSubject(): void
    {
        $this->assertParsesTriples([
            ['subject' => '_:b0', 'predicate' => 'b', 'object' => 'c', 'graph' => ''],
            ['subject' => '_:b0', 'predicate' => 'd', 'object' => 'c', 'graph' => ''],
            ['subject' => '_:b0', 'predicate' => self::RDF_REIFIES, 'object' => $this->tripleTerm('a', 'b', 'c'), 'graph' => ''],
        ], "<<<a> <b> <c>>> <b> <c>;\n<d> <c>.");
    }

    public function testParserAcceptsSharedReifiedTripleSubjectAndObject(): void
    {
        $this->assertParsesTriples([
            ['subject' => '_:b0', 'predicate' => 'b', 'object' => 'c', 'graph' => ''],
            ['subject' => '_:b0', 'predicate' => 'd', 'object' => '_:b1', 'graph' => ''],
            ['subject' => '_:b0', 'predicate' => self::RDF_REIFIES, 'object' => $this->tripleTerm('a', 'b', 'c'), 'graph' => ''],
            ['subject' => '_:b1', 'predicate' => self::RDF_REIFIES, 'object' => $this->tripleTerm('a', 'b', 'c'), 'graph' => ''],
        ], "<<<a> <b> <c>>> <b> <c>;\n<d> <<<a> <b> <c>>>.");
    }

    public function testParserPutsNestedReifiedTriplesInDefaultGraph(): void
    {
        $this->assertParsesTriples([
            ['subject' => 'a', 'predicate' => 'b', 'object' => 'c', 'graph' => 'g'],
            ['subject' => '_:b0', 'predicate' => 'd', 'object' => 'e', 'graph' => ''],
            ['subject' => '_:b0', 'predicate' => self::RDF_REIFIES, 'object' => $this->tripleTerm('a', 'b', 'c'), 'graph' => ''],
        ], "<a> <b> <c> <g>.\n<<<a> <b> <c>>> <d> <e>.");
    }

    public function testParserAcceptsSubjectListContainingReifiedTriples(): void
    {
        $this->assertParsesTriples([
            ['subject' => '_:b0', 'predicate' => 'a', 'object' => 'b', 'graph' => ''],
            ['subject' => '_:b0', 'predicate' => 'http://www.w3.org/1999/02/22-rdf-syntax-ns#first', 'object' => '_:b1', 'graph' => ''],
            ['subject' => '_:b0', 'predicate' => 'http://www.w3.org/1999/02/22-rdf-syntax-ns#rest', 'object' => '_:b2', 'graph' => ''],
            ['subject' => '_:b1', 'predicate' => self::RDF_REIFIES, 'object' => $this->tripleTerm('a1', 'b1', 'c1'), 'graph' => ''],
            ['subject' => '_:b2', 'predicate' => 'http://www.w3.org/1999/02/22-rdf-syntax-ns#first', 'object' => '_:b3', 'graph' => ''],
            ['subject' => '_:b2', 'predicate' => 'http://www.w3.org/1999/02/22-rdf-syntax-ns#rest', 'object' => 'http://www.w3.org/1999/02/22-rdf-syntax-ns#nil', 'graph' => ''],
            ['subject' => '_:b3', 'predicate' => self::RDF_REIFIES, 'object' => $this->tripleTerm('a2', 'b2', 'c2'), 'graph' => ''],
        ], '(<< <a1> <b1> <c1> >> << <a2> <b2> <c2> >>) <a> <b>.');
    }

    public function testParserAcceptsObjectListContainingReifiedTriples(): void
    {
        $this->assertParsesTriples([
            ['subject' => 'a', 'predicate' => 'b', 'object' => '_:b0', 'graph' => ''],
            ['subject' => '_:b0', 'predicate' => 'http://www.w3.org/1999/02/22-rdf-syntax-ns#first', 'object' => '_:b1', 'graph' => ''],
            ['subject' => '_:b0', 'predicate' => 'http://www.w3.org/1999/02/22-rdf-syntax-ns#rest', 'object' => '_:b2', 'graph' => ''],
            ['subject' => '_:b1', 'predicate' => self::RDF_REIFIES, 'object' => $this->tripleTerm('a1', 'b1', 'c1'), 'graph' => ''],
            ['subject' => '_:b2', 'predicate' => 'http://www.w3.org/1999/02/22-rdf-syntax-ns#first', 'object' => '_:b3', 'graph' => ''],
            ['subject' => '_:b2', 'predicate' => 'http://www.w3.org/1999/02/22-rdf-syntax-ns#rest', 'object' => 'http://www.w3.org/1999/02/22-rdf-syntax-ns#nil', 'graph' => ''],
            ['subject' => '_:b3', 'predicate' => self::RDF_REIFIES, 'object' => $this->tripleTerm('a2', 'b2', 'c2'), 'graph' => ''],
        ], '<a> <b> (<< <a1> <b1> <c1> >> << <a2> <b2> <c2> >>).');
    }

    public function testParserAcceptsAnnotationSyntaxWithOnePredicateObject(): void
    {
        $this->assertParsesTriples([
            ['subject' => 'a', 'predicate' => 'b', 'object' => 'c', 'graph' => ''],
            ['subject' => '_:b0', 'predicate' => 'b', 'object' => 'c', 'graph' => ''],
            ['subject' => '_:b0', 'predicate' => self::RDF_REIFIES, 'object' => $this->tripleTerm('a', 'b', 'c'), 'graph' => ''],
        ], '<a> <b> <c> {| <b> <c> |}.');
    }

    public function testParserAcceptsAnnotationSyntaxWithoutReifier(): void
    {
        $this->assertParsesTriples([
            ['subject' => 'a', 'predicate' => 'b', 'object' => 'c', 'graph' => ''],
            ['subject' => '_:b0', 'predicate' => self::RDF_REIFIES, 'object' => $this->tripleTerm('a', 'b', 'c'), 'graph' => ''],
        ], '<a> <b> <c> ~ .');
    }

    public function testParserAcceptsAnnotationSyntaxWithExplicitReifier(): void
    {
        $this->assertParsesTriples([
            ['subject' => 'a', 'predicate' => 'b', 'object' => 'c', 'graph' => ''],
            ['subject' => 'iri', 'predicate' => 'b', 'object' => 'c', 'graph' => ''],
            ['subject' => 'iri', 'predicate' => self::RDF_REIFIES, 'object' => $this->tripleTerm('a', 'b', 'c'), 'graph' => ''],
        ], '<a> <b> <c> ~ <iri> {| <b> <c> |}.');
    }

    public function testParserAcceptsAnnotationSyntaxWithTwoPredicateObjects(): void
    {
        $this->assertParsesTriples([
            ['subject' => 'a', 'predicate' => 'b', 'object' => 'c', 'graph' => ''],
            ['subject' => '_:b0', 'predicate' => 'b1', 'object' => 'c1', 'graph' => ''],
            ['subject' => '_:b0', 'predicate' => 'b2', 'object' => 'c2', 'graph' => ''],
            ['subject' => '_:b0', 'predicate' => self::RDF_REIFIES, 'object' => $this->tripleTerm('a', 'b', 'c'), 'graph' => ''],
        ], '<a> <b> <c> {| <b1> <c1>; <b2> <c2> |}.');
    }

    public function testParserAcceptsEmptyReifierInSubject(): void
    {
        $this->assertParsesTriples([
            ['subject' => '_:b0', 'predicate' => 'http://example/q', 'object' => 'http://example/z', 'graph' => ''],
            ['subject' => '_:b0', 'predicate' => self::RDF_REIFIES, 'object' => $this->tripleTerm('http://example/s', 'http://example/p', 'http://example/o'), 'graph' => ''],
        ], 'PREFIX : <http://example/> << :s :p :o ~ >> :q :z .');
    }

    public function testParserAcceptsEmptyReifierInObject(): void
    {
        $this->assertParsesTriples([
            ['subject' => 'http://example/a', 'predicate' => 'http://example/q', 'object' => '_:b0', 'graph' => ''],
            ['subject' => '_:b0', 'predicate' => self::RDF_REIFIES, 'object' => $this->tripleTerm('http://example/s', 'http://example/p', 'http://example/o'), 'graph' => ''],
        ], 'PREFIX : <http://example/> :a :q << :s :p :o ~ >> .');
    }

    public function testParserAcceptsAnnotationSyntaxWithBlankNodeObjects(): void
    {
        $this->assertParsesTriples([
            ['subject' => 'http://example/s', 'predicate' => 'http://example/p', 'object' => 'http://example/o', 'graph' => ''],
            ['subject' => '_:b0', 'predicate' => self::RDF_REIFIES, 'object' => $this->tripleTerm('http://example/s', 'http://example/p', 'http://example/o'), 'graph' => ''],
            ['subject' => '_:b0', 'predicate' => 'http://example/source', 'object' => '_:b1', 'graph' => ''],
            ['subject' => '_:b0', 'predicate' => 'http://example/source', 'object' => '_:b2', 'graph' => ''],
            ['subject' => '_:b1', 'predicate' => 'http://example/graph', 'object' => 'http://host1/', 'graph' => ''],
            ['subject' => '_:b1', 'predicate' => 'http://example/date', 'object' => '"2020-01-20"^^http://www.w3.org/2001/XMLSchema#date', 'graph' => ''],
            ['subject' => '_:b2', 'predicate' => 'http://example/graph', 'object' => 'http://host2/', 'graph' => ''],
            ['subject' => '_:b2', 'predicate' => 'http://example/date', 'object' => '"2020-12-31"^^http://www.w3.org/2001/XMLSchema#date', 'graph' => ''],
        ], 'PREFIX : <http://example/> PREFIX xsd: <http://www.w3.org/2001/XMLSchema#> :s :p :o {| :source [ :graph <http://host1/> ; :date "2020-01-20"^^xsd:date ] ; :source [ :graph <http://host2/> ; :date "2020-12-31"^^xsd:date ] |} .');
    }

    public function testParserAcceptsNestedAnnotations(): void
    {
        $this->assertParsesTriples([
            ['subject' => 'http://example/s', 'predicate' => 'http://example/p', 'object' => 'http://example/o', 'graph' => ''],
            ['subject' => '_:b0', 'predicate' => self::RDF_REIFIES, 'object' => $this->tripleTerm('http://example/s', 'http://example/p', 'http://example/o'), 'graph' => ''],
            ['subject' => '_:b0', 'predicate' => 'http://example/a', 'object' => 'http://example/b', 'graph' => ''],
            ['subject' => '_:b1', 'predicate' => self::RDF_REIFIES, 'object' => $this->tripleTerm('_:b0', 'http://example/a', 'http://example/b'), 'graph' => ''],
            ['subject' => '_:b1', 'predicate' => 'http://example/a2', 'object' => 'http://example/b2', 'graph' => ''],
        ], 'PREFIX : <http://example/> :s :p :o {| :a :b {| :a2 :b2 |} |}.');
    }

    public function testParserAcceptsAnnotationsWithReifiedTripleObjects(): void
    {
        $this->assertParsesTriples([
            ['subject' => 'http://example/s', 'predicate' => 'http://example/p', 'object' => 'http://example/o', 'graph' => ''],
            ['subject' => '_:b0', 'predicate' => self::RDF_REIFIES, 'object' => $this->tripleTerm('http://example/s', 'http://example/p', 'http://example/o'), 'graph' => ''],
            ['subject' => '_:b0', 'predicate' => 'http://example/r', 'object' => '_:b1', 'graph' => ''],
            ['subject' => '_:b1', 'predicate' => self::RDF_REIFIES, 'object' => $this->tripleTerm('http://example/s1', 'http://example/p1', 'http://example/o1'), 'graph' => ''],
        ], 'PREFIX : <http://example/> :s :p :o {| :r <<:s1 :p1 :o1>> |} .');
    }

    public function testParserAcceptsAnnotationsAfterPredicateAndObjectListContinuations(): void
    {
        $this->assertParsesTriples([
            ['subject' => 'http://example/s', 'predicate' => 'http://example/p', 'object' => 'http://example/o', 'graph' => ''],
            ['subject' => 'http://example/s', 'predicate' => 'http://example/p2', 'object' => 'http://example/o2', 'graph' => ''],
            ['subject' => 'http://example/s', 'predicate' => 'http://example/p2', 'object' => 'http://example/o3', 'graph' => ''],
            ['subject' => '_:b0', 'predicate' => self::RDF_REIFIES, 'object' => $this->tripleTerm('http://example/s', 'http://example/p', 'http://example/o'), 'graph' => ''],
            ['subject' => '_:b0', 'predicate' => 'http://example/a', 'object' => 'http://example/b', 'graph' => ''],
            ['subject' => '_:b1', 'predicate' => self::RDF_REIFIES, 'object' => $this->tripleTerm('http://example/s', 'http://example/p2', 'http://example/o2'), 'graph' => ''],
            ['subject' => '_:b1', 'predicate' => 'http://example/a2', 'object' => 'http://example/b2', 'graph' => ''],
            ['subject' => '_:b2', 'predicate' => self::RDF_REIFIES, 'object' => $this->tripleTerm('http://example/s', 'http://example/p2', 'http://example/o3'), 'graph' => ''],
            ['subject' => '_:b2', 'predicate' => 'http://example/a3', 'object' => 'http://example/b3', 'graph' => ''],
        ], 'PREFIX : <http://example/> :s :p :o {| :a :b |}; :p2 :o2 {| :a2 :b2 |}, :o3 {| :a3 :b3 |}.');
    }

    public function testParserAcceptsMultipleAnnotationBlocks(): void
    {
        $this->assertParsesTriples([
            ['subject' => 'a', 'predicate' => 'b', 'object' => 'c', 'graph' => ''],
            ['subject' => '_:b0', 'predicate' => 'b1', 'object' => 'c1', 'graph' => ''],
            ['subject' => '_:b0', 'predicate' => self::RDF_REIFIES, 'object' => $this->tripleTerm('a', 'b', 'c'), 'graph' => ''],
            ['subject' => '_:b1', 'predicate' => 'b2', 'object' => 'c2', 'graph' => ''],
            ['subject' => '_:b1', 'predicate' => self::RDF_REIFIES, 'object' => $this->tripleTerm('a', 'b', 'c'), 'graph' => ''],
        ], '<a> <b> <c> {| <b1> <c1> |} {| <b2> <c2> |}.');
    }

    public function testParserAcceptsAnnotationSyntaxInGraph(): void
    {
        $this->assertParsesTriples([
            ['subject' => 'a', 'predicate' => 'b', 'object' => 'c', 'graph' => 'G'],
            ['subject' => '_:b0', 'predicate' => 'b', 'object' => 'c', 'graph' => 'G'],
            ['subject' => '_:b0', 'predicate' => self::RDF_REIFIES, 'object' => $this->tripleTerm('a', 'b', 'c'), 'graph' => 'G'],
        ], '<G> { <a> <b> <c> {| <b> <c> |}. }');
    }

    public function testParserAcceptsReifiedTripleSubjectWithPrefixedNamesInGraph(): void
    {
        $this->assertParsesTriples([
            ['subject' => '_:b0', 'predicate' => 'http://example/q', 'object' => 'http://example/z', 'graph' => 'http://example/G'],
            ['subject' => '_:b0', 'predicate' => self::RDF_REIFIES, 'object' => $this->tripleTerm('http://example/s', 'http://example/p', 'http://example/o'), 'graph' => 'http://example/G'],
        ], "PREFIX : <http://example/>\n\n:G {<<:s :p :o>> :q :z .}");
    }

    public function testParserRejectsReifiedTriplesInNTriplesMode(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Disallowed reified triple on line 1.');

        $this->parse('<<<a> <b> <c>>> <b> <c>.', new TriGParser(['format' => 'N-Triples']));
    }

    public function testParserAcceptsReifiedTripleWithNumberAndBooleanObjectsBeforeEnd(): void
    {
        $this->assertParsesTriples([
            ['subject' => '_:b0', 'predicate' => 'b', 'object' => 'c', 'graph' => ''],
            ['subject' => '_:b0', 'predicate' => self::RDF_REIFIES, 'object' => $this->tripleTerm('a', 'b', '"1"^^http://www.w3.org/2001/XMLSchema#integer'), 'graph' => ''],
            ['subject' => '_:b1', 'predicate' => 'b', 'object' => 'c', 'graph' => ''],
            ['subject' => '_:b1', 'predicate' => self::RDF_REIFIES, 'object' => $this->tripleTerm('a', 'b', '"true"^^http://www.w3.org/2001/XMLSchema#boolean'), 'graph' => ''],
        ], '<<<a> <b> 1>> <b> <c>. <<<a> <b> true>> <b> <c>.');
    }

    /**
     * @dataProvider invalidTripleTermSyntaxProvider
     */
    public function testParserRejectsInvalidTripleTermSyntax(string $input): void
    {
        $this->expectException(\Exception::class);

        $this->parse($input);
    }

    /**
     * @return array<int, array<int, string>>
     */
    public function invalidTripleTermSyntaxProvider(): array
    {
        return [
            ['<a> <<<b> <c> <d>>> <e>'],
            ['<<(_:a <b> _:c)>> <b> <c>.'],
            ['<<(<<(<a> <b> <c>)>> <f> <g>)>> <d> <e>.'],
            ['<d> <e> <<(<<(<a> <b> <c>)>> <f> <g>.'],
            ['<d> <e> <<<<<a> <b> <c>>> <f> <g>.'],
            ['<d> <e> <<(<<(<a> <b> <c> <f> <g>)>>.'],
            ['<d> <e> <<<<<a> <b> <c> <f> <g>>>.'],
            ['<d> <e> <<(<<(<a> <b> <c>)>>)>> <f> <g>)>>.'],
            ['<d> <e> <<<<<a> <b> <c>>>>> <f> <g>>>.'],
            ['<d> <e> <<(<<(<a> <b> <c>)>> <f> <g>)>>)>>.'],
            ['<d> <e> <<<<<a> <b> <c>>> <f> <g>>>>>.'],
            ['<a> <b> <c>)>>.'],
            ['<a> <b> <c>>>.'],
            ['<d> <e> <<(<a> <b>)>>.'],
            ['<d> <e> <<<a> <b>>>.'],
            ['<<(<a> <b>)>> <d> <e>.'],
            ['<<<a> <b>>> <d> <e>.'],
            [')>> <<('],
            ['>> <<'],
            ['<<(<a> <b> <c>)>>.'],
            ['<<<a> <b> <c> <d> <e>>> <a> <b> .'],
            ['<a> <b> <c> {| |}'],
            ['<a> <b> <c> {| |} .'],
            ['<a> <b> <c> {| <b> |}'],
            ['<a> <b> <c> {| <b1> <c1>; |}'],
            ['<a> <b> <c> {| <b1> <c1>; <b2> |}'],
            ['<a> <b> <c> {| <b1> <c1>'],
            ['<a> <b> <c> {| <b1> <c1>. <a2> <b2> <c2>'],
            ['<a> <b> <c> |}'],
        ];
    }

    public function testWriterSerializesLanguageTagsWithDigitSubtags(): void
    {
        $writer = new TriGWriter();
        $writer->addTriple('a', 'b', '"cde"@qqq-002');

        $this->assertEquals("<a> <b> \"cde\"@qqq-002.\n", $writer->end());
    }

    public function testWriterSerializesDirectionalLanguageTags(): void
    {
        $writer = new TriGWriter();
        $writer->addTriple('a', 'b', '"cde"@en-us--ltr');

        $this->assertEquals("<a> <b> \"cde\"@en-us--ltr.\n", $writer->end());
    }

    public function testWriterSerializesTripleTerms(): void
    {
        $writer = new TriGWriter();
        $writer->addTriple('a', 'b', $this->tripleTerm('a', 'b', 'c'));

        $this->assertEquals("<a> <b> <<(<a> <b> <c>)>>.\n", $writer->end());
    }

    public function testWriterSerializesTripleTermSubjects(): void
    {
        $writer = new TriGWriter();
        $writer->addTriple($this->tripleTerm('a', 'b', 'c'), 'b', 'c');

        $this->assertEquals("<<(<a> <b> <c>)>> <b> <c>.\n", $writer->end());
    }

    public function testWriterSerializesTripleTermSubjectsWithBlankNodes(): void
    {
        $writer = new TriGWriter();
        $writer->addTriple($this->tripleTerm('_:b1', '_:b2', '_:b3'), 'b', 'c');

        $this->assertEquals("<<(_:b1 _:b2 _:b3)>> <b> <c>.\n", $writer->end());
    }

    public function testWriterSerializesTripleTermObjectsWithBlankNodes(): void
    {
        $writer = new TriGWriter();
        $writer->addTriple('a', 'b', $this->tripleTerm('_:b1', '_:b2', '_:b3'));

        $this->assertEquals("<a> <b> <<(_:b1 _:b2 _:b3)>>.\n", $writer->end());
    }

    public function testWriterSerializesTripleTermWithMixedComponents(): void
    {
        $writer = new TriGWriter();
        $writer->addTriple($this->tripleTerm('_:b1', 'b', '"l"'), 'b', 'c');

        $this->assertEquals("<<(_:b1 <b> \"l\")>> <b> <c>.\n", $writer->end());
    }

    public function testWriterSerializesQuadsWithTripleTermSubject(): void
    {
        $writer = new TriGWriter(['format' => 'N-Quads']);
        $writer->addTriple($this->tripleTerm('a', 'b', 'c'), 'b', 'c', 'g');

        $this->assertEquals("<<(<a> <b> <c>)>> <b> <c> <g>.\n", $writer->end());
    }

    public function testWriterSerializesQuadsWithTripleTermObject(): void
    {
        $writer = new TriGWriter(['format' => 'N-Quads']);
        $writer->addTriple('a', 'b', $this->tripleTerm('a', 'b', 'c'), 'g');

        $this->assertEquals("<a> <b> <<(<a> <b> <c>)>> <g>.\n", $writer->end());
    }

    public function testWriterSerializesTripleTermsWithGraphComponent(): void
    {
        $writer = new TriGWriter(['format' => 'N-Quads']);
        $writer->addTriple($this->tripleTerm('a', 'b', 'c', 'g'), 'b', 'c', 'g');

        $this->assertEquals("<<(<a> <b> <c> <g>)>> <b> <c> <g>.\n", $writer->end());
    }

    public function testWriterSerializesQuadTermObjects(): void
    {
        $writer = new TriGWriter(['format' => 'N-Quads']);
        $writer->addTriple('a', 'b', $this->tripleTerm('a', 'b', 'c', 'g'), 'g');

        $this->assertEquals("<a> <b> <<(<a> <b> <c> <g>)>> <g>.\n", $writer->end());
    }

    public function testWriterSerializesTripleWithQuadTermObject(): void
    {
        $writer = new TriGWriter();
        $writer->addTriple('a', 'b', $this->tripleTerm('a', 'b', 'c', 'g'));

        $this->assertEquals("<a> <b> <<(<a> <b> <c> <g>)>>.\n", $writer->end());
    }

    public function testWriterEscapesLowAsciiControlCharacters(): void
    {
        $writer = new TriGWriter();
        $writer->addTriple('a', 'b', '"c'.\chr(0).\chr(1).'"');

        $this->assertEquals("<a> <b> \"c\\u0000\\u0001\".\n", $writer->end());
    }

    public function testLineModeWriterIgnoresPrefixes(): void
    {
        $writer = new TriGWriter(['format' => 'N-Triples', 'prefixes' => ['a' => 'b#']]);
        $writer->addPrefix('c', 'd#');
        $writer->addTriple('a', 'b', '"c"');

        $this->assertEquals("<a> <b> \"c\".\n", $writer->end());
    }

    public function testWriterRejectsWritesAfterEnd(): void
    {
        $writer = new TriGWriter();
        $writer->addTriple('a', 'b', 'c');
        $writer->end();

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Cannot write because the writer has been closed.');

        $writer->addTriple('d', 'e', 'f');
    }

    public function testUtilUnderstandsDirectionalLanguageLiterals(): void
    {
        $literal = '"Hello"@EN--RTL';

        $this->assertEquals(Util::RDFDIRLANGSTRING, Util::getLiteralType($literal));
        $this->assertEquals('en', Util::getLiteralLanguage($literal));
        $this->assertEquals('rtl', Util::getLiteralDirection($literal));
    }
}
