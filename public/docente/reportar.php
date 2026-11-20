<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/layout.php';

$u = require_role(['admin', 'coordinador_apsti', 'coordinador_cont', 'coordinador_enfe', 'coordinador_agro']);
$scope = get_user_program_scope($u);
$pdo = getPDO();

$msg = '';
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $bienId = (int)($_POST['bien_id'] ?? 0);
    $falla = trim((string)($_POST['falla_reportada'] ?? ''));
    $prioridad = strtoupper(trim((string)($_POST['prioridad'] ?? 'ALTA')));
    if (!in_array($prioridad, ['ALTA', 'MEDIA', 'BAJA'], true)) {
        $prioridad = 'ALTA';
    }

    if ($bienId <= 0 || $falla === '') {
        $err = 'Selecciona un equipo y describe la falla presentada.';
    } else {
        $stBien = $pdo->prepare('SELECT * FROM bienes_inventario WHERE id = ? LIMIT 1');
        $stBien->execute([$bienId]);
        $bien = $stBien->fetch();

        if (!$bien) {
            $err = 'El equipo seleccionado no existe.';
        } elseif ($scope !== null && strtoupper((string)$bien['programa']) !== $scope) {
            render_access_denied('No puedes reportar fallas de equipos que pertenecen a otro programa.');
        } else {
            // Bloquear duplicados si ya existe una solicitud abierta/pendiente para el mismo equipo
            $stOpen = $pdo->prepare("SELECT codigo_solicitud FROM solicitudes_mantenimiento WHERE bien_id = ? AND estado_flujo != '3_ATENDIDO_AREA_TECNICA' LIMIT 1");
            $stOpen->execute([$bienId]);
            $abierta = $stOpen->fetch();
            if ($abierta) {
                $err = 'Este equipo ya tiene una solicitud abierta pendiente de cierre (' . $abierta['codigo_solicitud'] . ').';
            } else {
                try {
                    $fotoRuta = procesar_foto_evidencia('foto_falla', 'falla');
                    $nextNum = (int)$pdo->query('SELECT COUNT(*) FROM solicitudes_mantenimiento')->fetchColumn() + 14;
                    $codigoSol = 'SOL-2026-' . str_pad((string)$nextNum, 3, '0', STR_PAD_LEFT);

                    $stIns = $pdo->prepare(
                        "INSERT INTO solicitudes_mantenimiento
                         (codigo_solicitud, bien_id, codigo_bien, descripcion_bien, area_solicitante, usuario_reporta, falla_reportada, prioridad, fecha_reporte, verificado_admin, estado_flujo, resultado_servicio, foto_falla)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0, '1_REPORTADO_AREA', 'PENDIENTE', ?)"
                    );
                    $stIns->execute([
                        $codigoSol,
                        (int)$bien['id'],
                        (string)$bien['codigo_apsti'],
                        (string)$bien['denominacion'],
                        (string)$bien['programa'] . ' - ' . (string)$bien['area_ambiente'],
                        (string)($u['nombres'] ?? 'Coordinador'),
                        $falla,
                        $prioridad,
                        date('Y-m-d H:i:s'),
                        $fotoRuta,
                    ]);
                    $newId = (int)$pdo->lastInsertId();
                    registrar_auditoria($pdo, 'REPORTAR_FALLA', 'solicitudes_mantenimiento', $newId, "Solicitud {$codigoSol} para {$bien['codigo_apsti']}");
                    $msg = "Solicitud {$codigoSol} registrada correctamente. Queda pendiente de verificación por Administración (Paso 2).";
                } catch (Throwable $ex) {
                    $err = $ex->getMessage();
                }
            }
        }
    }
}

// Filtro de programa para el historial (Admin puede elegir Todos/ENFE/CONT/AGRO/APSTI; Coordinador solo su área)
$filtroHist = $scope !== null ? $scope : strtoupper(trim((string)($_GET['programa'] ?? 'TODOS')));
if (!in_array($filtroHist, ['TODOS', 'ENFE', 'CONT', 'AGRO', 'APSTI', 'CAFETIN', 'ADMIN'], true)) {
    $filtroHist = 'TODOS';
}

// Cargar selector de equipos filtrado estrictamente por área cuando es coordinador
if ($scope !== null) {
    $stEq = $pdo->prepare('SELECT id, codigo_apsti, denominacion, area_ambiente FROM bienes_inventario WHERE programa = ? AND requiere_mantenimiento = 1 ORDER BY codigo_apsti ASC');
    $stEq->execute([$scope]);
    $equipos = $stEq->fetchAll();
} else {
    $equipos = $pdo->query('SELECT id, codigo_apsti, denominacion, area_ambiente, programa FROM bienes_inventario WHERE requiere_mantenimiento = 1 ORDER BY programa ASC, codigo_apsti ASC')->fetchAll();
}

