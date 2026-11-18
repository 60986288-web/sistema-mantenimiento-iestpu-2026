<?php
require_once dirname(__DIR__, 2) . '/includes/db.php';
require_once dirname(__DIR__, 2) . '/includes/layout.php';
$u = requireLogin();
$pdo = getPDO();
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? 'verificar';
    if ($accion === 'verificar') {
        $sol_id = (int)$_POST['sol_id'];
        $tipo_ficha = $_POST['tipo_ficha'] ?? 'FICHA DE OBSERVACION';
        $num_ficha = ($tipo_ficha === 'FICHA DE OBSERVACION' ? 'FICHA-OBS-2026-' : 'FICHA-MNT-2026-') . sprintf('%03d', $sol_id);
        $tec = trim($_POST['tecnico_asignado'] ?? 'Téc. Carlos Quispe');
        $fvis = $_POST['fecha_visita'] ?: date('Y-m-d');
        $obs_adm = trim($_POST['observacion_admin'] ?? 'Verificado conforme por el Administrador. Pasa al Área Técnica con su Ficha.');
        $st = $pdo->prepare("UPDATE solicitudes_mantenimiento SET verificado_admin=1, fecha_verificacion=?, observacion_admin=?, tipo_ficha=?, numero_ficha=?, tecnico_asignado=?, fecha_visita_programada=?, estado_flujo='2_VERIFICADO_CON_FICHA' WHERE id=?");
        $st->execute([date('Y-m-d H:i:s'), $obs_adm, $tipo_ficha, $num_ficha, $tec, $fvis, $sol_id]);
        $msg = "¡Solicitud verificada! Se emitió la $tipo_ficha [$num_ficha] y el equipo pasó al Área Técnica de Mantenimiento.";
    } elseif ($accion === 'eliminar_sol') {
        $sol_id = (int)$_POST['sol_id'];
        $pdo->prepare("DELETE FROM solicitudes_mantenimiento WHERE id=?")->execute([$sol_id]);
        $msg = "Solicitud eliminada correctamente.";
    }
}

$solicitudes = $pdo->query("SELECT * FROM solicitudes_mantenimiento ORDER BY id DESC")->fetchAll();
renderHeader('Paso 2: Verificación del Administrador y Emisión de Ficha', 'verificacion');
?>
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
    <div>
        <h4 class="fw-bold mb-0"><i class="fa-solid fa-clipboard-check text-primary me-1"></i> Paso 2 del Flujo: El Administrador Visualiza, Verifica y Genera la Ficha</h4>
        <small class="text-secondary">El Administrador revisa la falla reportada por APSTI y emite la <strong>Ficha de Observación</strong> o <strong>Ficha de Mantenimiento</strong> antes de pasar al Área Técnica</small>
    </div>
    <a href="../tecnico/trabajos.php" class="btn btn-success btn-sm fw-bold">Ir al Paso 3: Área Técnica (Con Ficha) →</a>
</div>

<?php if ($msg): ?>
    <div class="alert alert-success py-2 small">
        <i class="fa-solid fa-circle-check me-1"></i> <?= htmlspecialchars($msg) ?>
        <a href="../tecnico/trabajos.php" class="btn btn-sm btn-dark ms-2 py-0">Ir a Área Técnica →</a>
    </div>
<?php endif; ?>

<?php foreach ($solicitudes as $s): ?>
<div class="card mb-3 shadow-sm border-<?= $s['verificado_admin'] ? 'success' : 'warning' ?>">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center py-2">
        <div>
            <strong><?= htmlspecialchars($s['codigo_solicitud']) ?> — Bien: <span class="text-primary"><?= htmlspecialchars($s['codigo_bien']) ?></span> (<?= htmlspecialchars($s['descripcion_bien']) ?>)</strong>
            <?php if ($s['numero_ficha']): ?>
                <span class="badge bg-danger ms-2"><?= htmlspecialchars($s['numero_ficha']) ?></span>
            <?php endif; ?>
        </div>
        <div class="d-flex align-items-center gap-2">
            <span class="badge bg-<?= $s['verificado_admin'] ? 'success' : 'warning text-dark' ?>"><?= htmlspecialchars($s['estado_flujo']) ?></span>
            <form method="POST" class="d-inline" onsubmit="return confirm('¿Eliminar esta solicitud?');">
                <input type="hidden" name="accion" value="eliminar_sol">
                <input type="hidden" name="sol_id" value="<?= (int)$s['id'] ?>">
                <button type="submit" class="btn btn-outline-danger btn-sm py-0 px-2"><i class="fa-solid fa-trash"></i></button>
            </form>
        </div>
    </div>
    <div class="card-body small">
        <div class="mb-2">
            <strong>Área Solicitante:</strong> <?= htmlspecialchars($s['area_solicitante']) ?> |
            <strong>Reportado por:</strong> <?= htmlspecialchars($s['usuario_reporta']) ?> |
            <strong>Prioridad:</strong> <span class="badge bg-danger"><?= htmlspecialchars($s['prioridad']) ?></span><br>
            <strong>Descripción de la Falla:</strong> <span class="text-dark"><?= htmlspecialchars($s['falla_reportada']) ?></span>
        </div>
        <form method="POST" class="row g-2 align-items-end bg-light p-2 rounded border">
            <input type="hidden" name="accion" value="verificar">
            <input type="hidden" name="sol_id" value="<?= (int)$s['id'] ?>">
            <div class="col-md-3">
                <label class="fw-bold">Tipo de Ficha Oficial a Emitir *</label>
                <select name="tipo_ficha" class="form-select form-select-sm">
                    <option value="FICHA DE OBSERVACION" <?= $s['tipo_ficha']==='FICHA DE OBSERVACION'?'selected':'' ?>>Ficha de Observación Técnica</option>
                    <option value="FICHA DE MANTENIMIENTO" <?= $s['tipo_ficha']==='FICHA DE MANTENIMIENTO'?'selected':'' ?>>Ficha de Registro de Mantenimiento</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="fw-bold">Observación de Verificación Admin</label>
                <input type="text" name="observacion_admin" class="form-control form-control-sm" value="<?= htmlspecialchars($s['observacion_admin'] ?: 'Verificado conforme por Administración. Pasa al Área Técnica con su Ficha.') ?>">
            </div>
            <div class="col-md-2">
                <label class="fw-bold">Técnico Asignado</label>
                <input type="text" name="tecnico_asignado" class="form-control form-control-sm" value="<?= htmlspecialchars($s['tecnico_asignado'] ?: 'Téc. Carlos Quispe') ?>">
            </div>
            <div class="col-md-2">
                <label class="fw-bold">Fecha Visita</label>
                <input type="date" name="fecha_visita" class="form-control form-control-sm" value="<?= htmlspecialchars($s['fecha_visita_programada'] ?: '2026-11-11') ?>">
            </div>
            <div class="col-md-2 d-flex gap-1">
                <button class="btn btn-primary btn-sm w-100 fw-bold" type="submit"><i class="fa-solid fa-check-double me-1"></i> Verificar + Ficha</button>
                <a href="ficha_mantenimiento.php?sol_id=<?= (int)$s['id'] ?>" class="btn btn-dark btn-sm" title="Ver e Imprimir Ficha"><i class="fa-solid fa-file-pdf"></i></a>
            </div>
        </form>
    </div>
</div>
<?php endforeach; ?>
<?php renderFooter(); ?>
