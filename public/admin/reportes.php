<?php
require_once dirname(__DIR__, 2) . '/includes/db.php';
require_once dirname(__DIR__, 2) . '/includes/layout.php';
$u = requireLogin();
$pdo = getPDO();

$tab = $_GET['tab'] ?? 'consolidado'; // consolidado | inventario | fichas | vencimientos
$prog = $_GET['programa'] ?? '';
$est = $_GET['estado_servicio'] ?? '';
$q = trim($_GET['q'] ?? '');

// Cargar universo completo de bienes y solicitudes
$allBienes = $pdo->query("SELECT * FROM bienes_inventario ORDER BY programa ASC, id ASC")->fetchAll();
$allSolicitudes = $pdo->query("SELECT * FROM solicitudes_mantenimiento ORDER BY id DESC")->fetchAll();

// Aplicar filtros al listado detallado
$rows = array_values(array_filter($allBienes, function($r) use ($prog, $est, $q) {
    if ($prog !== '' && ($r['programa'] ?? '') !== $prog) return false;
    if ($est !== '' && ($r['estado_servicio'] ?? '') !== $est) return false;
    if ($q !== '') {
        $hay = strtolower(($r['correlativo'] ?? '') . ' ' . ($r['codigo_apsti'] ?? '') . ' ' . ($r['denominacion'] ?? '') . ' ' . ($r['marca'] ?? '') . ' ' . ($r['area_ambiente'] ?? '') . ' ' . ($r['diagnostico'] ?? ''));
        if (strpos($hay, strtolower($q)) === false) return false;
    }
    return true;
}));

// Cálculos gerenciales y semáforos en vivo
$hoy = new DateTimeImmutable(date('Y-m-d'));
$totalBienes = count($allBienes);
$conMant = 0;
$completos = 0;
$pendientes = 0;
$vencidos = 0;
$porVencer = 0;
$enRegla = 0;
$porPrograma = [];
$listaVencimientos = [];

foreach ($allBienes as $b) {
    $pKey = $b['programa'] ?: 'OTROS';
    if (!isset($porPrograma[$pKey])) {
        $porPrograma[$pKey] = ['total' => 0, 'mant' => 0, 'completos' => 0, 'pendientes' => 0, 'vencidos' => 0];
    }
    $porPrograma[$pKey]['total']++;
    $reqM = (int)($b['requiere_mantenimiento'] ?? 1);
    if ($reqM === 1) {
        $conMant++;
        $porPrograma[$pKey]['mant']++;
        $lim = DateTimeImmutable::createFromFormat('Y-m-d', (string)($b['fecha_limite_mantenimiento'] ?? '')) ?: $hoy;
        $diff = (int)$hoy->diff($lim)->format('%r%a');
        if ($diff < 0) {
            $vencidos++;
            $porPrograma[$pKey]['vencidos']++;
            $b['_dias'] = $diff;
            $b['_alerta'] = 'VENCIDO';
            $listaVencimientos[] = $b;
        } elseif ($diff <= 15) {
            $porVencer++;
            $b['_dias'] = $diff;
            $b['_alerta'] = 'POR VENCER';
            $listaVencimientos[] = $b;
        } else {
            $enRegla++;
        }
    }
    if (($b['estado_servicio'] ?? '') === 'Servicio Completo') {
        $completos++;
        $porPrograma[$pKey]['completos']++;
    } else {
        $pendientes++;
        $porPrograma[$pKey]['pendientes']++;
    }
}

$pctCumplimiento = $totalBienes > 0 ? round(($completos / $totalBienes) * 100, 1) : 100;
$totalSol = count($allSolicitudes);
$solVerificadas = count(array_filter($allSolicitudes, fn($s) => (int)($s['verificado_admin'] ?? 0) === 1));

