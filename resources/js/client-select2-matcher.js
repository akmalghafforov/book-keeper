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

const latinLetters = {
    а: 'a', б: 'b', в: 'v', г: 'g', ғ: 'g', д: 'd', е: 'e', ё: 'yo', ж: 'zh', з: 'z',
    и: 'i', ӣ: 'i', й: 'y', к: 'k', қ: 'k', л: 'l', м: 'm', н: 'n', о: 'o', п: 'p',
    р: 'r', с: 's', т: 't', у: 'u', ӯ: 'u', ф: 'f', х: 'kh', ҳ: 'h', ц: 'ts', ч: 'ch',
    ҷ: 'j', ш: 'sh', щ: 'shch', ъ: '', ы: 'y', ь: '', э: 'e', ю: 'yu', я: 'ya',
};

const plainCyrillic = { ғ: 'г', ӣ: 'и', қ: 'к', ӯ: 'у', ҳ: 'х', ҷ: 'ч' };

function foldTajik(value) {
    return value.toLowerCase().replace(/[ғӣқӯҳҷ]/g, letter => plainCyrillic[letter]);
}

function searchableName(value) {
    return value.toLowerCase().replace(/[а-яёғӣқӯҳҷ]/g, letter => latinLetters[letter]);
}

/**
 * Match client names using the typed text, Tajik keyboard layout, or Latin
 * transliteration. Plain Cyrillic spellings also match Tajik letters.
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

    const latinName = searchableName(clientName);
    const foldedName = foldTajik(clientName);
    return searchTerms.some(searchTerm => {
        const searchable = searchableName(searchTerm);
        return foldedName.includes(foldTajik(searchTerm)) || (searchable && latinName.includes(searchable));
    })
        ? data
        : null;
}

/**
 * Makes a closed Select2 client selector open as soon as a searchable
 * character is typed, preserving that first character in its search field.
 */
export function enableSelect2SearchOnTyping(select) {
    const $select = window.jQuery(select);
    const $selection = $select.next('.select2-container').find('.select2-selection');

    $selection.on('keydown.client-search', (event) => {
        if (event.isComposing || event.keyCode === 229 || event.key.length !== 1 || $select.data('select2')?.isOpen()) {
            return;
        }

        event.preventDefault();
        $select.select2('open');

        const $search = window.jQuery('.select2-container--open .select2-search__field').last();
        $search.val(event.key).trigger('input').trigger('focus');
    });
}

window.clientSelect2Matcher = clientSelect2Matcher;
window.enableSelect2SearchOnTyping = enableSelect2SearchOnTyping;
