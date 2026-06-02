'use strict';

const { spawnSync } = require('node:child_process');
const path = require('node:path');
const { DataFactory } = require('n3');

const { namedNode, blankNode, literal, defaultGraph, quad } = DataFactory;

function formatFromTestCase(baseIRI, options, testCase) {
  const types = (testCase && testCase.types ? testCase.types : []).join(' ');
  if (/NQuads/i.test(types) || /\.nq(?:$|[#?])/.test(baseIRI)) {
    return 'N-Quads';
  }
  if (/NTriples/i.test(types) || /\.nt(?:$|[#?])/.test(baseIRI)) {
    return 'N-Triples';
  }
  if (/Trig/i.test(types) || /\.trig(?:$|[#?])/.test(baseIRI)) {
    return 'TriG';
  }
  if (/Turtle/i.test(types) || /\.ttl(?:$|[#?])/.test(baseIRI)) {
    return 'Turtle';
  }

  return options && options.format ? options.format : 'Turtle';
}

function toRdfjsTerm(term) {
  switch (term.termType) {
    case 'NamedNode':
      return namedNode(term.value);
    case 'BlankNode':
      return blankNode(term.value);
    case 'DefaultGraph':
      return defaultGraph();
    case 'Literal':
      if (term.direction && term.language) {
        return literal(term.value, term.language, term.direction);
      }
      if (term.language) {
        return literal(term.value, term.language);
      }
      return literal(term.value, namedNode(term.datatype));
    case 'Quad':
      return quad(
        toRdfjsTerm(term.subject),
        toRdfjsTerm(term.predicate),
        toRdfjsTerm(term.object),
        term.graph ? toRdfjsTerm(term.graph) : defaultGraph(),
      );
    default:
      throw new Error(`Unsupported term type from hardf bridge: ${term.termType}`);
  }
}

module.exports = {
  async parse(data, baseIRI, options, testCase) {
    const bridge = path.join(__dirname, 'hardf-rdf-test-parser.php');
    const result = spawnSync('php', [ bridge ], {
      cwd: path.join(__dirname, '..'),
      encoding: 'utf8',
      input: JSON.stringify({
        baseIRI,
        data,
        format: formatFromTestCase(baseIRI, options || {}, testCase),
      }),
      maxBuffer: 64 * 1024 * 1024,
    });

    if (result.status !== 0) {
      throw new Error((result.stderr || result.stdout || `hardf parser exited with ${result.status}`).trim());
    }

    return JSON.parse(result.stdout).map(item => quad(
      toRdfjsTerm(item.subject),
      toRdfjsTerm(item.predicate),
      toRdfjsTerm(item.object),
      toRdfjsTerm(item.graph),
    ));
  },

  async parseNegative(data, baseIRI, options, testCase) {
    const bridge = path.join(__dirname, 'hardf-rdf-test-parser.php');
    const result = spawnSync('php', [ bridge ], {
      cwd: path.join(__dirname, '..'),
      encoding: 'utf8',
      input: JSON.stringify({
        baseIRI,
        data,
        format: formatFromTestCase(baseIRI, options || {}, testCase),
      }),
      maxBuffer: 64 * 1024 * 1024,
    });

    if (result.status === 0) {
      throw new Error('Expected parser to reject invalid input, but it succeeded');
    }
  },
};
