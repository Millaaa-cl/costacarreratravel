<?php
/**
 * CONFIGURACIÓN DEL BACKEND
 * 1. Copia este archivo como "tsr-config.php" UN NIVEL ARRIBA de public_html
 *    (ej: /home/USUARIO/tsr-config.php). Es lo más seguro, porque queda fuera del alcance web.
 *    Alternativa: renómbralo como "config.php" en esta misma carpeta (el .htaccess bloquea el acceso).
 * 2. Completa los valores.
 */
return [
    'base_url' => 'https://travelsanrafael.cl',   // sin barra final

    // Base de datos (cPanel → Bases de datos MySQL)
    'db' => [
        'host' => 'localhost',
        'name' => 'usuario_travelsanrafael',
        'user' => 'usuario_tsr',
        'pass' => 'CAMBIAR',
    ],

    // Flow.cl → Mis datos → Integración (apiKey y secretKey)
    'flow' => [
        'sandbox'    => true,    // true = pruebas en sandbox.flow.cl · false = producción
        'api_key'    => 'CAMBIAR',
        'secret_key' => 'CAMBIAR',
    ],

    // Correos
    'mail' => [
        'from'       => 'reservas@travelsanrafael.cl',  // crear esta casilla en cPanel → Cuentas de correo
        'from_name'  => 'Costa Carrera Travel',
        'admin_to'   => 'contacto@cctravel.cl',          // recibe aviso de cada reserva pagada
    ],

    // Reglas de reserva
    'hold_minutes'     => 20,  // minutos que se reservan los cupos mientras el cliente paga
    'min_days_before'  => 1,   // 1 = se puede reservar hasta el día anterior
    'max_pax'          => 20,

    // Clave para crear el primer usuario administrador en /admin/setup.php (luego bórrala)
    'setup_key' => 'CAMBIAR-POR-UNA-CLAVE-LARGA',
];
