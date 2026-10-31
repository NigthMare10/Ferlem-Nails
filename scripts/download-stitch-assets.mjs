import fs from 'node:fs';
import path from 'node:path';
const root = 'public/images/stitch';
fs.mkdirSync(root, { recursive: true });
const manifest = {};
for (const name of ['home', 'rewards', 'profile', 'admin']) {
    const html = fs.readFileSync(`docs/design/stitch/${name}.html`, 'utf8');
    manifest[name] = [];
    const urls = [...html.matchAll(/<img[^>]+src="([^"]+)"/g)].map((m) => m[1]);
    for (const [i, url] of urls.entries()) {
        const filename = `${name}-${i}.webp`;
        const response = await fetch(url);
        if (!response.ok) throw new Error(`Cannot freeze approved image: ${response.status} ${url}`);
        fs.writeFileSync(path.join(root, filename), Buffer.from(await response.arrayBuffer()));
        manifest[name].push(`/images/stitch/${filename}`);
    }
}
fs.writeFileSync('docs/design/stitch/assets.json', JSON.stringify(manifest, null, 2));
