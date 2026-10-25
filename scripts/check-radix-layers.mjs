/**
 * Пази Radix от дублирани копия на пакетите, които споделят състояние.
 *
 * `react-dismissable-layer` пази регистър на отворените „слоеве“ (диалог,
 * popover, select) в модулна променлива. Ако npm инсталира две копия, всяко
 * получава собствен регистър и слоевете спират да се виждат помежду си:
 * popover, отворен вътре в диалог, остава с `pointer-events: none` и всяко
 * кликване минава под него. Същото важи за фокус капана.
 *
 * Дублирането се предотвратява от блока `overrides` в package.json.
 * Този скрипт проверява резултата, защото грешката е тиха.
 */
import { readFileSync, readdirSync, statSync } from 'node:fs';
import { join } from 'node:path';

const SHARED = ['react-dismissable-layer', 'react-focus-guards', 'react-focus-scope'];

/** @returns {string[]} пътищата до всички физически копия на пакета */
function copiesOf(packageName, root = 'node_modules', depth = 0) {
    if (depth > 4) {
        return [];
    }

    let entries;

    try {
        entries = readdirSync(root);
    } catch {
        return [];
    }

    const found = [];

    for (const entry of entries) {
        const path = join(root, entry);

        if (!statSync(path).isDirectory()) {
            continue;
        }

        if (entry === packageName && root.endsWith(join('node_modules', '@radix-ui'))) {
            found.push(path);

            continue;
        }

        if (entry === 'node_modules' || entry.startsWith('@') || root.includes('node_modules')) {
            found.push(...copiesOf(packageName, path, depth + 1));
        }
    }

    return found;
}

const problems = [];

for (const name of SHARED) {
    const copies = copiesOf(name);

    if (copies.length > 1) {
        const versions = copies.map((path) => `${JSON.parse(readFileSync(join(path, 'package.json'), 'utf8')).version} (${path})`);

        problems.push(`@radix-ui/${name}: ${copies.length} копия\n    ${versions.join('\n    ')}`);
    }
}

if (problems.length > 0) {
    console.error('Намерени са дублирани Radix пакети със споделено състояние:\n');
    console.error(problems.join('\n\n'));
    console.error('\nПоправка: изравнете версиите в блока "overrides" в package.json и изпълнете npm install.');
    process.exit(1);
}

console.log('Radix слоевете са в по едно копие.');
