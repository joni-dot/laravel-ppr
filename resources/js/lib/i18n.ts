import { shallowRef } from 'vue';

type Messages = Record<string, string>;

export type Replacements = Record<string, string | number>;

const languageFiles = import.meta.glob<Messages>('../../../lang/*.json', {
    import: 'default',
});

const messages = shallowRef<Messages>({});

/**
 * Loads lang/<locale>.json, the same file Laravel's __() reads.
 * A language without a file shows the English text, which is the key.
 */
export async function loadLanguage(locale: string): Promise<void> {
    const file = languageFiles[`../../../lang/${locale}.json`];

    messages.value = file ? await file() : {};
}

/**
 * The locale Laravel rendered the page in, from <html lang="en-GB">,
 * in Laravel's spelling ("en_GB").
 */
export function pageLocale(): string {
    if (typeof document === 'undefined') {
        return 'en';
    }

    return document.documentElement.lang.replace('-', '_') || 'en';
}

/**
 * Translates English text. Placeholders work like Laravel's: with
 * { name: 'ann' }, ":name" becomes "ann", ":Name" "Ann" and ":NAME" "ANN".
 */
export function trans(key: string, replacements: Replacements = {}): string {
    return fillPlaceholders(messages.value[key] ?? key, replacements);
}

/**
 * Replaces all placeholders in one pass, like PHP's strtr() in Laravel,
 * so a value that itself contains ":name" is never replaced again.
 */
function fillPlaceholders(line: string, replacements: Replacements): string {
    const values = new Map<string, string>();

    for (const [name, rawValue] of Object.entries(replacements)) {
        const value = String(rawValue);

        values.set(`:${upperFirst(name)}`, upperFirst(value));
        values.set(`:${name.toUpperCase()}`, value.toUpperCase());
        values.set(`:${name}`, value);
    }

    if (values.size === 0) {
        return line;
    }

    const longestFirst = [...values.keys()].sort((a, b) => b.length - a.length);
    const pattern = new RegExp(longestFirst.map(escapeRegExp).join('|'), 'g');

    return line.replace(pattern, (placeholder) => values.get(placeholder)!);
}

function upperFirst(text: string): string {
    return text.charAt(0).toUpperCase() + text.slice(1);
}

function escapeRegExp(text: string): string {
    return text.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}
