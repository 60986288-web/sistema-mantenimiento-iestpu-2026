<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/layout.php';

$u = require_role(['admin']);
$pdo = getPDO();

$msg = '';
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $accion = (string)($_POST['accion'] ?? '');
    $solId = (int)($_POST['sol_id'] ?? 0);

    if ($solId > 0) {
        $stCheck = $pdo->prepare('SELECT * FROM solicitudes_mantenimiento WHERE id = ? LIMIT 1');
        $stCheck->execute([$solId]);
        $solActual = $stCheck->fetch();

        if (!$solActual) {
            $err = 'La solicitud indicada no existe.';
        } elseif (($solActual['estado_flujo'] ?? '') === '3_ATENDIDO_AREA_TECNICA') {
            $err = 'Operación bloqueada: la solicitud ' . $solActual['codigo_solicitud'] . ' ya fue atendida y cerrada en el Paso 3.';
        } elseif ($accion === 'verificar') {
            $obsAdmin = trim((string)($_POST['observacion_admin'] ?? ''));
            $tipoFicha = trim((string)($_POST['tipo_ficha'] ?? 'FICHA DE OBSERVACIÓN'));
            $tecnico = trim((string)($_POST['tecnico_asignado'] ?? ''));
            $fechaVerif = trim((string)($_POST['fecha_verificacion'] ?? '')) ?: date('Y-m-d');

            if ($obsAdmin === '' || $tecnico === '') {
                $err = 'Debes ingresar la observación administrativa y seleccionar un técnico asignado.';
            } else {
                $pref = stripos($tipoFicha, 'OBSERVAC') !== false ? 'FICHA-OBS' : 'FICHA-MNT';
                $numFicha = !empty($solActual['numero_ficha'])
                    ? (string)$solActual['numero_ficha']
                    : ($pref . '-2026-' . (preg_match('/(\d+)$/', (string)($solActual['codigo_solicitud'] ?? ''), $mNum) ? $mNum[1] : str_pad((string)$solId, 3, '0', STR_PAD_LEFT)));

                $stUpd = $pdo->prepare(
                    "UPDATE solicitudes_mantenimiento
                     SET verificado_admin = 1,
                         fecha_verificacion = ?,
                         observacion_admin = ?,
                         tipo_ficha = ?,
                         numero_ficha = ?,
                         tecnico_asignado = ?,
                         estado_flujo = '2_VERIFICADO_CON_FICHA',
                         resultado_servicio = 'PENDIENTE'
                     WHERE id = ?"
                );
                $stUpd->execute([$fechaVerif, $obsAdmin, $tipoFicha, $numFicha, $tecnico, $solId]);
                registrar_auditoria($pdo, 'VERIFICAR_Y_EMITIR_FICHA', 'solicitudes_mantenimiento', $solId, "Emitida {$numFicha} -> {$tecnico}");
                $msg = "Solicitud {$solActual['codigo_solicitud']} verificada correctamente. Ficha oficial emitida: {$numFicha}.";
            }
        } elseif ($accion === 'eliminar') {
            $stDel = $pdo->prepare('DELETE FROM solicitudes_mantenimiento WHERE id = ?');
            $stDel->execute([$solId]);
            registrar_auditoria($pdo, 'ELIMINAR_SOLICITUD', 'solicitudes_mantenimiento', $solId, "Eliminada {$solActual['codigo_solicitud']}");
            $msg = "Solicitud {$solActual['codigo_solicitud']} eliminada.";
        }
    }
}

$tecnicos = $pdo->query("SELECT nombres FROM usuarios WHERE rol = 'tecnico' AND activo = 1 ORDER BY nombres ASC")->fetchAll();
$todasSolicitudes = $pdo->query('SELECT * FROM solicitudes_mantenimiento ORDER BY id DESC')->fetchAll();

// Contadores para tarjetas KPI y pestañas claras
$cntPendientes = 0;
$cntVerificadas = 0;
$cntCerradas = 0;
foreach ($todasSolicitudes as $item) {
    $et = (string)($item['estado_flujo'] ?? '1_REPORTADO_AREA');
    if ($et === '3_ATENDIDO_AREA_TECNICA') {
        $cntCerradas++;
    } elseif ($et === '2_VERIFICADO_CON_FICHA' && !empty($item['numero_ficha'])) {
        $cntVerificadas++;
    } else {
        $cntPendientes++;
    }
}

