<?php
/**
 * Panel de administración · travelsanrafael.cl/admin/
 * - Reservas: listado, filtros, cambio de estado (anular/reembolsar libera cupos), exportación CSV.
 * - Salidas: creación masiva por rango de fechas y días de semana, edición de cupos, precios y estado.
 */
declare(strict_types=1);
require_once __DIR__ . '/../api/lib/bootstrap.php';

session_set_cookie_params(['lifetime' => 0, 'path' => '/admin/', 'secure' => !empty($_SERVER['HTTPS']), 'httponly' => true, 'samesite' => 'Strict']);
session_name('tsr_admin');
session_start();
header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: no-store');

function h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function money($n): string { return '$' . number_format((int)$n, 0, ',', '.'); }
function csrf(): string { return $_SESSION['csrf'] ??= bin2hex(random_bytes(16)); }
function check_csrf(): void { if (!hash_equals($_SESSION['csrf'] ?? '', (string)($_POST['csrf'] ?? ''))) { http_response_code(403); exit('CSRF inválido'); } }
function redirect(string $q, string $flash = ''): void { if ($flash) $_SESSION['flash'] = $flash; header("Location: ./$q"); exit; }
function authed(): bool { return !empty($_SESSION['admin_id']) && ($_SESSION['ua'] ?? '') === ($_SERVER['HTTP_USER_AGENT'] ?? ''); }

$p = (string)($_GET['p'] ?? 'bookings');
$pdo = db();

// ---------- Login / logout ----------
if ($p === 'logout') { session_destroy(); redirect('?p=login'); }
if (!authed()) {
    $error = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        check_csrf();
        $st = $pdo->prepare('SELECT id, password_hash FROM admins WHERE username = ?');
        $st->execute([trim((string)($_POST['user'] ?? ''))]);
        $u = $st->fetch();
        if ($u && password_verify((string)($_POST['pass'] ?? ''), $u['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['admin_id'] = (int)$u['id'];
            $_SESSION['ua'] = $_SERVER['HTTP_USER_AGENT'] ?? '';
            redirect('?p=bookings');
        }
        sleep(2);
        $error = 'Usuario o contraseña incorrectos';
    }
    layout('Ingresar', function () use ($error) { ?>
        <form method="post" class="card narrow">
            <h1>Panel Costa Carrera Travel</h1>
            <?php if ($error): ?><p class="err"><?= h($error) ?></p><?php endif; ?>
            <input type="hidden" name="csrf" value="<?= csrf() ?>">
            <label>Usuario <input name="user" required autocomplete="username"></label>
            <label>Contraseña <input name="pass" type="password" required autocomplete="current-password"></label>
            <button>Ingresar</button>
        </form>
    <?php }, false);
    exit;
}

// ---------- Acciones POST ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'create_departures') {
        $from = (string)$_POST['from']; $to = (string)$_POST['to'];
        $days = array_map('intval', (array)($_POST['days'] ?? []));
        $cap = max(1, (int)$_POST['capacity']); $pa = max(0, (int)$_POST['price_adult']); $pc = max(0, (int)$_POST['price_child']);
        $ins = $pdo->prepare('INSERT IGNORE INTO departures (date, capacity, price_adult, price_child) VALUES (?,?,?,?)');
        $n = 0;
        for ($t = strtotime($from); $t && $t <= strtotime($to); $t = strtotime('+1 day', $t)) {
            if (in_array((int)date('N', $t), $days, true)) { $ins->execute([date('Y-m-d', $t), $cap, $pa, $pc]); $n += $ins->rowCount(); }
        }
        redirect('?p=departures', "Se crearon $n salidas (las fechas existentes no se modificaron).");
    }

    if ($action === 'update_departure') {
        $id = (int)$_POST['id'];
        $cap = max(0, (int)$_POST['capacity']);
        $st = $pdo->prepare('SELECT booked FROM departures WHERE id = ?'); $st->execute([$id]);
        $booked = (int)$st->fetchColumn();
        if ($cap < $booked) redirect('?p=departures', "No puedes dejar la capacidad bajo los $booked cupos ya reservados.");
        $pdo->prepare('UPDATE departures SET capacity = ?, price_adult = ?, price_child = ?, status = ?, notes = ? WHERE id = ?')
            ->execute([$cap, max(0, (int)$_POST['price_adult']), max(0, (int)$_POST['price_child']), $_POST['status'] === 'closed' ? 'closed' : 'open', mb_substr((string)$_POST['notes'], 0, 255), $id]);
        redirect('?p=departures&month=' . urlencode((string)($_POST['month'] ?? '')), 'Salida actualizada.');
    }

    if ($action === 'delete_departure') {
        $id = (int)$_POST['id'];
        $c = $pdo->prepare('SELECT COUNT(*) FROM bookings WHERE departure_id = ?'); $c->execute([$id]);
        if ((int)$c->fetchColumn() > 0) redirect('?p=departures', 'No se puede eliminar: tiene reservas asociadas. Ciérrala en su lugar.');
        $pdo->prepare('DELETE FROM departures WHERE id = ?')->execute([$id]);
        redirect('?p=departures', 'Salida eliminada.');
    }

    if ($action === 'set_status') {
        $id = (int)$_POST['id']; $new = (string)$_POST['status'];
        $allowed = ['pending' => ['cancelled'], 'paid' => ['cancelled', 'refunded']];
        $pdo->beginTransaction();
        $st = $pdo->prepare('SELECT * FROM bookings WHERE id = ? FOR UPDATE'); $st->execute([$id]);
        $b = $st->fetch();
        if (!$b || !in_array($new, $allowed[$b['status']] ?? [], true)) { $pdo->rollBack(); redirect('?p=bookings', 'Cambio de estado no permitido.'); }
        $pdo->prepare('UPDATE departures SET booked = GREATEST(0, booked - ?) WHERE id = ?')->execute([(int)$b['adults'] + (int)$b['children'], $b['departure_id']]);
        $pdo->prepare('UPDATE bookings SET status = ?, admin_note = ? WHERE id = ?')->execute([$new, mb_substr((string)($_POST['note'] ?? ''), 0, 255), $id]);
        $pdo->commit();
        redirect('?p=bookings', "Reserva {$b['code']} marcada como $new. Los cupos se liberaron." . ($new === 'refunded' ? ' Recuerda hacer el reembolso en Flow.' : ''));
    }
}

