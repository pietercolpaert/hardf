<?php

use PHPUnit\Framework\TestCase;
use pietercolpaert\hardf\Util;

/**
 * @covers Util
 */
class UtilTest extends PHPUnit_Framework_TestCase
{
    public function testIsIRI ()
    {   
        $this->assertInternalType(
            "boolean",
            Util::isIRI("http://test.be")
        );
        $this->assertEquals(
            true,
            Util::isIRI("http://test.be")
        );
        $this->assertEquals(
            false,
            Util::isIRI("\"http://test.be\"")
        );
        //Does not match a blank node
        $this->assertEquals(
            false,
            Util::isIRI("_:A")
        );
        $this->assertEquals(false,Util::isIRI(null));
    }

    public function testIsLiteral ()
    {
        $this->assertEquals(true,Util::isLiteral('"http://example.org/"'));
        $this->assertEquals(true,Util::isLiteral('"English"@en'));
        //it('matches a literal with a language that contains a number', function () {
        $this->assertEquals(true,Util::isLiteral('"English"@es-419'));
        //it('matches a literal with a type', function () {
        $this->assertEquals(true,Util::isLiteral('"3"^^http://www.w3.org/2001/XMLSchema#integer'));
        //it('matches a literal with a newline', function () {
        $this->assertEquals(true,Util::isLiteral('"a\nb"'));
        //it('matches a literal with a cariage return', function () {
        $this->assertEquals(true,Util::isLiteral('"a\rb"'));
        //it('does not match an IRI', function () {
        $this->assertEquals(false,Util::isLiteral('http://example.org/'));
        //it('does not match a blank node', function () {
        $this->assertEquals(false,Util::isLiteral('_:x'));
        //it('does not match null', function () {
        $this->assertEquals(false,Util::isLiteral(null));
    }
    
    public function testIsBlank ()
    {
        //it('matches a blank node', function () {
        $this->assertEquals(true,Util::isBlank('_:x'));
        //it('does not match an IRI', function () {
        $this->assertEquals(false,Util::isBlank('http://example.org/'));
        //it('does not match a literal', function () {
        $this->assertEquals(false,Util::isBlank('"http://example.org/"'));
        $this->assertEquals(false,Util::isBlank(null));
    }

    public function testIsDefaultGraph () 
    {

        $this->assertEquals(false,Util::isDefaultGraph('_:x'));
        $this->assertEquals(false,Util::isDefaultGraph('http://example.org/'));
        $this->assertEquals(false,Util::isDefaultGraph('"http://example.org/"'));
        //it('matches null', function () {
        $this->assertEquals(true,Util::isDefaultGraph(null));
        //it('matches the empty string', function () {
        $this->assertEquals(true,Util::isDefaultGraph(''));
    }


    public function testinDefaultGraph () {
        //it('does not match a blank node', function () {
        $this->assertEquals(false,Util::inDefaultGraph(["graph" => '_:x' ]));
        //it('does not match an IRI', function () {
        $this->assertEquals(false,Util::inDefaultGraph(["graph" =>'http://example.org/' ]));
        //it('does not match a literal', function () {
        $this->assertEquals(false,Util::inDefaultGraph(["graph" => '"http://example.org/"' ]));
        //it('matches null', function () {
        $this->assertEquals(true,Util::inDefaultGraph(["graph" => null ]));
        //it('matches the empty string', function () {
        $this->assertEquals(true,Util::inDefaultGraph(["graph" => '' ]));
    }

    public function testGetLiteralValue () {
        //it('gets the value of a literal', function () {
        $this->assertEquals('Mickey', Util::getLiteralValue('"Mickey"'));
    

        //it('gets the value of a literal with a language', function () {
        $this->assertEquals('English', Util::getLiteralValue('"English"@en'));
    

        //it('gets the value of a literal with a language that contains a number', function () {
        $this->assertEquals('English', Util::getLiteralValue('"English"@es-419'));
    

        //it('gets the value of a literal with a type', function () {
        $this->assertEquals('3', Util::getLiteralValue('"3"^^http://www.w3.org/2001/XMLSchema#integer'));
    

        //it('gets the value of a literal with a newline', function () {
        $this->assertEquals('Mickey\nMouse', Util::getLiteralValue('"Mickey\nMouse"'));
    

        //it('gets the value of a literal with a cariage return', function () {
        $this->assertEquals('Mickey\rMouse', Util::getLiteralValue('"Mickey\rMouse"'));
    

        //it('does not work with non-literals', function () {
        //TODO: Util::getLiteralValue.bind(null, 'http://ex.org/').should.throw('http://ex.org/ is not a literal');
    

        //it('does not work with null', function () {
        //TODO: Util::getLiteralValue.bind(null, null).should.throw('null is not a literal');    
    }

