import test from 'node:test';
import assert from 'node:assert/strict';

global.window = {};

const { clientSelect2Matcher, toTajikKeyboardLayout } = await import('../../resources/js/client-select2-matcher.js');

test('converts English and Russian keyboard output to official Tajik layout', () => {
    assert.equal(toTajikKeyboardLayout('frvfk'), 'акмал');
    assert.equal(toTajikKeyboardLayout('-арибак'), 'ғарибак');
    assert.equal(toTajikKeyboardLayout('цщыь='), 'қҳҷӣӯ');
});

test('preserves uppercase output for Tajik layout characters', () => {
    assert.equal(toTajikKeyboardLayout('FRVFK'), 'АКМАЛ');
    assert.equal(toTajikKeyboardLayout('_FH'), 'ҒАР');
});

test('matches client names with direct, converted, and original terms', () => {
    const akmal = { id: '1', text: 'Акмал' };
    const gharibak = { id: '2', text: 'Ғарибак' };
    const acme = { id: '3', text: 'Acme Trading' };

    assert.equal(clientSelect2Matcher({ term: 'frvfk' }, akmal), akmal);
    assert.equal(clientSelect2Matcher({ term: '-арибак' }, gharibak), gharibak);
    assert.equal(clientSelect2Matcher({ term: 'ғар' }, gharibak), gharibak);
    assert.equal(clientSelect2Matcher({ term: 'Acme' }, acme), acme);
    assert.equal(clientSelect2Matcher({ term: 'frvfk' }, gharibak), null);
});

test('keeps Select2 empty-term behavior', () => {
    const client = { id: '1', text: 'Акмал' };

    assert.equal(clientSelect2Matcher({ term: '' }, client), client);
    assert.equal(clientSelect2Matcher({}, client), client);
});
