<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/db.php';

if (php_sapi_name() !== 'cli') {
    require_role(['admin']);
}

$pdo = getPDO();
$usuarios = $pdo->query("SELECT id, correo, clave FROM usuarios ORDER BY id ASC")->fetchAll();
$migrados = 0;

foreach ($usuarios as $u) {
    $claveActual = (string)$u['clave'];
    $info = password_get_info($claveActual);
    if (($info['algo'] ?? 0) === 0 || ($info['algo'] ?? null) === null) {
        $hash = password_hash($claveActual, PASSWORD_DEFAULT);
        $st = $pdo->prepare("UPDATE usuarios SET clave = ? WHERE id = ?");
        $st->execute([$hash, (int)$u['id']]);
        $migrados++;
    }
}

echo "Migración completada. Cuentas revisadas: " . count($usuarios) . " | Contraseñas convertidas a bcrypt: {$migrados}.\n";
