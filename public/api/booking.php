<?php
/**
 * POST /api/booking.php  (JSON)
 * Valida, reserva cupos de forma atómica, crea el pago en Flow y devuelve la URL de pago.
 */
declare(strict_types=1);
require_once __DIR__ . '/lib/bootstrap.php';
require_once __DIR__ . '/lib/Flow.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_out(['error' => 'Método no permitido'], 405);

$in = json_decode((string)file_get_contents('php://input'), true);
if (!is_array($in)) json_out(['error' => 'Solicitud inválida'], 400);

$lang = ($in['lang'] ?? 'es') === 'en' ? 'en' : 'es';
$msg = fn(string $es, string $en) => $lang === 'es' ? $es : $en;

// Honeypot anti-spam
if (!empty($in['website'])) json_out(['error' => $msg('Solicitud inválida', 'Invalid request')], 400);

$cfg = config();
$clean = fn($v, int $max) => mb_substr(trim(strip_tags((string)($v ?? ''))), 0, $max);
$date = (string)($in['date'] ?? '');
$adults = (int)($in['adults'] ?? 0);
$children = (int)($in['children'] ?? 0);
$name = $clean($in['name'] ?? '', 120);
$email = filter_var(trim((string)($in['email'] ?? '')), FILTER_VALIDATE_EMAIL) ?: '';
$phone = $clean($in['phone'] ?? '', 30);
$country = $clean($in['country'] ?? '', 60);
$lodging = $clean($in['lodging'] ?? '', 160);
$notes = $clean($in['notes'] ?? '', 500);
$pax = $adults + $children;

$d = DateTime::createFromFormat('!Y-m-d', $date);
if (!$d || $d->format('Y-m-d') !== $date) json_out(['error' => $msg('Fecha inválida', 'Invalid date')], 422);
if ($adults < 1 || $children < 0 || $pax > (int)$cfg['max_pax']) json_out(['error' => $msg('Cantidad de pasajeros inválida', 'Invalid number of passengers')], 422);
if (mb_strlen($name) < 3 || !$email || mb_strlen($phone) < 6) json_out(['error' => $msg('Revisa tu nombre, correo y teléfono', 'Please check your name, email and phone')], 422);
if ($date < date('Y-m-d', strtotime('+' . (int)$cfg['min_days_before'] . ' day'))) json_out(['error' => $msg('Esta fecha ya no admite reservas en línea', 'This date can no longer be booked online')], 422);

try {
    $pdo = db();

    // Límite simple por IP: 6 intentos cada 10 minutos
    $rl = $pdo->prepare('SELECT COUNT(*) FROM bookings WHERE ip = ? AND created_at > ?');
    $rl->execute([client_ip(), date('Y-m-d H:i:s', time() - 600)]);
    if ((int)$rl->fetchColumn() >= 6) json_out(['error' => $msg('Demasiados intentos. Espera unos minutos.', 'Too many attempts. Please wait a few minutes.')], 429);

    expire_pending();

    $pdo->beginTransaction();
    $q = $pdo->prepare("SELECT * FROM departures WHERE date = ? AND status = 'open' FOR UPDATE");
    $q->execute([$date]);
    $dep = $q->fetch();
    if (!$dep) { $pdo->rollBack(); json_out(['error' => $msg('No hay salida disponible en esa fecha', 'No departure available on that date')], 409); }
    $seats = (int)$dep['capacity'] - (int)$dep['booked'];
    if ($pax > $seats) {
        $pdo->rollBack();
        json_out(['error' => $seats > 0
            ? $msg("Solo quedan $seats cupos para esa fecha", "Only $seats seats left on that date")
            : $msg('La fecha se agotó', 'That date is sold out')], 409);
    }

    $amount = $adults * (int)$dep['price_adult'] + $children * (int)$dep['price_child'];
    do {
        $code = 'LSR-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));
        $exists = $pdo->prepare('SELECT 1 FROM bookings WHERE code = ?');
        $exists->execute([$code]);
    } while ($exists->fetchColumn());

    $created = now();
    $expires = date('Y-m-d H:i:s', time() + 60 * (int)$cfg['hold_minutes']);
    $pdo->prepare('INSERT INTO bookings (code, departure_id, adults, children, amount, name, email, phone, country, lodging, notes, lang, status, ip, created_at, expires_at)
                   VALUES (?,?,?,?,?,?,?,?,?,?,?,?,\'pending\',?,?,?)')
        ->execute([$code, $dep['id'], $adults, $children, $amount, $name, $email, $phone, $country, $lodging, $notes, $lang, client_ip(), $created, $expires]);
    $bookingId = (int)$pdo->lastInsertId();
    $pdo->prepare('UPDATE departures SET booked = booked + ? WHERE id = ?')->execute([$pax, $dep['id']]);
    $pdo->commit();
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    log_error('booking: ' . $e->getMessage());
    json_out(['error' => $msg('No pudimos crear la reserva. Inténtalo nuevamente.', 'We could not create your booking. Please try again.')], 500);
}

// Crear el pago en Flow (fuera de la transacción)
try {
    $flow = new Flow($cfg['flow']);
    $base = rtrim($cfg['base_url'], '/');
    $res = $flow->createPayment([
        'commerceOrder' => $code,
        'subject' => ($lang === 'es' ? 'Tour Laguna San Rafael ' : 'San Rafael Lagoon Tour ') . $date . " ($pax pax)",
        'currency' => 'CLP',
        'amount' => $amount,
        'email' => $email,
        'urlConfirmation' => "$base/api/flow-confirm.php",
        'urlReturn' => "$base/api/flow-return.php",
        'timeout' => 60 * (int)$cfg['hold_minutes'],
        'optional' => json_encode(['lang' => $lang]),
    ]);
    if (empty($res['url']) || empty($res['token'])) throw new RuntimeException('Respuesta de Flow incompleta');
    db()->prepare('UPDATE bookings SET flow_token = ?, flow_order = ? WHERE id = ?')->execute([$res['token'], $res['flowOrder'] ?? null, $bookingId]);
    json_out(['ok' => true, 'code' => $code, 'url' => $res['url'] . '?token=' . urlencode($res['token'])]);
} catch (Throwable $e) {
    log_error('flow create: ' . $e->getMessage());
    // Liberar cupos si no se pudo iniciar el pago
    try {
        db()->prepare('UPDATE departures SET booked = GREATEST(0, booked - ?) WHERE id = ?')->execute([$pax, $dep['id']]);
        db()->prepare("UPDATE bookings SET status = 'cancelled', admin_note = 'Error al crear pago en Flow' WHERE id = ?")->execute([$bookingId]);
    } catch (Throwable $e2) { log_error('release: ' . $e2->getMessage()); }
    json_out(['error' => $msg('No pudimos conectar con el medio de pago. Inténtalo nuevamente o escríbenos por WhatsApp.', 'We could not connect to the payment provider. Please try again or message us on WhatsApp.')], 502);
}
