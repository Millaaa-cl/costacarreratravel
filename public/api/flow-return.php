<?php
/**
 * urlReturn de Flow: el navegador del cliente vuelve aquí (POST con token) después de pagar.
 * Sincroniza el estado y redirige a la página de confirmación o de error en el idioma de la reserva.
 */
declare(strict_types=1);
require_once __DIR__ . '/lib/payments.php';

$token = (string)($_POST['token'] ?? $_GET['token'] ?? '');
$base = rtrim(config()['base_url'], '/');
$lang = 'es';
$target = '/reserva/fallida/';
$query = '';

if (preg_match('/^[A-Za-z0-9]{10,64}$/', $token)) {
    try {
        [$b, $status] = sync_payment($token);
        if ($b) {
            $lang = $b['lang'] === 'en' ? 'en' : 'es';
            $query = '?codigo=' . urlencode($b['code']);
            if ($status === 2) $target = $lang === 'en' ? '/en/booking/confirmed/' : '/reserva/confirmada/';
            elseif ($status === 1) { $target = $lang === 'en' ? '/en/booking/confirmed/' : '/reserva/confirmada/'; $query .= '&estado=pendiente'; }
            else $target = $lang === 'en' ? '/en/booking/failed/' : '/reserva/fallida/';
        }
    } catch (Throwable $e) {
        log_error('flow-return: ' . $e->getMessage());
    }
}

header('Location: ' . $base . $target . $query, true, 303);
exit;
