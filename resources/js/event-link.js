// U13: the link of a new event follows its name while the host has not typed a link of their
// own, made the way the server makes one (App\Support\EventLink, Str::slug): lower case, plain
// letters, hyphens, at most 60 characters, "-event" after a reserved word or a very short name.
// A link that another event already uses is left as it is, so the server's check says so.

const MAX = 60;

export function linkFromName(name, reserved) {
    const slug = name
        .normalize('NFKD')
        .replace(/[̀-ͯ]/g, '')
        .toLowerCase()
        .replace(/@/g, '-at-')
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '');
    const base = slug.slice(0, MAX).replace(/-+$/, '');

    if (base === '') {
        return '';
    }

    return base.length < 3 || reserved.includes(base) ? `${base}-event` : base;
}

/** The Alpine state around the New event fields. */
export function eventLink({ reserved, link = '' }) {
    return {
        // A refused form comes back with the link that was sent: it stays as typed.
        link,
        own: link.trim() !== '',

        nameTyped(name) {
            if (!this.own) {
                this.link = linkFromName(name, reserved);
            }
        },

        linkTyped(link) {
            this.link = link;
            this.own = link.trim() !== '';
        },
    };
}
