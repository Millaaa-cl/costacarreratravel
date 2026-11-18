<?php
/**
 * GET /api/availability.php[?month=YYYY-MM]
 * Devuelve las salidas del mes con cupos y precios. Sin "month", elige el mes de la próxima salida disponible.
 */
declare(strict_types=1);
require_once __DIR__ . '/lib/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') json_out(['error' => 'Método no permitido'], 405);

try {
    expire_pending();
    $cfg = config();
    $pdo = db();
    $firstDate = date('Y-m-d', strtotime('+' . (int)$cfg['min_days_before'] . ' day'));
    $minMonth = date('Y-m');

    $last = $pdo->query("SELECT MAX(date) FROM departures WHERE status = 'open'")->fetchColumn();
    $maxMonth = $last ? max(substr((string)$last, 0, 7), $minMonth) : date('Y-m', strtotime('+6 month'));

    $month = (string)($_GET['month'] ?? '');
    if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) {
        $next = $pdo->prepare("SELECT date FROM departures WHERE status = 'open' AND date >= ? AND booked < capacity ORDER BY date LIMIT 1");
        $next->execute([$firstDate]);
        $n = $next->fetchColumn();
        $month = $n ? substr((string)$n, 0, 7) : $minMonth;
    }
    if ($month < $minMonth) $month = $minMonth;
    if ($month > $maxMonth) $month = $maxMonth;

    $start = "$month-01";
    $end = date('Y-m-t', strtotime($start));
    $st = $pdo->prepare("SELECT date, capacity, booked, price_adult, price_child FROM departures WHERE status = 'open' AND date BETWEEN ? AND ? AND date >= ? ORDER BY date");
    $st->execute([$start, $end, $firstDate]);

    $deps = array_map(fn($r) => [
        'date' => $r['date'],
        'seats' => max(0, (int)$r['capacity'] - (int)$r['booked']),
        'priceAdult' => (int)$r['price_adult'],
        'priceChild' => (int)$r['price_child'],
    ], $st->fetchAll());

    json_out(['month' => $month, 'minMonth' => $minMonth, 'maxMonth' => $maxMonth, 'departures' => $deps]);
} catch (Throwable $e) {
    log_error('availability: ' . $e->getMessage());
    json_out(['error' => 'No se pudo cargar la disponibilidad'], 500);
}
