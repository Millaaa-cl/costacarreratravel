<?php
declare(strict_types=1);

/** Envío de correos con mail() del hosting (cPanel). Para SMTP autenticado se puede cambiar por PHPMailer. */
final class Mailer
{
    public static function send(string $to, string $subject, string $html, ?string $replyTo = null): bool
    {
        $m = config()['mail'];
        $fromName = '=?UTF-8?B?' . base64_encode($m['from_name']) . '?=';
        $headers = [
            'MIME-Version: 1.0',
            'Content-Type: text/html; charset=UTF-8',
            "From: $fromName <{$m['from']}>",
            'Reply-To: ' . ($replyTo ?: $m['admin_to']),
            'X-Mailer: travelsanrafael',
        ];
        $subj = '=?UTF-8?B?' . base64_encode($subject) . '?=';
        $ok = @mail($to, $subj, $html, implode("\r\n", $headers), '-f' . $m['from']);
        if (!$ok) log_error("mail() falló para $to");
        return $ok;
    }

    private static function money(int $n): string
    {
        return '$' . number_format($n, 0, ',', '.');
    }

    private static function e(?string $s): string
    {
        return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
    }

    /** Voucher para el cliente (ES/EN). */
    public static function customerConfirmation(array $b): void
    {
        $es = $b['lang'] !== 'en';
        $date = self::formatDate($b['tour_date'], $es);
        $rows = [
            [$es ? 'Código de reserva' : 'Booking code', '<strong>' . self::e($b['code']) . '</strong>'],
            [$es ? 'Tour' : 'Tour', $es ? 'Full Day Laguna San Rafael' : 'San Rafael Lagoon Full Day'],
            [$es ? 'Fecha' : 'Date', $date],
            [$es ? 'Pasajeros' : 'Passengers', $b['adults'] . ($es ? ' adulto(s)' : ' adult(s)') . ($b['children'] ? ', ' . $b['children'] . ($es ? ' niño(s)' : ' child(ren)') : '')],
            [$es ? 'Total pagado' : 'Total paid', self::money((int)$b['amount']) . ' CLP'],
            [$es ? 'Punto de encuentro' : 'Meeting point', $es ? 'Esquina Dagoberto Godoy con Exploradores, Puerto Río Tranquilo · <strong>06:20 AM</strong>' : 'Corner of Dagoberto Godoy and Exploradores, Puerto Río Tranquilo · <strong>6:20 am</strong>'],
        ];
        $intro = $es
            ? 'Hola ' . self::e($b['name']) . ', ¡tu reserva está confirmada! Estos son los detalles:'
            : 'Hi ' . self::e($b['name']) . ', your booking is confirmed! Here are the details:';
        $tips = $es
            ? '<p><strong>Antes del tour:</strong> te esperamos a las 06:20 AM en el punto de encuentro (el bus sale puntual). Te escribiremos por WhatsApp el día anterior para confirmar la salida. Incluye desayuno, almuerzo, snack y degustación frente al glaciar. Lleva ropa en capas, chaqueta impermeable, gorro, guantes y lentes de sol.</p><p>Si la Autoridad Marítima cierra el puerto por mal tiempo, reprogramamos tu tour o te devolvemos el 100%.</p>'
            : '<p><strong>Before the tour:</strong> please be at the meeting point at 6:20 am (the bus leaves on time). We will message you on WhatsApp the day before to confirm the departure. Breakfast, lunch, a snack and the glacier tasting are included. Bring layered clothing, a waterproof jacket, hat, gloves and sunglasses.</p><p>If the Maritime Authority closes the port due to weather, we will reschedule or refund 100%.</p>';
        $html = self::layout($intro, $rows, $tips);
        self::send($b['email'], ($es ? 'Reserva confirmada · ' : 'Booking confirmed · ') . $b['code'], $html);
    }

    /** Aviso interno al administrador. */
    public static function adminNotification(array $b, string $extra = ''): void
    {
        $rows = [
            ['Código', self::e($b['code'])],
            ['Fecha tour', self::e($b['tour_date'])],
            ['Pasajeros', $b['adults'] . ' adultos, ' . $b['children'] . ' niños'],
            ['Monto', self::money((int)$b['amount'])],
            ['Nombre', self::e($b['name'])],
            ['Email', self::e($b['email'])],
            ['Teléfono', self::e($b['phone'])],
            ['País', self::e($b['country'])],
            ['Alojamiento', self::e($b['lodging'])],
            ['Comentarios', nl2br(self::e($b['notes']))],
            ['Idioma', self::e($b['lang'])],
            ['Medio de pago', self::e($b['payment_media'] ?? '')],
        ];
        $html = self::layout('Nueva reserva PAGADA en travelsanrafael.cl' . ($extra ? '<br><strong style="color:#b42318">' . self::e($extra) . '</strong>' : ''), $rows, '');
        self::send(config()['mail']['admin_to'], "Nueva reserva {$b['code']} · {$b['tour_date']} · " . ($b['adults'] + $b['children']) . ' pax', $html, $b['email']);
    }

    private static function formatDate(string $ymd, bool $es): string
    {
        $ts = strtotime($ymd);
        $days = $es ? ['domingo', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado'] : ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
        $months = $es ? ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'] : ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
        $d = $days[(int)date('w', $ts)];
        $m = $months[(int)date('n', $ts) - 1];
        return $es ? "$d " . date('j', $ts) . " de $m de " . date('Y', $ts) : "$d, $m " . date('j', $ts) . ', ' . date('Y', $ts);
    }

    private static function layout(string $intro, array $rows, string $footer): string
    {
        $tr = '';
        foreach ($rows as [$k, $v]) {
            $tr .= "<tr><td style=\"padding:8px 12px;color:#4d5b64;border-bottom:1px solid #e3f1f6;width:40%\">$k</td><td style=\"padding:8px 12px;border-bottom:1px solid #e3f1f6\">$v</td></tr>";
        }
        $c = config();
        return "<!doctype html><html><body style=\"margin:0;background:#f3f9fb;font-family:Arial,Helvetica,sans-serif;color:#0b2a3c\">
<table width=\"100%\" cellpadding=\"0\" cellspacing=\"0\" style=\"background:#f3f9fb;padding:24px 0\"><tr><td align=\"center\">
<table width=\"600\" cellpadding=\"0\" cellspacing=\"0\" style=\"max-width:600px;background:#fff;border-radius:12px;overflow:hidden\">
<tr><td style=\"background:#1d1d1b;color:#fff;padding:24px;font-size:22px;font-family:Arial,sans-serif;font-weight:bold;border-bottom:6px solid #2fb3c3\">Costa Carrera <span style=\"color:#d7262b\">Travel</span></td></tr>
<tr><td style=\"padding:24px\"><p style=\"font-size:16px\">$intro</p>
<table width=\"100%\" cellpadding=\"0\" cellspacing=\"0\" style=\"font-size:15px;margin:16px 0\">$tr</table>$footer</td></tr>
<tr><td style=\"padding:16px 24px;background:#e3f1f6;font-size:12px;color:#4d5b64\">{$c['mail']['from_name']} · {$c['base_url']} · {$c['mail']['admin_to']}</td></tr>
</table></td></tr></table></body></html>";
    }
}
