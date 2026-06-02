#!/usr/bin/env node

'use strict';

const fs = require('node:fs');
const path = require('node:path');
const N3 = require('n3');

function usage() {
  console.log('Usage: n3js-perf.js filename');
  process.exit(1);
}

function inferFormat(filename) {
  const extension = path.extname(filename).toLowerCase();
  const formats = {
    '.trig': 'application/trig',
    '.ttl': 'text/turtle',
    '.nq': 'application/n-quads',
    '.nt': 'application/n-triples',
    '.n3': 'text/n3',
  };

  return formats[extension] || undefined;
}

if (process.argv.length !== 3) {
  usage();
}

const filename = process.argv[2];
if (!fs.existsSync(filename)) {
  console.log(`File not found or not readable: ${filename}`);
  process.exit(1);
}

const start = process.hrtime.bigint();
let count = 0;
let finished = false;

function elapsedSeconds() {
  return Number(process.hrtime.bigint() - start) / 1e9;
}

function printSuccess() {
  if (finished) {
    return;
  }
  finished = true;
  console.log(`- Parsing file ${filename}: ${elapsedSeconds()}s`);
  console.log(`* Triples parsed: ${count}`);
  console.log(`* Memory usage: ${process.memoryUsage().heapUsed / 1024 / 1024}MB`);
}

function printFailure(error) {
  if (finished) {
    return;
  }
  finished = true;
  console.log(`- Parsing file ${filename} failed after ${elapsedSeconds()}s`);
  console.log(`* Error: ${error.message}`);
  process.exit(1);
}

const rdfStream = fs.createReadStream(filename, { encoding: 'utf8' });
const parser = new N3.StreamParser({
  baseIRI: `file://${filename}`,
  format: inferFormat(filename),
});

rdfStream.on('error', printFailure);
parser.on('error', printFailure);
parser.on('data', () => {
  count += 1;
});
parser.on('end', printSuccess);

rdfStream.pipe(parser);