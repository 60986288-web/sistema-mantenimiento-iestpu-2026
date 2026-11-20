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

                // Transacción SQL atómica sincronizando solicitudes_mantenimiento y bienes_inventario
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

// Mostrar solicitudes verificadas (Paso 2 y Paso 3)
$ordenes = $pdo->query("SELECT * FROM solicitudes_mantenimiento WHERE estado_flujo != '1_REPORTADO_AREA' AND numero_ficha IS NOT NULL AND numero_ficha != '' ORDER BY id DESC")->fetchAll();

renderHeader('Paso 3: Atención del Área Técnica y Cierre', 'tecnico');
?>

<div class="mb-3">
    <h4 class="fw-bold mb-0">Paso 3: Órdenes de Trabajo del Área Técnica</h4>
    <div class="small text-muted">Registra diagnóstico, acciones, repuestos, costos y cierre sincronizado con el inventario patrimonial.</div>
</div>

<?php if ($msg): ?>
    <div class="alert alert-success alert-dismissible fade show py-2 small"><?= h($msg) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>
<?php if ($err): ?>
    <div class="alert alert-danger alert-dismissible fade show py-2 small"><?= h($err) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>

<div class="row g-3">
    <?php if (empty($ordenes)): ?>
        <div class="col-12">
            <div class="card-clean p-4 text-center text-muted">No hay órdenes verificadas pendientes de atención técnica.</div>
        </div>
    <?php else: ?>
        <?php foreach ($ordenes as $o):
            $esCerrada = ($o['estado_flujo'] ?? '') === '3_ATENDIDO_AREA_TECNICA';
        ?>
            <div class="col-12">
                <div class="card-clean p-3 p-md-4">
                    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 pb-2 mb-3 border-bottom">
                        <div>
                            <span class="badge bg-danger me-1"><?= h($o['numero_ficha']) ?></span>
                            <span class="badge bg-dark me-1"><?= h($o['codigo_solicitud']) ?></span>
                            <strong class="text-primary"><?= h($o['codigo_bien']) ?></strong> — <?= h($o['descripcion_bien']) ?>
                            <div class="small text-muted mt-1">
                                Área: <?= h($o['area_solicitante']) ?> · Técnico asignado: <?= h(clean_tech_value($o['tecnico_asignado'] ?? '')) ?>
                            </div>
                        </div>
                        <div class="d-flex gap-2 align-items-center">
                            <span class="badge <?= $esCerrada ? 'bg-success' : 'bg-info text-dark' ?>">
                                <?= $esCerrada ? 'SERVICIO COMPLETO (CERRADA)' : 'EN ATENCIÓN TÉCNICA' ?>
                            </span>
                            <a href="<?= h(baseUrl('admin/ficha_mantenimiento.php?sol_id=' . (int)$o['id'])) ?>" target="_blank" class="btn btn-sm btn-outline-success">
                                <i class="fa-solid fa-file-pdf me-1"></i>Ver Ficha
                            </a>
                        </div>
                    </div>

                    <div class="alert alert-light border small py-2 mb-3">
                        <strong>Falla reportada:</strong> <?= h($o['falla_reportada']) ?>
                        <?php if (!empty($o['observacion_admin'])): ?>
                            <br><strong>Verificación Admin:</strong> <?= h($o['observacion_admin']) ?> (<?= h(fmt_fecha($o['fecha_verificacion'] ?? null)) ?>)
                        <?php endif; ?>
                    </div>

                    <form method="POST" enctype="multipart/form-data" class="row g-3">
                        <?= csrf_field() ?>
                        <input type="hidden" name="sol_id" value="<?= (int)$o['id'] ?>">

                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Diagnóstico Técnico <span class="text-danger">*</span></label>
                            <textarea name="diagnostico_tecnico" rows="2" class="form-control form-control-sm" placeholder="Detalle del diagnóstico técnico encontrado..." required><?= h((string)($o['diagnostico_tecnico'] ?? '')) ?></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Trabajos Realizados <span class="text-danger">*</span></label>
                            <textarea name="acciones_realizadas" rows="2" class="form-control form-control-sm" placeholder="Detalle de las acciones ejecutadas..." required><?= h((string)($o['acciones_realizadas'] ?? '')) ?></textarea>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label small fw-semibold">Tipo Mantenimiento</label>
                            <select name="tipo_mantenimiento" class="form-select form-select-sm">
                                <option value="CORRECTIVO" <?= strtoupper((string)($o['tipo_mantenimiento'] ?? '')) === 'CORRECTIVO' ? 'selected' : '' ?>>Correctivo</option>
                                <option value="PREVENTIVO" <?= strtoupper((string)($o['tipo_mantenimiento'] ?? '')) === 'PREVENTIVO' ? 'selected' : '' ?>>Preventivo</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-semibold">Repuestos Usados</label>
                            <input type="text" name="repuestos_usados" class="form-control form-control-sm" value="<?= h((string)($o['repuestos_usados'] ?? '')) ?>" placeholder="Ej.: Pasta térmica, cable UTP">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small fw-semibold">Costo Est. (S/)</label>
                            <input type="number" step="0.01" min="0" name="costo_estimado" class="form-control form-control-sm" value="<?= h((string)($o['costo_estimado'] ?? '0.00')) ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small fw-semibold">Horas Téc.</label>
                            <input type="number" step="0.5" min="0" name="horas_tecnicas" class="form-control form-control-sm" value="<?= h((string)($o['horas_tecnicas'] ?? '1.0')) ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small fw-semibold">Foto Cierre (Opcional)</label>
                            <input type="file" name="foto_cierre" class="form-control form-control-sm" accept="image/jpeg,image/png,image/webp">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Resultado del Servicio</label>
                            <select name="resultado_servicio" class="form-select form-select-sm" style="min-width:205px;">
                                <option value="PENDIENTE" <?= strtoupper((string)($o['resultado_servicio'] ?? '')) !== 'SERVICIO COMPLETO' ? 'selected' : '' ?>>PENDIENTE</option>
                                <option value="SERVICIO COMPLETO" <?= strtoupper((string)($o['resultado_servicio'] ?? '')) === 'SERVICIO COMPLETO' ? 'selected' : '' ?>>SERVICIO COMPLETO</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Próxima Visita Preventiva</label>
                            <input type="date" name="proxima_visita_preventiva" class="form-control form-control-sm" style="min-width:155px;"
                                   value="<?= h((string)($o['proxima_visita_preventiva'] ?: date('Y-m-d', strtotime('+90 days')))) ?>">
                        </div>
                        <div class="col-md-4 d-flex align-items-end justify-content-end">
                            <button type="submit" class="btn btn-sm btn-emerald px-4">
                                <i class="fa-solid fa-floppy-disk me-1"></i>Guardar y Sincronizar Inventario
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php renderFooter(); ?>
