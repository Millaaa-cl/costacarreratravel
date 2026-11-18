# travelsanrafael.cl — sitio nuevo

Sitio estático en Astro, bilingüe (ES/EN), optimizado para SEO, GEO y AEO. Incluye reservas con calendario de cupos y pago en línea con **Flow.cl**, con un backend PHP + MySQL que corre en el mismo hosting cPanel.

## Estructura

| Carpeta / archivo | Qué contiene |
|---|---|
| `src/data/tour.ts` | **Fuente única** de datos del negocio y del tour: precios mostrados, horarios, dirección, imágenes. Edita aquí primero. |
| `src/i18n/content.ts` | Textos ES/EN de la home y la ficha del tour: itinerario, incluye, FAQ, reseñas, widget. |
| `src/pages/` | Páginas. Las de texto (cómo llegar, nosotros, blog, términos) se editan directamente aquí. |
| `src/components/BookingWidget.astro` | Calendario y formulario de reserva (JS vanilla). |
| `src/lib/schema.ts` | Datos estructurados JSON-LD (TravelAgency, TouristTrip, Product/Offer, FAQPage, Breadcrumb, Article). |
| `src/pages/llms.txt.ts` | Genera `/llms.txt` (resumen para IA) a partir de `tour.ts`. |
| `public/api/` | Backend PHP: disponibilidad, reserva, Flow y correos. |
| `public/admin/` | Panel de administración: reservas y salidas. |
| `public/.htaccess` | HTTPS, dominio sin www, redirecciones 301 del WordPress antiguo, caché y seguridad. |
| `sql/schema.sql` | Tablas MySQL. |
| `scripts/seo-audit.mjs` | Revisa title, description, H1, canonical, hreflang, alt y JSON-LD de cada página. |

## Comandos

```bash
npm install          # primera vez
npm run dev          # vista previa en http://localhost:4321 (el calendario necesita el backend PHP)
npm run audit        # build + auditoría SEO
npm run package      # build + auditoría + travelsanrafael-public_html.zip listo para subir
```

## Puesta en marcha en cPanel (una sola vez)

1. **Respaldo:** descarga una copia completa del WordPress actual (Administrador de archivos y la base de datos desde phpMyAdmin).
2. **Base de datos:**
   - En cPanel, entra a *Bases de datos MySQL* y crea una base, un usuario y la relación entre ambos con *todos los privilegios*.
   - En phpMyAdmin, selecciona la base, entra a *Importar* y sube `sql/schema.sql`.
3. **Correo:** crea la casilla `reservas@travelsanrafael.cl` en *Cuentas de correo*.
4. **Configuración:**
   - Copia `public/api/config.sample.php` como **`/home/TU_USUARIO/tsr-config.php`**, es decir, *fuera* de `public_html`.
   - Completa los datos de la base, Flow, correo y la `setup_key`.
   - Deja `'sandbox' => true` para las pruebas.
5. **Subir el sitio:**
   - Vacía `public_html`. Antes, mueve el WordPress a una carpeta de respaldo.
   - Sube `travelsanrafael-public_html.zip` y usa *Extraer*.
   - En el *Administrador de archivos*, activa "Mostrar archivos ocultos" y verifica que exista `.htaccess`.
6. **PHP:** en *Seleccionar versión de PHP* elige **PHP 8.1 o superior** con las extensiones `pdo_mysql` y `curl`.
7. **Administrador:**
   - Abre `https://travelsanrafael.cl/admin/setup.php`, ingresa la `setup_key` y crea tu usuario.
   - **Borra `public_html/admin/setup.php`** del servidor.
8. **Salidas:** en `/admin/` ve a *Salidas* y usa *Crear salidas en lote*. Indica el rango de la temporada, los días, los cupos (20) y los precios.
9. **Flow:**
   - En sandbox.flow.cl, haz una reserva de prueba con las tarjetas de prueba de Flow. Prueba un pago aprobado, uno rechazado y uno abandonado. Verifica el correo y el panel.
   - Luego cambia a `'sandbox' => false` con las llaves de producción y haz una compra real de bajo monto.
10. **Cron (opcional):** en *Cron Jobs*, programa cada 10 minutos `php /home/TU_USUARIO/public_html/api/cron-expire.php`.
11. **Search Console y Bing Webmaster:**
    - Verifica el dominio y envía `https://travelsanrafael.cl/sitemap-index.xml`.
    - Revisa la home y la ficha del tour en el [Rich Results Test](https://search.google.com/test/rich-results).
12. **Google Business Profile:** usa exactamente el mismo nombre, dirección y teléfono que en `tour.ts`, y enlaza al sitio.

### Actualizar el sitio después

Edita los textos o datos y ejecuta `npm run package`. Sube el zip y extráelo sobre `public_html`. No toca `tsr-config.php`, que está fuera de esa carpeta.

## Cómo funciona una reserva

1. `GET /api/availability.php?month=YYYY-MM` entrega las fechas abiertas, los cupos libres y los precios, tomados de la base.
2. `POST /api/booking.php` hace lo siguiente:
   - valida los datos,
   - recalcula el precio en el servidor (nunca confía en el navegador),
   - bloquea la fila de la salida (`SELECT … FOR UPDATE`) para evitar sobreventa,
   - aparta los cupos por 20 minutos,
   - crea el pago en Flow y devuelve la URL de pago.
3. Flow avisa a `/api/flow-confirm.php` (webhook) y el cliente vuelve por `/api/flow-return.php`. Ambos consultan `payment/getStatus` con firma y verifican el monto. Si el pago está aprobado, la reserva queda `paid` y se envían el voucher al cliente y el aviso a `contacto@cctravel.cl`.
4. Los cupos de pagos no completados se liberan solos cuando vence el plazo.

## Pendientes de contenido (buscar `TODO` en el código)

- [x] Precios ($170.000 adulto, $120.000 niño hasta 12 años), horario de salida (06:20), punto de encuentro, itinerario y qué incluye.
- [x] Fotos propias optimizadas en `public/images/`.
- [ ] Al crear las salidas en `/admin/`, usa **$170.000** adulto y **$120.000** niño. El formulario ya trae esos valores por defecto. Si cambian, actualiza también `tour.ts`.
- [ ] Confirmar la temporada de operación (hoy dice "septiembre a abril").
- [x] Logo transparente (PNG generado desde el SVG del cliente). Si existe un SVG vectorial real (exportado como vectores, no como imagen), conviene reemplazarlo: sería más liviano y nítido a cualquier tamaño.
- [ ] **Foto para el paso 1 del itinerario** (salida en bus desde Puerto Río Tranquilo) y, si existe, del desayuno a bordo. Se agregan en `images.steps` de `src/data/tour.ts`. El paso 2 ya tiene la foto del puerto de embarque.
- [ ] Coordenadas de la oficina, localidad exacta y redes sociales (`business.social`, para `sameAs`).
- [ ] Historia real del equipo en *Nosotros* (E-E-A-T).
- [ ] Política de cancelación definitiva en *Términos*.
- [ ] Confirmar si se cobra la entrada a CONAF y si hay guía en inglés.
