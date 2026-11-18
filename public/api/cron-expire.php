<?php
/**
 * Opcional: libera cupos de pagos vencidos. La web ya lo hace en cada consulta, pero el cron lo
 * mantiene al día aunque nadie visite el sitio.
 * cPanel → Cron Jobs (cada 10 min):  php /home/USUARIO/public_html/api/cron-expire.php
 * (El .htaccess bloquea el acceso web a este archivo.)
 */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
require_once __DIR__ . '/lib/bootstrap.php';
expire_pending();
echo "ok\n";
