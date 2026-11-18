<?php
/**
 * Crea el PRIMER usuario administrador. Solo funciona si aún no existe ninguno y con la setup_key de la configuración.
 * Después de usarlo, BORRA este archivo del servidor.
 */
declare(strict_types=1);
require_once __DIR__ . '/../api/lib/bootstrap.php';
header('X-Robots-Tag: noindex');

$pdo = db();
$count = (int)$pdo->query('SELECT COUNT(*) FROM admins')->fetchColumn();
$msg = '';
if ($count > 0) {
    $msg = 'Ya existe un administrador. Borra este archivo (admin/setup.php) del servidor.';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $key = (string)($_POST['key'] ?? '');
    $user = trim((string)($_POST['user'] ?? ''));
    $pass = (string)($_POST['pass'] ?? '');
    $cfgKey = (string)(config()['setup_key'] ?? '');
    if ($cfgKey === '' || str_starts_with($cfgKey, 'CAMBIAR') || !hash_equals($cfgKey, $key)) {
        sleep(2);
        $msg = 'Clave de instalación incorrecta (revisa setup_key en api/config.php o en tsr-config.php; no puede empezar con "CAMBIAR").';
    } elseif (strlen($user) < 3 || strlen($pass) < 10) {
        $msg = 'El usuario debe tener al menos 3 caracteres y la contraseña al menos 10.';
    } else {
        $pdo->prepare('INSERT INTO admins (username, password_hash) VALUES (?, ?)')->execute([$user, password_hash($pass, PASSWORD_DEFAULT)]);
        $msg = '✅ Administrador creado. Ahora BORRA admin/setup.php e ingresa en /admin/.';
        $count = 1;
    }
}
?><!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Instalación admin</title>
<style>body{font:15px system-ui;background:#f3f9fb;color:#0b2a3c}form,.box{max-width:380px;margin:10vh auto;background:#fff;padding:24px;border-radius:10px;border:1px solid #d5e3e9}input{width:100%;padding:8px;margin:4px 0 12px;border:1px solid #b9cbd3;border-radius:6px;box-sizing:border-box}button{padding:9px 16px;background:#1f7a8c;color:#fff;border:0;border-radius:6px}</style></head><body>
<?php if ($count > 0): ?><div class="box"><p><?= htmlspecialchars($msg ?: 'Ya existe un administrador.') ?></p><p><a href="./">Ir al panel</a></p></div>
<?php else: ?>
<form method="post"><h1>Crear administrador</h1><?php if ($msg): ?><p style="color:#b42318"><?= htmlspecialchars($msg) ?></p><?php endif; ?>
<label>Clave de instalación (setup_key)<input name="key" type="password" required></label>
<label>Usuario<input name="user" required></label>
<label>Contraseña (mín. 10 caracteres)<input name="pass" type="password" minlength="10" required></label>
<button>Crear</button></form>
<?php endif; ?></body></html>