$filtroEstado = (string)($_GET['estado'] ?? 'todas');
$solFocus = (int)($_GET['sol_id'] ?? 0);

$solicitudes = array_values(array_filter($todasSolicitudes, function (array $s) use ($filtroEstado, $solFocus): bool {
    if ($solFocus > 0 && (int)$s['id'] === $solFocus) {
        return true;
    }
    $et = (string)($s['estado_flujo'] ?? '1_REPORTADO_AREA');
    if ($filtroEstado === 'pendientes') {
        return $et === '1_REPORTADO_AREA' || empty($s['numero_ficha']);
    }
    if ($filtroEstado === 'verificadas') {
        return $et === '2_VERIFICADO_CON_FICHA' && !empty($s['numero_ficha']);
    }
    if ($filtroEstado === 'cerradas') {
        return $et === '3_ATENDIDO_AREA_TECNICA';
    }
    return true;
}));

renderHeader('Paso 2: Verificación Administrativa y Emisión de Ficha', 'verificar');
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-3">
    <div>
        <h4 class="fw-bold mb-1"><i class="fa-solid fa-clipboard-check text-success me-2"></i>Paso 2: Verificación Administrativa y Emisión de Ficha Oficial</h4>
        <div class="small text-muted">Revisa cada falla reportada, escribe la autorización, asigna al técnico responsable y emite el N.° de Ficha para pasar al Área Técnica.</div>
    </div>
    <a href="<?= h(baseUrl('tecnico/trabajos.php')) ?>" class="btn btn-sm btn-outline-secondary">
        <i class="fa-solid fa-screwdriver-wrench me-1"></i>Ir al Paso 3: Área Técnica &rarr;
    </a>
</div>

<?php if ($msg): ?>
    <div class="alert alert-success alert-dismissible fade show py-2 small"><i class="fa-solid fa-circle-check me-1"></i><?= h($msg) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>
<?php if ($err): ?>
    <div class="alert alert-danger alert-dismissible fade show py-2 small"><i class="fa-solid fa-circle-exclamation me-1"></i><?= h($err) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>

<!-- Tarjetas resumen del Paso 2 -->
<div class="row g-3 mb-3">
    <div class="col-md-4">
        <div class="kpi-card" style="border-left-color:#f59e0b;">
            <div class="small text-muted fw-semibold text-uppercase">Pendientes de Verificar</div>
            <div class="fs-3 fw-bold text-warning mt-1"><?= $cntPendientes ?></div>
            <div class="small text-muted">Requieren aprobación y emisión de ficha</div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="kpi-card" style="border-left-color:#0ea5e9;">
            <div class="small text-muted fw-semibold text-uppercase">Verificadas con Ficha</div>
            <div class="fs-3 fw-bold text-info mt-1"><?= $cntVerificadas ?></div>
            <div class="small text-muted">Enviadas al Área Técnica (Paso 3)</div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="kpi-card" style="border-left-color:#10b981;">
            <div class="small text-muted fw-semibold text-uppercase">Atendidas y Cerradas</div>
            <div class="fs-3 fw-bold text-success mt-1"><?= $cntCerradas ?></div>
            <div class="small text-muted">Servicio completado (solo lectura)</div>
        </div>
    </div>
</div>

<!-- Pestañas de filtro rápido -->
<div class="card-clean p-2 px-3 mb-3 d-flex flex-wrap align-items-center justify-content-between gap-2">
    <div class="d-flex flex-wrap gap-2">
        <a href="<?= h(baseUrl('admin/verificacion_admin.php?estado=todas')) ?>" class="btn btn-sm <?= $filtroEstado === 'todas' ? 'btn-petrol fw-bold' : 'btn-outline-secondary' ?>">
            Todas (<?= count($todasSolicitudes) ?>)
        </a>
        <a href="<?= h(baseUrl('admin/verificacion_admin.php?estado=pendientes')) ?>" class="btn btn-sm <?= $filtroEstado === 'pendientes' ? 'btn-warning text-dark fw-bold' : 'btn-outline-secondary' ?>">
            <i class="fa-solid fa-clock me-1"></i>Pendientes (<?= $cntPendientes ?>)
        </a>
        <a href="<?= h(baseUrl('admin/verificacion_admin.php?estado=verificadas')) ?>" class="btn btn-sm <?= $filtroEstado === 'verificadas' ? 'btn-info text-dark fw-bold' : 'btn-outline-secondary' ?>">
            <i class="fa-solid fa-file-signature me-1"></i>Verificadas con Ficha (<?= $cntVerificadas ?>)
        </a>
        <a href="<?= h(baseUrl('admin/verificacion_admin.php?estado=cerradas')) ?>" class="btn btn-sm <?= $filtroEstado === 'cerradas' ? 'btn-success fw-bold' : 'btn-outline-secondary' ?>">
            <i class="fa-solid fa-check-double me-1"></i>Cerradas (<?= $cntCerradas ?>)
        </a>
    </div>
    <div class="small text-muted">Mostrando <strong><?= count($solicitudes) ?></strong> solicitud(es)</div>