// Historial con JOIN a bienes_inventario para mostrar todos los campos oficiales del formato institucional
$sqlHist = "SELECT s.*,
                   b.correlativo, b.codigo_apsti, b.codigo_interno, b.denominacion AS bien_denominacion,
                   b.marca, b.modelo, b.tipo_material, b.color, b.serie, b.dimensiones,
                   b.estado_conservacion, b.programa AS bien_programa
            FROM solicitudes_mantenimiento s
            LEFT JOIN bienes_inventario b ON b.id = s.bien_id";
$histParams = [];
if ($filtroHist !== 'TODOS') {
    $sqlHist .= " WHERE b.programa = ?";
    $histParams[] = $filtroHist;
}
$sqlHist .= " ORDER BY s.id DESC";
$stHist = $pdo->prepare($sqlHist);
$stHist->execute($histParams);
$historial = $stHist->fetchAll();

renderHeader('Paso 1: Reportar Falla de Equipo', 'reportar');
?>

<div class="mb-3">
    <h4 class="fw-bold mb-0">Paso 1: Reporte de Falla o Solicitud de Mantenimiento</h4>
    <div class="small text-muted">
        <?= $scope ? h("Mostrando exclusivamente equipos y solicitudes del área {$scope}.") : 'Registro institucional de incidencias técnicas para verificación administrativa.' ?>
    </div>
</div>

<?php if ($msg): ?>
    <div class="alert alert-success alert-dismissible fade show py-2 small"><?= h($msg) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>
<?php if ($err): ?>
    <div class="alert alert-danger alert-dismissible fade show py-2 small"><?= h($err) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>

