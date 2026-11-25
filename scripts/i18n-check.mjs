// Checks the SPA translations:
//  1. uk.json and en.json have the same keys;
//  2. every literal key used in t('…'), $t('…') or label: '…' exists.
// Dynamic keys (t(`status.${x}`)) can't be checked statically.
// Usage: npm run i18n:check
import { readFileSync, readdirSync, statSync } from 'node:fs';
import { join } from 'node:path';

const root = new URL('../resources/js/', import.meta.url).pathname.replace(/^\/([A-Za-z]:)/, '$1');
const load = (lang) => JSON.parse(readFileSync(join(root, 'i18n', `${lang}.json`), 'utf8'));

function flatten(obj, prefix = '', out = new Set()) {
    for (const [k, v] of Object.entries(obj)) {
        const key = prefix ? `${prefix}.${k}` : k;
        if (v && typeof v === 'object') flatten(v, key, out);
        else out.add(key);
    }
    return out;
}

function files(dir, out = []) {
    for (const name of readdirSync(dir)) {
        const path = join(dir, name);
        if (statSync(path).isDirectory()) files(path, out);
        else if (/\.(vue|ts)$/.test(name)) out.push(path);
    }
    return out;
}

const uk = flatten(load('uk'));
const en = flatten(load('en'));
const problems = [];

for (const k of uk) if (!en.has(k)) problems.push(`missing in en.json: ${k}`);
for (const k of en) if (!uk.has(k)) problems.push(`missing in uk.json: ${k}`);

// Prefixes of objects also count (labelMap('status.workshop', …) reads children)
const usedPattern = /(?:\$?\bt\(\s*|label:\s*)['"]([a-zA-Z][\w-]*(?:\.[\w-]+)+)['"]/g;
for (const file of files(root)) {
    const source = readFileSync(file, 'utf8');
    for (const [, key] of source.matchAll(usedPattern)) {
        if (!uk.has(key)) problems.push(`unknown key ${key} in ${file.slice(root.length)}`);
    }
}

if (problems.length) {
    console.error(problems.join('\n'));
    console.error(`\n${problems.length} problem(s)`);
    process.exit(1);
}
console.log(`i18n ok: ${uk.size} keys in each language`);
