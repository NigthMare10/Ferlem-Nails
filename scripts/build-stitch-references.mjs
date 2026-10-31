// Freeze approved Stitch exports and compile their utilities without a runtime CDN.
// Usage: node scripts/build-stitch-references.mjs <portal-export.json> <auth-export.json>
import fs from 'node:fs';
import path from 'node:path';
import { execFileSync } from 'node:child_process';
import postcss from 'postcss';

const names = ['admin', 'home', 'rewards', 'profile', 'login', 'register', 'recovery'];
const entries =
    process.argv.length > 2
        ? process.argv
              .slice(2)
              .flatMap((file) => JSON.parse(fs.readFileSync(file, 'utf8')))
              .filter((entry) => entry.html)
        : names.map((name) => ({ name: `approved-${name}`, html: fs.readFileSync(`docs/design/stitch/${name}.html`, 'utf8') }));
fs.mkdirSync('docs/design/stitch', { recursive: true });
fs.mkdirSync('resources/css/stitch', { recursive: true });
for (const [i, entry] of entries.entries()) {
    const name = names[i];
    const html = entry.html;
    const directory = 'docs/design/stitch';
    const source = path.join(directory, `${name}.html`);
    fs.writeFileSync(source, html);
    const config = html
        .match(/tailwind\.config\s*=\s*([\s\S]*?)<\/script>/)[1]
        .trim()
        .replace(/;$/, '');
    const configFile = path.join(directory, `${name}.config.cjs`);
    fs.writeFileSync(
        configFile,
        `const config = ${config};\nmodule.exports = {...config, content: ['./${source.replaceAll('\\', '/')}'], corePlugins: {preflight: false}, important: '.stitch-${name}'};`,
    );
    const input = path.join(directory, 'utilities.css');
    fs.writeFileSync(input, '@tailwind base;\n@tailwind utilities;');
    const output = `resources/css/stitch/${name}.css`;
    execFileSync(
        process.platform === 'win32' ? 'npx.cmd' : 'npx',
        ['--yes', '--package', 'tailwindcss@3.4.17', 'tailwindcss', '-c', configFile, '-i', input, '-o', output, '--minify'],
        { shell: process.platform === 'win32', stdio: 'inherit' },
    );
    // Tailwind's base only contains CSS variable initializers when preflight is off.
    // Scope those and the original custom glass treatments to their owning screen.
    const root = postcss.parse(fs.readFileSync(output, 'utf8'));
    root.walkRules((rule) => {
        if (!rule.selector.includes(`.stitch-${name}`) && !rule.parent?.name?.includes('keyframes')) {
            rule.selector = rule.selector
                .split(',')
                .map((s) => `.stitch-${name} ${s}`)
                .join(',');
        }
    });
    const custom = [...html.matchAll(/<style>([\s\S]*?)<\/style>/g)].map((m) => m[1]).join('\n');
    const customRoot = postcss.parse(custom);
    customRoot.walkRules((rule) => {
        rule.selector = rule.selector
            .split(',')
            .map((s) => (s.trim() === 'body' ? `.stitch-${name}` : `.stitch-${name} ${s.trim()}`))
            .join(',');
    });
    fs.writeFileSync(output, `/* Generated from the approved Stitch export: ${entry.name}. */\n${root}\n${customRoot}`);
}
