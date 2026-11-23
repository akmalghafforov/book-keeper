import { formatDraftAmount } from './form-amount.js';

const numberFormat = new Intl.NumberFormat('en', { maximumFractionDigits: 4 });
const missing = '—';

function draftTag(revision) {
    return '"' + revision + '"';
}

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

        missingOpen: false,
        photos: [],
        photoIndex: 0,
        photoStatus: 'loading',
        photoRequest: 0,
        correctionField: null,
        missingEntries: {},
        missingIssues: {},
        missingMessage: '',
        missingStale: null,
        missingUnavailable: false,
        missingTrigger: null,
        catalogEntry: null,
        catalogBusy: false,

        openCatalogEntry(field, trigger) {
            this.catalogEntry = {
                field, trigger, type: field === 'supplier_id' ? 'supplier' : 'client',
                values: {}, error: '',
            };
            this.$nextTick(() => this.$refs.catalogEntryInput?.focus());
        },

        closeCatalogEntry() {
            const trigger = this.catalogEntry?.trigger;
            this.catalogEntry = null;
            this.$nextTick(() => trigger?.focus());
        },

        async createCatalogEntry() {
            if (this.catalogBusy || !this.catalogEntry) return;
            const entry = this.catalogEntry;
            this.catalogBusy = true;
            entry.error = '';
            try {
                const response = await fetch(this.catalogUrls[entry.type], {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': this.csrf,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify(entry.values),
                });
                const body = await response.json();
                if (!response.ok || !body.success) {
                    entry.error = Object.values(body.errors ?? {}).flat().join(' ') || body.message || this.messages.failed;
                    return;
                }
                const record = body[entry.type];
                const option = { id: record.id, name: record.name ?? record.car_number };
                for (const choices of Object.values(this.draftChoices)) {
                    for (const key of entry.type === 'client' ? ['client_id', 'credit_client_id'] : ['supplier_id']) {
                        choices[key]?.push(option);
                    }
                }
                this.setMissing(entry.field, String(option.id));
                this.closeCatalogEntry();
            } catch {
                entry.error = this.messages.failed;
            } finally {
                this.catalogBusy = false;
            }
        },

        get missingProduct() {
            return this.draftChoices.distribution?.product_id.find(product => String(product.id) === String(this.missingFields.product_id));
        },

        isMissing(value) {
            return value == null || (typeof value === 'string' && value.trim() === '');
        },

        get missingFields() {
            return { ...this.saved, ...this.missingEntries };
        },

        applicableMissing(field) {
            const fields = this.missingFields;
            const payment = fields.type === 'payment';
            if (['currency', 'exchange_rate', 'payment_method', 'payment_purpose', 'reference_id'].includes(field)) return payment;
            if (field === 'payer_name') return payment && fields.payment_purpose === 'on_behalf_of';
            if (field === 'car_number') return fields.type === 'charge';
            if (field === 'credit_client_price') return !this.isMissing(fields.credit_client_id);
            if (this.kind === 'distribution' && ['provider_buy_price', 'provider_received_at'].includes(field)) {
                return Boolean(this.missingProduct?.default_provider_id);
            }
            return true;
        },

        canCorrect(field) {
            return !this.isMissing(this.original[field]) || !this.isMissing(this.saved[field]);
        },

        canFill(field) {
            if (this.correctionField) return field === this.correctionField;
            return this.isMissing(this.saved[field]) && this.isMissing(this.original[field]) && this.applicableMissing(field);
        },

        showMissingField(field) {
            return this.canFill(field) || !this.isMissing(this.saved[field]) || !this.isMissing(this.original[field]) || this.missingErrors(field).length > 0;
        },

        missingErrors(field) {
            return this.missingIssues['fields.' + field] ?? this.missingIssues[field] ?? [];
        },

        missingValue(field, value) {
            return this.isMissing(value) ? missing : this.choiceName(field, value) ?? this.draftCatalog[field]?.[value] ?? value;
        },

        get filledDetails() {
            const displayed = this.kind === 'distribution'
                ? [...this.columns, 'quantity_unit']
                : this.freightId === this.id ? ['amount', 'currency'] : [...this.columns, 'currency', 'notes'];
            return Object.keys(this.fieldLabels).filter(field => !displayed.includes(field)
                && this.isMissing(this.original[field]) && !this.isMissing(this.saved[field]));
        },

        missingOptions(field) {
            const parent = { shop_id: 'client_id', product_id: 'product_category_id' }[field];
            const choices = this.draftChoices[this.kind][field];
            const options = parent ? choices.filter(choice => String(choice[parent]) === String(this.missingFields[parent])) : choices;
            const savedValue = this.saved[field];
            if (this.correctionField === field && !this.isMissing(savedValue)
                && !options.some(option => String(option.id) === String(savedValue))) {
                return [{ id: savedValue, name: this.missingValue(field, savedValue) }, ...options];
            }
            return options;
        },

        missingInput(field) {
            if (this.correctionField === field) return this.missingEntries[field] ?? this.saved[field] ?? '';
            if (field === 'exchange_rate' && this.missingFields.currency === 'TJS') return '1';
            if (field === 'provider_buy_price') return this.missingEntries[field] ?? this.missingProduct?.buy_price ?? '';
            return this.missingEntries[field] ?? '';
        },

        pickerInput(field) {
            const value = this.missingInput(field);
            if (field !== 'provider_received_at' || !/(Z|[+-]\d{2}:?\d{2})$/.test(value)) return value;
            const date = new Date(value);
            if (Number.isNaN(date.valueOf())) return '';
            const parts = Object.fromEntries(new Intl.DateTimeFormat('en', {
                timeZone: this.timezone, year: 'numeric', month: '2-digit', day: '2-digit',
                hour: '2-digit', minute: '2-digit', hourCycle: 'h23',
            }).formatToParts(date).map(({ type, value }) => [type, value]));
            return parts.year + '-' + parts.month + '-' + parts.day + 'T' + parts.hour + ':' + parts.minute;
        },

        setMissing(field, value) {
            if (field === 'product_id' && String(this.missingFields.product_id ?? '') !== String(value)) {
                delete this.missingEntries.provider_buy_price;
            }
            this.missingEntries[field] = value;
            delete this.missingIssues[field];
            delete this.missingIssues['fields.' + field];
            const child = { client_id: 'shop_id', product_category_id: 'product_id' }[field];
            if (child && this.missingEntries[child] && !this.missingOptions(child).some(option => String(option.id) === this.missingEntries[child])) {
                delete this.missingEntries[child];
            }
        },

        formatDraftAmount,

        setMissingAmount(field, input) {
            const { raw, display } = formatDraftAmount(input.value);
            this.setMissing(field, raw);
            input.value = display;
        },

        magnify(event) {
            if (event.pointerType !== 'mouse') return;
            const frame = event.currentTarget;
            const image = frame.firstElementChild;
            const bounds = frame.getBoundingClientRect();
            const percent = (offset, size) => Math.max(0, Math.min(100, offset / size * 100));
            image.style.transformOrigin = percent(event.clientX - bounds.left, bounds.width) + '% ' + percent(event.clientY - bounds.top, bounds.height) + '%';
            image.style.transform = 'scale(2)';
        },

        openMissing(trigger, field = null) {
            this.missingTrigger = trigger;
            this.correctionField = field;
            this.missingEntries = {};
            this.missingIssues = {};
            this.missingMessage = '';
            this.missingStale = null;
            this.missingUnavailable = false;
            this.missingOpen = true;
            this.$refs.missingDialog.showModal();
            this.refreshPhotos();
        },

        async refreshPhotos() {
            const request = ++this.photoRequest;
            this.photos = [];
            this.photoIndex = 0;
            this.photoStatus = 'loading';
            try {
                const { response, body } = await this.request('show', 'GET');
                if (request !== this.photoRequest) return;
                const images = body.data?.attributes?.images;
                if (!response.ok || !Array.isArray(images)) throw new Error('Could not load draft images');
                this.photos = images.map(image => image.url);
                this.photoStatus = 'ready';
            } catch {
                if (request === this.photoRequest) this.photoStatus = 'failed';
            }
        },

        closeMissing() {
            if (this.busy || this.catalogBusy) return;
            if (this.catalogEntry) {
                this.closeCatalogEntry();
                return;
            }
            this.photoRequest++;
            this.missingOpen = false;
            this.$refs.missingDialog.close();
        },

        acceptMissingDraft(draft) {
            const edits = this.changes;
            this.saved = draft.current_fields;
            this.original = draft.original_fields;
            this.etag = draftTag(draft.revision);
            this.fields = { ...this.saved, ...edits };
            this.confirmed = false;
            this.preview = emptyPreview();
        },

        reviewMissingRevision() {
            this.acceptMissingDraft(this.missingStale.data);
            if (this.correctionField) {
                this.missingStale = null;
                this.missingMessage = '';
                return;
            }
            // Drop entries locked by the new revision before checking their dependents.
            this.missingEntries = Object.fromEntries(Object.entries(this.missingEntries)
                .filter(([field]) => this.isMissing(this.original[field]) && this.isMissing(this.saved[field])));
            this.missingEntries = Object.fromEntries(Object.entries(this.missingEntries)
                .filter(([field, value]) => this.canFill(field) && (!this.draftChoices[this.kind][field]
                    || this.isMissing(value) || this.missingOptions(field).some(option => String(option.id) === String(value)))));
            this.missingStale = null;
            this.missingMessage = '';
        },

        async saveMissing() {
            if (this.busy || this.missingStale || this.missingUnavailable || this.catalogEntry) return;
            this.checking++;
            this.busy = true;
            this.missingIssues = {};
            this.missingMessage = '';
            const correction = this.correctionField;
            const fields = correction
                ? (Object.hasOwn(this.missingEntries, correction) ? { [correction]: (this.missingEntries[correction] === '' ? null : this.missingEntries[correction]) } : {})
                : Object.fromEntries(Object.entries(this.missingEntries)
                    .filter(([field, value]) => this.canFill(field) && !this.isMissing(value)));
            if (!correction && this.canFill('exchange_rate') && this.missingFields.currency === 'TJS') fields.exchange_rate = 1;
            let saved = false;

            try {
                const { response, body } = await this.request(correction ? 'update' : 'missing-fields', 'PATCH', { fields });
                if (response.ok) {
                    if (correction && Object.hasOwn(fields, correction)) delete this.edits[correction];
                    this.acceptMissingDraft(body.data.attributes);
                    this.message = this.messages.saved;
                    saved = true;
                } else {
                    this.missingMessage = body.message ?? this.messages.failed;
                    this.missingIssues = body.errors ?? {};
                    if (response.status === 412 && body.data) this.missingStale = { data: body.data.attributes };
                    if (response.status === 409 && body.data && body.data.attributes.status !== 'pending') {
                        this.missingUnavailable = true;
                        this.close(body.data.attributes);
                    }
                }
            } catch {
                this.missingMessage = this.messages.failed;
            } finally {
                this.busy = false;
                if (saved) {
                    this.closeMissing();
                    this.check();
                }
            }
        },

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
            return this.busy || Object.keys(this.edits).length > 0;
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
            if (this.closed || (!this.editorOpen && !this.dismissalOpen)) return;
            const token = ++this.checking;
            try {
                const { response, body } = await this.request('preview', 'POST', { fields: saved ? {} : this.changes });
                if (this.checking !== token) return;
                if (response.ok && draftTag(body.revision) !== this.etag) {
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
            else await this.refreshRevision(body.data.attributes);
        },

        async refreshRevision(draft) {
            const changes = Object.entries(this.changes).filter(([field]) =>
                Object.is(this.saved[field], draft.current_fields[field]));
            this.saved = draft.current_fields;
            this.fields = { ...this.saved, ...Object.fromEntries(changes) };
            this.edits = Object.fromEntries(changes.map(([field]) => [field, true]));
            this.etag = draftTag(draft.revision);
            this.preview = emptyPreview();
            this.previewSaved = false;
            this.postedId = '';
            this.confirmed = false;
            this.approvalRetry = null;
            this.issues = {};
            this.message = this.messages.refreshed;
            await this.check();
        },

        cancelEditor() {
            this.checking++;
            this.editorOpen = false;
            this.dismissalOpen = false;
            this.fields = { ...this.saved };
            this.edits = {};
            this.issues = {};
            this.preview = emptyPreview();
            this.confirmed = false;
            this.approvalRetry = null;
            this.message = '';
        },

        close(draft) {
            if (this.closed) return;
            this.saved = draft.current_fields;
            this.closed = draft;
            this.remaining.count = Math.max(0, this.remaining.count - 1);
        },

        async act(action) {
            if (this.busy || this.closed) return;
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
                        this.etag = draftTag(draft.revision);
                        this.confirmed = false;
                        this.approvalRetry = null;
                        this.preview = emptyPreview();
                        this.editorOpen = false;
                        this.message = this.messages.saved;
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
                if (response.status === 412 && body.data) {
                    await this.refreshRevision(body.data.attributes);
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

export function missingPicker(field) {
    let picker;
    return {
        init() {
            const input = this.$el;
            const timed = field === 'provider_received_at';
            input.value = this.pickerInput(field);
            this.$watch(() => this.pickerInput(field), value => {
                if (value === input.value) return;
                if (picker) picker.setDate(value);
                else input.value = value;
            });
            const syncValue = () => {
                if (input.value !== this.pickerInput(field)) this.setMissing(field, input.value);
            };
            input.addEventListener('change', syncValue);
            if (!window.flatpickr) return;
            input.type = 'text';
            picker = window.flatpickr(input, {
                static: timed,
                appendTo: input.parentElement,
                dateFormat: timed ? 'Y-m-d\\TH:i' : 'Y-m-d',
                altInput: true,
                altInputClass: input.className,
                altFormat: timed ? 'd/n/Y H:i' : 'd/n/Y',
                defaultDate: this.pickerInput(field) || undefined,
                enableTime: timed,
                time_24hr: true,
                allowInput: true,
                disableMobile: true,
                // Flatpickr debounces time changes, so sync before the form can submit.
                onValueUpdate: syncValue,
                onReady: (_, __, picker) => {
                    picker.altInput.id = input.id;
                    input.removeAttribute('id');
                },
            });
        },
        destroy() {
            picker?.destroy();
        },
    };
}

export function missingSelect(field) {
    let select;
    return {
        init() {
            const input = this.$el;
            input.addEventListener('change', () => this.setMissing(field, input.value));
            this.$watch(() => [this.missingOptions(field), this.missingInput(field)], () => this.syncMissingSelect(input));
            this.$nextTick(() => {
                if (!input.isConnected) return;
                if (window.jQuery?.fn.select2) {
                    select = window.jQuery(input).select2({
                        dropdownParent: window.jQuery(input.parentElement),
                        width: '100%',
                        placeholder: ' ',
                        allowClear: true,
                        ...(field.endsWith('client_id') ? { matcher: window.clientSelect2Matcher } : {}),
                    }).on('change.missing', () => this.setMissing(field, input.value));
                    if (field.endsWith('client_id')) window.enableSelect2SearchOnTyping(input);
                }
                this.syncMissingSelect(input);
            });
        },
        syncMissingSelect(input) {
            this.$nextTick(() => {
                const value = String(this.missingInput(field));
                input.value = value;
                select?.trigger('change.select2');
            });
        },
        destroy() {
            select?.off('.missing').select2('destroy');
        },
    };
}
