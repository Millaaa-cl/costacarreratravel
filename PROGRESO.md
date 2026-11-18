# Progreso del proyecto: Costa Carrera Travel (travelsanrafael.cl)

Última actualización: 17 de noviembre de 2026

## Qué hicimos hoy

### 1. Sitio nuevo completo
- Sitio estático en **Astro**, bilingüe: español en `/`, inglés en `/en/`. Tiene 27 páginas:
  - inicio y ficha del tour;
  - cómo llegar, preguntas frecuentes, nosotros, contacto y términos;
  - 3 artículos de blog por idioma;
  - páginas de reserva confirmada y de pago fallido.
- **SEO, GEO y AEO:**
  - JSON-LD de agencia, tour, oferta, FAQ, migas de pan y artículos;
  - hreflang, sitemap, `robots.txt` y `/llms.txt`;
  - redirecciones 301 desde las URLs del WordPress antiguo.
- **Reservas en línea:**
  - calendario con cupos reales y pago con **Flow.cl**;
  - backend PHP + MySQL que corre en el mismo hosting cPanel;
  - panel de administración en `/admin/` para reservas y salidas;
  - correos de confirmación.
- **Datos reales del tour:**
  - precios: $170.000 por adulto y $120.000 por niño de hasta 12 años (confirmados el 18-11-2026);
  - salida a las 06:20 desde la esquina de Dagoberto Godoy con Exploradores;
  - regreso entre las 17:00 y las 18:00;
  - itinerario completo;
  - 17 fotos propias optimizadas.

### 2. Rediseño del front-end ("Ola y glaciar")
- Hecho con la skill oficial `frontend-design` de Anthropic.
- **Marca:** Costa Carrera Travel, con logo en el header y favicon nuevo.
- **Paleta del logo:**
  - negro `#1d1d1b`;
  - rojo `#d7262b`, solo para reservar;
  - turquesa `#2fb3c3` / `#137f8c`;
  - hielo `#eaf6f8`.
- **Tipografía:** Archivo, con títulos condensados que recuerdan al "TRAVEL" del logo.
- **Interacción propia en cada sección:**
  - olas animadas;
  - contadores;
  - itinerario que se dibuja al hacer scroll;
  - checks animados;
  - parallax;
  - galería con visor;
  - carrusel de reseñas;
  - FAQ animadas;
  - barra de reserva en móvil.
- **Fotos** en cada bloque: resumen, itinerario, incluye, embarcación, reseñas, FAQ, mapa y cierre.

### 3. Correcciones
- **Collage y fotos que no aparecían:** el efecto de revelado (`clip-path` al 100 %) impedía que el navegador las detectara y las cargara. Se cambió por un fundido con zoom.
- **Calendario vacío en "Elige la fecha":** ahora siempre se dibuja el mes, aunque el backend no responda, y muestra el aviso con el enlace a WhatsApp. El nombre del mes queda como "Noviembre de 2026".
- **Íconos descuadrados en Contacto:** no tenían tamaño definido. Ahora van en círculos de 44 px, y todos los íconos del sitio tienen un tamaño base de 24 px.

### 4. Control de versiones
- Se instaló Git y se inicializó el repositorio.
- Primer commit: `6ba85a2`.
- Subido a GitHub en https://github.com/Millaaa-cl/costacarreratravel, rama `main`.

## Decisiones tomadas