// Exportaciones Excel (.xls) y CSV según pestaña activa
if (($_GET['export'] ?? '') === 'xls') {
    if ($tab === 'consolidado') {
        $xlsRows = [];
        foreach ($porPrograma as $pName => $stP) {
            $pctP = $stP['total'] > 0 ? round(($stP['completos'] / $stP['total']) * 100, 1) . ' %' : '0 %';
            $xlsRows[] = [$pName, $stP['total'], $stP['mant'], $stP['completos'], $stP['pendientes'], $stP['vencidos'], $pctP];
        }
        $xlsRows[] = ['TOTAL CONSOLIDADO', $totalBienes, $conMant, $completos, $pendientes, $vencidos, $pctCumplimiento . ' %'];
        exportarTablaXLS(
            'Reporte_Consolidado_Gerencial_IESTPU_2026',
            'REPORTE CONSOLIDADO GERENCIAL POR PROGRAMA Y ÁREA — IESTP UTCUBAMBA 2026',
            ['Programa / Área', 'Total Bienes', 'Requieren Mant.', 'Servicio Completo', 'Pendientes', 'Alertas Vencidas', '% Cumplimiento'],
            $xlsRows
        );
    } elseif ($tab === 'fichas') {
        $xlsRows = [];
        foreach ($allSolicitudes as $s) {
            $xlsRows[] = [
                $s['codigo_solicitud'], $s['numero_ficha'] ?: 'Pendiente', $s['tipo_ficha'],
                $s['codigo_bien'], $s['descripcion_bien'], $s['area_solicitante'],
                $s['falla_reportada'], $s['tecnico_asignado'], $s['estado_flujo'], $s['resultado_servicio']
            ];
        }
        exportarTablaXLS(
            'Reporte_Solicitudes_y_Fichas_IESTPU_2026',
            'REPORTE OFICIAL DE SOLICITUDES, VERIFICACIONES Y FICHAS DE MANTENIMIENTO',
            ['Código Solicitud', 'N.º Ficha', 'Tipo de Ficha', 'Código Bien', 'Descripción', 'Área Solicitante', 'Falla Reportada', 'Técnico Asignado', 'Etapa Flujo', 'Resultado'],
            $xlsRows
        );
    } elseif ($tab === 'vencimientos') {
        $xlsRows = [];
        foreach ($listaVencimientos as $v) {
            $xlsRows[] = [
                $v['correlativo'], $v['codigo_apsti'], $v['denominacion'] . ' ' . $v['marca'],
                $v['programa'], $v['area_ambiente'], $v['fecha_limite_mantenimiento'],
                $v['_alerta'], $v['estado_servicio'], $v['diagnostico']
            ];
        }
        exportarTablaXLS(
            'Reporte_Alertas_Vencimiento_IESTPU_2026',
            'REPORTE DE EQUIPOS CON MANTENIMIENTO VENCIDO O PRÓXIMO A VENCER (SEMÁFORO)',
            ['N.º', 'Código', 'Equipo / Marca', 'Programa', 'Ambiente', 'Fecha Límite', 'Alerta Semáforo', 'Estado Servicio', 'Diagnóstico'],
            $xlsRows
        );
    } else {
        $xlsRows = [];
        foreach ($rows as $r) {
            $xlsRows[] = [
                $r['correlativo'], $r['codigo_apsti'], $r['codigo_interno'],
                $r['denominacion'], $r['marca'], $r['programa'], $r['area_ambiente'],
                $r['responsable'], $r['fecha_compra'], $r['estado_conservacion'],
                $r['fecha_limite_mantenimiento'], $r['estado_servicio'], $r['diagnostico']
            ];
        }
        exportarTablaXLS(
            'Reporte_Detallado_Bienes_' . ($prog ?: 'IESTPU_2026'),
            'REPORTE DETALLADO DE INVENTARIO Y MANTENIMIENTO — FILTRO: ' . ($prog ?: 'TODOS'),
            ['N.º', 'Código APSTI', 'Código Interno', 'Denominación', 'Marca', 'Programa', 'Área / Ambiente', 'Responsable', 'Fecha Compra', 'Conservación', 'Fecha Límite', 'Estado Servicio', 'Diagnóstico'],
            $xlsRows
        );
    }
}

