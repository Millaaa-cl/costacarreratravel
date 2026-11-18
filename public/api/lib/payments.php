<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/Flow.php';
require_once __DIR__ . '/Mailer.php';

/**
 * Consulta a Flow el estado del pago y actualiza la reserva. Es idempotente: la llaman el webhook
 * (flow-confirm.php) y el retorno del usuario (flow-return.php), así que funciona aunque llegue
 * primero uno u otro, o aunque se repitan.
 * Devuelve [booking|null, flowStatus:int].
 */
function sync_payment(string $token): array
{
    $flow = new Flow(config()['flow']);
    $st = $flow->getStatus($token);
    $flowStatus = (int)($st['status'] ?? 0);
    $code = (string)($st['commerceOrder'] ?? '');

    $pdo = db();
    $pdo->beginTransaction();
    try {
        $q = $pdo->prepare('SELECT * FROM bookings WHERE code = ? FOR UPDATE');
        $q->execute([$code]);
        $b = $q->fetch();
        if (!$b) { $pdo->rollBack(); log_error("sync_payment: reserva $code no existe"); return [null, $flowStatus]; }

        // Verificación del monto pagado contra el registrado.
        if ($flowStatus === 2 && (int)round((float)($st['amount'] ?? 0)) !== (int)$b['amount']) {
            $pdo->rollBack();
            log_error("sync_payment: monto no coincide en $code");
            return [$b, 0];
        }

        $pax = (int)$b['adults'] + (int)$b['children'];
        $notify = false;
        $warning = '';

        if ($flowStatus === 2 && $b['status'] !== 'paid') {
            // Si la reserva había expirado o se anuló, se vuelven a tomar los cupos (puede generar sobreventa: se avisa al admin).
            if (in_array($b['status'], ['expired', 'cancelled', 'rejected'], true)) {
                $pdo->prepare('UPDATE departures SET booked = booked + ? WHERE id = ?')->execute([$pax, $b['departure_id']]);
                $cap = $pdo->prepare('SELECT capacity, booked FROM departures WHERE id = ?');
                $cap->execute([$b['departure_id']]);
                $d = $cap->fetch();
                if ($d && (int)$d['booked'] > (int)$d['capacity']) $warning = 'ATENCIÓN: pago recibido después de expirar la reserva y la salida quedó sobrevendida. Revisar en el panel.';
            }
            $media = (string)($st['paymentData']['media'] ?? '');
            $pdo->prepare("UPDATE bookings SET status = 'paid', paid_at = ?, flow_order = ?, payment_media = ? WHERE id = ?")
                ->execute([now(), $st['flowOrder'] ?? null, substr($media, 0, 60), $b['id']]);
            $notify = true;
        } elseif (in_array($flowStatus, [3, 4], true) && $b['status'] === 'pending') {
            $pdo->prepare('UPDATE departures SET booked = GREATEST(0, booked - ?) WHERE id = ?')->execute([$pax, $b['departure_id']]);
            $pdo->prepare('UPDATE bookings SET status = ? WHERE id = ?')->execute([$flowStatus === 3 ? 'rejected' : 'cancelled', $b['id']]);
        }
        $pdo->commit();

        $full = booking_with_departure('code', $code);
        if ($notify && $full) {
            Mailer::customerConfirmation($full);
            Mailer::adminNotification($full, $warning);
        }
        return [$full, $flowStatus];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}
