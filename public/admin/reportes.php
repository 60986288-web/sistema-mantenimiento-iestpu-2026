<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/layout.php';

$u = require_role(['admin', 'coordinador_apsti', 'coordinador_cont', 'coordinador_enfe', 'coordinador_agro']);
$scope = get_user_program_scope($u);
$pdo = getPDO();

$programasNombres = [
    'TODOS'   => 'TODOS LOS PROGRAMAS Y ÁREAS',
    'ENFE'    => 'ENFERMERÍA TÉCNICA',
    'CONT'    => 'CONTABILIDAD',
    'AGRO'    => 'PRODUCCIÓN AGROPECUARIA',
    'APSTI'   => 'ARQUITECTURA DE PLATAFORMAS Y SERVICIOS DE TI (APSTI)',
    'CAFETIN' => 'MÓDULO CAFETÍN INSTITUCIONAL',
    'ADMIN'   => 'DIRECCIÓN / ADMINISTRACIÓN',
];

// Si es coordinador, su alcance es obligatorio (RBAC)
if ($scope !== null) {
    $progSeleccionado = $scope;
} else {
    $reqProg = strtoupper(trim((string)($_GET['programa'] ?? 'TODOS')));
    $progSeleccionado = array_key_exists($reqProg, $programasNombres) ? $reqProg : 'TODOS';
}

$whereSql = $progSeleccionado !== 'TODOS' ? ' WHERE programa = ? ' : '';
$params = $progSeleccionado !== 'TODOS' ? [$progSeleccionado] : [];

// 1. Resumen consolidado (matemática exacta P0)
$stmt = $pdo->prepare(
    "SELECT programa,
            COUNT(*) AS total_bienes,
            SUM(CASE WHEN requiere_mantenimiento = 1 THEN 1 ELSE 0 END) AS con_mantenimiento,
            SUM(CASE WHEN requiere_mantenimiento = 0 THEN 1 ELSE 0 END) AS sin_mantenimiento,
            SUM(CASE WHEN requiere_mantenimiento = 1 AND estado_servicio = 'Servicio Completo' THEN 1 ELSE 0 END) AS servicio_completo,
            SUM(CASE WHEN requiere_mantenimiento = 1 AND estado_servicio != 'Servicio Completo' THEN 1 ELSE 0 END) AS servicio_pendiente
     FROM bienes_inventario
     {$whereSql}
     GROUP BY programa
     ORDER BY programa ASC"
);
$stmt->execute($params);
$resumen = $stmt->fetchAll();

$totBienes = 0;
$totConMant = 0;
$totSinMant = 0;
$totCompletos = 0;
$totPendientes = 0;
foreach ($resumen as $r) {
    $totBienes += (int)$r['total_bienes'];
    $totConMant += (int)$r['con_mantenimiento'];
    $totSinMant += (int)$r['sin_mantenimiento'];
    $totCompletos += (int)$r['servicio_completo'];
    $totPendientes += (int)$r['servicio_pendiente'];
}
$porcSobreMantenimiento = $totConMant > 0 ? round(($totCompletos / $totConMant) * 100, 1) : 0.0;
$porcSobreTotalBD = $totBienes > 0 ? round(($totCompletos / $totBienes) * 100, 1) : 0.0;

// 2. Detalle oficial de bienes (Formato INVENTARIO GENERAL DE BIENES INSTITUCIONAL)
$stDet = $pdo->prepare("SELECT * FROM bienes_inventario {$whereSql} ORDER BY programa ASC, id ASC");
$stDet->execute($params);
$bienesDetalle = $stDet->fetchAll();

$nombreAreaReporte = $programasNombres[$progSeleccionado] ?? $progSeleccionado;