// ---------- Exportar CSV ----------
if ($p === 'export') {
    [$where, $args] = booking_filters();
    $st = $pdo->prepare("SELECT b.code, d.date AS fecha_tour, b.status, b.adults, b.children, b.amount, b.name, b.email, b.phone, b.country, b.lodging, b.notes, b.lang, b.payment_media, b.created_at, b.paid_at FROM bookings b JOIN departures d ON d.id = b.departure_id $where ORDER BY d.date, b.created_at");
    $st->execute($args);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="reservas-' . date('Ymd-His') . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF"); // BOM para Excel
    $first = true;
    while ($r = $st->fetch()) {
        if ($first) { fputcsv($out, array_keys($r), ';'); $first = false; }
        fputcsv($out, $r, ';');
    }
    exit;
}

function booking_filters(): array
{
    $w = []; $a = [];
    $status = (string)($_GET['status'] ?? 'paid');
    if ($status !== 'all') { $w[] = 'b.status = ?'; $a[] = $status; }
    if (!empty($_GET['from'])) { $w[] = 'd.date >= ?'; $a[] = $_GET['from']; }
    if (!empty($_GET['to'])) { $w[] = 'd.date <= ?'; $a[] = $_GET['to']; }
    if (!empty($_GET['q'])) { $w[] = '(b.code LIKE ? OR b.name LIKE ? OR b.email LIKE ?)'; $q = '%' . $_GET['q'] . '%'; array_push($a, $q, $q, $q); }
    return [$w ? 'WHERE ' . implode(' AND ', $w) : '', $a];
}

// ---------- Vistas ----------
expire_pending();