<div class="card-clean p-3 p-md-4 mb-4">
    <h6 class="fw-bold mb-3"><i class="fa-solid fa-triangle-exclamation text-warning me-2"></i>Nueva Solicitud de Mantenimiento</h6>
    <form method="POST" enctype="multipart/form-data" class="row g-3">
        <?= csrf_field() ?>
        <div class="col-md-6">
            <label class="form-label small fw-semibold">Equipo Patrimonial <span class="text-danger">*</span></label>
            <select name="bien_id" class="form-select" required>
                <option value="">-- Seleccione un equipo --</option>
                <?php foreach ($equipos as $e): ?>
                    <option value="<?= (int)$e['id'] ?>">
                        <?= h($e['codigo_apsti']) ?> — <?= h($e['denominacion']) ?> (<?= h($e['area_ambiente']) ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label small fw-semibold">Prioridad <span class="text-danger">*</span></label>
            <select name="prioridad" class="form-select" required>
                <option value="ALTA">Alta (Urgente)</option>
                <option value="MEDIA">Media (Programable)</option>
                <option value="BAJA">Baja (Preventivo)</option>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label small fw-semibold">Foto Evidencia (Opcional, máx. 3 MB)</label>
            <input type="file" name="foto_falla" class="form-control" accept="image/jpeg,image/png,image/webp">
        </div>
        <div class="col-12">
            <label class="form-label small fw-semibold">Descripción detallada de la falla <span class="text-danger">*</span></label>
            <textarea name="falla_reportada" rows="2" class="form-control" placeholder="Describe el problema observado en el equipo..." required></textarea>
        </div>
        <div class="col-12 d-flex justify-content-end">
            <button type="submit" class="btn btn-emerald px-4">
                <i class="fa-solid fa-paper-plane me-1"></i>Enviar Solicitud a Administración
            </button>
        </div>
    </form>
</div>

<div class="card-clean p-3">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-3">
        <h6 class="fw-bold mb-0">Historial de Solicitudes Registradas (Detalle Patrimonial Oficial)</h6>
        <?php if ($scope === null): ?>
            <div class="d-flex flex-wrap gap-1">
                <?php
                $filtrosBtn = [
                    'TODOS' => 'Todos',
                    'ENFE'  => 'Enfermería',
                    'CONT'  => 'Contabilidad',
                    'AGRO'  => 'Agropecuaria',
                    'APSTI' => 'APSTI',
                ];
                foreach ($filtrosBtn as $cod => $lbl):
                ?>
                    <a href="<?= h(baseUrl('docente/reportar.php?programa=' . $cod)) ?>"
                       class="btn btn-sm <?= $filtroHist === $cod ? 'btn-emerald fw-bold' : 'btn-outline-secondary' ?>">
                        <?= h($lbl) ?>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="table-responsive" style="overflow-x:auto;">
        <table class="table table-sm table-bordered table-hover align-middle small mb-0">
            <thead>
                <tr class="text-center">
                    <th rowspan="2">N.°</th>
                    <th rowspan="2" class="nowrap-col">Solicitud</th>
                    <th colspan="11">DESCRIPCIÓN DE LOS BIENES (INVENTARIO OFICIAL)</th>
                    <th rowspan="2">Falla Reportada</th>
                    <th rowspan="2" class="nowrap-col">Etapa / N.º Ficha</th>
                    <th rowspan="2" class="nowrap-col">Doc.</th>
                </tr>
                <tr class="text-center">
                    <th class="nowrap-col">Cód. Patrimonial</th>
                    <th class="nowrap-col">Cód. Interno</th>
                    <th>Denominación</th>
                    <th>Marca</th>
                    <th>Modelo</th>
                    <th>Tipo</th>
                    <th>Color</th>
                    <th>Serie</th>
                    <th>Dimensiones</th>
                    <th class="nowrap-col">Situación</th>
                    <th class="nowrap-col">Est. Cons.</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($historial)): ?>
                    <tr><td colspan="16" class="text-center text-muted py-4">No existen solicitudes registradas para este filtro.</td></tr>
                <?php else: ?>
                    <?php $idx = 1; foreach ($historial as $s):
                        $etapa = (string)($s['estado_flujo'] ?? '1_REPORTADO_AREA');
                        $badgeEtapa = match ($etapa) {
                            '1_REPORTADO_AREA' => '<span class="badge bg-warning text-dark">Paso 1: Reportado</span>',
                            '2_VERIFICADO_CON_FICHA' => '<span class="badge bg-info text-dark">Paso 2: Verificado</span>',
                            '3_ATENDIDO_AREA_TECNICA' => '<span class="badge bg-success">Paso 3: Atendido</span>',
                            default => '<span class="badge bg-secondary">En proceso</span>',
                        };
                        $ficha = trim((string)($s['numero_ficha'] ?? ''));
                        $estC = strtoupper(trim((string)($s['estado_conservacion'] ?? 'B')));
                        $sit = $estC === 'M' ? 'D' : 'U';
                    ?>
                        <tr>
                            <td class="text-center"><?= $idx++ ?></td>
                            <td class="nowrap-col fw-bold">
                                <?= h($s['codigo_solicitud']) ?>
                                <div class="text-muted" style="font-size:.7rem;"><?= h(fmt_fecha($s['fecha_reporte'] ?? null)) ?></div>
                            </td>
                            <td class="nowrap-col text-center"><?= h(clean_tech_value($s['correlativo'] ?? '')) ?></td>
                            <td class="nowrap-col fw-bold text-primary"><?= h(clean_tech_value($s['codigo_bien'] ?? $s['codigo_apsti'] ?? '')) ?></td>
                            <td class="fw-semibold"><?= h(clean_tech_value($s['bien_denominacion'] ?? $s['descripcion_bien'] ?? '')) ?></td>
                            <td><?= h(clean_tech_value($s['marca'] ?? '')) ?></td>
                            <td><?= h(clean_tech_value($s['modelo'] ?? '')) ?></td>
                            <td><?= h(clean_tech_value($s['tipo_material'] ?? '')) ?></td>
                            <td><?= h(clean_tech_value($s['color'] ?? '')) ?></td>
                            <td><?= h(clean_tech_value($s['serie'] ?? '')) ?></td>
                            <td><?= h(clean_tech_value($s['dimensiones'] ?? '')) ?></td>
                            <td class="text-center fw-bold"><?= h($sit) ?></td>
                            <td class="text-center fw-bold"><?= h($estC) ?></td>
                            <td style="min-width:170px;">
                                <?= h($s['falla_reportada']) ?>
                                <?php if (!empty($s['foto_falla'])): ?>
                                    <div class="mt-1"><a href="<?= h(baseUrl((string)$s['foto_falla'])) ?>" target="_blank" class="badge bg-light text-primary border text-decoration-none"><i class="fa-solid fa-image me-1"></i>Evidencia</a></div>
                                <?php endif; ?>
                            </td>
                            <td class="nowrap-col">
                                <?= $badgeEtapa ?>
                                <div class="mt-1"><?= $ficha !== '' ? '<span class="fw-bold text-danger">' . h($ficha) . '</span>' : '<span class="text-muted fst-italic">Pendiente</span>' ?></div>
                            </td>
                            <td class="nowrap-col text-center">
                                <?php if ($ficha !== '' && $etapa !== '1_REPORTADO_AREA'): ?>
                                    <a href="<?= h(baseUrl('admin/ficha_mantenimiento.php?sol_id=' . (int)$s['id'])) ?>" target="_blank" class="btn btn-sm btn-outline-success" title="Ver Ficha PDF">
                                        <i class="fa-solid fa-file-pdf"></i>
                                    </a>
                                <?php else: ?>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" disabled title="Pendiente de verificación administrativa">
                                        <i class="fa-solid fa-lock"></i>
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php renderFooter(); ?>
