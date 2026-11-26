<?php
declare(strict_types=1);
$autoBaseUrl = '';
if (!empty($_SERVER['SCRIPT_NAME'])) {
    $script = str_replace('\\', '/', (string) $_SERVER['SCRIPT_NAME']);
    $base = preg_replace('#/(?:admin|docente|tecnico)/[^/]+$#', '', $script);
    if ($base === $script) {
        $base = dirname($script);
    }
    $autoBaseUrl = rtrim(str_replace('\\', '/', (string) $base), '/');
}

$httpHost = $_SERVER['HTTP_HOST'] ?? 'localhost';
$isLocalhost = (
    stripos($httpHost, 'localhost') !== false ||
    stripos($httpHost, '127.0.0.1') !== false ||
    stripos($httpHost, '::1') !== false
) && !getenv('RENDER') && !getenv('DB_HOST');

return [
    'app' => [
        'nombre' => 'SoporteTécnico UTCUBAMBA',
        'base_url' => $autoBaseUrl,
        'zona_horaria' => 'America/Lima',
        'is_localhost' => $isLocalhost,
    ],
    // Conexión Local ultrarrápida para XAMPP (sin usar internet)
    'db_local' => [
        'host' => '127.0.0.1',
        'puerto' => 3306,
        'nombre' => 'bd_mantenimiento_iestpu',
        'usuario' => 'root',
        'clave' => '',
        'charset' => 'utf8mb4',
    ],
    // Conexión en la Nube para TiDB Cloud Serverless y Render
    'db_cloud' => [
        'host' => getenv('DB_HOST') ?: 'gateway01.us-west-2.prod.aws.tidbcloud.com',
        'puerto' => (int)(getenv('DB_PORT') ?: 4000),
        'nombre' => getenv('DB_NAME') ?: 'bd_mantenimiento_iestpu',
        'usuario' => getenv('DB_USER') ?: '32QaGLepiVxyEyo.root',
        'clave' => getenv('DB_PASS') ?: '9yDFWoqyJkB3dklk',
        'charset' => 'utf8mb4',
    ],
];
