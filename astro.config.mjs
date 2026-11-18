// @ts-check
import { defineConfig } from 'astro/config';
import sitemap from '@astrojs/sitemap';

// Dominio canónico: sin www y con HTTPS (el .htaccess redirige todo lo demás aquí).
export default defineConfig({
  site: 'https://travelsanrafael.cl',
  trailingSlash: 'always',
  build: { format: 'directory' },
  integrations: [
    sitemap({
      // Las páginas de resultado de pago y el 404 no se indexan.
      filter: (page) => !/\/(reserva|booking)\/|\/404\/?$/.test(page),
    }),
  ],
});
