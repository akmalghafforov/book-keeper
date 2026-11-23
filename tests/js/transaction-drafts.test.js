import test from 'node:test';
import assert from 'node:assert/strict';
import { draftReview, missingPicker } from '../../resources/js/transaction-drafts.js';

test('Quick edit uses the saved revision even when response ETags vary by encoding', async () => {
    const review = draftReview({
        id: 'draft',
        etag: '"1"',
        savedDrafts: { draft: {} },
        messages: { saved: 'Draft saved.', refreshed: 'Draft refreshed.' },
    });
    const draft = { revision: 2, status: 'pending', current_fields: { payment_method: 'cash' } };
    const headers = new Headers({ ETag: '"2-gzip"' });
    review.openEditor();
    review.setField('payment_method', 'card');
    review.setField('notes', 'Keep these notes');
    review.request = async action => {
        if (action === 'update') {
            return { response: { ok: false, status: 412, headers }, body: { data: { attributes: draft } } };
        }
        assert.equal(review.etag, '"2"');
        assert.equal(action, 'preview');
        return { response: { ok: true, headers }, body: { revision: 2, validation_issues: {} } };
    };
    await review.act('update');
    assert.equal(review.message, 'Draft refreshed.');
    assert.equal(review.fields.payment_method, 'cash');
    assert.deepEqual(review.changes, { notes: 'Keep these notes' });

    review.request = async action => {
        assert.equal(action, 'update');
        assert.equal(review.etag, '"2"');
        const saved = { ...draft, revision: 3, current_fields: { ...draft.current_fields, notes: 'Keep these notes' } };
        return { response: { ok: true, headers }, body: { data: { attributes: saved } } };
    };
    await review.act('update');
    assert.equal(review.editorOpen, false);
    assert.equal(review.etag, '"3"');
    assert.equal(review.message, 'Draft saved.');
});

test('Saving immediately after a provider time update submits the selected timestamp', async () => {
    const field = 'provider_received_at';
    const review = draftReview({
        id: 'draft',
        kind: 'distribution',
        original: { product_id: 7 },
        savedDrafts: { draft: { product_id: 7 } },
        draftChoices: { distribution: { product_id: [{ id: 7, default_provider_id: 1 }] } },
        messages: { saved: 'Draft saved.' },
        $refs: { missingDialog: { close() {} } },
    });
    const input = Object.assign(new EventTarget(), { value: '' });
    const timestamp = '2026-11-21T20:21';
    let options;
    const originalWindow = globalThis.window;
    globalThis.window = { flatpickr: (_, config) => { options = config; } };
    try {
        missingPicker(field).init.call(Object.assign(review, { $el: input, $watch() {} }));
    } finally {
        if (originalWindow === undefined) delete globalThis.window;
        else globalThis.window = originalWindow;
    }
    const requests = [];
    review.request = async (action, method, body) => {
        requests.push({ action, method, body });
        return {
            response: { ok: true },
            body: { data: { attributes: {
                revision: 2,
                original_fields: review.original,
                current_fields: { product_id: 7, [field]: timestamp + ':00+05:00' },
            } } },
        };
    };
    review.check = () => {};

    input.value = timestamp;
    options.onValueUpdate();
    await review.saveMissing();

    assert.deepEqual(requests, [{ action: 'missing-fields', method: 'PATCH', body: { fields: { [field]: timestamp } } }]);
    assert.equal(review.saved[field], timestamp + ':00+05:00');
});
