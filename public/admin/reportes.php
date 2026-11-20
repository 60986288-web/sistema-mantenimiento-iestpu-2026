<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/layout.php';

$u = require_role(['admin', 'coordinador_apsti', 'coordinador_cont', 'coordinador_enfe', 'coordinador_agro']);
$scope = get_user_program_scope($u);
$pdo = getDB();

$whereSql = $scope !== null ? ' WHERE programa = ? ' : '';
$params = $scope !== null ? [$scope] : [];

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

// Exportación Excel (.xls) segura
if (isset($_GET['export']) && $_GET['export'] === 'excel') {
    header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
    header('Content-Disposition: attachment; filename="Reporte_Consolidado_IESTPU_' . date('Ymd') . '.xls"');
    echo "\xEF\xBB\xBF";
    echo '<table border="1">';
    echo '<tr><th colspan="7">REPORTE CONSOLIDADO DE MANTENIMIENTO — IESTP UTCUBAMBA (' . date('d/m/Y') . ')</th></tr>';
    echo '<tr><th>Programa / Área</th><th>Total Bienes (BD)</th><th>Sujetos a Mantenimiento</th><th>Mobiliario (No Aplica)</th><th>Servicio Completo</th><th>Servicio Pendiente</th><th>% Cumplimiento (sobre Mant.)</th></tr>';
    foreach ($resumen as $r) {
        $cm = (int)$r['con_mantenimiento'];
        $sc = (int)$r['servicio_completo'];
        $pct = $cm > 0 ? round(($sc / $cm) * 100, 1) : 0.0;
        echo '<tr>';
        echo '<td>' . h($r['programa']) . '</td>';
        echo '<td>' . (int)$r['total_bienes'] . '</td>';
        echo '<td>' . $cm . '</td>';
        echo '<td>' . (int)$r['sin_mantenimiento'] . '</td>';
        echo '<td>' . $sc . '</td>';
        echo '<td>' . (int)$r['servicio_pendiente'] . '</td>';
        echo '<td>' . number_format($pct, 1) . '%</td>';
        echo '</tr>';
    }
    echo '<tr><th>TOTAL</th><th>' . $totBienes . '</th><th>' . $totConMant . '</th><th>' . $totSinMant . '</th><th>' . $totCompletos . '</th><th>' . $totPendientes . '</th><th>' . number_format($porcSobreMantenimiento, 1) . '%</th></tr>';
    echo '</table>';
    exit;
}

renderHeader($scope ? "Reportes Consolidados — {$scope}" : 'Reportes Consolidados (PDF / Excel)', 'reportes');
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-4">
    <div>
        <h4 class="fw-bold mb-1">Consolidado de Mantenimiento por Programa y Área</h4>
        <div class="small text-muted">
            Cálculo auditado sobre bienes activos en base de datos (<?= $totBienes ?> registrados<?= $scope ? " en {$scope}" : '; padrón histórico Excel referencial: 3,475' ?>).
        </div>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= h(baseUrl('admin/reportes.php?export=excel')) ?>" class="btn btn-sm btn-emerald">
            <i class="fa-solid fa-file-excel me-1"></i>Descargar Excel (.xls)
        </a>
        <button type="button" class="btn btn-sm btn-petrol" onclick="window.print()">
            <i class="fa-solid fa-print me-1"></i>Imprimir / PDF
        </button>
    </div>
</div>

<!-- KPIs con denominadores explícitos -->
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="kpi-card">
            <div class="small text-muted fw-semibold">CUMPLIMIENTO MANTENIMIENTO</div>
            <div class="fs-2 fw-bold text-success mt-1"><?= number_format($porcSobreMantenimiento, 1) ?>%</div>
            <div class="small text-muted"><?= $totCompletos ?> completos / <?= $totConMant ?> sujetos a mant.</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="kpi-card" style="border-left-color:#0ea5e9;">
            <div class="small text-muted fw-semibold">COBERTURA SOBRE TOTAL BD</div>
            <div class="fs-2 fw-bold mt-1"><?= number_format($porcSobreTotalBD, 1) ?>%</div>
            <div class="small text-muted"><?= $totCompletos ?> completos / <?= $totBienes ?> bienes en BD</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="kpi-card" style="border-left-color:#f59e0b;">
            <div class="small text-muted fw-semibold">PENDIENTES DE SERVICIO</div>
            <div class="fs-2 fw-bold text-warning mt-1"><?= $totPendientes ?></div>
            <div class="small text-muted">Equipos con mantenimiento pendiente</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="kpi-card" style="border-left-color:#64748b;">
            <div class="small text-muted fw-semibold">MOBILIARIO (NO APLICA)</div>
            <div class="fs-2 fw-bold mt-1"><?= $totSinMant ?></div>
            <div class="small text-muted">Excluidos del denominador de mant.</div>
        </div>
    </div>
</div>

<div class="card-clean p-3 p-md-4">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Programa / Área</th>
                    <th class="text-center">Total Bienes (BD)</th>
                    <th class="text-center">Con Mantenimiento</th>
                    <th class="text-center">Mobiliario (No Aplica)</th>
                    <th class="text-center">Servicio Completo</th>
                    <th class="text-center">Servicio Pendiente</th>
                    <th style="width:200px;">% Cumplimiento (sobre Mant.)</th>
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
                                <div class="progress flex-grow-1" style="height:8px;">
                                    <div class="progress-bar bg-success" style="width:<?= $pct ?>%;"></div>
                                </div>
                                <span class="small fw-bold nowrap-col"><?= number_format($pct, 1) ?>%</span>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr class="fw-bold table-secondary">
                    <td>TOTAL CONSOLIDADO</td>
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
    <div class="alert alert-info mt-3 mb-0 small">
        <i class="fa-solid fa-circle-info me-1"></i>
        <strong>Nota metodológica:</strong> El porcentaje de cumplimiento divide únicamente los bienes con <em>Servicio Completo</em> entre los bienes <em>Sujetos a Mantenimiento</em> (<code>requiere_mantenimiento = 1</code>). Los bienes de mobiliario (mesas, sillas, estantes) se contabilizan aparte como <em>No Aplica</em> para evitar distorsiones estadísticas.
    </div>
</div>

<?php renderFooter(); ?>
