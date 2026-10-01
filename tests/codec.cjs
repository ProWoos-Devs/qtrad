const assert = require('node:assert/strict');
const codec = require('../qtrad/assets/js/codec.js');
const cases = require('./fixtures/codec.json');
for (const test of cases) {
  const parts = codec.split(test.text, test.enabled, false);
  assert.deepEqual({...parts}, test.parts, test.name);
  if (!test.skipRoundTrip) assert.equal(codec.join(parts, test.format, test.enabled, test.force), test.joined ?? test.text, test.name);
}
const more = {'en':'Intro<!--more-->End','de':'Anfang<!--more-->Ende','es':'Inicio<!--more-->Fin'};
assert.deepEqual({...codec.split(codec.joinContent(more,'comment',['en','de']),['en','de'],false)},more);
console.log(`${cases.length + 1} codec cases passed.`);
