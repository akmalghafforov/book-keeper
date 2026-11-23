import test from 'node:test';
import assert from 'node:assert/strict';
import { draftReview } from '../../resources/js/transaction-drafts.js';

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