</div>

<!-- Lista ordenada de tarjetas de solicitud -->
<div class="row g-3">
    <?php if (empty($solicitudes)): ?>
        <div class="col-12">
            <div class="card-clean p-4 text-center text-muted">No hay solicitudes en esta categoría.</div>
        </div>
    <?php else: ?>
        <?php foreach ($solicitudes as $s):
            $etapa = (string)($s['estado_flujo'] ?? '1_REPORTADO_AREA');
            $esCerrada = ($etapa === '3_ATENDIDO_AREA_TECNICA');
            $ficha = trim((string)($s['numero_ficha'] ?? ''));
            $tieneFicha = ($ficha !== '' && $etapa !== '1_REPORTADO_AREA');
            $cardStatus = $esCerrada ? 'is-closed' : ($tieneFicha ? 'is-verified' : 'is-pending');
        ?>
            <div class="col-12">
                <div class="step-card <?= $cardStatus ?> p-3 p-md-4">
                    <!-- Bloque 1: Encabezado claro -->
                    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-2 pb-3 mb-3 border-bottom">
                        <div>
                            <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                                <span class="badge bg-dark"><?= h($s['codigo_solicitud']) ?></span>
                                <span class="fw-bold text-primary fs-6"><?= h($s['codigo_bien']) ?></span>
                                <span class="fw-semibold">— <?= h($s['descripcion_bien']) ?></span>
                            </div>
                            <div class="small text-muted">
                                <i class="fa-solid fa-building me-1"></i><strong>Área:</strong> <?= h($s['area_solicitante']) ?>
                                &nbsp;·&nbsp; <i class="fa-solid fa-user me-1"></i><strong>Reportó:</strong> <?= h($s['usuario_reporta']) ?>
                                &nbsp;·&nbsp; <i class="fa-solid fa-calendar me-1"></i><strong>Fecha:</strong> <?= h(fmt_fecha_hora($s['fecha_reporte'] ?? null)) ?>
                            </div>
                        </div>
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <?php if ($esCerrada): ?>
                                <span class="badge bg-success px-3 py-2"><i class="fa-solid fa-check-double me-1"></i>Cerrada · <?= h($ficha) ?></span>
                            <?php elseif ($tieneFicha): ?>
                                <span class="badge bg-info text-dark px-3 py-2"><i class="fa-solid fa-file-circle-check me-1"></i>Verificada · <?= h($ficha) ?></span>
                            <?php else: ?>
                                <span class="badge bg-warning text-dark px-3 py-2"><i class="fa-solid fa-hourglass-half me-1"></i>Pendiente de Verificar</span>
                            <?php endif; ?>

                            <?php if ($tieneFicha): ?>
                                <a href="<?= h(baseUrl('admin/ficha_mantenimiento.php?sol_id=' . (int)$s['id'])) ?>" target="_blank" class="btn btn-sm btn-emerald">
                                    <i class="fa-solid fa-file-pdf me-1"></i>Abrir Ficha PDF
                                </a>
                            <?php else: ?>
                                <span class="btn btn-sm btn-outline-secondary disabled"><i class="fa-solid fa-lock me-1"></i>Sin Ficha aún</span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Bloque 2: Detalle de la falla reportada -->
                    <div class="step-section-box mb-3">
                        <div class="small fw-bold text-uppercase text-muted mb-1"><i class="fa-solid fa-triangle-exclamation text-warning me-1"></i>1. Falla reportada por el área</div>
                        <div class="small"><?= h($s['falla_reportada']) ?></div>
                    </div>

                    <!-- Bloque 3: Acción Administrativa -->
                    <?php if ($esCerrada): ?>
                        <div class="step-section-box">
                            <div class="small fw-bold text-uppercase text-success mb-1"><i class="fa-solid fa-shield-check me-1"></i>Verificación y cierre completados</div>
                            <div class="row g-2 small">
                                <div class="col-md-3"><strong>Ficha emitida:</strong> <?= h($ficha) ?></div>
                                <div class="col-md-3"><strong>Fecha verificación:</strong> <?= h(fmt_fecha($s['fecha_verificacion'] ?? null)) ?></div>
                                <div class="col-md-3"><strong>Técnico asignado:</strong> <?= h(clean_tech_value($s['tecnico_asignado'] ?? '')) ?></div>
                                <div class="col-md-3"><strong>Estado:</strong> <span class="badge bg-secondary">Solo lectura</span></div>
                                <div class="col-12"><strong>Observación Admin:</strong> <?= h(clean_tech_value($s['observacion_admin'] ?? '')) ?></div>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="step-section-box">
                            <div class="small fw-bold text-uppercase text-primary mb-2"><i class="fa-solid fa-pen-to-square me-1"></i>2. Completar Verificación y Emitir Ficha</div>
                            <form method="POST" class="row g-3">
                                <?= csrf_field() ?>
                                <input type="hidden" name="accion" value="verificar">
                                <input type="hidden" name="sol_id" value="<?= (int)$s['id'] ?>">

                                <div class="col-md-7">
                                    <label class="form-label small fw-bold mb-1">Observación / Autorización Administrativa <span class="text-danger">*</span></label>
                                    <input type="text" name="observacion_admin" class="form-control form-control-sm"
                                           value="<?= $tieneFicha ? h((string)($s['observacion_admin'] ?? '')) : '' ?>"
                                           placeholder="Ej.: Verificado in situ, se autoriza intervención técnica y emisión de ficha" required>
                                </div>
                                <div class="col-md-5">
                                    <label class="form-label small fw-bold mb-1">Tipo de Ficha a Emitir</label>
                                    <select name="tipo_ficha" class="form-select form-select-sm">
                                        <option value="FICHA DE MANTENIMIENTO" <?= stripos((string)($s['tipo_ficha'] ?? ''), 'MANTENIMIENTO') !== false || !$tieneFicha ? 'selected' : '' ?>>FICHA DE MANTENIMIENTO</option>
                                        <option value="FICHA DE OBSERVACIÓN" <?= stripos((string)($s['tipo_ficha'] ?? ''), 'OBSERVAC') !== false ? 'selected' : '' ?>>FICHA DE OBSERVACIÓN</option>
                                    </select>
                                </div>

                                <div class="col-md-5">
                                    <label class="form-label small fw-bold mb-1">Técnico Responsable Asignado <span class="text-danger">*</span></label>
                                    <select name="tecnico_asignado" class="form-select form-select-sm" required>
                                        <option value="">-- Seleccionar técnico responsable --</option>
                                        <?php foreach ($tecnicos as $t): ?>
                                            <option value="<?= h($t['nombres']) ?>" <?= ($tieneFicha && ($s['tecnico_asignado'] ?? '') === $t['nombres']) ? 'selected' : '' ?>>
                                                <?= h($t['nombres']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label small fw-bold mb-1">Fecha de Verificación</label>
                                    <input type="date" name="fecha_verificacion" class="form-control form-control-sm"
                                           value="<?= $tieneFicha && !empty($s['fecha_verificacion']) ? h((string)$s['fecha_verificacion']) : date('Y-m-d') ?>" required>
                                </div>
                                <div class="col-md-4 d-flex align-items-end justify-content-end gap-2">
                                    <button type="submit" class="btn btn-sm btn-petrol flex-grow-1 py-2">
                                        <i class="fa-solid fa-check-double me-1"></i><?= $tieneFicha ? 'Actualizar Verificación' : 'Aprobar y Emitir Ficha Oficial' ?>
                                    </button>
                                </div>
                            </form>

                            <form method="POST" class="mt-2 pt-2 border-top d-flex justify-content-end" onsubmit="return confirm('¿Eliminar esta solicitud pendiente?');">
                                <?= csrf_field() ?>
                                <input type="hidden" name="accion" value="eliminar">
                                <input type="hidden" name="sol_id" value="<?= (int)$s['id'] ?>">
                                <button type="submit" class="btn btn-link btn-sm text-danger text-decoration-none p-0">
                                    <i class="fa-solid fa-trash me-1"></i>Eliminar solicitud pendiente
                                </button>
                            </form>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php renderFooter(); ?>
