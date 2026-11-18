<?php
/**
 * Webhook urlConfirmation de Flow: Flow envía por POST el "token" cuando cambia el estado del pago.
 * Siempre se valida consultando payment/getStatus (firmado), nunca se confía en el POST.
 */
declare(strict_types=1);
require_once __DIR__ . '/lib/payments.php';

$token = (string)($_POST['token'] ?? '');
if (!preg_match('/^[A-Za-z0-9]{10,64}$/', $token)) { http_response_code(400); exit('bad token'); }

try {
    sync_payment($token);
    http_response_code(200);
    echo 'OK';
} catch (Throwable $e) {
    log_error('flow-confirm: ' . $e->getMessage());
    http_response_code(500); // Flow reintentará
    echo 'ERROR';
}
