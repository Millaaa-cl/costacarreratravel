// Auditoría SEO rápida del build: title, meta description, H1, canonical, hreflang y validez del JSON-LD.
// Uso: npm run build && node scripts/seo-audit.mjs
import fs from 'node:fs';
import path from 'node:path';

const root = 'dist';
let issues = 0;
const pages = [];
const walk = (d) => fs.readdirSync(d).forEach((f) => {
  const p = path.join(d, f);
  if (fs.statSync(p).isDirectory()) walk(p);
  else if (p.endsWith('.html')) pages.push(p);
});
walk(root);

for (const p of pages) {
  const h = fs.readFileSync(p, 'utf8');
  const rel = '/' + path.relative(root, p).replace(/\\/g, '/');
  const title = (h.match(/<title>(.*?)<\/title>/) || [])[1] || '';
  const desc = (h.match(/name="description" content="(.*?)"/) || [])[1] || '';
  const h1 = (h.match(/<h1[\s>]/g) || []).length;
  const noindex = h.includes('content="noindex');
  const hreflang = (h.match(/hreflang="/g) || []).length;
  const imgsNoAlt = (h.match(/<img(?![^>]*\balt=)[^>]*>/g) || []).length;
  const warn = [];
  for (const m of h.matchAll(/<script type="application\/ld\+json">([\s\S]*?)<\/script>/g)) {
    try { JSON.parse(m[1]); } catch { warn.push('JSON-LD inválido'); }
  }
  if (!title) warn.push('sin title');
  if (!noindex && (desc.length < 70 || desc.length > 175)) warn.push(`description ${desc.length}c`);
  if (!noindex && title.length > 70) warn.push(`title largo ${title.length}c`);
  if (h1 !== 1) warn.push(`${h1} H1`);
  if (!h.includes('rel="canonical"')) warn.push('sin canonical');
  if (!noindex && hreflang < 3 && !rel.includes('404')) warn.push('sin hreflang');
  if (imgsNoAlt) warn.push(`${imgsNoAlt} img sin alt`);
  issues += warn.length;
  console.log(`${rel.padEnd(60)} t:${String(title.length).padStart(3)} d:${String(desc.length).padStart(3)} ${noindex ? 'noindex ' : ''}${warn.length ? '⚠ ' + warn.join(', ') : '✓'}`);
}
console.log(`\n${pages.length} páginas · ${issues} advertencias`);
process.exitCode = issues ? 1 : 0;
