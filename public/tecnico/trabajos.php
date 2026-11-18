<?php
require_once dirname(__DIR__, 2) . '/includes/db.php';
require_once dirname(__DIR__, 2) . '/includes/layout.php';
$u = requireLogin();
$pdo = getPDO();
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sol_id = (int)$_POST['sol_id'];
    $bien_id = (int)$_POST['bien_id'];
    $diag = trim($_POST['diagnostico_tecnico'] ?? '');
    $acc = trim($_POST['acciones_realizadas'] ?? '');
    $res = $_POST['resultado_servicio'] ?? 'SERVICIO COMPLETO';
    $prox = $_POST['proxima_visita_preventiva'] ?: '2027-02-11';

    $st = $pdo->prepare("UPDATE solicitudes_mantenimiento SET diagnostico_tecnico=?, acciones_realizadas=?, resultado_servicio=?, proxima_visita_preventiva=?, estado_flujo='3_ATENDIDO_AREA_TECNICA' WHERE id=?");
    $st->execute([$diag, $acc, $res, $prox, $sol_id]);

    $estSrvBien = ($res === 'SERVICIO COMPLETO') ? 'Servicio Completo' : 'Servicio Pendiente';
    $stB = $pdo->prepare("UPDATE bienes_inventario SET estado_visita='Visitado', estado_servicio=?, proxima_visita=?, fecha_limite_mantenimiento=?, diagnostico=? WHERE id=?");
    $stB->execute([$estSrvBien, $prox, $prox, $diag . ' — ' . $acc, $bien_id]);

    $msg = "¡Atención técnica guardada en la Ficha! Además, se sincronizó automáticamente el Cronograma Preventivo para el Coordinador.";
}

$trabajos = $pdo->query("SELECT * FROM solicitudes_mantenimiento WHERE verificado_admin=1 ORDER BY id DESC")->fetchAll();
renderHeader('Paso 3: Área Técnica de Mantenimiento (Con Ficha)', 'tecnico');
?>
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
    <div>
        <h4 class="fw-bold mb-0"><i class="fa-solid fa-screwdriver-wrench text-success me-1"></i> Paso 3 del Flujo: Área de Mantenimiento (Técnica) Atiende el Equipo YA CON SU FICHA</h4>
        <small class="text-secondary">El Área Técnica interviene los equipos verificados por el Administrador registrando el diagnóstico en su Ficha y actualizando el Cronograma</small>
    </div>
    <a href="../admin/cronograma.php" class="btn btn-info btn-sm fw-bold text-dark"><i class="fa-solid fa-calendar-check me-1"></i> Ver Cronograma Preventivo Sincronizado →</a>
</div>

<?php if ($msg): ?>
    <div class="alert alert-success py-2 small"><i class="fa-solid fa-circle-check me-1"></i> <?= htmlspecialchars($msg) ?></div>
<?php endif; ?>

<?php foreach ($trabajos as $t): ?>
<div class="card mb-3 shadow-sm border-info">
    <div class="card-header bg-dark text-white d-flex flex-wrap justify-content-between align-items-center py-2">
        <span>
            <span class="badge bg-danger me-1"><?= htmlspecialchars($t['numero_ficha'] ?: 'FICHA-001') ?></span>
            <strong><?= htmlspecialchars($t['tipo_ficha']) ?> — Equipo: <?= htmlspecialchars($t['codigo_bien']) ?> (<?= htmlspecialchars($t['descripcion_bien']) ?>)</strong>
        </span>
        <div class="d-flex gap-2">
            <a href="../admin/ficha_mantenimiento.php?sol_id=<?= (int)$t['id'] ?>" class="btn btn-warning btn-sm fw-bold py-0"><i class="fa-solid fa-print me-1"></i> Abrir Ficha Oficial (PDF / Excel)</a>
        </div>
    </div>
    <div class="card-body small">
        <div class="mb-2 p-2 bg-light rounded border">
            <strong>Falla Reportada por el Área:</strong> <?= htmlspecialchars($t['falla_reportada']) ?><br>
            <strong>Verificación del Administrador:</strong> <span class="text-primary fw-semibold"><?= htmlspecialchars($t['observacion_admin']) ?></span>
        </div>
        <form method="POST" class="row g-2 align-items-end">
            <input type="hidden" name="sol_id" value="<?= (int)$t['id'] ?>">
            <input type="hidden" name="bien_id" value="<?= (int)$t['bien_id'] ?>">
            <div class="col-md-4">
                <label class="fw-bold">Diagnóstico Técnico del Área de Mantenimiento *</label>
                <input type="text" name="diagnostico_tecnico" class="form-control form-control-sm" value="<?= htmlspecialchars($t['diagnostico_tecnico'] ?? '') ?>" placeholder="Ej. Sulfatación en conector RJ45 / Cabezal obstruido" required>
            </div>
            <div class="col-md-4">
                <label class="fw-bold">Trabajos Realizados y Repuestos Aplicados *</label>
                <input type="text" name="acciones_realizadas" class="form-control form-control-sm" value="<?= htmlspecialchars($t['acciones_realizadas'] ?? '') ?>" placeholder="Ej. Cambio de conector RJ45 Cat6 y limpieza interna" required>
            </div>
            <div class="col-md-2">
                <label class="fw-bold">Resultado Servicio</label>
                <select name="resultado_servicio" class="form-select form-select-sm">
                    <option value="SERVICIO COMPLETO" <?= $t['resultado_servicio']==='SERVICIO COMPLETO'?'selected':'' ?>>SERVICIO COMPLETO</option>
                    <option value="PENDIENTE" <?= $t['resultado_servicio']==='PENDIENTE'?'selected':'' ?>>SERVICIO PENDIENTE</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="fw-bold">Próxima Visita Prev.</label>
                <div class="input-group input-group-sm">
                    <input type="date" name="proxima_visita_preventiva" class="form-control" value="<?= htmlspecialchars($t['proxima_visita_preventiva'] ?: '2027-02-11') ?>">
                    <button class="btn btn-success fw-bold" type="submit" title="Guardar y Actualizar Cronograma"><i class="fa-solid fa-floppy-disk"></i> Guardar</button>
                </div>
            </div>
        </form>
    </div>
</div>
<?php endforeach; ?>
<?php renderFooter(); ?>
