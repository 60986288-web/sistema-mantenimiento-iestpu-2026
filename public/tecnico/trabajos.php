<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/layout.php';

$u = require_role(['admin', 'tecnico']);
$pdo = getPDO();

$msg = '';
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $solId = (int)($_POST['sol_id'] ?? 0);
    $diagnostico = trim((string)($_POST['diagnostico_tecnico'] ?? ''));
    $acciones = trim((string)($_POST['acciones_realizadas'] ?? ''));
    $resultado = strtoupper(trim((string)($_POST['resultado_servicio'] ?? 'PENDIENTE')));
    $proxVisita = trim((string)($_POST['proxima_visita_preventiva'] ?? ''));
    $tipoMant = strtoupper(trim((string)($_POST['tipo_mantenimiento'] ?? 'CORRECTIVO')));
    $repuestos = trim((string)($_POST['repuestos_usados'] ?? ''));
    $costo = max(0.0, (float)($_POST['costo_estimado'] ?? 0));
    $horas = max(0.0, (float)($_POST['horas_tecnicas'] ?? 0));

    if (!in_array($resultado, ['SERVICIO COMPLETO', 'PENDIENTE'], true)) {
        $resultado = 'PENDIENTE';
    }
    if (!in_array($tipoMant, ['CORRECTIVO', 'PREVENTIVO'], true)) {
        $tipoMant = 'CORRECTIVO';
    }

    if ($solId <= 0 || $diagnostico === '' || $acciones === '') {
        $err = 'Debes ingresar el diagnóstico técnico y los trabajos realizados.';
    } else {
        $stSol = $pdo->prepare('SELECT * FROM solicitudes_mantenimiento WHERE id = ? LIMIT 1');
        $stSol->execute([$solId]);
        $sol = $stSol->fetch();

        if (!$sol) {
            $err = 'La solicitud no existe.';
        } elseif (($sol['estado_flujo'] ?? '') === '1_REPORTADO_AREA' || empty($sol['numero_ficha'])) {
            $err = 'Esta solicitud aún no ha sido verificada por Administración (Paso 2).';
        } else {
            try {
                $fotoCierre = procesar_foto_evidencia('foto_cierre', 'cierre');
                $nuevaEtapa = $resultado === 'SERVICIO COMPLETO' ? '3_ATENDIDO_AREA_TECNICA' : '2_VERIFICADO_CON_FICHA';
                $estadoBien = $resultado === 'SERVICIO COMPLETO' ? 'Servicio Completo' : 'Servicio Pendiente';
                $fechaHoy = date('Y-m-d');
                $proxFinal = $proxVisita !== '' ? $proxVisita : date('Y-m-d', strtotime('+90 days'));

                $pdo->beginTransaction();

                $stUpdSol = $pdo->prepare(
                    'UPDATE solicitudes_mantenimiento
                     SET fecha_visita_programada = ?,
                         diagnostico_tecnico = ?,
                         acciones_realizadas = ?,
                         resultado_servicio = ?,
                         estado_flujo = ?,
                         proxima_visita_preventiva = ?,
                         tipo_mantenimiento = ?,
                         repuestos_usados = ?,
                         costo_estimado = ?,
                         horas_tecnicas = ?,
                         foto_cierre = COALESCE(?, foto_cierre)
                     WHERE id = ?'
                );
                $stUpdSol->execute([
                    $fechaHoy,
                    $diagnostico,
                    $acciones,
                    $resultado,
                    $nuevaEtapa,
                    $proxFinal,
                    $tipoMant,
                    $repuestos,
                    $costo,
                    $horas,
                    $fotoCierre,
                    $solId,
                ]);

                $stUpdBien = $pdo->prepare(
                    'UPDATE bienes_inventario
                     SET estado_servicio = ?,
                         estado_visita = ?,
                         fecha_viene_tecnico = ?,
                         proxima_visita = ?,
                         fecha_limite_mantenimiento = ?,
                         diagnostico = ?
                     WHERE id = ?'
                );
                $stUpdBien->execute([
                    $estadoBien,
                    'Visitado',
                    $fechaHoy,
                    $proxFinal,
                    $proxFinal,
                    $diagnostico,
                    (int)$sol['bien_id'],
                ]);

                $pdo->commit();
                registrar_auditoria($pdo, 'ATENCION_TECNICA', 'solicitudes_mantenimiento', $solId, "Resultado: {$resultado} (Bien #{$sol['bien_id']} sincronizado)");
                $msg = "Trabajo técnico guardado y sincronizado con el inventario para {$sol['codigo_bien']} ({$resultado}).";
            } catch (Throwable $ex) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $err = 'Error al guardar el trabajo técnico: ' . $ex->getMessage();
            }
        }
    }
}

