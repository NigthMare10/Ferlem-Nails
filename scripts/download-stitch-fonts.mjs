import fs from 'node:fs';
const base = 'https://fonts.googleapis.com/css2?';
const families =
    'family=Plus+Jakarta+Sans:wght@300;400;500;600;700&family=Syne:wght@500;600;700;800&family=DM+Sans:wght@400;500;600;700&family=Noto+Serif:wght@400;500;600';
const icons = [
    'add',
    'arrow_back',
    'arrow_back_ios_new',
    'arrow_forward',
    'arrow_forward_ios',
    'badge',
    'calendar_today',
    'check',
    'check_circle',
    'chevron_left',
    'chevron_right',
    'close',
    'diamond',
    'edit',
    'help_outline',
    'home',
    'info',
    'lock',
    'lock_open',
    'logout',
    'loyalty',
    'mail',
    'menu',
    'notifications',
    'person',
    'qr_code_2',
    'qr_code_scanner',
    'schedule',
    'search',
    'spa',
    'tune',
    'verified_user',
    'visibility',
    'visibility_off',
    'workspace_premium',
];
const urls = [
    base + families + '&display=swap',
    base + 'family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&icon_names=' + icons.sort().join(',') + '&display=block',
];
fs.mkdirSync('public/fonts/stitch', { recursive: true });
let css = '/* Frozen official font files used in the approved Stitch screens. */\n';
const files = new Map();
for (const url of urls) {
    const response = await fetch(url, {
        headers: { 'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Safari/537.36' },
    });
    if (!response.ok) throw new Error(`Font stylesheet failed: ${response.status}`);
    let text = await response.text();
    for (const match of text.matchAll(/url\((https:\/\/[^)]+)\)/g)) {
        const remote = match[1];
        if (!files.has(remote)) {
            const font = await fetch(remote);
            if (!font.ok) throw new Error(`Font failed: ${font.status}`);
            const bytes = Buffer.from(await font.arrayBuffer());
            const filename = `font-${files.size}.${bytes.subarray(0, 4).toString() === 'wOF2' ? 'woff2' : 'ttf'}`;
            fs.writeFileSync('public/fonts/stitch/' + filename, bytes);
            files.set(remote, '/fonts/stitch/' + filename);
        }
        text = text.replaceAll(remote, files.get(remote));
    }
    css += text + '\n';
}
fs.writeFileSync('resources/css/stitch/fonts.css', css);
for (const family of ['dmsans', 'notoserif', 'plusjakartasans', 'syne']) {
    const response = await fetch(`https://raw.githubusercontent.com/google/fonts/main/ofl/${family}/OFL.txt`);
    if (!response.ok) throw new Error(`Cannot download ${family} font license`);
    fs.writeFileSync(`public/fonts/stitch/LICENSE-${family}.txt`, await response.text());
}
const symbolsLicense = await fetch('https://raw.githubusercontent.com/google/material-design-icons/master/LICENSE');
if (!symbolsLicense.ok) throw new Error('Cannot download Material Symbols license');
fs.writeFileSync('public/fonts/stitch/LICENSE-material-symbols.txt', await symbolsLicense.text());
console.log(`Froze ${files.size} fonts locally.`);