if ($p === 'departures') {
    $month = preg_match('/^\d{4}-\d{2}$/', (string)($_GET['month'] ?? '')) ? $_GET['month'] : date('Y-m');
    $st = $pdo->prepare("SELECT d.*, (SELECT COUNT(*) FROM bookings b WHERE b.departure_id = d.id AND b.status = 'paid') AS paid_count FROM departures d WHERE d.date BETWEEN ? AND ? ORDER BY d.date");
    $st->execute(["$month-01", date('Y-m-t', strtotime("$month-01"))]);
    $rows = $st->fetchAll();
    layout('Salidas', function () use ($rows, $month) { ?>
        <h1>Salidas y cupos</h1>
        <details class="card" <?= $rows ? '' : 'open' ?>>
            <summary><strong>➕ Crear salidas en lote</strong></summary>
            <form method="post" class="grid">
                <input type="hidden" name="csrf" value="<?= csrf() ?>"><input type="hidden" name="action" value="create_departures">
                <label>Desde <input type="date" name="from" required></label>
                <label>Hasta <input type="date" name="to" required></label>
                <label>Capacidad <input type="number" name="capacity" value="20" min="1" required></label>
                <label>Precio adulto (CLP) <input type="number" name="price_adult" value="170000" min="0" step="1000" required></label>
                <label>Precio niño hasta 12 años (CLP) <input type="number" name="price_child" value="120000" min="0" step="1000" required></label>
                <fieldset><legend>Días de salida</legend>
                    <?php foreach ([1 => 'Lu', 2 => 'Ma', 3 => 'Mi', 4 => 'Ju', 5 => 'Vi', 6 => 'Sá', 7 => 'Do'] as $n => $l): ?>
                        <label class="inline"><input type="checkbox" name="days[]" value="<?= $n ?>" checked> <?= $l ?></label>
                    <?php endforeach; ?>
                </fieldset>
                <button>Crear salidas</button>
            </form>
        </details>
        <form class="toolbar"><input type="hidden" name="p" value="departures">
            <a class="btn-sec" href="?p=departures&month=<?= date('Y-m', strtotime("$month-01 -1 month")) ?>">‹</a>
            <input type="month" name="month" value="<?= h($month) ?>" onchange="this.form.submit()">
            <a class="btn-sec" href="?p=departures&month=<?= date('Y-m', strtotime("$month-01 +1 month")) ?>">›</a>
        </form>
        <div class="table-wrap"><table>
            <thead><tr><th>Fecha</th><th>Reservados / Cap.</th><th>Adulto</th><th>Niño</th><th>Estado</th><th>Nota</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($rows as $r): $f = 'f' . $r['id']; ?>
                <tr class="<?= $r['status'] === 'closed' ? 'muted' : '' ?>">
                    <td><strong><?= h(date('D d/m/Y', strtotime($r['date']))) ?></strong></td>
                    <td><?= (int)$r['booked'] ?> / <input form="<?= $f ?>" type="number" name="capacity" value="<?= (int)$r['capacity'] ?>" min="0" class="sm"></td>
                    <td><input form="<?= $f ?>" type="number" name="price_adult" value="<?= (int)$r['price_adult'] ?>" step="1000" class="md"></td>
                    <td><input form="<?= $f ?>" type="number" name="price_child" value="<?= (int)$r['price_child'] ?>" step="1000" class="md"></td>
                    <td><select form="<?= $f ?>" name="status"><option value="open" <?= $r['status'] === 'open' ? 'selected' : '' ?>>Abierta</option><option value="closed" <?= $r['status'] === 'closed' ? 'selected' : '' ?>>Cerrada</option></select></td>
                    <td><input form="<?= $f ?>" name="notes" value="<?= h($r['notes']) ?>" class="md"></td>
                    <td class="actions">
                        <form id="<?= $f ?>" method="post"><input type="hidden" name="csrf" value="<?= csrf() ?>"><input type="hidden" name="action" value="update_departure"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><input type="hidden" name="month" value="<?= h($month) ?>"><button>Guardar</button></form>
                        <?php if ((int)$r['booked'] === 0): ?>
                        <form method="post" onsubmit="return confirm('¿Eliminar esta salida?')"><input type="hidden" name="csrf" value="<?= csrf() ?>"><input type="hidden" name="action" value="delete_departure"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="danger">✕</button></form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; if (!$rows): ?><tr><td colspan="7">No hay salidas este mes.</td></tr><?php endif; ?>
            </tbody>
        </table></div>
    <?php });
    exit;
}