    public function testGetLiteralType () {
        //it('gets the type of a literal', function () {
        $this->assertEquals('http://www.w3.org/2001/XMLSchema#string', Util::getLiteralType('"Mickey"'));

        //it('gets the type of a literal with a language', function () {
        $this->assertEquals('http://www.w3.org/1999/02/22-rdf-syntax-ns#langString', Util::getLiteralType('"English"@en'));
    

        //it('gets the type of a literal with a language that contains a number', function () {
        $this->assertEquals('http://www.w3.org/1999/02/22-rdf-syntax-ns#langString', Util::getLiteralType('"English"@es-419'));
    

        //it('gets the type of a literal with a type', function () {
        $this->assertEquals('http://www.w3.org/2001/XMLSchema#integer', Util::getLiteralType('"3"^^http://www.w3.org/2001/XMLSchema#integer'));
    

        //it('gets the type of a literal with a newline', function () {
        $this->assertEquals('abc', Util::getLiteralType('"Mickey\nMouse"^^abc'));
    

        //it('gets the type of a literal with a cariage return', function () {
        $this->assertEquals('abc', Util::getLiteralType('"Mickey\rMouse"^^abc'));
    

        //it('does not work with non-literals', function () {
        //TODO: Util::getLiteralType.bind(null, 'http://example.org/').should.throw('http://example.org/ is not a literal');
    

        //it('does not work with null', function () {
        //TODO: Util::getLiteralType.bind(null, null).should.throw('null is not a literal');

    }    
  

    public function testGetLiteralLanguage () {
        //it('gets the language of a literal', function () {
        $this->assertEquals('', Util::getLiteralLanguage('"Mickey"'));
    

        //it('gets the language of a literal with a language', function () {
        $this->assertEquals('en', Util::getLiteralLanguage('"English"@en'));
    

        //it('gets the language of a literal with a language that contains a number', function () {
        $this->assertEquals('es-419', Util::getLiteralLanguage('"English"@es-419'));
    

        //it('normalizes the language to lowercase', function () {
        $this->assertEquals('en-gb', Util::getLiteralLanguage('"English"@en-GB'));
    

        //it('gets the language of a literal with a type', function () {
        $this->assertEquals('', Util::getLiteralLanguage('"3"^^http://www.w3.org/2001/XMLSchema#integer'));
    

        //it('gets the language of a literal with a newline', function () {
        $this->assertEquals('en', Util::getLiteralLanguage('"Mickey\nMouse"@en'));
    

        //it('gets the language of a literal with a cariage return', function () {
        $this->assertEquals('en', Util::getLiteralLanguage('"Mickey\rMouse"@en'));
    

        //it('does not work with non-literals', function () {
        //TODO: Util::getLiteralLanguage.bind(null, 'http://example.org/').should.throw('http://example.org/ is not a literal');
    

        //it('does not work with null', function () {
        //TODO: Util::getLiteralLanguage.bind(null, null).should.throw('null is not a literal');    
    }
 

    public function testIsPrefixedName () {
        //it('matches a prefixed name', function () {
        $this->assertEquals(true,Util::isPrefixedName('ex:Test'));
    

        //it('does not match an IRI', function () {
        $this->assertEquals(false,Util::isPrefixedName('http://example.org/'));
    

        //it('does not match a literal', function () {
        $this->assertEquals(false,Util::isPrefixedName('"http://example.org/"'));
    

        //it('does not match a literal with a colon', function () {
        $this->assertEquals(false,Util::isPrefixedName('"a:b"'));
    

        //it('does not match null', function () {
        $this->assertEquals(null, Util::isPrefixedName(null));
    }    
  

    public function testExpandPrefixedName () {
        //it('expands a prefixed name', function () {
        $this->assertEquals('http://ex.org/#Test', Util::expandPrefixedName('ex:Test', [ 'ex' => 'http://ex.org/#' ]));
        //it('expands a type with a prefixed name', function () {
        $this->assertEquals('"a"^^http://ex.org/#type', Util::expandPrefixedName('"a"^^ex:type', [ "ex" => 'http://ex.org/#' ]));
        //it('expands a prefixed name with the empty prefix', function () {
        $this->assertEquals('http://ex.org/#Test', Util::expandPrefixedName(':Test', [ '' => 'http://ex.org/#' ]));
        //it('does not expand a prefixed name if the prefix is unknown', function () {
        $this->assertEquals('a:Test', Util::expandPrefixedName('a:Test', [ "b"=> 'http://ex.org/#' ]));
        //it('returns the input if //it is not a prefixed name', function () {
        $this->assertEquals('abc', Util::expandPrefixedName('abc', null));
    }

