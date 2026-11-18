/**
 * Convierte dist/ en una versión de PRUEBA para un subdominio (ej: prueba.travelsanrafael.cl):
 * - robots.txt bloquea todo;
 * - cabecera X-Robots-Tag "noindex, nofollow" en el .htaccess;
 * - <meta name="robots" content="noindex, nofollow"> en cada HTML (por si el hosting no tiene mod_headers).
 * Se ejecuta después de "astro build" (ver "npm run package:prueba"). No toca src/ ni public/.
 */
import { readdirSync, readFileSync, writeFileSync, statSync } from 'node:fs';
import { join } from 'node:path';

const dist = new URL('../dist/', import.meta.url).pathname.replace(/^\/([A-Za-z]:)/, '$1');

writeFileSync(join(dist, 'robots.txt'),
  '# Sitio de PRUEBA: no indexar. El sitio real es https://travelsanrafael.cl\nUser-agent: *\nDisallow: /\n');

const htaccess = join(dist, '.htaccess');
writeFileSync(htaccess, readFileSync(htaccess, 'utf8') +
  '\n# Sitio de PRUEBA: que ningún buscador lo indexe\n<IfModule mod_headers.c>\n  Header always set X-Robots-Tag "noindex, nofollow"\n</IfModule>\n');

let n = 0;
const walk = (dir) => {
  for (const f of readdirSync(dir)) {
    const p = join(dir, f);
    if (statSync(p).isDirectory()) walk(p);
    else if (f.endsWith('.html')) {
      let html = readFileSync(p, 'utf8');
      html = html.replace(/<meta name="robots"[^>]*>/g, '');
      html = html.replace('<head>', '<head><meta name="robots" content="noindex, nofollow">');
      writeFileSync(p, html); n++;
    }
  }
};
walk(dist);
console.log(`Versión de prueba lista: ${n} páginas con noindex, robots.txt bloqueado y X-Robots-Tag en .htaccess.`);