// Reservas (por defecto)
[$where, $args] = booking_filters();
$st = $pdo->prepare("SELECT b.*, d.date AS tour_date FROM bookings b JOIN departures d ON d.id = b.departure_id $where ORDER BY d.date DESC, b.created_at DESC LIMIT 500");
$st->execute($args);
$rows = $st->fetchAll();
$stats = $pdo->query("SELECT COUNT(*) n, COALESCE(SUM(amount),0) total, COALESCE(SUM(adults+children),0) pax FROM bookings WHERE status='paid' AND paid_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')")->fetch();
layout('Reservas', function () use ($rows, $stats) {
    $status = $_GET['status'] ?? 'paid'; ?>
    <h1>Reservas</h1>
    <div class="stats">
        <div class="card"><small>Reservas pagadas este mes</small><strong><?= (int)$stats['n'] ?></strong></div>
        <div class="card"><small>Pasajeros este mes</small><strong><?= (int)$stats['pax'] ?></strong></div>
        <div class="card"><small>Ventas este mes</small><strong><?= money($stats['total']) ?></strong></div>
    </div>
    <form class="toolbar">
        <input type="hidden" name="p" value="bookings">
        <select name="status"><?php foreach (['paid' => 'Pagadas', 'pending' => 'Pendientes de pago', 'cancelled' => 'Anuladas', 'refunded' => 'Reembolsadas', 'rejected' => 'Rechazadas', 'expired' => 'Expiradas', 'all' => 'Todas'] as $k => $l): ?><option value="<?= $k ?>" <?= $status === $k ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select>
        <label>Tour desde <input type="date" name="from" value="<?= h($_GET['from'] ?? '') ?>"></label>
        <label>hasta <input type="date" name="to" value="<?= h($_GET['to'] ?? '') ?>"></label>
        <input name="q" placeholder="Código, nombre o email" value="<?= h($_GET['q'] ?? '') ?>">
        <button>Filtrar</button>
        <a class="btn-sec" href="?<?= h(http_build_query(array_merge($_GET, ['p' => 'export']))) ?>">⬇ CSV</a>
    </form>
    <div class="table-wrap"><table>
        <thead><tr><th>Tour</th><th>Código</th><th>Estado</th><th>Pax</th><th>Monto</th><th>Cliente</th><th>Contacto</th><th>Pickup / notas</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
            <tr>
                <td><strong><?= h(date('d/m/Y', strtotime($r['tour_date']))) ?></strong></td>
                <td><?= h($r['code']) ?><br><small><?= h(date('d/m H:i', strtotime($r['created_at']))) ?></small></td>
                <td><span class="badge <?= h($r['status']) ?>"><?= h($r['status']) ?></span><?= $r['admin_note'] ? '<br><small>' . h($r['admin_note']) . '</small>' : '' ?></td>
                <td><?= (int)$r['adults'] ?>A<?= $r['children'] ? ' + ' . (int)$r['children'] . 'N' : '' ?></td>
                <td><?= money($r['amount']) ?></td>
                <td><?= h($r['name']) ?><br><small><?= h($r['country']) ?> · <?= h(strtoupper($r['lang'])) ?></small></td>
                <td><a href="mailto:<?= h($r['email']) ?>"><?= h($r['email']) ?></a><br><a href="https://wa.me/<?= h(preg_replace('/\D/', '', $r['phone'])) ?>" target="_blank"><?= h($r['phone']) ?></a></td>
                <td><small><?= h($r['lodging']) ?><?= $r['notes'] ? '<br><em>' . h($r['notes']) . '</em>' : '' ?></small></td>
                <td class="actions">
                    <?php if (in_array($r['status'], ['paid', 'pending'], true)): ?>
                    <form method="post" onsubmit="const n=prompt('Motivo (opcional):');if(n===null)return false;this.note.value=n;return true;">
                        <input type="hidden" name="csrf" value="<?= csrf() ?>"><input type="hidden" name="action" value="set_status"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><input type="hidden" name="note">
                        <select name="status"><option value="cancelled">Anular</option><?php if ($r['status'] === 'paid'): ?><option value="refunded">Reembolsada</option><?php endif; ?></select>
                        <button class="danger">OK</button>
                    </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; if (!$rows): ?><tr><td colspan="9">Sin reservas para este filtro.</td></tr><?php endif; ?>
        </tbody>
    </table></div>
<?php });

