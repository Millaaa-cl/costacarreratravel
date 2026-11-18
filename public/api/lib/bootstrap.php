<?php
declare(strict_types=1);

date_default_timezone_set('America/Santiago');
mb_internal_encoding('UTF-8');

/** Carga la configuración: primero fuera de public_html, si no, junto a la API. */
function config(): array
{
    static $cfg = null;
    if ($cfg !== null) return $cfg;
    $candidates = [
        dirname(__DIR__, 3) . '/tsr-config.php', // /home/USUARIO/tsr-config.php
        dirname(__DIR__) . '/config.php',         // public_html/api/config.php
    ];
    foreach ($candidates as $file) {
        if (is_file($file)) { $cfg = require $file; return $cfg; }
    }
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'Backend no configurado (falta tsr-config.php).']);
    exit;
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
