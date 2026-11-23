const numberFormat = new Intl.NumberFormat('en', { maximumFractionDigits: 4 });
const missing = '—';

function withUnit(number, unit) {
    return [numberFormat.format(number), unit].filter(Boolean).join(' ');
}

export function draftMessage(config) {
    return {
        ...config,

        get showShop() {
            return this.distributionIds.some(id => Boolean(this.savedDrafts[id].shop_id));
        },

        get freightId() {
            if (this.distributionIds.length !== 1) return null;
            const vehicles = this.paymentIds.filter(id => {
                const { type, payment_purpose } = this.savedDrafts[id];
                return type === 'payment' && payment_purpose === 'vehicle';
            });
            return vehicles.length === 1 ? vehicles[0] : null;
        },

        hasPaymentRows(ids) {
            return ids.some(id => id !== this.freightId);
        },
    };
}

export function draftReview(config) {
    const emptyPreview = () => ({ validation_issues: {}, effects: [], matches: [], payment: null });
    const getKey = () => Array.from(crypto.getRandomValues(new Uint8Array(16)), byte => byte.toString(16).padStart(2, '0')).join('');

    return {
        ...config,
        fields: {},
        edits: {},
        editorReady: false,
        editorOpen: false,
        dismissalOpen: false,
        approvalRetry: null,
        issues: {},
        preview: emptyPreview(),
        stale: null,
        closed: null,
        message: '',
        confirmed: false,
        busy: false,
        reason: '',
        postedId: '',
        previewSaved: false,
        checking: 0,
        pendingApproval: null,
        gradeFamily: '',

        async request(action, method = 'POST', payload = {}, approvalKey = null) {
            const headers = { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': this.csrf, 'If-Match': this.etag };
            if (approvalKey) headers['Idempotency-Key'] = approvalKey;
            const response = await fetch(this.urls[action], { method, headers, body: method === 'GET' ? undefined : JSON.stringify(payload) });
            const body = await response.json().catch(() => ({}));
            return { response, body };
        },

        get changes() {
            return Object.fromEntries(Object.keys(this.edits).map(key => [key, this.fields[key] ?? null]));
        },

        get postedRecord() {
            return this.closed?.posted_record ?? this.closed?.matched_posted_record;
        },

        get postedMatches() {
            return this.previewSaved
                ? this.preview.matches.filter(match => match.source === 'posted' && match.kind === this.kind)
                : [];
        },

        init() {
            this.fields = { ...this.saved };
            this.gradeFamily = this.cementFamilies[this.id] ?? '';
        },

        get saved() {
            return this.savedDrafts[this.id];
        },

        set saved(fields) {
            this.savedDrafts[this.id] = fields;
        },

        choiceName(field, value) {
            return this.draftChoices[this.kind]?.[field]?.find(option => String(option.id) === String(value))?.name;
        },

        get gradeFamilies() {
            if (this.closed || this.saved.product_id != null || this.saved.quantity_unit !== 'per_bag'
                || String(this.saved.product_category_id) !== String(this.cementReview.baggedCategory)) return [];
            const families = Object.keys(this.cementReview.gradePairs);
            const suggested = this.cementFamilies[this.id];
            return suggested ? families.filter(family => family === suggested) : families;
        },

        get gradePair() {
            return this.gradeFamilies.includes(this.gradeFamily) ? this.cementReview.gradePairs[this.gradeFamily] : null;
        },

        get gradeDisabled() {
            return this.busy || !!this.stale || Object.keys(this.edits).length > 0;
        },

        async saveGrade(grade) {
            const product = this.gradePair?.[grade];
            if (!product || this.gradeDisabled) return;
            this.dismissalOpen = false;
            this.setField('product_id', product);
            await this.act('update');
            if (!this.closed && this.edits.product_id) this.openEditor();
        },

        get bulkWeightWarning() {
            const overweight = fields => this.cementReview.bulkCategory != null
                && String(fields.product_category_id) === String(this.cementReview.bulkCategory)
                && fields.quantity_unit === 'per_ton' && Number(fields.quantity) > 40;
            return !this.closed && (overweight(this.saved) || (this.editorOpen && overweight(this.fields)));
        },

        cellValue(field) {
            const value = this.saved[field];
            switch (field) {
                case 'quantity':
                    return value == null ? missing : withUnit(value, this.choiceName('quantity_unit', this.saved.quantity_unit));
                case 'price':
                    return value == null ? missing : numberFormat.format(value);
                case 'total': {
                    const { quantity, price } = this.saved;
                    return quantity == null || price == null ? missing : numberFormat.format(quantity * price);
                }
                case 'amount':
                case 'freight': {
                    const payment = field === 'amount' ? this.saved : this.savedDrafts[this.freightId];
                    return payment?.amount == null ? missing : withUnit(payment.amount, payment.currency);
                }
                case 'description': {
                    const { notes, payment_purpose, payer_name } = this.saved;
                    if (notes?.trim()) return notes;
                    const purpose = this.choiceName('payment_purpose', payment_purpose);
                    return purpose?.replace(':payerName', () => payer_name?.trim() ? payer_name : missing) ?? missing;
                }
                default:
                    return this.choiceName(field, value) ?? this.draftCatalog[field]?.[value] ?? missing;
            }
        },

        openEditor() {
            this.editorReady = true;
            this.editorOpen = true;
        },

        toggleEditor() {
            this.checking++;
            if (this.editorOpen) {
                this.editorOpen = false;
                return;
            }
            this.dismissalOpen = false;
            this.openEditor();
            this.check();
        },

        toggleDismissal() {
            this.checking++;
            this.dismissalOpen = !this.dismissalOpen;
            if (this.dismissalOpen) {
                this.editorReady = true;
                this.editorOpen = false;
                this.confirmed = false;
                if (this.reason === 'already_recorded') this.check(true);
            }
        },

        markStale(response, body) {
            this.openEditor();
            this.stale = { etag: response.headers.get('ETag'), data: body.data.attributes };
        },

        setField(field, value) {
            delete this.issues[field];
            delete this.issues['fields.' + field];
            this.fields[field] = value === '' ? null : value;
            if (String(this.saved[field] ?? '') === String(this.fields[field] ?? '')) delete this.edits[field];
            else this.edits[field] = true;
            this.confirmed = false;
            this.preview = emptyPreview();
            this.checking++;
        },

        visible(field) {
            if (this.errors(field).length) return true;
            if (['currency', 'exchange_rate', 'payment_method', 'payment_purpose', 'reference_id'].includes(field)) return this.fields.type === 'payment';
            if (field === 'payer_name') return this.fields.type === 'payment' && this.fields.payment_purpose === 'on_behalf_of';
            if (field === 'car_number') return this.fields.type !== 'payment';
            if (field === 'credit_client_price') return Boolean(this.fields.credit_client_id) || Boolean(this.fields[field]);
            return true;
        },

        options(field, choices) {
            const parent = { shop_id: 'client_id', product_id: 'product_category_id' }[field];
            const options = parent ? choices.filter(choice => String(choice[parent]) === String(this.fields[parent])) : choices;
            const value = this.fields[field];
            return value && !options.some(option => String(option.id) === String(value))
                ? [{ id: value, name: '#' + value }, ...options]
                : options;
        },

        errors(field) {
            return this.issues[field] ?? this.issues['fields.' + field] ?? this.preview.validation_issues[field] ?? [];
        },

        async check(saved = this.dismissalOpen) {
            if (this.closed || this.stale || (!this.editorOpen && !this.dismissalOpen)) return;
            const token = ++this.checking;
            try {
                const { response, body } = await this.request('preview', 'POST', { fields: saved ? {} : this.changes });
                if (this.checking !== token) return;
                if (response.ok && response.headers.get('ETag') !== this.etag) {
                    await this.readCurrent(token);
                    return;
                }
                if (response.status === 409) {
                    await this.readCurrent(token);
                    return;
                }
                if (response.ok) {
                    this.preview = body;
                    this.previewSaved = saved || Object.keys(this.changes).length === 0;
                } else if (response.status === 422) {
                    this.issues = body.errors ?? {};
                }
            } catch {
                if (this.checking === token) this.message = this.messages.failed;
            }
        },

        async readCurrent(token) {
            const { response, body } = await this.request('show', 'GET');
            if (!response.ok || this.checking !== token) return;
            if (body.data.attributes.status !== 'pending') this.close(body.data.attributes);
            else this.markStale(response, body);
        },

        rebase() {
            const edits = this.changes;
            this.saved = this.stale.data.current_fields;
            this.fields = { ...this.saved, ...edits };
            this.edits = Object.fromEntries(Object.keys(edits)
                .filter(key => String(edits[key] ?? '') !== String(this.saved[key] ?? ''))
                .map(key => [key, true]));
            if (this.dismissalOpen) this.editorOpen = false;
            this.preview = emptyPreview();
            this.postedId = '';
            this.etag = this.stale.etag;
            this.stale = null;
            this.confirmed = false;
            this.issues = {};
            this.message = '';
            this.check();
        },

        close(draft) {
            if (this.closed) return;
            this.saved = draft.current_fields;
            this.closed = draft;
            this.remaining.count = Math.max(0, this.remaining.count - 1);
        },

        async act(action) {
            if (this.busy || this.stale || this.closed) return;
            const approving = ['approve', 'editApprove'].includes(action);
            const saved = action === 'approve' || Object.keys(this.changes).length === 0;
            const payload = action === 'dismiss'
                ? { reason: this.reason, ...(this.reason === 'already_recorded' && this.postedId ? { posted_record_id: this.postedId } : {}) }
                : { fields: saved ? {} : this.changes };
            if (approving && this.confirmed && this.previewSaved === saved && this.preview.duplicate_confirmation) {
                payload.duplicate_confirmation = this.preview.duplicate_confirmation;
            }
            let approvalKey = null;
            if (approving) {
                const identity = JSON.stringify([this.etag, payload]);
                if (this.pendingApproval?.identity !== identity) this.pendingApproval = { identity, key: getKey() };
                approvalKey = this.pendingApproval.key;
            }
            this.checking++;
            this.busy = true;
            this.message = '';
            this.issues = {};

            try {
                const { response, body } = await this.request(approving ? 'approve' : action, action === 'update' ? 'PATCH' : 'POST', payload, approvalKey);
                if (response.ok) {
                    const draft = body.data.attributes;
                    if (draft.status === 'pending') {
                        this.saved = draft.current_fields;
                        this.fields = { ...this.saved };
                        this.edits = {};
                        this.etag = response.headers.get('ETag');
                        this.confirmed = false;
                        this.approvalRetry = null;
                        this.message = this.messages.saved;
                        await this.check();
                    } else {
                        this.close(draft);
                    }
                    return;
                }

                this.message = body.message ?? (response.status === 422 ? this.messages.checkFields : this.messages.failed);
                if (response.status === 422) {
                    this.issues = body.errors ?? {};
                    if (action !== 'dismiss') {
                        this.dismissalOpen = false;
                        this.openEditor();
                    }
                }
                if (response.status === 412) {
                    if (action !== 'dismiss') this.dismissalOpen = false;
                    this.markStale(response, body);
                }
                if (response.status === 409 && body.data && body.data.attributes.status !== 'pending') this.close(body.data.attributes);
                if (response.status === 409 && body.matches) {
                    if (approving) {
                        this.dismissalOpen = false;
                        this.openEditor();
                        this.approvalRetry = action;
                    }
                    this.preview = { ...emptyPreview(), ...body };
                    this.previewSaved = action === 'dismiss' || saved;
                    this.confirmed = false;
                }
            } catch {
                this.message = this.messages.failed;
            } finally {
                this.busy = false;
            }
        },
    };
}

export function draftHistory(url) {
    return {
        draft: null,
        failed: false,
        get postedRecord() {
            return this.draft?.posted_record ?? this.draft?.matched_posted_record;
        },
        async load() {
            if (this.draft) return;
            try {
                const response = await fetch(url, { headers: { Accept: 'application/json' } });
                if (!response.ok) throw new Error('Draft history is unavailable');
                this.draft = (await response.json()).data.attributes;
                this.failed = false;
            } catch {
                this.failed = true;
            }
        },
    };
}