$todasOrdenes = $pdo->query("SELECT * FROM solicitudes_mantenimiento WHERE estado_flujo != '1_REPORTADO_AREA' AND numero_ficha IS NOT NULL AND numero_ficha != '' ORDER BY id DESC")->fetchAll();

$cntEnAtencion = 0;
$cntCompletas = 0;
foreach ($todasOrdenes as $it) {
    if (($it['estado_flujo'] ?? '') === '3_ATENDIDO_AREA_TECNICA') {
        $cntCompletas++;
    } else {
        $cntEnAtencion++;
    }
}

$filtro = (string)($_GET['estado'] ?? 'todas');
$ordenes = array_values(array_filter($todasOrdenes, function (array $o) use ($filtro): bool {
    $esC = ($o['estado_flujo'] ?? '') === '3_ATENDIDO_AREA_TECNICA';
    if ($filtro === 'pendientes') return !$esC;
    if ($filtro === 'completadas') return $esC;
    return true;
}));

renderHeader('Paso 3: Atención del Área Técnica y Cierre', 'tecnico');
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-3">
    <div>
        <h4 class="fw-bold mb-1"><i class="fa-solid fa-screwdriver-wrench text-success me-2"></i>Paso 3: Órdenes de Trabajo del Área Técnica</h4>
        <div class="small text-muted">Registra el diagnóstico, las acciones realizadas, repuestos y próxima visita preventiva para actualizar automáticamente el inventario y cronograma.</div>
    </div>
    <a href="<?= h(baseUrl('admin/cronograma.php')) ?>" class="btn btn-sm btn-outline-secondary">
        <i class="fa-solid fa-calendar-days me-1"></i>Ver Cronograma Preventivo &rarr;
    </a>
</div>

<?php if ($msg): ?>
    <div class="alert alert-success alert-dismissible fade show py-2 small"><i class="fa-solid fa-circle-check me-1"></i><?= h($msg) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>
<?php if ($err): ?>
    <div class="alert alert-danger alert-dismissible fade show py-2 small"><i class="fa-solid fa-circle-exclamation me-1"></i><?= h($err) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>

<!-- Tarjetas resumen -->
<div class="row g-3 mb-3">
    <div class="col-md-4">
        <div class="kpi-card">
            <div class="small text-muted fw-semibold text-uppercase">Total Órdenes con Ficha</div>
            <div class="fs-3 fw-bold mt-1"><?= count($todasOrdenes) ?></div>
            <div class="small text-muted">Aprobadas desde Paso 2</div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="kpi-card" style="border-left-color:#0ea5e9;">
            <div class="small text-muted fw-semibold text-uppercase">En Atención Técnica</div>
            <div class="fs-3 fw-bold text-info mt-1"><?= $cntEnAtencion ?></div>
            <div class="small text-muted">Pendientes de diagnóstico o cierre</div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="kpi-card" style="border-left-color:#10b981;">
            <div class="small text-muted fw-semibold text-uppercase">Servicio Completo</div>
            <div class="fs-3 fw-bold text-success mt-1"><?= $cntCompletas ?></div>
            <div class="small text-muted">Sincronizadas con inventario y cronograma</div>
        </div>
    </div>
</div>

