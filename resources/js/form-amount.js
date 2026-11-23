export function formatDraftAmount(value) {
    const [whole, ...parts] = String(value).replace(/,/g, '').replace(/[^\d.]/g, '').split('.');
    const fraction = parts.length ? '.' + parts.join('') : '';
    return { raw: whole + fraction, display: whole.replace(/\B(?=(\d{3})+(?!\d))/g, ',') + fraction };
}
