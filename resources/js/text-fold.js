// B-8 (audit DATA-1): text compared the way the database's utf8mb4_unicode_ci compares it:
// without case, accents, letter width or the difference between ß and ss. Mirrors
// App\People\PersonName::normalize().

/**
 * @param {string} text
 */
export function foldText(text) {
    return text
        .normalize('NFKD')
        .replace(/\p{Mn}+/gu, '')
        .toLowerCase()
        .replace(/ß/g, 'ss')
        .normalize('NFC');
}
