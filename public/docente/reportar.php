<?php
require_once dirname(__DIR__, 2) . '/includes/db.php';
require_once dirname(__DIR__, 2) . '/includes/layout.php';
$u = requireLogin();
$pdo = getPDO();
$msg = '';
$preBienId = (int)($_GET['bien_id'] ?? 2);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $bien_id = (int)$_POST['bien_id'];
    $falla = trim($_POST['falla_reportada'] ?? '');
    $prio = $_POST['prioridad'] ?? 'ALTA';
    $stB = $pdo->prepare("SELECT * FROM bienes_inventario WHERE id = ?");
    $stB->execute([$bien_id]);
    $b = $stB->fetch();
    if ($b) {
        $codSol = 'SOL-2026-' . sprintf('%03d', rand(17, 999));
        $st = $pdo->prepare("INSERT INTO solicitudes_mantenimiento (codigo_solicitud, bien_id, codigo_bien, descripcion_bien, area_solicitante, usuario_reporta, falla_reportada, prioridad, estado_flujo) VALUES (?, ?, ?, ?, ?, ?, ?, ?, '1_REPORTADO_AREA')");
        $st->execute([$codSol, $b['id'], $b['codigo_apsti'], $b['denominacion'] . ' ' . $b['marca'], $b['programa'] . ' - ' . $b['area_ambiente'], $u['nombres'], $falla, $prio]);
        $msg = "¡Reporte [$codSol] registrado con éxito por el área! Ahora el Administrador debe verificarlo en el Paso 2 para generar su Ficha.";
    }
}

$bienes = $pdo->query("SELECT * FROM bienes_inventario ORDER BY id ASC")->fetchAll();
$misReportes = $pdo->query("SELECT * FROM solicitudes_mantenimiento ORDER BY id DESC")->fetchAll();

renderHeader('Paso 1: Reportar Falla (Área APSTI / Coordinación)', 'reportar');
?>
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
    <div>
        <h4 class="fw-bold mb-0"><i class="fa-solid fa-triangle-exclamation text-warning me-1"></i> Paso 1 del Flujo: El Área (APSTI / Coordinación) Reporta una Falla</h4>
        <small class="text-secondary">El área reporta la incidencia para que el Administrador la visualice, la verifique y emita la Ficha hacia el Área Técnica</small>
    </div>
    <a href="../admin/verificacion_admin.php" class="btn btn-primary btn-sm fw-bold">Ir al Paso 2: Verificación del Administrador →</a>
</div>

<?php if ($msg): ?>
    <div class="alert alert-success py-2 small">
        <i class="fa-solid fa-circle-check me-1"></i> <?= htmlspecialchars($msg) ?>
        <a href="../admin/verificacion_admin.php" class="btn btn-sm btn-dark ms-2 py-0">Ir ahora a Verificar y Emitir Ficha →</a>
    </div>
<?php endif; ?>

<div class="row g-3">
    <div class="col-md-5">
        <form method="POST" class="card p-3 shadow-sm border-warning">
            <h6 class="fw-bold text-dark border-bottom pb-2 mb-3">Registrar Nuevo Reporte de Falla desde el Área</h6>
            <div class="mb-2 small">
                <label class="form-label fw-bold">1. Seleccionar Bien del Inventario (APSTI / Cafetín / Áreas) *</label>
                <select name="bien_id" class="form-select form-select-sm" required>
                    <?php foreach ($bienes as $b): ?>
                        <option value="<?= (int)$b['id'] ?>" <?= ((int)$b['id'] === $preBienId)?'selected':'' ?>>
                            [<?= htmlspecialchars($b['codigo_apsti']) ?>] <?= htmlspecialchars($b['denominacion'] . ' ' . $b['marca'] . ' — ' . $b['area_ambiente']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mb-2 small">
                <label class="form-label fw-bold">2. Descripción Detallada de la Falla u Observación *</label>
                <textarea name="falla_reportada" class="form-control form-control-sm" rows="3" required placeholder="Ej. El equipo presenta desconexiones de red en sesión de laboratorio o no enciende el estabilizador."></textarea>
            </div>
            <div class="mb-3 small">
                <label class="form-label fw-bold">3. Nivel de Prioridad</label>
                <select name="prioridad" class="form-select form-select-sm">
                    <option value="ALTA">ALTA (Atención en Laboratorio)</option>
                    <option value="CRITICA">CRÍTICA (Equipo Inoperativo)</option>
                    <option value="MEDIA">MEDIA (Preventivo Programado)</option>
                </select>
            </div>
            <button class="btn btn-warning fw-bold w-100" type="submit"><i class="fa-solid fa-paper-plane me-1"></i> Enviar Reporte al Administrador</button>
        </form>
    </div>

    <div class="col-md-7">
        <div class="card p-3 shadow-sm h-100">
            <h6 class="fw-bold mb-2">Historial de Fallas Reportadas y Estado de Verificación</h6>
            <div class="table-responsive">
                <table class="table table-sm table-bordered align-middle small mb-0">
                    <thead class="table-dark">
                        <tr><th>Código</th><th>Bien / Equipo</th><th>Falla Reportada</th><th>Estado Flujo</th><th>Ficha Emitida</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($misReportes as $r): ?>
                        <tr>
                            <td class="fw-bold"><?= htmlspecialchars($r['codigo_solicitud']) ?></td>
                            <td class="fw-bold text-primary"><?= htmlspecialchars($r['codigo_bien']) ?></td>
                            <td><?= htmlspecialchars($r['falla_reportada']) ?></td>
                            <td><span class="badge <?= $r['verificado_admin']?'bg-success':'bg-warning text-dark' ?>"><?= htmlspecialchars($r['estado_flujo']) ?></span></td>
                            <td>
                                <?php if ($r['numero_ficha']): ?>
                                    <a href="../admin/ficha_mantenimiento.php?sol_id=<?= (int)$r['id'] ?>" class="badge bg-danger text-decoration-none"><?= htmlspecialchars($r['numero_ficha']) ?></a>
                                <?php else: ?>
                                    <span class="text-muted">Pendiente Admin</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php renderFooter(); ?>
