// F27 (audit FE-10): the phone pages load their own JavaScript entry, without the host panel's
// code. Run after `npm run build`: it adds up the gzip size of each entry with every chunk it
// imports, fails when the phone entry is over its budget or carries host-only libraries, and
// prints the sizes so a change in either direction shows in the CI log.
import { readFileSync } from 'node:fs';
import { gzipSync } from 'node:zlib';

/**
 * Measured 10 October 2026: 141 KB for the one shared entry before F27; 55.8 KB for the phone
 * entry after it (with motion/mini). The budget leaves room for small changes, not for a library.
 */
const PHONE_BUDGET_KB = 65;
const PHONE_ENTRY = 'resources/js/phone.js';
const HOST_ENTRY = 'resources/js/app.js';
// Text that only these libraries ship; none of them may reach a phone (U18: Unpoly is for the
// host panel only).
const HOST_ONLY = { Unpoly: 'up-follow' };

const build = 'public/build';
const manifest = JSON.parse(readFileSync(`${build}/manifest.json`, 'utf8'));

/** The files of an entry and of every chunk it imports, each once. */
function filesOf(key, seen = new Set()) {
    const chunk = manifest[key];

    if (!chunk || seen.has(chunk.file)) {
        return seen;
    }

    seen.add(chunk.file);

    for (const imported of chunk.imports ?? []) {
        filesOf(imported, seen);
    }

    return seen;
}

function measure(key) {
    const files = [...filesOf(key)].filter((file) => file.endsWith('.js'));
    const sources = files.map((file) => readFileSync(`${build}/${file}`));
    const bytes = sources.reduce((total, source) => total + gzipSync(source, { level: 9 }).length, 0);

    return { files, text: sources.map(String).join('\n'), kb: Math.round(bytes / 102.4) / 10 };
}

const problems = [];

if (!manifest[PHONE_ENTRY]) {
    problems.push(`${PHONE_ENTRY} is not an entry of the build (vite.config.js input).`);
} else {
    const phone = measure(PHONE_ENTRY);
    const host = measure(HOST_ENTRY);
    console.log(`phone entry: ${phone.kb} KB gzip in ${phone.files.length} file(s), budget ${PHONE_BUDGET_KB} KB`);
    console.log(`host entry:  ${host.kb} KB gzip in ${host.files.length} file(s)`);

    if (phone.kb > PHONE_BUDGET_KB) {
        problems.push(`the phone entry is ${phone.kb} KB gzip, over its ${PHONE_BUDGET_KB} KB budget.`);
    }

    for (const [library, marker] of Object.entries(HOST_ONLY)) {
        if (phone.text.includes(marker)) {
            problems.push(`the phone entry carries ${library}, which only the host panel uses.`);
        }
    }
}

if (problems.length > 0) {
    console.error(`F27 bundle budget:\n- ${problems.join('\n- ')}`);
    process.exit(1);
}

console.log('F27 bundle budget: within budget');