// 3. Exportación Excel (.xls) con el formato oficial exacto de la institución
if (isset($_GET['export']) && $_GET['export'] === 'excel') {
    $slug = strtolower($progSeleccionado);
    header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
    header('Content-Disposition: attachment; filename="Inventario_Oficial_IESTPU_' . $slug . '_' . date('Ymd') . '.xls"');
    header('Pragma: no-cache');
    header('Expires: 0');
    echo "\xEF\xBB\xBF";
    echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">';
    echo '<head><meta http-equiv="Content-Type" content="text/html; charset=UTF-8"></head><body>';
    echo '<table border="1" style="border-collapse:collapse;font-family:Arial,sans-serif;font-size:10pt;">';
    echo '<tr><th colspan="12" style="background:#0f3b3c;color:#ffffff;font-size:12pt;padding:8px;">GOBIERNO REGIONAL AMAZONAS — DIRECCIÓN REGIONAL DE EDUCACIÓN AMAZONAS<br>INSTITUTO DE EDUCACIÓN SUPERIOR TECNOLÓGICO PÚBLICO «UTCUBAMBA»</th></tr>';
    echo '<tr><th colspan="12" style="background:#e2e8f0;color:#0f172a;font-size:11pt;padding:6px;">INVENTARIO GENERAL DE BIENES INSTITUCIONAL - 2026 (' . h($nombreAreaReporte) . ')</th></tr>';
    echo '<tr>'
       . '<td colspan="2"><b>NOMBRE DE LA ENTIDAD:</b></td><td colspan="4">I.E.S.T. PÚBLICO UTCUBAMBA</td>'
       . '<td colspan="2"><b>LUGAR / DISTRITO:</b></td><td colspan="4">BAGUA GRANDE — UTCUBAMBA</td></tr>';
    echo '<tr>'
       . '<td colspan="2"><b>CÓDIGO MODULAR:</b></td><td colspan="4">741744</td>'
       . '<td colspan="2"><b>COMITÉ DE INVENTARIOS:</b></td><td colspan="4">Sandoval Dávila Carlos Enrique · Cruz Bobadilla Stalin · Occ Briceño Lluvitza</td></tr>';
    echo '<tr>'
       . '<td colspan="2"><b>ÁREA / PROGRAMA:</b></td><td colspan="4">' . h($nombreAreaReporte) . '</td>'
       . '<td colspan="2"><b>FECHA DE EMISIÓN:</b></td><td colspan="4">' . date('d/m/Y') . '</td></tr>';
    echo '<tr>'
       . '<th rowspan="2" style="background:#cbd5e1;">N.°</th>'
       . '<th colspan="11" style="background:#cbd5e1;text-align:center;">DESCRIPCIÓN DE LOS BIENES</th>'
       . '</tr>';
    echo '<tr style="background:#e2e8f0;">'
       . '<th>CÓDIGO PATRIMONIAL</th>'
       . '<th>CÓDIGO INTERNO</th>'
       . '<th>DENOMINACIÓN</th>'
       . '<th>MARCA</th>'
       . '<th>MODELO</th>'
       . '<th>TIPO</th>'
       . '<th>COLOR</th>'
       . '<th>SERIE</th>'
       . '<th>DIMENSIONES (Medidas)</th>'
       . '<th>SITUACIÓN (1)</th>'
       . '<th>ESTADO DE CONSERVACIÓN (2)</th>'
       . '</tr>';
    $idx = 1;
    foreach ($bienesDetalle as $b) {
        $sit = strtoupper((string)($b['estado_conservacion'] ?? 'B')) === 'M' ? 'D' : 'U';
        echo '<tr>';
        echo '<td style="text-align:center;">' . $idx++ . '</td>';
        echo '<td>' . h(clean_tech_value($b['correlativo'] ?? '')) . '</td>';
        echo '<td>' . h(clean_tech_value($b['codigo_apsti'] ?? $b['codigo_interno'] ?? '')) . '</td>';
        echo '<td>' . h(clean_tech_value($b['denominacion'] ?? '')) . '</td>';
        echo '<td>' . h(clean_tech_value($b['marca'] ?? '')) . '</td>';
        echo '<td>' . h(clean_tech_value($b['modelo'] ?? '')) . '</td>';
        echo '<td>' . h(clean_tech_value($b['tipo_material'] ?? '')) . '</td>';
        echo '<td>' . h(clean_tech_value($b['color'] ?? '')) . '</td>';
        echo '<td>' . h(clean_tech_value($b['serie'] ?? '')) . '</td>';
        echo '<td>' . h(clean_tech_value($b['dimensiones'] ?? '')) . '</td>';
        echo '<td style="text-align:center;">' . h($sit) . '</td>';
        echo '<td style="text-align:center;">' . h(clean_tech_value($b['estado_conservacion'] ?? 'B')) . '</td>';
        echo '</tr>';
    }
    echo '</table></body></html>';
    exit;
}

renderHeader($scope ? "Reportes Oficiales — {$scope}" : 'Reportes Oficiales (PDF / Excel)', 'reportes');
?>

