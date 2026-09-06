const CONTROL_SELECTOR = 'input, select, [data-arrow-navigation-action]';

function isTextarea(element) {
    return element?.tagName?.toLowerCase() === 'textarea';
}

function isInputOrSelect(element) {
    const tagName = element?.tagName?.toLowerCase();

    return tagName === 'input' || tagName === 'select';
}

function isHidden(element, form) {
    for (let current = element; current && current !== form.parentElement; current = current.parentElement) {
        if (current.hidden || current.style?.display === 'none' || current.style?.visibility === 'hidden') {
            return true;
        }

        if (typeof getComputedStyle === 'function') {
            const style = getComputedStyle(current);
            if (style.display === 'none' || style.visibility === 'hidden') return true;
        }
    }

    return false;
}

export function isEligibleNavigationItem(element, form) {
    if (!element || element.disabled || element.matches?.(':disabled') || isHidden(element, form)) return false;

    if (element.hasAttribute?.('data-arrow-navigation-action')) return true;
    if (!isInputOrSelect(element)) return false;

    return element.type?.toLowerCase() !== 'hidden';
}

export function navigationItems(form) {
    return Array.from(form.querySelectorAll(CONTROL_SELECTOR))
        .filter((element) => isEligibleNavigationItem(element, form));
}

function select2Source(target, form) {
    if (!target?.closest) return null;

    const container = target.closest('.select2-container');
    if (!container) return null;

    return Array.from(form.querySelectorAll('select.select2-hidden-accessible'))
        .find((select) => select.nextElementSibling === container) ?? null;
}

function sourceForTarget(target, form) {
    if (isInputOrSelect(target) || target?.hasAttribute?.('data-arrow-navigation-action')) return target;

    return select2Source(target, form);
}

function focusItem(item) {
    if (item.matches?.('select.select2-hidden-accessible')) {
        const selection = item.nextElementSibling?.querySelector?.('.select2-selection');
        if (selection) {
            selection.focus();
            return;
        }
    }

    item.focus();
}

export function isSelect2Open(documentRef = globalThis.document) {
    return Boolean(documentRef?.querySelector?.('.select2-container--open'));
}

export function isFlatpickrOpen(source, documentRef = globalThis.document) {
    return Boolean(source?._flatpickr?.isOpen || documentRef?.querySelector?.('.flatpickr-calendar.open'));
}

export function shouldHandleArrowEvent(event) {
    return (event.key === 'ArrowUp' || event.key === 'ArrowDown')
        && !event.altKey
        && !event.ctrlKey
        && !event.metaKey
        && !event.shiftKey
        && !event.isComposing
        && event.keyCode !== 229;
}

export function handleArrowNavigation(event, documentRef = globalThis.document) {
    if (!shouldHandleArrowEvent(event) || isTextarea(event.target)) return false;

    const form = event.target?.closest?.('form[data-arrow-navigation]');
    if (!form || isSelect2Open(documentRef)) return false;

    const source = sourceForTarget(event.target, form);
    if (!source || isFlatpickrOpen(source, documentRef)) return false;

    const items = navigationItems(form);
    const index = items.indexOf(source);
    if (index === -1) return false;

    const nextIndex = index + (event.key === 'ArrowDown' ? 1 : -1);
    if (nextIndex < 0 || nextIndex >= items.length) return false;

    event.preventDefault();
    focusItem(items[nextIndex]);
    return true;
}

export function initializeFormArrowNavigation(documentRef = globalThis.document) {
    documentRef?.addEventListener?.('keydown', (event) => handleArrowNavigation(event, documentRef));
}
