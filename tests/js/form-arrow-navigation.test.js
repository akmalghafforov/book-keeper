import test from 'node:test';
import assert from 'node:assert/strict';

const {
    handleArrowNavigation,
    navigationItems,
    shouldHandleArrowEvent,
} = await import('../../resources/js/form-arrow-navigation.js');

function element(tagName, options = {}) {
    const attributes = new Set(options.action ? ['data-arrow-navigation-action'] : []);
    const item = {
        tagName: tagName.toUpperCase(),
        type: options.type,
        disabled: options.disabled ?? false,
        hidden: options.hidden ?? false,
        style: options.style ?? {},
        parentElement: options.parentElement ?? null,
        nextElementSibling: options.nextElementSibling ?? null,
        _flatpickr: options.flatpickr,
        focused: false,
        hasAttribute: (name) => attributes.has(name),
        matches: (selector) => selector === 'select.select2-hidden-accessible' && options.select2 === true,
        querySelector: () => options.selection ?? null,
        focus() { this.focused = true; },
    };

    return item;
}

function form(items) {
    const result = {
        parentElement: null,
        querySelectorAll(selector) {
            if (selector === 'select.select2-hidden-accessible') return items.filter((item) => item.matches('select.select2-hidden-accessible'));
            return items;
        },
    };

    items.forEach((item) => {
        item.parentElement = result;
        item.closest = (selector) => selector === 'form[data-arrow-navigation]' ? result : null;
    });
    return result;
}

function event(target, key = 'ArrowDown', extras = {}) {
    return {
        target,
        key,
        preventDefaultCalled: false,
        preventDefault() { this.preventDefaultCalled = true; },
        ...extras,
    };
}

const closedWidgets = { querySelector: () => null };

test('moves through fields and terminal actions in DOM order without wrapping', () => {
    const first = element('input', { type: 'text' });
    const second = element('select');
    const cancel = element('a', { action: true });
    const save = element('button', { action: true });
    form([first, second, cancel, save]);

    const down = event(first);
    assert.equal(handleArrowNavigation(down, closedWidgets), true);
    assert.equal(down.preventDefaultCalled, true);
    assert.equal(second.focused, true);

    const up = event(cancel, 'ArrowUp');
    assert.equal(handleArrowNavigation(up, closedWidgets), true);
    assert.equal(second.focused, true);

    const start = event(first, 'ArrowUp');
    const end = event(save);
    assert.equal(handleArrowNavigation(start, closedWidgets), false);
    assert.equal(handleArrowNavigation(end, closedWidgets), false);
    assert.equal(start.preventDefaultCalled, false);
    assert.equal(end.preventDefaultCalled, false);
});

test('skips disabled and Alpine-hidden fields', () => {
    const first = element('input', { type: 'number' });
    const disabled = element('select', { disabled: true });
    const hidden = element('input', { style: { display: 'none' } });
    const action = element('button', { action: true });
    const currentForm = form([first, disabled, hidden, action]);
    assert.deepEqual(navigationItems(currentForm), [first, action]);
    assert.equal(handleArrowNavigation(event(first), closedWidgets), true);
    assert.equal(action.focused, true);
});

test('does not handle modified, composing, or textarea arrow keys', () => {
    const input = element('input', { type: 'number' });
    const next = element('input');
    form([input, next]);
    const textarea = element('textarea');
    textarea.closest = () => null;

    assert.equal(shouldHandleArrowEvent(event(input, 'ArrowDown', { ctrlKey: true })), false);
    assert.equal(shouldHandleArrowEvent(event(input, 'ArrowDown', { isComposing: true })), false);
    assert.equal(shouldHandleArrowEvent(event(input, 'ArrowDown', { keyCode: 229 })), false);
    assert.equal(handleArrowNavigation(event(textarea), closedWidgets), false);
});

test('leaves open Select2 and Flatpickr widgets in control of arrows', () => {
    const input = element('input', { type: 'number' });
    const next = element('input');
    form([input, next]);

    assert.equal(handleArrowNavigation(event(input), { querySelector: (selector) => selector === '.select2-container--open' ? {} : null }), false);
    input._flatpickr = { isOpen: true };
    assert.equal(handleArrowNavigation(event(input), closedWidgets), false);
    assert.equal(next.focused, false);
});

test('focuses Select2’s rendered selection when it is the next field', () => {
    const first = element('input');
    const selection = element('span');
    const container = element('span', { selection });
    const select = element('select', { select2: true, nextElementSibling: container });
    form([first, select]);

    assert.equal(handleArrowNavigation(event(first), closedWidgets), true);
    assert.equal(selection.focused, true);
    assert.equal(select.focused, false);
});