<style>
    .oficial-sheet {
        background: #fff;
        border: 1px solid #cbd5e1;
        border-radius: 12px;
        padding: 18px;
    }
    .oficial-header-tbl { width: 100%; border-collapse: collapse; font-size: .78rem; margin-bottom: 10px; }
    .oficial-header-tbl td { border: 1px solid #94a3b8; padding: 4px 8px; }
    .oficial-bienes-tbl { width: 100%; border-collapse: collapse; font-size: .76rem; }
    .oficial-bienes-tbl th, .oficial-bienes-tbl td { border: 1px solid #64748b; padding: 5px 6px; vertical-align: middle; }
    .oficial-bienes-tbl thead th { background: #0f3b3c !important; color: #fff !important; text-align: center; font-size: .72rem; }
    body.dark-mode .oficial-sheet { background: #13222b; border-color: #243b49; }
    body.dark-mode .oficial-header-tbl td { border-color: #2d4857; color: #e2e8f0; }
    @media print {
        .app-sidebar, .app-topbar, .no-print { display: none !important; }
        .app-content { padding: 0 !important; }
        .oficial-sheet { border: none !important; padding: 0 !important; box-shadow: none !important; }
        body { background: #fff !important; color: #000 !important; }
    }
</style>

<!-- Encabezado y botones de exportación -->
<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-3 no-print">
    <div>
        <h4 class="fw-bold mb-1">Reportes Oficiales de Inventario y Mantenimiento (PDF / Excel)</h4>
        <div class="small text-muted">
            Filtro activo: <strong><?= h($nombreAreaReporte) ?></strong> — <?= count($bienesDetalle) ?> bienes mostrados.
        </div>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a href="<?= h(baseUrl('admin/reportes.php?programa=' . urlencode($progSeleccionado) . '&export=excel')) ?>" class="btn btn-sm btn-emerald">
            <i class="fa-solid fa-file-excel me-1"></i>Descargar Excel (.xls)
        </a>
        <button type="button" class="btn btn-sm btn-petrol" onclick="window.print()">
            <i class="fa-solid fa-file-pdf me-1"></i>Imprimir / Guardar PDF
        </button>
    </div>
</div>

<!-- Botones de filtro por programa (Todos, Enfermería, Contabilidad, Agropecuaria, APSTI, Cafetín, Admin) -->
<?php if ($scope === null): ?>
<div class="card-clean p-3 mb-3 no-print">
    <div class="small fw-semibold text-muted mb-2"><i class="fa-solid fa-filter me-1 text-success"></i>Seleccionar Programa / Área para el reporte (Pantalla, PDF y Excel):</div>
    <div class="d-flex flex-wrap gap-2">
        <?php
        $botonesFiltro = [
            'TODOS'   => ['label' => 'Todos', 'icon' => 'fa-layer-group'],
            'ENFE'    => ['label' => 'Enfermería', 'icon' => 'fa-user-nurse'],
            'CONT'    => ['label' => 'Contabilidad', 'icon' => 'fa-calculator'],
            'AGRO'    => ['label' => 'Agropecuaria', 'icon' => 'fa-seedling'],
            'APSTI'   => ['label' => 'APSTI', 'icon' => 'fa-laptop-code'],
            'CAFETIN' => ['label' => 'Cafetín', 'icon' => 'fa-mug-hot'],
            'ADMIN'   => ['label' => 'Dirección / Admin', 'icon' => 'fa-building'],
        ];
        foreach ($botonesFiltro as $cod => $meta):
            $activo = ($progSeleccionado === $cod);
        ?>
            <a href="<?= h(baseUrl('admin/reportes.php?programa=' . $cod)) ?>"
               class="btn btn-sm <?= $activo ? 'btn-emerald fw-bold' : 'btn-outline-secondary' ?>">
                <i class="fa-solid <?= h($meta['icon']) ?> me-1"></i><?= h($meta['label']) ?>
            </a>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- KPIs y Resumen Consolidado (no-print opcional o visible siempre) -->
<div class="row g-3 mb-3 no-print">
    <div class="col-md-3">
        <div class="kpi-card">
            <div class="small text-muted fw-semibold">CUMPLIMIENTO MANTENIMIENTO</div>
            <div class="fs-3 fw-bold text-success mt-1"><?= number_format($porcSobreMantenimiento, 1) ?>%</div>
            <div class="small text-muted"><?= $totCompletos ?> completos / <?= $totConMant ?> sujetos a mant.</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="kpi-card" style="border-left-color:#0ea5e9;">
            <div class="small text-muted fw-semibold">TOTAL BIENES EN FILTRO</div>
            <div class="fs-3 fw-bold mt-1"><?= $totBienes ?></div>
            <div class="small text-muted">Cobertura BD: <?= number_format($porcSobreTotalBD, 1) ?>%</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="kpi-card" style="border-left-color:#f59e0b;">
            <div class="small text-muted fw-semibold">PENDIENTES DE SERVICIO</div>
            <div class="fs-3 fw-bold text-warning mt-1"><?= $totPendientes ?></div>
            <div class="small text-muted">Equipos con mantenimiento pendiente</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="kpi-card" style="border-left-color:#64748b;">
            <div class="small text-muted fw-semibold">MOBILIARIO (NO APLICA)</div>
            <div class="fs-3 fw-bold mt-1"><?= $totSinMant ?></div>
            <div class="small text-muted">Excluidos del denominador de mant.</div>
        </div>
    </div>
</div>

<!-- Tabla Resumen Consolidado -->
<div class="card-clean p-3 mb-4 no-print">
    <h6 class="fw-bold mb-2"><i class="fa-solid fa-chart-bar text-success me-2"></i>Resumen Consolidado de Cumplimiento</h6>
    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0 small">
            <thead>
                <tr>
                    <th>Programa / Área</th>
                    <th class="text-center">Total Bienes</th>
                    <th class="text-center">Con Mantenimiento</th>
                    <th class="text-center">Mobiliario (No Aplica)</th>
                    <th class="text-center">Servicio Completo</th>
                    <th class="text-center">Servicio Pendiente</th>
                    <th style="width:180px;">% Cumplimiento</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($resumen as $r):
                    $cm = (int)$r['con_mantenimiento'];
                    $sc = (int)$r['servicio_completo'];
                    $pct = $cm > 0 ? round(($sc / $cm) * 100, 1) : 0.0;
                ?>
                    <tr>
                        <td class="fw-bold"><?= h($r['programa']) ?></td>
                        <td class="text-center"><?= (int)$r['total_bienes'] ?></td>
                        <td class="text-center fw-semibold"><?= $cm ?></td>
                        <td class="text-center text-muted"><?= (int)$r['sin_mantenimiento'] ?></td>
                        <td class="text-center"><span class="badge bg-success"><?= $sc ?></span></td>
                        <td class="text-center"><span class="badge bg-warning text-dark"><?= (int)$r['servicio_pendiente'] ?></span></td>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <div class="progress flex-grow-1" style="height:7px;">
                                    <div class="progress-bar bg-success" style="width:<?= $pct ?>%;"></div>
                                </div>
                                <span class="fw-bold nowrap-col"><?= number_format($pct, 1) ?>%</span>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr class="fw-bold table-secondary">
                    <td>TOTAL</td>
                    <td class="text-center"><?= $totBienes ?></td>
                    <td class="text-center"><?= $totConMant ?></td>
                    <td class="text-center"><?= $totSinMant ?></td>
                    <td class="text-center"><?= $totCompletos ?></td>
                    <td class="text-center"><?= $totPendientes ?></td>
                    <td><?= number_format($porcSobreMantenimiento, 1) ?>% (<?= $totCompletos ?>/<?= $totConMant ?>)</td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

<!-- FORMATO OFICIAL INVENTARIO GENERAL DE BIENES INSTITUCIONAL (Igual a la plantilla oficial) -->
<div class="oficial-sheet">
    <div class="d-flex justify-content-between align-items-center border-bottom pb-2 mb-2">
        <img src="<?= h(baseUrl('assets/img/logo.png')) ?>" alt="Escudo IESTPU" style="height:56px;object-fit:contain;">
        <div class="text-center px-2">
            <div class="fw-bold" style="font-size:.9rem;">GOBIERNO REGIONAL AMAZONAS</div>
            <div class="small fw-semibold">DIRECCIÓN REGIONAL DE EDUCACIÓN AMAZONAS</div>
            <div class="small fw-bold">INSTITUTO DE EDUCACIÓN SUPERIOR TECNOLÓGICO PÚBLICO «UTCUBAMBA»</div>
        </div>
        <img src="<?= h(baseUrl('assets/img/logo.png')) ?>" alt="Escudo IESTPU" style="height:56px;object-fit:contain;">
    </div>

    <div class="text-center fw-bold py-1 mb-2 border border-dark bg-light text-dark" style="font-size:.86rem;">
        INVENTARIO GENERAL DE BIENES INSTITUCIONAL - 2026 · <?= h($nombreAreaReporte) ?>
    </div>

    <table class="oficial-header-tbl">
        <tr>
            <td style="width:18%;"><strong>NOMBRE DE LA ENTIDAD:</strong></td>
            <td style="width:36%;">INSTITUTO DE EDUCACIÓN SUPERIOR TECNOLÓGICO PÚBLICO UTCUBAMBA</td>
            <td style="width:16%;"><strong>LUGAR / DISTRITO:</strong></td>
            <td style="width:30%;">BAGUA GRANDE — PROV. UTCUBAMBA</td>
        </tr>
        <tr>
            <td><strong>CÓDIGO MODULAR:</strong></td>
            <td>741744</td>
            <td><strong>COMITÉ DE INVENTARIOS:</strong></td>
            <td>Sandoval Dávila Carlos Enrique · Cruz Bobadilla Stalin · Occ Briceño Lluvitza</td>
        </tr>
        <tr>
            <td><strong>ÁREA / PROGRAMA:</strong></td>
            <td><strong><?= h($nombreAreaReporte) ?></strong></td>
            <td><strong>FECHA DE EMISIÓN:</strong></td>
            <td><?= date('d/m/Y') ?> (<?= count($bienesDetalle) ?> registros)</td>
        </tr>
    </table>

    <div class="table-responsive">
        <table class="oficial-bienes-tbl table table-sm table-hover mb-0">
            <thead>
                <tr>
                    <th rowspan="2" style="width:36px;">N.°</th>
                    <th colspan="11">DESCRIPCIÓN DE LOS BIENES</th>
                </tr>
                <tr>
                    <th class="nowrap-col">CÓDIGO PATRIMONIAL</th>
                    <th class="nowrap-col">CÓDIGO INTERNO</th>
                    <th>DENOMINACIÓN</th>
                    <th>MARCA</th>
                    <th>MODELO</th>
                    <th>TIPO</th>
                    <th>COLOR</th>
                    <th>SERIE</th>
                    <th>DIMENSIONES (Medidas)</th>
                    <th class="nowrap-col">SITUACIÓN (1)</th>
                    <th class="nowrap-col">ESTADO DE CONSERVACIÓN (2)</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($bienesDetalle)): ?>
                    <tr><td colspan="12" class="text-center text-muted py-3">No hay bienes registrados en este programa.</td></tr>
                <?php else: ?>
                    <?php $n = 1; foreach ($bienesDetalle as $b):
                        $estadoC = strtoupper(trim((string)($b['estado_conservacion'] ?? 'B')));
                        $situacion = $estadoC === 'M' ? 'D' : 'U';
                    ?>
                        <tr>
                            <td class="text-center"><?= $n++ ?></td>
                            <td class="nowrap-col"><?= h(clean_tech_value($b['correlativo'] ?? '')) ?></td>
                            <td class="nowrap-col fw-bold text-primary"><?= h(clean_tech_value($b['codigo_apsti'] ?? $b['codigo_interno'] ?? '')) ?></td>
                            <td class="fw-semibold"><?= h(clean_tech_value($b['denominacion'] ?? '')) ?></td>
                            <td><?= h(clean_tech_value($b['marca'] ?? '')) ?></td>
                            <td><?= h(clean_tech_value($b['modelo'] ?? '')) ?></td>
                            <td><?= h(clean_tech_value($b['tipo_material'] ?? '')) ?></td>
                            <td><?= h(clean_tech_value($b['color'] ?? '')) ?></td>
                            <td><?= h(clean_tech_value($b['serie'] ?? '')) ?></td>
                            <td><?= h(clean_tech_value($b['dimensiones'] ?? '')) ?></td>
                            <td class="text-center fw-bold"><?= h($situacion) ?></td>
                            <td class="text-center fw-bold"><?= h($estadoC) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <div class="small text-muted mt-2">
        <strong>Leyenda:</strong> (1) Situación: <code>U</code> = En Uso, <code>D</code> = En Desuso. (2) Estado de Conservación: <code>N</code> = Nuevo, <code>MB</code> = Muy Bueno, <code>B</code> = Bueno, <code>R</code> = Regular, <code>M</code> = Malo.
    </div>
</div>

<?php renderFooter(); ?>