if (($_GET['export'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="reporte_iestpu_' . $tab . '_' . ($prog ?: 'general') . '.csv"');
    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
    fputcsv($out, ['Correlativo', 'Codigo_APSTI', 'Denominacion', 'Marca', 'Programa', 'Area_Ambiente', 'Estado_Conservacion', 'Fecha_Limite', 'Estado_Servicio', 'Diagnostico']);
    foreach ($rows as $r) {
        fputcsv($out, [$r['correlativo'], $r['codigo_apsti'], $r['denominacion'], $r['marca'], $r['programa'], $r['area_ambiente'], $r['estado_conservacion'], $r['fecha_limite_mantenimiento'], $r['estado_servicio'], $r['diagnostico']]);
    }
    fclose($out);
    exit;
}

renderHeader('Centro Gerencial de Reportes (PDF y Excel)', 'reportes');
?>
<style>
    .rep-kpi { border-radius: 12px; padding: 14px 16px; border-left: 5px solid #1d4ed8; }
    .rep-tab-btn { border-radius: 10px; font-weight: 600; font-size: 0.84rem; padding: 8px 14px; }
    .prog-bar-wrap { height: 10px; background: #e2e8f0; border-radius: 6px; overflow: hidden; min-width: 90px; }
    .prog-bar-fill { height: 100%; border-radius: 6px; }
    .print-firmas-reporte { display: none; }
    @media print {
        .print-firmas-reporte { display: grid !important; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-top: 32px; text-align: center; font-size: 9pt; }
        .prog-bar-fill { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    }
</style>

<!-- CABECERA Y ACCIONES DE EXPORTACIÓN -->
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2 no-print">
    <div>
        <h4 class="fw-bold mb-0"><i class="fa-solid fa-chart-pie text-primary me-1"></i> Centro Gerencial de Reportes Institucionales (PDF y Excel)</h4>
        <small class="text-secondary">Generación inmediata de reportes consolidados, inventario detallado, historial de fichas y alertas de semáforo preventivo</small>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <button onclick="window.print()" class="btn btn-danger btn-sm fw-bold shadow-sm"><i class="fa-solid fa-file-pdf me-1"></i> Imprimir / Exportar PDF Oficial</button>
        <a href="reportes.php?tab=<?= urlencode($tab) ?>&programa=<?= urlencode($prog) ?>&estado_servicio=<?= urlencode($est) ?>&q=<?= urlencode($q) ?>&export=xls" class="btn btn-success btn-sm fw-bold shadow-sm"><i class="fa-solid fa-file-excel me-1"></i> Descargar Excel Formateado (.xls)</a>
        <a href="reportes.php?tab=<?= urlencode($tab) ?>&programa=<?= urlencode($prog) ?>&estado_servicio=<?= urlencode($est) ?>&q=<?= urlencode($q) ?>&export=csv" class="btn btn-outline-success btn-sm fw-bold">CSV</a>
    </div>
</div>

<!-- TARJETAS KPI GERENCIALES -->
<div class="row g-3 mb-3">
    <div class="col-6 col-lg-3">
        <div class="card rep-kpi shadow-sm h-100" style="border-left-color:#1d4ed8;">
            <div class="small text-secondary fw-bold">BIENES INVENTARIADOS</div>
            <div class="fs-3 fw-bold text-primary"><?= $totalBienes ?> <span class="fs-6 text-muted fw-normal">/ 3,475 Excel</span></div>
            <div class="small text-muted"><?= $conMant ?> equipos sujetos a mantenimiento</div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card rep-kpi shadow-sm h-100" style="border-left-color:#15803d;">
            <div class="small text-secondary fw-bold">CUMPLIMIENTO DE SERVICIO</div>
            <div class="fs-3 fw-bold text-success"><?= $pctCumplimiento ?>%</div>
            <div class="small text-muted"><?= $completos ?> completos · <?= $pendientes ?> pendientes</div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card rep-kpi shadow-sm h-100" style="border-left-color:#d97706;">
            <div class="small text-secondary fw-bold">ALERTAS DE SEMÁFORO</div>
            <div class="fs-3 fw-bold text-warning"><?= $vencidos + $porVencer ?></div>
            <div class="small text-muted"><span class="text-danger fw-bold"><?= $vencidos ?> vencidos</span> · <?= $porVencer ?> por vencer</div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card rep-kpi shadow-sm h-100" style="border-left-color:#6d1ab7;">
            <div class="small text-secondary fw-bold">FICHAS Y SOLICITUDES</div>
            <div class="fs-3 fw-bold" style="color:#6d1ab7;"><?= $totalSol ?></div>
            <div class="small text-muted"><?= $solVerificadas ?> verificadas con Ficha Oficial</div>
        </div>
    </div>
</div>

<!-- PESTAÑAS DE TIPOS DE REPORTE -->
<div class="card p-2 mb-3 shadow-sm no-print">
    <div class="d-flex flex-wrap gap-2">
        <a href="reportes.php?tab=consolidado" class="btn rep-tab-btn <?= $tab==='consolidado'?'btn-primary':'btn-outline-primary' ?>">
            <i class="fa-solid fa-layer-group me-1"></i> 1. Consolidado por Programa y Área
        </a>
        <a href="reportes.php?tab=inventario" class="btn rep-tab-btn <?= $tab==='inventario'?'btn-primary':'btn-outline-primary' ?>">
            <i class="fa-solid fa-table-list me-1"></i> 2. Inventario Detallado (Filtros)
        </a>
        <a href="reportes.php?tab=fichas" class="btn rep-tab-btn <?= $tab==='fichas'?'btn-primary':'btn-outline-primary' ?>">
            <i class="fa-solid fa-file-signature me-1"></i> 3. Historial de Fichas Emitidas (<?= $totalSol ?>)
        </a>
        <a href="reportes.php?tab=vencimientos" class="btn rep-tab-btn <?= $tab==='vencimientos'?'btn-danger':'btn-outline-danger' ?>">
            <i class="fa-solid fa-bell me-1"></i> 4. Alertas Vencidas / Por Vencer (<?= count($listaVencimientos) ?>)
        </a>
    </div>
</div>

<?php if ($tab === 'consolidado'): ?>
<!-- REPORTE 1: CONSOLIDADO GERENCIAL POR PROGRAMA -->
<div class="card p-3 shadow-sm mb-3">
    <div class="d-flex justify-content-between align-items-center border-bottom pb-2 mb-3">
        <div>
            <h6 class="fw-bold mb-0"><i class="fa-solid fa-building-columns text-primary me-1"></i> REPORTE CONSOLIDADO DE OPERATIVIDAD POR PROGRAMA DE ESTUDIOS Y ÁREAS</h6>
            <small class="text-secondary">Resumen cuantitativo para la Dirección General, Jefatura de Unidad Académica y Coordinadores</small>
        </div>
        <span class="badge bg-primary">Emisión: <?= date('d/m/Y H:i') ?></span>
    </div>
    <div class="table-responsive">
        <table class="table table-bordered table-hover align-middle small mb-0">
            <thead class="table-dark">
                <tr>
                    <th>Programa de Estudios / Área</th>
                    <th class="text-center">Total Bienes</th>
                    <th class="text-center">Con Mantenimiento</th>
                    <th class="text-center">Servicio Completo</th>
                    <th class="text-center">Pendientes</th>
                    <th class="text-center">Alertas Vencidas</th>
                    <th>Nivel de Cumplimiento</th>
                    <th class="no-print text-center">Ver Detalle</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($porPrograma as $pName => $stP):
                $pct = $stP['total'] > 0 ? round(($stP['completos'] / $stP['total']) * 100, 1) : 0;
                $barCol = $pct >= 80 ? '#16a34a' : ($pct >= 50 ? '#d97706' : '#dc2626');
            ?>
                <tr>
                    <td class="fw-bold text-primary"><?= htmlspecialchars($pName) ?></td>
                    <td class="text-center fw-bold"><?= $stP['total'] ?></td>
                    <td class="text-center"><?= $stP['mant'] ?></td>
                    <td class="text-center"><span class="badge bg-success"><?= $stP['completos'] ?></span></td>
                    <td class="text-center"><span class="badge bg-warning text-dark"><?= $stP['pendientes'] ?></span></td>
                    <td class="text-center"><span class="badge <?= $stP['vencidos']>0?'bg-danger':'bg-secondary' ?>"><?= $stP['vencidos'] ?></span></td>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <div class="prog-bar-wrap flex-grow-1"><div class="prog-bar-fill" style="width:<?= $pct ?>%;background:<?= $barCol ?>;"></div></div>
                            <span class="fw-bold" style="min-width:44px;"><?= $pct ?>%</span>
                        </div>
                    </td>
                    <td class="no-print text-center">
                        <a href="reportes.php?tab=inventario&programa=<?= urlencode($pName) ?>" class="btn btn-outline-primary btn-sm py-0 px-2">Ver Lista</a>
                        <a href="reportes.php?tab=inventario&programa=<?= urlencode($pName) ?>&export=xls" class="btn btn-outline-success btn-sm py-0 px-2" title="Descargar Excel"><i class="fa-solid fa-file-excel"></i></a>
                    </td>
                </tr>
            <?php endforeach; ?>
                <tr class="table-primary fw-bold">
                    <td>TOTAL CONSOLIDADO EN SISTEMA</td>
                    <td class="text-center"><?= $totalBienes ?></td>
                    <td class="text-center"><?= $conMant ?></td>
                    <td class="text-center"><?= $completos ?></td>
                    <td class="text-center"><?= $pendientes ?></td>
                    <td class="text-center"><?= $vencidos ?></td>
                    <td><?= $pctCumplimiento ?>% Operativo</td>
                    <td class="no-print text-center">—</td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<?php elseif ($tab === 'fichas'): ?>
<!-- REPORTE 3: HISTORIAL DE FICHAS EMITIDAS -->
<div class="card p-3 shadow-sm mb-3">
    <div class="d-flex justify-content-between align-items-center border-bottom pb-2 mb-3">
        <h6 class="fw-bold mb-0"><i class="fa-solid fa-file-signature text-primary me-1"></i> REPORTE HISTÓRICO DE SOLICITUDES, VERIFICACIONES Y FICHAS OFICIALES</h6>
        <span class="badge bg-dark"><?= count($allSolicitudes) ?> fichas/solicitudes</span>
    </div>
    <div class="table-responsive">
        <table class="table table-sm table-bordered table-hover align-middle small mb-0">
            <thead class="table-dark">
                <tr>
                    <th>Solicitud</th>
                    <th>N.º Ficha</th>
                    <th>Tipo de Documento</th>
                    <th>Equipo / Código</th>
                    <th>Área Solicitante</th>
                    <th>Falla Reportada</th>
                    <th>Técnico Asignado</th>
                    <th>Estado Servicio</th>
                    <th class="no-print">Abrir Ficha</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($allSolicitudes as $s): ?>
                <tr>
                    <td class="fw-bold"><?= htmlspecialchars($s['codigo_solicitud']) ?></td>
                    <td class="fw-bold text-danger"><?= htmlspecialchars($s['numero_ficha'] ?: 'Pendiente Admin') ?></td>
                    <td><?= htmlspecialchars($s['tipo_ficha']) ?></td>
                    <td class="fw-bold text-primary"><?= htmlspecialchars($s['codigo_bien']) ?><br><small class="text-muted"><?= htmlspecialchars($s['descripcion_bien']) ?></small></td>
                    <td><?= htmlspecialchars($s['area_solicitante']) ?></td>
                    <td><?= htmlspecialchars($s['falla_reportada']) ?></td>
                    <td><?= htmlspecialchars($s['tecnico_asignado']) ?></td>
                    <td><span class="badge <?= stripos((string)$s['resultado_servicio'],'COMPLETO')!==false?'bg-success':'bg-warning text-dark' ?>"><?= htmlspecialchars($s['resultado_servicio']) ?></span></td>
                    <td class="no-print text-center">
                        <a href="ficha_mantenimiento.php?sol_id=<?= (int)$s['id'] ?>" class="btn btn-dark btn-sm py-0 px-2"><i class="fa-solid fa-file-pdf me-1"></i>Ver Ficha</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php elseif ($tab === 'vencimientos'): ?>
<!-- REPORTE 4: ALERTAS DE SEMÁFORO PREVENTIVO -->
<div class="card p-3 shadow-sm mb-3">
    <div class="d-flex justify-content-between align-items-center border-bottom pb-2 mb-3">
        <h6 class="fw-bold mb-0 text-danger"><i class="fa-solid fa-triangle-exclamation me-1"></i> REPORTE PRIORITARIO DE EQUIPOS CON MANTENIMIENTO VENCIDO O PRÓXIMO A VENCER</h6>
        <span class="badge bg-danger"><?= count($listaVencimientos) ?> equipos en alerta</span>
    </div>
    <div class="table-responsive">
        <table class="table table-sm table-bordered table-hover align-middle small mb-0">
            <thead class="table-dark">
                <tr>
                    <th>N.º</th>
                    <th>Código</th>
                    <th>Equipo / Marca</th>
                    <th>Programa / Ambiente</th>
                    <th>Fecha Límite</th>
                    <th>Semáforo</th>
                    <th>Estado Actual</th>
                    <th>Diagnóstico / Observación</th>
                    <th class="no-print">Ficha</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($listaVencimientos as $v): ?>
                <tr>
                    <td class="fw-bold text-center"><?= htmlspecialchars($v['correlativo']) ?></td>
                    <td class="fw-bold text-primary"><?= htmlspecialchars($v['codigo_apsti']) ?></td>
                    <td><?= htmlspecialchars($v['denominacion'] . ' ' . ($v['marca'] !== '-' ? $v['marca'] : '')) ?></td>
                    <td><?= htmlspecialchars($v['programa'] . ' — ' . $v['area_ambiente']) ?></td>
                    <td class="fw-bold"><?= htmlspecialchars($v['fecha_limite_mantenimiento']) ?></td>
                    <td><span class="badge <?= $v['_alerta']==='VENCIDO'?'bg-danger':'bg-warning text-dark' ?>"><?= htmlspecialchars($v['_alerta']) ?></span></td>
                    <td><?= htmlspecialchars($v['estado_servicio']) ?></td>
                    <td><?= htmlspecialchars($v['diagnostico']) ?></td>
                    <td class="no-print text-center"><a href="ficha_mantenimiento.php?bien_id=<?= (int)$v['id'] ?>" class="btn btn-dark btn-sm py-0 px-2"><i class="fa-solid fa-file-pdf"></i></a></td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($listaVencimientos)): ?>
                <tr><td colspan="9" class="text-center text-success py-3 fw-bold">¡Excelente! No hay equipos vencidos en este momento.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php else: ?>
<!-- REPORTE 2: INVENTARIO DETALLADO CON FILTROS AVANZADOS -->
<div class="card p-3 mb-3 shadow-sm no-print">
    <form method="GET" class="row g-2 align-items-end">
        <input type="hidden" name="tab" value="inventario">
        <div class="col-md-3">
            <label class="small fw-bold">Programa / Área:</label>
            <select name="programa" class="form-select form-select-sm">
                <option value="">Todos los programas y Cafetín</option>
                <?php foreach (array_keys($porPrograma) as $pOpt): ?>
                    <option value="<?= htmlspecialchars($pOpt) ?>" <?= $prog===$pOpt?'selected':'' ?>><?= htmlspecialchars($pOpt) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <label class="small fw-bold">Estado de Servicio:</label>
            <select name="estado_servicio" class="form-select form-select-sm">
                <option value="">Todos los estados</option>
                <option value="Servicio Completo" <?= $est==='Servicio Completo'?'selected':'' ?>>Servicio Completo</option>
                <option value="Servicio Pendiente" <?= $est==='Servicio Pendiente'?'selected':'' ?>>Servicio Pendiente</option>
            </select>
        </div>
        <div class="col-md-4">
            <label class="small fw-bold">Buscar por código, equipo o diagnóstico:</label>
            <input type="text" name="q" class="form-control form-control-sm" value="<?= htmlspecialchars($q) ?>" placeholder="Ej. TEROS, CAF-C, impresora, internet...">
        </div>
        <div class="col-md-2 d-flex gap-1">
            <button type="submit" class="btn btn-primary btn-sm w-100 fw-bold"><i class="fa-solid fa-filter me-1"></i> Filtrar</button>
            <a href="reportes.php?tab=inventario" class="btn btn-outline-secondary btn-sm">Limpiar</a>
        </div>
    </form>
</div>

<div class="card p-3 shadow-sm mb-3">
    <div class="d-flex justify-content-between align-items-center border-bottom pb-2 mb-2">
        <h6 class="fw-bold mb-0">REPORTE DETALLADO DE BIENES Y MANTENIMIENTO — FILTRO: <?= htmlspecialchars($prog ?: 'TODOS LOS PROGRAMAS Y CAFETÍN') ?></h6>
        <span class="badge bg-dark"><?= count($rows) ?> registros mostrados</span>
    </div>
    <div class="table-responsive">
        <table class="table table-sm table-bordered table-striped align-middle small mb-0">
            <thead class="table-dark">
                <tr>
                    <th>N.º</th>
                    <th>Código</th>
                    <th>Denominación / Marca</th>
                    <th>Programa / Ambiente</th>
                    <th>Compra</th>
                    <th>Est.</th>
                    <th>Semáforo Límite</th>
                    <th>Estado Servicio</th>
                    <th>Diagnóstico / Observación</th>
                    <th class="no-print">Ficha</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $r):
                $sem = calcularSemaforo((string)($r['fecha_limite_mantenimiento'] ?? '2026-11-30'), (int)($r['requiere_mantenimiento'] ?? 1));
            ?>
                <tr>
                    <td class="fw-bold text-center"><?= htmlspecialchars($r['correlativo']) ?></td>
                    <td class="fw-bold text-primary"><?= htmlspecialchars($r['codigo_apsti']) ?></td>
                    <td><?= htmlspecialchars($r['denominacion'] . ' ' . ($r['marca'] !== '-' ? $r['marca'] : '')) ?></td>
                    <td><?= htmlspecialchars($r['programa'] . ' — ' . $r['area_ambiente']) ?></td>
                    <td><?= htmlspecialchars($r['fecha_compra']) ?></td>
                    <td class="text-center"><?= htmlspecialchars($r['estado_conservacion']) ?></td>
                    <td><span class="badge <?= $sem['clase'] ?>"><?= htmlspecialchars($sem['texto']) ?></span></td>
                    <td><span class="badge <?= $r['estado_servicio']==='Servicio Completo'?'bg-success':'bg-warning text-dark' ?>"><?= htmlspecialchars($r['estado_servicio']) ?></span></td>
                    <td><?= htmlspecialchars($r['diagnostico']) ?></td>
                    <td class="no-print text-center"><a href="ficha_mantenimiento.php?bien_id=<?= (int)$r['id'] ?>" class="btn btn-dark btn-sm py-0 px-2" title="Abrir Ficha Oficial"><i class="fa-solid fa-file-pdf"></i></a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- PIE DE FIRMAS OFICIAL SOLO AL IMPRIMIR EN PDF -->
<div class="print-firmas-reporte">
    <div>
        <div style="border-top:1px solid #000;width:75%;margin:0 auto 5px auto;"></div>
        <strong>Lic. Ernesto Correa Barrantes</strong><br>Administrador General — IESTP Utcubamba
    </div>
    <div>
        <div style="border-top:1px solid #000;width:75%;margin:0 auto 5px auto;"></div>
        <strong>Ing. Carlos Enrique Sandoval Dávila</strong><br>Coordinador Programa de Estudios APSTI
    </div>
    <div>
        <div style="border-top:1px solid #000;width:75%;margin:0 auto 5px auto;"></div>
        <strong>Téc. Carlos Quispe</strong><br>Responsable Área de Mantenimiento
    </div>
</div>
<?php renderFooter(); ?>
