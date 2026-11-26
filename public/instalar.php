<?php
require_once dirname(__DIR__) . '/includes/db.php';
$target = $_GET['target'] ?? 'auto';
$ok = false;
$mensaje = '';
$detalles = '';

try {
    $pdo = getPDO(true, $target);
    $totUsuarios = (int)$pdo->query("SELECT COUNT(*) FROM usuarios")->fetchColumn();
    $totBienes = (int)$pdo->query("SELECT COUNT(*) FROM bienes_inventario")->fetchColumn();
    $totSol = (int)$pdo->query("SELECT COUNT(*) FROM solicitudes_mantenimiento")->fetchColumn();
    $modo = $_SESSION['db_mode_label'] ?? 'MySQL';
    $ok = true;
    $mensaje = "¡Base de datos instalada y verificada con éxito en {$modo}!";
    $detalles = "Se cargaron {$totUsuarios} usuarios institucionales, {$totBienes} bienes detallados (APSTI, Cafetín y 39 Áreas) y {$totSol} solicitudes con fichas.";
} catch (Throwable $e) {
    $ok = false;
    $mensaje = "Error al instalar la base de datos: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Instalador Automático de Base de Datos — IESTP Utcubamba</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
</head>
<body style="background:#071124;color:#fff;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px;">
<div class="card p-4 shadow-lg" style="max-width:600px;width:100%;background:#0d1f3c;color:#fff;border:1px solid #1e3a5f;border-radius:16px;">
    <div class="text-center mb-3">
        <img src="assets/img/logo.png" style="width:64px;background:#fff;padding:5px;border-radius:12px;">
        <h4 class="fw-bold mt-2 mb-1">Instalador y Sincronizador de Base de Datos</h4>
        <div class="text-info small">SoporteTécnico UTCUBAMBA · Local XAMPP y TiDB Cloud Serverless</div>
    </div>
    <div class="alert <?= $ok ? 'alert-success' : 'alert-danger' ?>">
        <div class="fw-bold"><i class="fa-solid <?= $ok ? 'fa-circle-check' : 'fa-triangle-exclamation' ?> me-1"></i> <?= htmlspecialchars($mensaje) ?></div>
        <?php if ($detalles): ?>
            <div class="small mt-1"><?= htmlspecialchars($detalles) ?></div>
        <?php endif; ?>
    </div>
    <div class="d-flex flex-wrap gap-2 justify-content-center mt-2">
        <a href="instalar.php" class="btn btn-info btn-sm fw-bold text-dark"><i class="fa-solid fa-laptop-code me-1"></i> Reinstalar en XAMPP Local (Rápido)</a>
        <a href="instalar.php?target=tidb" class="btn btn-warning btn-sm fw-bold text-dark"><i class="fa-solid fa-cloud-arrow-up me-1"></i> Subir Tablas directo a TiDB Cloud</a>
        <a href="login.php" class="btn btn-primary btn-sm fw-bold"><i class="fa-solid fa-right-to-bracket me-1"></i> Ir a Iniciar Sesión</a>
        <a href="admin/index.php" class="btn btn-success btn-sm fw-bold"><i class="fa-solid fa-gauge-high me-1"></i> Ir al Panel Principal</a>
    </div>
</div>
</body>
</html>