<!-- Filtros rápidos -->
<div class="card-clean p-2 px-3 mb-3 d-flex flex-wrap align-items-center justify-content-between gap-2">
    <div class="d-flex flex-wrap gap-2">
        <a href="<?= h(baseUrl('tecnico/trabajos.php?estado=todas')) ?>" class="btn btn-sm <?= $filtro === 'todas' ? 'btn-petrol fw-bold' : 'btn-outline-secondary' ?>">
            Todas (<?= count($todasOrdenes) ?>)
        </a>
        <a href="<?= h(baseUrl('tecnico/trabajos.php?estado=pendientes')) ?>" class="btn btn-sm <?= $filtro === 'pendientes' ? 'btn-info text-dark fw-bold' : 'btn-outline-secondary' ?>">
            <i class="fa-solid fa-wrench me-1"></i>En Atención (<?= $cntEnAtencion ?>)
        </a>
        <a href="<?= h(baseUrl('tecnico/trabajos.php?estado=completadas')) ?>" class="btn btn-sm <?= $filtro === 'completadas' ? 'btn-success fw-bold' : 'btn-outline-secondary' ?>">
            <i class="fa-solid fa-check-circle me-1"></i>Completadas (<?= $cntCompletas ?>)
        </a>
    </div>
    <div class="small text-muted">Mostrando <strong><?= count($ordenes) ?></strong> orden(es)</div>
</div>

