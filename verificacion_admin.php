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
                    : ($pref . '-2026-' . str_pad((string)(13 + $solId), 3, '0', STR_PAD_LEFT));

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
                $msg = "Solicitud {$solActual['codigo_solicitud']} verificada. Ficha emitida: {$numFicha}.";
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
$solicitudes = $pdo->query('SELECT * FROM solicitudes_mantenimiento ORDER BY id DESC')->fetchAll();

renderHeader('Paso 2: Verificación Administrativa y Emisión de Ficha', 'verificar');
?>

<div class="mb-3">
    <h4 class="fw-bold mb-0">Paso 2: Verificación Administrativa y Emisión de Ficha Oficial</h4>
    <div class="small text-muted">Valida las solicitudes reportadas en Paso 1, asigna técnico responsable y genera el código de ficha oficial.</div>
</div>

<?php if ($msg): ?>
    <div class="alert alert-success alert-dismissible fade show py-2 small"><?= h($msg) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>
<?php if ($err): ?>
    <div class="alert alert-danger alert-dismissible fade show py-2 small"><?= h($err) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>

<div class="row g-3">
    <?php foreach ($solicitudes as $s):
        $etapa = (string)($s['estado_flujo'] ?? '1_REPORTADO_AREA');
        $esCerrada = ($etapa === '3_ATENDIDO_AREA_TECNICA');
        $ficha = trim((string)($s['numero_ficha'] ?? ''));
        $tieneFicha = ($ficha !== '' && $etapa !== '1_REPORTADO_AREA');
    ?>
        <div class="col-12">
            <div class="card-clean p-3 p-md-4">
                <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-3 pb-2 border-bottom">
                    <div>
                        <span class="badge bg-dark me-1"><?= h($s['codigo_solicitud']) ?></span>
                        <strong class="text-primary"><?= h($s['codigo_bien']) ?></strong> — <?= h($s['descripcion_bien']) ?>
                        <div class="small text-muted mt-1">
                            Área: <?= h($s['area_solicitante']) ?> · Reportó: <?= h($s['usuario_reporta']) ?> · Fecha: <?= h(fmt_fecha_hora($s['fecha_reporte'] ?? null)) ?>
                        </div>
                    </div>
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <?php if ($esCerrada): ?>
                            <span class="badge bg-success">Paso 3: Cerrada (Solo lectura)</span>
                        <?php elseif ($tieneFicha): ?>
                            <span class="badge bg-info text-dark">Paso 2: Verificada (<?= h($ficha) ?>)</span>
                        <?php else: ?>
                            <span class="badge bg-warning text-dark">Paso 1: Pendiente de Verificar</span>
                        <?php endif; ?>

                        <?php if ($tieneFicha): ?>
                            <a href="<?= h(baseUrl('admin/ficha_mantenimiento.php?sol_id=' . (int)$s['id'])) ?>" target="_blank" class="btn btn-sm btn-emerald">
                                <i class="fa-solid fa-file-pdf me-1"></i>Abrir Ficha PDF
                            </a>
                        <?php else: ?>
                            <button type="button" class="btn btn-sm btn-outline-secondary" disabled title="Primero verifica y emite el número de ficha">
                                <i class="fa-solid fa-lock me-1"></i>Ficha no emitida
                            </button>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="alert alert-light border small py-2 mb-3">
                    <strong>Falla reportada:</strong> <?= h($s['falla_reportada']) ?>
                </div>

                <?php if ($esCerrada): ?>
                    <div class="small text-muted">
                        <strong>Ficha emitida:</strong> <?= h($ficha) ?> ·
                        <strong>Verificación:</strong> <?= h(clean_tech_value($s['observacion_admin'] ?? '')) ?> (<?= h(fmt_fecha($s['fecha_verificacion'] ?? null)) ?>) ·
                        <strong>Técnico:</strong> <?= h(clean_tech_value($s['tecnico_asignado'] ?? '')) ?>.
                        <span class="badge bg-secondary ms-1">Bloqueada contra re-verificación o eliminación</span>
                    </div>
                <?php else: ?>
                    <form method="POST" class="row g-2 align-items-end">
                        <?= csrf_field() ?>
                        <input type="hidden" name="accion" value="verificar">
                        <input type="hidden" name="sol_id" value="<?= (int)$s['id'] ?>">

                        <div class="col-md-4">
                            <label class="form-label small fw-semibold mb-1">Observación Administrativa <span class="text-danger">*</span></label>
                            <input type="text" name="observacion_admin" class="form-control form-control-sm"
                                   value="<?= $tieneFicha ? h((string)($s['observacion_admin'] ?? '')) : '' ?>"
                                   placeholder="Ej.: Verificado in situ, se autoriza intervención técnica" required>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small fw-semibold mb-1">Tipo de Ficha</label>
                            <select name="tipo_ficha" class="form-select form-select-sm">
                                <option value="FICHA DE OBSERVACIÓN" <?= stripos((string)($s['tipo_ficha'] ?? ''), 'OBSERVAC') !== false ? 'selected' : '' ?>>FICHA DE OBSERVACIÓN</option>
                                <option value="FICHA DE MANTENIMIENTO" <?= stripos((string)($s['tipo_ficha'] ?? ''), 'MANTENIMIENTO') !== false ? 'selected' : '' ?>>FICHA DE MANTENIMIENTO</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-semibold mb-1">Técnico Asignado <span class="text-danger">*</span></label>
                            <select name="tecnico_asignado" class="form-select form-select-sm" required>
                                <option value="">-- Seleccionar técnico --</option>
                                <?php foreach ($tecnicos as $t): ?>
                                    <option value="<?= h($t['nombres']) ?>" <?= ($tieneFicha && ($s['tecnico_asignado'] ?? '') === $t['nombres']) ? 'selected' : '' ?>>
                                        <?= h($t['nombres']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small fw-semibold mb-1">Fecha Verificación</label>
                            <input type="date" name="fecha_verificacion" class="form-control form-control-sm"
                                   value="<?= $tieneFicha && !empty($s['fecha_verificacion']) ? h((string)$s['fecha_verificacion']) : date('Y-m-d') ?>" required>
                        </div>
                        <div class="col-md-1 d-grid">
                            <button type="submit" class="btn btn-sm btn-petrol" title="Guardar y emitir ficha">
                                <i class="fa-solid fa-check"></i>
                            </button>
                        </div>
                    </form>
                    <form method="POST" class="mt-2 text-end" onsubmit="return confirm('¿Eliminar esta solicitud pendiente?');">
                        <?= csrf_field() ?>
                        <input type="hidden" name="accion" value="eliminar">
                        <input type="hidden" name="sol_id" value="<?= (int)$s['id'] ?>">
                        <button type="submit" class="btn btn-link btn-sm text-danger text-decoration-none p-0">
                            <i class="fa-solid fa-trash me-1"></i>Eliminar solicitud pendiente
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<?php renderFooter(); ?>
