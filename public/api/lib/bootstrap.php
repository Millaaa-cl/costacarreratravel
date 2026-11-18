<?php
declare(strict_types=1);

date_default_timezone_set('America/Santiago');
mb_internal_encoding('UTF-8');

/**
 * Carga la configuración. Primero busca api/config.php (propio de esta copia del sitio: así un
 * subdominio de prueba nunca lee la configuración de producción); si no existe, usa tsr-config.php
 * fuera de la carpeta pública (lo recomendado en producción). El .htaccess bloquea api/config.php.
 */
function config(): array
{
    static $cfg = null;
    if ($cfg !== null) return $cfg;
    $candidates = [
        dirname(__DIR__) . '/config.php',         // <carpeta del sitio>/api/config.php
        dirname(__DIR__, 3) . '/tsr-config.php', // /home/USUARIO/tsr-config.php
    ];
    $placeholder = null;
    foreach ($candidates as $file) {
        if (!is_file($file)) continue;
        // ob_start descarta cualquier salida accidental del archivo (un BOM o espacios antes de "<?php"
        // que agregan algunos editores), que rompería las cabeceras y el JSON de la API.
        ob_start(); $c = require $file; ob_end_clean();
        // Un archivo con los datos de ejemplo sin completar se salta (y se avisa), para no tapar a uno válido
        if (!is_array($c) || config_is_placeholder($c)) { $placeholder ??= $file; log_error("Configuración con valores de ejemplo, se ignora: $file"); continue; }
        $GLOBALS['__config_file'] = $file;
        return $cfg = $c;
    }
    log_error($placeholder ? "Solo hay configuración con valores de ejemplo: $placeholder" : 'No existe api/config.php ni tsr-config.php');
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => $placeholder
        ? 'Backend sin configurar: ' . basename($placeholder) . ' tiene los datos de ejemplo (base de datos, usuario o contraseña).'
        : 'Backend no configurado (falta api/config.php o tsr-config.php).']);
    exit;
}

/** true si la configuración conserva los valores de ejemplo de config.sample.php o de la guía. */
function config_is_placeholder(array $c): bool
{
    $db = $c['db'] ?? [];
    $name = (string)($db['name'] ?? ''); $user = (string)($db['user'] ?? ''); $pass = (string)($db['pass'] ?? '');
    return $name === '' || $user === '' || $pass === '' || $pass === 'CAMBIAR'
        || in_array($name, ['usuario_travelsanrafael', 'tuusuario_tsrprueba'], true)
        || in_array($user, ['usuario_tsr', 'tuusuario_tsr'], true)
        || str_starts_with($name, 'tuusuario_') || str_starts_with($user, 'tuusuario_');
}

/** Ruta (relativa, para mostrar) del archivo de configuración en uso. */
function config_file_label(): string
{
    config();
    $f = (string)($GLOBALS['__config_file'] ?? '');
    return str_ends_with($f, '/api/config.php') ? 'api/config.php' : ($f ? 'tsr-config.php (fuera de la carpeta del sitio)' : '(ninguno)');
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo) return $pdo;
    $c = config()['db'];
    $pdo = new PDO(
        "mysql:host={$c['host']};dbname={$c['name']};charset=utf8mb4",
        $c['user'],
        $c['pass'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
    $pdo->exec("SET time_zone = '" . (new DateTime())->format('P') . "'");
    return $pdo;
}

function now(): string { return date('Y-m-d H:i:s'); }

function json_out($data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function client_ip(): string
{
    return substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
}

function log_error(string $msg): void
{
    error_log('[travelsanrafael] ' . $msg);
}

/**
 * Libera los cupos de reservas pendientes cuyo plazo de pago venció.
 * Se ejecuta en cada consulta de disponibilidad y de reserva, así que el cron es opcional.
 */
function expire_pending(): void
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $rows = $pdo->prepare("SELECT id, departure_id, adults + children AS pax FROM bookings WHERE status = 'pending' AND expires_at < ? FOR UPDATE");
        $rows->execute([now()]);
        $upd = $pdo->prepare('UPDATE departures SET booked = GREATEST(0, booked - ?) WHERE id = ?');
        $mark = $pdo->prepare("UPDATE bookings SET status = 'expired' WHERE id = ?");
        foreach ($rows->fetchAll() as $r) {
            $upd->execute([(int)$r['pax'], (int)$r['departure_id']]);
            $mark->execute([(int)$r['id']]);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        log_error('expire_pending: ' . $e->getMessage());
    }
}

function booking_with_departure(string $field, string $value): ?array
{
    $allowed = ['code', 'flow_token'];
    if (!in_array($field, $allowed, true)) return null;
    $st = db()->prepare("SELECT b.*, d.date AS tour_date FROM bookings b JOIN departures d ON d.id = b.departure_id WHERE b.$field = ? LIMIT 1");
    $st->execute([$value]);
    return $st->fetch() ?: null;
}