    public function testCreateIRI () {
        //it('converts a plain IRI', function () {
        $this->assertEquals('http://ex.org/foo#bar', Util::createIRI('http://ex.org/foo#bar'));
    

        //it('converts a literal', function () {
        $this->assertEquals('http://ex.org/foo#bar', Util::createIRI('"http://ex.org/foo#bar"^^uri:type'));
    

        //it('converts null', function () {
        $this->assertEquals(null, Util::createIRI(null));
    
    }

    public function testCreateLiteral () {
        //it('converts the empty string', function () {
        $this->assertEquals('""', Util::createLiteral(''));
    

        //it('converts the empty string with a language', function () {
        $this->assertEquals('""@en-gb', Util::createLiteral('', 'en-GB'));
    

        //it('converts the empty string with a type', function () {
        $this->assertEquals('""^^http://ex.org/type', Util::createLiteral('', 'http://ex.org/type'));
    

        //it('converts a non-empty string', function () {
        $this->assertEquals('"abc"', Util::createLiteral('abc'));
    

        //it('converts a non-empty string with a language', function () {
        $this->assertEquals('"abc"@en-gb', Util::createLiteral('abc', 'en-GB'));
    

        //it('converts a non-empty string with a type', function () {
        $this->assertEquals('"abc"^^http://ex.org/type', Util::createLiteral('abc', 'http://ex.org/type'));
    

        //it('converts an integer', function () {
        $this->assertEquals('"123"^^http://www.w3.org/2001/XMLSchema#integer', Util::createLiteral(123));
    

        //it('converts a decimal', function () {
        $this->assertEquals('"2.3"^^http://www.w3.org/2001/XMLSchema#double', Util::createLiteral(2.3));
    

        //it('converts infinity', function () {
        $this->assertEquals('"INF"^^http://www.w3.org/2001/XMLSchema#double', Util::createLiteral(INF));
    

        //it('converts false', function () {
        $this->assertEquals('"false"^^http://www.w3.org/2001/XMLSchema#boolean', Util::createLiteral(false));
    

        //it('converts true', function () {
        $this->assertEquals('"true"^^http://www.w3.org/2001/XMLSchema#boolean', Util::createLiteral(true));
    
    } 
/*
  public function testprefix () {
  var baz = Util::prefix('http://ex.org/baz#');
  //it('should return a function', function () {
  $this->assertEquals(an.instanceof(Function), baz);
    
  }
  public function testthe function () {
  //it('should expand the prefix', function () {
  expect(baz('bar')).to.equal('http://ex.org/baz#bar');
      
  }   
*/
/*
  public function testprefixes () {
  public function testCalled without arguments () {
  var prefixes = Util::prefixes();
  //it('should return a function', function () {
  $this->assertEquals(an.instanceof(Function), prefixes);
      

  public function testthe function () {
  //it('should not expand non-registered prefixes', function () {
  expect(prefixes('baz')('bar')).to.equal('bar');
        

  //it('should allow registering prefixes', function () {
  var p = prefixes('baz', 'http://ex.org/baz#');
  expect(p).to.exist;
  expect(p).to.equal(prefixes('baz'));
        

  //it('should expand the newly registered prefix', function () {
  expect(prefixes('baz')('bar')).to.equal('http://ex.org/baz#bar');
        
      
  }*/    
/*
    public function testCalled with a hash of prefixes () {
        var prefixes = Util::prefixes({ foo: 'http://ex.org/foo#', bar: 'http://ex.org/bar#' 
                //it('should return a function', function () {
                $this->assertEquals(an.instanceof(Function), prefixes);
      

            public function testthe function () {
                //it('should expand registered prefixes', function () {
                expect(prefixes('foo')('bar')).to.equal('http://ex.org/foo#bar');
                expect(prefixes('bar')('bar')).to.equal('http://ex.org/bar#bar');
        

                //it('should not expand non-registered prefixes', function () {
                expect(prefixes('baz')('bar')).to.equal('bar');
        

                //it('should allow registering prefixes', function () {
                var p = prefixes('baz', 'http://ex.org/baz#');
                expect(p).to.exist;
                expect(p).to.equal(prefixes('baz'));
        

                //it('should expand the newly registered prefix', function () {
                expect(prefixes('baz')('bar')).to.equal('http://ex.org/baz#bar');
        
      
            }
*/
}
        