| Decisión | Motivo |
|---|---|
| Astro estático + PHP/MySQL en cPanel | El hosting no tiene Node. Se sube `dist/` a `public_html` y el backend corre allí mismo. |
| Pago con Flow.cl | Lo eligió el cliente: Webpay, tarjetas y transferencia en Chile. |
| Español + inglés, con slugs distintos por idioma | Público nacional y extranjero, y mejor SEO en cada idioma. |
| Los precios cobrados salen de la base de datos (`departures`) | El servidor recalcula el total y nunca confía en el navegador. `tour.ts` solo define el precio que se muestra. |
| Cupos apartados 20 minutos mientras se paga | Evita la sobreventa. Si el pago no se completa, los cupos se liberan solos. |
| Marca: **Costa Carrera Travel**, dominio travelsanrafael.cl | Confirmada por el cliente. Coincide con el logo. |
| Tipografía Archivo, solo fotos propias, logo JPEG actual | Supuestos por defecto, porque esas preguntas quedaron sin respuesta. |
| Animaciones en JS propio (~5 KB), sin GSAP ni AOS | Menos peso. Todo respeta `prefers-reduced-motion` y el contenido se ve sin JS. |
| Descartados: cursor personalizado, partículas y video en autoplay | Afectan el rendimiento y la accesibilidad. |
| Header siempre blanco | Las letras del logo son negras y no se leerían sobre la foto del hero. |
| Zip con `tar -a` en vez de Compress-Archive | Compress-Archive usa `\` en las rutas y rompe la extracción en cPanel. |
| `.gitignore` excluye `config.php` y `tsr-config.php` | Esos archivos guardan claves de la base y de Flow, y no deben ir a GitHub. |

## Pendiente

### Contenido (lo entrega el cliente; buscar `TODO` en el código)
- [ ] Confirmar la temporada de operación. Hoy dice "septiembre a abril".
- [ ] Confirmar si se cobra la entrada a CONAF y si hay guía en inglés.
- [x] Logo transparente, recibido el 18-11-2026 (PNG generado desde el SVG del cliente).
- [ ] Fotos de los pasos 1 y 2 del itinerario (bus, Valle Exploradores) y, si hay, del desayuno a bordo.
- [ ] Coordenadas de la oficina, redes sociales y razón social.
- [ ] Historia real del equipo en *Nosotros*.
- [ ] Política de cancelación definitiva en *Términos*.

### Puesta en marcha (pasos detallados en README.md)
- [ ] Respaldar el WordPress actual (archivos y base de datos).
- [ ] Crear la base MySQL e importar `sql/schema.sql`.
- [ ] Crear el correo `reservas@travelsanrafael.cl`.
- [ ] Crear `/home/USUARIO/tsr-config.php` con los datos de la base, Flow y correo, y `sandbox => true`.
- [ ] Subir `travelsanrafael-public_html.zip` a `public_html` y extraerlo.
- [ ] Elegir PHP 8.1 o superior, con `pdo_mysql` y `curl`.
- [ ] Crear el administrador en `/admin/setup.php` y luego **borrar ese archivo**.
- [ ] Crear las salidas de la temporada en `/admin/`: 20 cupos, $170.000 adulto y $120.000 niño.
- [ ] Probar en el sandbox de Flow un pago aprobado, uno rechazado y uno abandonado.
- [ ] Pasar Flow a producción y hacer una compra real de bajo monto.
- [ ] Search Console y Bing: enviar `sitemap-index.xml`.
- [ ] Revisar los datos estructurados en el Rich Results Test.
- [ ] Google Business Profile con el mismo nombre, dirección y teléfono.

### No verificado todavía
- Reserva completa con pago real: necesita el hosting con MySQL y el sandbox de Flow.
- Calendario con fechas reales seleccionables.
- Fluidez de las animaciones con scroll real.
- Safari y celulares físicos. Solo se revisó con capturas de Edge a 1440, 768 y 390 px.

## Siguiente paso

**Instalar el sitio en el hosting y probar una reserva en el sandbox de Flow**, siguiendo los pasos de "Puesta en marcha en cPanel" del README. Es lo único que falta para que los días del calendario se puedan seleccionar y para confirmar que el pago, los correos y el panel funcionan de punta a punta.

Mientras tanto, conviene pedir al cliente la temporada confirmada.

## Cómo retomar

```powershell
cd C:\Users\holam\travelsanrafael
npm.cmd run dev        # vista previa en http://localhost:4321
npm.cmd run package    # build + auditoría SEO + zip para subir
git add -A; git commit -m "mensaje"; git push
```