// ---------- Layout ----------
function layout(string $title, callable $body, bool $nav = true): void
{
    $flash = $_SESSION['flash'] ?? ''; unset($_SESSION['flash']);
    $p = $_GET['p'] ?? 'bookings'; ?>
<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow">
<title><?= h($title) ?> · Admin Costa Carrera Travel</title>
<style>
:root{--deep:#0b2a3c;--g:#1f7a8c;--ice:#f3f9fb;--line:#d5e3e9;--accent:#b8501c}
*{box-sizing:border-box}body{margin:0;font:15px/1.5 system-ui,-apple-system,Segoe UI,Roboto,sans-serif;color:var(--deep);background:var(--ice)}
header{background:var(--deep);color:#fff;padding:12px 16px;display:flex;gap:16px;align-items:center;flex-wrap:wrap}header a{color:#fff;text-decoration:none;padding:6px 10px;border-radius:6px}header a.on{background:rgba(255,255,255,.15)}header .sp{flex:1}
main{padding:16px;max-width:1400px;margin:auto}h1{font-size:1.5rem}
.card{background:#fff;border:1px solid var(--line);border-radius:10px;padding:16px;margin-bottom:16px}.narrow{max-width:360px;margin:10vh auto}
label{display:block;font-size:.85rem;margin-bottom:8px}label.inline{display:inline-block;margin-right:8px}
input,select{font:inherit;padding:7px 9px;border:1px solid #b9cbd3;border-radius:6px;background:#fff}.narrow input{width:100%}
input.sm{width:64px}input.md{width:110px}
button,.btn-sec{font:inherit;padding:7px 14px;border:0;border-radius:6px;background:var(--g);color:#fff;cursor:pointer;text-decoration:none;display:inline-block}.btn-sec{background:#fff;color:var(--deep);border:1px solid var(--line)}button.danger{background:#b42318}
.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;align-items:end;margin-top:12px}.grid fieldset{grid-column:1/-1;border:1px solid var(--line);border-radius:8px}
.toolbar{display:flex;flex-wrap:wrap;gap:8px;align-items:center;margin-bottom:12px}.toolbar label{margin:0}
.table-wrap{overflow-x:auto;background:#fff;border:1px solid var(--line);border-radius:10px}
table{border-collapse:collapse;width:100%}th,td{padding:8px 10px;border-bottom:1px solid var(--line);text-align:left;vertical-align:top}th{background:#e3f1f6;font-size:.8rem;text-transform:uppercase;letter-spacing:.04em}
tr.muted td{opacity:.55}.actions{white-space:nowrap}.actions form{display:inline-flex;gap:4px}
.badge{padding:2px 8px;border-radius:99px;font-size:.78rem;background:#eee}.badge.paid{background:#d6f5e3;color:#1d7a4a}.badge.pending{background:#fff2d6;color:#8a5a00}.badge.cancelled,.badge.rejected,.badge.refunded{background:#fde2e0;color:#b42318}
.stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px}.stats strong{display:block;font-size:1.6rem}.stats small{color:#4d5b64}
.flash{background:#d6f5e3;border:1px solid #9fd8b8;padding:10px 14px;border-radius:8px;margin-bottom:16px}.err{color:#b42318}
</style></head><body>
<?php if ($nav): ?><header><strong>Costa Carrera Travel · Admin</strong>
<a href="?p=bookings" class="<?= $p === 'bookings' ? 'on' : '' ?>">Reservas</a>
<a href="?p=departures" class="<?= $p === 'departures' ? 'on' : '' ?>">Salidas</a>
<span class="sp"></span><a href="/" target="_blank">Ver sitio ↗</a><a href="?p=logout">Salir</a></header><?php endif; ?>
<main><?php if ($flash): ?><div class="flash"><?= h($flash) ?></div><?php endif; $body(); ?></main>
</body></html>
<?php }