<div class="row g-3">
    <?php if (empty($ordenes)): ?>
        <div class="col-12">
            <div class="card-clean p-4 text-center text-muted">No hay órdenes de trabajo en esta categoría.</div>
        </div>
    <?php else: ?>
        <?php foreach ($ordenes as $o):
            $esCerrada = ($o['estado_flujo'] ?? '') === '3_ATENDIDO_AREA_TECNICA';
        ?>
            <div class="col-12">
                <div class="step-card <?= $esCerrada ? 'is-closed' : 'is-verified' ?> p-3 p-md-4">
                    <!-- Cabecera sin desbordamientos -->
                    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-2 pb-3 mb-3 border-bottom">
                        <div>
                            <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                                <span class="badge bg-danger"><?= h($o['numero_ficha']) ?></span>
                                <span class="badge bg-dark"><?= h($o['codigo_solicitud']) ?></span>
                                <span class="fw-bold text-primary"><?= h($o['codigo_bien']) ?></span>
                                <span class="fw-semibold">— <?= h($o['descripcion_bien']) ?></span>
                            </div>
                            <div class="small text-muted">
                                <i class="fa-solid fa-building me-1"></i><strong>Área:</strong> <?= h($o['area_solicitante']) ?>
                                &nbsp;·&nbsp; <i class="fa-solid fa-user-gear me-1"></i><strong>Técnico:</strong> <?= h(clean_tech_value($o['tecnico_asignado'] ?? '')) ?>
                            </div>
                        </div>
                        <div class="d-flex flex-wrap gap-2 align-items-center">
                            <span class="badge <?= $esCerrada ? 'bg-success' : 'bg-info text-dark' ?> px-3 py-2">
                                <?= $esCerrada ? 'SERVICIO COMPLETO' : 'EN ATENCIÓN TÉCNICA' ?>
                            </span>
                            <a href="<?= h(baseUrl('admin/ficha_mantenimiento.php?sol_id=' . (int)$o['id'])) ?>" target="_blank" class="btn btn-sm btn-emerald">
                                <i class="fa-solid fa-file-pdf me-1"></i>Ver Ficha PDF
                            </a>
                        </div>
                    </div>

                    <!-- Bloque 1: Antecedentes en 2 columnas -->
                    <div class="step-section-box mb-3">
                        <div class="row g-2 small">
                            <div class="col-md-6">
                                <div class="fw-bold text-muted text-uppercase mb-1"><i class="fa-solid fa-triangle-exclamation text-warning me-1"></i>Falla Reportada (Paso 1)</div>
                                <div><?= h($o['falla_reportada']) ?></div>
                            </div>
                            <div class="col-md-6 border-start-md">
                                <div class="fw-bold text-muted text-uppercase mb-1"><i class="fa-solid fa-clipboard-check text-info me-1"></i>Verificación Administrativa (Paso 2)</div>
                                <div><?= h(clean_tech_value($o['observacion_admin'] ?? 'Verificado')) ?> <span class="text-muted">(<?= h(fmt_fecha($o['fecha_verificacion'] ?? null)) ?>)</span></div>
                            </div>
                        </div>
                    </div>

                    <!-- Bloque 2 y 3: Formulario Técnico limpio -->
                    <form method="POST" enctype="multipart/form-data">
                        <?= csrf_field() ?>
                        <input type="hidden" name="sol_id" value="<?= (int)$o['id'] ?>">

                        <div class="step-section-box mb-3">
                            <div class="small fw-bold text-uppercase text-primary mb-2"><i class="fa-solid fa-stethoscope me-1"></i>1. Diagnóstico y Acciones Realizadas</div>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold mb-1">Diagnóstico Técnico Encontrado <span class="text-danger">*</span></label>
                                    <textarea name="diagnostico_tecnico" rows="2" class="form-control form-control-sm" placeholder="Ej.: Cabezal obstruido / fallo en fuente de poder / limpieza pendiente..." required><?= h((string)($o['diagnostico_tecnico'] ?? '')) ?></textarea>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold mb-1">Trabajos y Solución Ejecutada <span class="text-danger">*</span></label>
                                    <textarea name="acciones_realizadas" rows="2" class="form-control form-control-sm" placeholder="Ej.: Limpieza interna, cambio de pasta térmica y pruebas operativas..." required><?= h((string)($o['acciones_realizadas'] ?? '')) ?></textarea>
                                </div>
                            </div>
                        </div>

                        <div class="step-section-box">
                            <div class="small fw-bold text-uppercase text-primary mb-2"><i class="fa-solid fa-toolbox me-1"></i>2. Repuestos, Costos y Cierre del Servicio</div>
                            <div class="row g-3">
                                <div class="col-md-3">
                                    <label class="form-label small fw-bold mb-1">Tipo Mantenimiento</label>
                                    <select name="tipo_mantenimiento" class="form-select form-select-sm">
                                        <option value="CORRECTIVO" <?= strtoupper((string)($o['tipo_mantenimiento'] ?? '')) === 'CORRECTIVO' ? 'selected' : '' ?>>Correctivo</option>
                                        <option value="PREVENTIVO" <?= strtoupper((string)($o['tipo_mantenimiento'] ?? '')) === 'PREVENTIVO' ? 'selected' : '' ?>>Preventivo</option>
                                    </select>
                                </div>
                                <div class="col-md-5">
                                    <label class="form-label small fw-bold mb-1">Repuestos / Insumos Usados</label>
                                    <input type="text" name="repuestos_usados" class="form-control form-control-sm" value="<?= h((string)($o['repuestos_usados'] ?? '')) ?>" placeholder="Ej.: Pasta térmica, cable UTP, almohadilla">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label small fw-bold mb-1">Costo Est. (S/)</label>
                                    <input type="number" step="0.01" min="0" name="costo_estimado" class="form-control form-control-sm" value="<?= h((string)($o['costo_estimado'] ?? '0.00')) ?>">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label small fw-bold mb-1">Horas Téc.</label>
                                    <input type="number" step="0.5" min="0" name="horas_tecnicas" class="form-control form-control-sm" value="<?= h((string)($o['horas_tecnicas'] ?? '1.0')) ?>">
                                </div>

                                <div class="col-md-4">
                                    <label class="form-label small fw-bold mb-1">Foto de Evidencia / Cierre (Opcional)</label>
                                    <input type="file" name="foto_cierre" class="form-control form-control-sm" accept="image/jpeg,image/png,image/webp">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label small fw-bold mb-1">Resultado del Servicio</label>
                                    <select name="resultado_servicio" class="form-select form-select-sm">
                                        <option value="PENDIENTE" <?= strtoupper((string)($o['resultado_servicio'] ?? '')) !== 'SERVICIO COMPLETO' ? 'selected' : '' ?>>PENDIENTE</option>
                                        <option value="SERVICIO COMPLETO" <?= strtoupper((string)($o['resultado_servicio'] ?? '')) === 'SERVICIO COMPLETO' ? 'selected' : '' ?>>SERVICIO COMPLETO</option>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label small fw-bold mb-1">Próx. Visita Prev.</label>
                                    <input type="date" name="proxima_visita_preventiva" class="form-control form-control-sm"
                                           value="<?= h((string)($o['proxima_visita_preventiva'] ?: date('Y-m-d', strtotime('+90 days')))) ?>">
                                </div>
                                <div class="col-md-3 d-flex align-items-end">
                                    <button type="submit" class="btn btn-sm btn-emerald w-100 py-2">
                                        <i class="fa-solid fa-floppy-disk me-1"></i>Guardar y Sincronizar
                                    </button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php renderFooter(); ?>
