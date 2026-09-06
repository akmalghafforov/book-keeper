const tajikLayout = new Map(Object.entries({
    q: 'й', w: 'қ', e: 'у', r: 'к', t: 'е', y: 'н', u: 'г', i: 'ш', o: 'ҳ', p: 'з', '[': 'х', ']': 'ъ',
    a: 'ф', s: 'ҷ', d: 'в', f: 'а', g: 'п', h: 'р', j: 'о', k: 'л', l: 'д', ';': 'ж', "'": 'э',
    z: 'я', x: 'ч', c: 'с', v: 'м', b: 'и', n: 'т', m: 'ӣ', ',': 'б', '.': 'ю', '/': '.',
    '-': 'ғ', '_': 'Ғ', '=': 'ӯ', '+': 'Ӯ',
    й: 'й', ц: 'қ', у: 'у', к: 'к', е: 'е', н: 'н', г: 'г', ш: 'ш', щ: 'ҳ', з: 'з', х: 'х', ъ: 'ъ',
    ф: 'ф', ы: 'ҷ', в: 'в', а: 'а', п: 'п', р: 'р', о: 'о', л: 'л', д: 'д', ж: 'ж', э: 'э',
    я: 'я', ч: 'ч', с: 'с', м: 'м', и: 'и', т: 'т', ь: 'ӣ', б: 'б', ю: 'ю',
}));

/**
 * Converts characters typed with English QWERTY or Russian keyboard layouts
 * into their official Tajik keyboard-layout equivalents.
 */
export function toTajikKeyboardLayout(value) {
    return String(value).split('').map((character) => {
        const lowerCharacter = character.toLowerCase();
        const converted = tajikLayout.get(lowerCharacter);

        if (!converted) {
            return character;
        }

        return character !== lowerCharacter && character === character.toUpperCase()
            ? converted.toUpperCase()
            : converted;
    }).join('');
}

/**
 * Select2 matcher for client names. It keeps Select2's usual behavior while
 * also checking the official Tajik-layout version of the search term.
 */
export function clientSelect2Matcher(params, data) {
    const term = (params.term || '').trim();

    if (!term) {
        return data;
    }

    if (typeof data.text !== 'string') {
        return null;
    }

    const clientName = data.text.toLowerCase();
    const searchTerms = [term, toTajikKeyboardLayout(term)];

    return searchTerms.some((searchTerm) => clientName.includes(searchTerm.toLowerCase()))
        ? data
        : null;
}

window.clientSelect2Matcher = clientSelect2Matcher;
