<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/includes/bootstrap.php';
require dirname(__DIR__, 2) . '/includes/layout.php';
require dirname(__DIR__, 2) . '/includes/catalogos.php';

// Permitir scripts de impresión (window.print) sin bloqueo de CSP en esta vista
header("Content-Security-Policy: default-src 'self'; style-src 'self' https://cdn.jsdelivr.net 'unsafe-inline'; script-src 'self' https://cdn.jsdelivr.net 'unsafe-inline'; img-src 'self' data:; font-src 'self' https://cdn.jsdelivr.net; frame-ancestors 'none'");

$u = requiere_rol('admin_general', 'admin_programa', 'tecnico');
$progs = programas_del_usuario((int) $u['id'], $u['rol']);
if (!$progs) {
    $progs = [0];
}

$fPrograma = get_int('programa');
$fAmbiente = get_int('ambiente');
$fSoloMant = isset($_GET['solo_mant']) ? (int) $_GET['solo_mant'] : 1;

[$whereAmb, $paramsAmb] = filtro_programas('a.programa_id', $progs);
if ($fPrograma && in_array($fPrograma, $progs, true)) {
    $whereAmb .= ' AND a.programa_id = ?';
    $paramsAmb[] = $fPrograma;
}

// 1. Resumen consolidado por cada Área / Ambiente / Laboratorio
$resumenAreas = consulta(
    "SELECT a.id AS ambiente_id, a.codigo AS ambiente_cod, a.nombre AS ambiente, a.tipo,
            pr.id AS programa_id, pr.codigo AS programa_cod, pr.nombre AS programa_nombre,
            COUNT(e.id) AS total_bienes,
            SUM(CASE WHEN e.requiere_mantenimiento = 1 THEN 1 ELSE 0 END) AS equipos_mant,
            SUM(CASE WHEN e.requiere_mantenimiento = 1 AND JSON_UNQUOTE(JSON_EXTRACT(e.especificaciones, '$.estado_servicio')) = 'completo' THEN 1 ELSE 0 END) AS mant_completos,
            SUM(CASE WHEN e.requiere_mantenimiento = 1 AND JSON_UNQUOTE(JSON_EXTRACT(e.especificaciones, '$.estado_servicio')) <> 'completo' THEN 1 ELSE 0 END) AS mant_pendientes,
            SUM(CASE WHEN e.requiere_mantenimiento = 1 AND e.fecha_limite_mantenimiento BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) AS por_vencer_30d,
            SUM(CASE WHEN e.requiere_mantenimiento = 1 AND e.fecha_limite_mantenimiento < CURDATE() THEN 1 ELSE 0 END) AS vencidos
       FROM ambientes a
       JOIN programas pr ON pr.id = a.programa_id
       LEFT JOIN equipos e ON e.ambiente_id = a.id AND e.estado <> 'dado_de_baja'
      WHERE a.activo = 1 AND $whereAmb
      GROUP BY a.id, a.codigo, a.nombre, a.tipo, pr.id, pr.codigo, pr.nombre
      ORDER BY FIELD(pr.codigo,'APSTI','CONT','ENFE','AGRO','ADMIN'), equipos_mant DESC, a.nombre",
    $paramsAmb
)->fetchAll();

// 2. Detalle de equipos del Área o Programa seleccionado
[$whereEq, $paramsEq] = filtro_programas('e.programa_id', $progs);
if ($fPrograma && in_array($fPrograma, $progs, true)) {
    $whereEq .= ' AND e.programa_id = ?';
    $paramsEq[] = $fPrograma;
}
if ($fAmbiente) {
    $whereEq .= ' AND e.ambiente_id = ?';
    $paramsEq[] = $fAmbiente;
}
if ($fSoloMant === 1) {
    $whereEq .= ' AND e.requiere_mantenimiento = 1';
}

$equiposDetalle = consulta(
    "SELECT e.id, e.numero_orden, e.codigo_inventario, e.codigo_interno, e.codigo_patrimonial,
            e.nombre, e.marca, e.modelo, e.numero_serie, e.fecha_adquisicion,
            e.estado, e.estado_conservacion, e.requiere_mantenimiento, e.frecuencia_mantenimiento,
            e.ultimo_mantenimiento, e.fecha_limite_mantenimiento, e.responsable_bien, e.especificaciones,
            pr.codigo AS programa_cod, pr.nombre AS programa_nombre,
            a.nombre AS ambiente_nombre,
            DATEDIFF(e.fecha_limite_mantenimiento, CURDATE()) AS dias_restantes
       FROM equipos e
       JOIN programas pr ON pr.id = e.programa_id
       LEFT JOIN ambientes a ON a.id = e.ambiente_id
      WHERE $whereEq AND e.estado <> 'dado_de_baja'
      ORDER BY pr.codigo, a.nombre, e.fecha_limite_mantenimiento ASC, e.codigo_inventario
      LIMIT 1000",
    $paramsEq
)->fetchAll();

// Calcular KPIs totales para encabezados de Excel y PDF
$kpiTotal      = count($equiposDetalle);
$kpiCompletos  = 0;
$kpiPendientes = 0;
$kpiVencidos   = 0;
$kpiProximos   = 0;
foreach ($equiposDetalle as $row) {
    $espTmp = json_decode((string) ($row['especificaciones'] ?? '{}'), true) ?: [];
    $estSrv = strtolower((string) ($espTmp['estado_servicio'] ?? 'pendiente'));
    if ($estSrv === 'completo') {
        $kpiCompletos++;
    } else {
        $kpiPendientes++;
    }
    if ($row['dias_restantes'] !== null) {
        $d = (int) $row['dias_restantes'];
        if ($d < 0) {
            $kpiVencidos++;
        } elseif ($d <= 30) {
            $kpiProximos++;
        }
    }
}

$exportar = (string) ($_GET['exportar'] ?? '');

// =========================================================================
// EXPORTACIÓN 1: EXCEL PROFESIONAL CON COLORES, ENCABEZADOS Y ANCHOS FIJOS
// =========================================================================
if ($exportar === 'excel' || $exportar === 'csv') {
    $filename = 'Reporte_Mantenimiento_IESTPU_' . date('Ymd_His') . '.xls';
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: max-age=0');

    echo "\xEF\xBB\xBF";
    ?>
<html xmlns:o="urn:schemas-microsoft-com:office:office"
      xmlns:x="urn:schemas-microsoft-com:office:excel"
      xmlns="http://www.w3.org/TR/REC-html40">
<head>
  <meta charset="utf-8">
  <!--[if gte mso 9]>
  <xml>
    <x:ExcelWorkbook>
      <x:ExcelWorksheets>
        <x:ExcelWorksheet>
          <x:Name>Reporte Mantenimiento</x:Name>
          <x:WorksheetOptions>
            <x:DisplayGridlines/>
            <x:FreezePanes/>
            <x:FrozenNoSplit/>
            <x:SplitHorizontal>6</x:SplitHorizontal>
            <x:TopRowBottomPane>6</x:TopRowBottomPane>
            <x:ActivePane>2</x:ActivePane>
          </x:WorksheetOptions>
        </x:ExcelWorksheet>
      </x:ExcelWorksheets>
    </x:ExcelWorkbook>
  </xml>
  <![endif]-->
  <style>
    body { font-family: Calibri, Arial, sans-serif; font-size: 10pt; color: #0F172A; }
    table { border-collapse: collapse; }
    .banner-inst { background: #1E3A8A; color: #FFFFFF; font-size: 14pt; font-weight: bold; text-align: center; height: 30px; }
    .banner-sub  { background: #1D4ED8; color: #E0E7FF; font-size: 11pt; font-weight: bold; text-align: center; height: 24px; }
    .meta-row    { background: #F1F5F9; color: #334155; font-size: 9.5pt; text-align: left; height: 22px; }
    .kpi-lbl     { background: #E2E8F0; color: #1E293B; font-weight: bold; text-align: center; border: 1px solid #94A3B8; }
    .kpi-val     { background: #FFFFFF; font-weight: bold; font-size: 11pt; text-align: center; border: 1px solid #94A3B8; }
    .th-main     { background: #0F172A; color: #FFFFFF; font-weight: bold; text-align: center; border: 1px solid #334155; height: 28px; vertical-align: middle; }
    .td-data     { border: 1px solid #CBD5E1; padding: 5px 6px; vertical-align: middle; height: 22px; }
    .td-zebra    { background: #F8FAFC; }
    .txt-code    { mso-number-format:"\@"; font-weight: bold; color: #1E3A8A; }
    .txt-plain   { mso-number-format:"\@"; }
    .center      { text-align: center; }
    .badge-ok    { background: #DCFCE7; color: #166534; font-weight: bold; text-align: center; }
    .badge-warn  { background: #FEF3C7; color: #92400E; font-weight: bold; text-align: center; }
    .badge-err   { background: #FEE2E2; color: #991B1B; font-weight: bold; text-align: center; }
    .badge-prog  { background: #DBEAFE; color: #1E40AF; font-weight: bold; text-align: center; }
  </style>
</head>
<body>
  <table>
    <col width="90">
    <col width="220">
    <col width="130">
    <col width="190">
    <col width="115">
    <col width="150">
    <col width="145">
    <col width="110">
    <col width="95">
    <col width="110">
    <col width="120">
    <col width="135">
    <col width="125">
    <col width="220">

    <!-- Encabezado Institucional -->
    <tr>
      <td colspan="14" class="banner-inst">INSTITUTO DE EDUCACIÓN SUPERIOR TECNOLÓGICO PÚBLICO "UTCUBAMBA" · CÓD. MODULAR 741744</td>
    </tr>
    <tr>
      <td colspan="14" class="banner-sub">REPORTE DE INVENTARIO Y CONTROL DE MANTENIMIENTO POR PROGRAMA DE ESTUDIO Y ÁREA</td>
    </tr>
    <tr>
      <td colspan="14" class="meta-row">
        <strong>Emitido por:</strong> <?= e($u['nombres'] . ' ' . $u['apellidos'] . ' (' . $u['rol_nombre'] . ')') ?>
        &nbsp;&nbsp;|&nbsp;&nbsp;<strong>Fecha de emisión:</strong> <?= e(date('d/m/Y H:i')) ?>
        &nbsp;&nbsp;|&nbsp;&nbsp;<strong>Filtro:</strong> <?= $fSoloMant === 1 ? 'Solo Equipos con Mantenimiento' : 'Todos los Bienes del Área' ?>
      </td>
    </tr>

    <!-- Tarjetas de Resumen KPI -->
    <tr>
      <td colspan="2" class="kpi-lbl">TOTAL REGISTROS</td>
      <td colspan="1" class="kpi-val" style="color:#1E3A8A;"><?= $kpiTotal ?></td>
      <td colspan="2" class="kpi-lbl">SERVICIO COMPLETO</td>
      <td colspan="1" class="kpi-val" style="background:#DCFCE7;color:#166534;"><?= $kpiCompletos ?></td>
      <td colspan="2" class="kpi-lbl">SERVICIO PENDIENTE</td>
      <td colspan="1" class="kpi-val" style="background:#FEF3C7;color:#92400E;"><?= $kpiPendientes ?></td>
      <td colspan="2" class="kpi-lbl">POR VENCER (≤ 30 DÍAS)</td>
      <td colspan="1" class="kpi-val" style="background:#FEF3C7;color:#92400E;"><?= $kpiProximos ?></td>
      <td colspan="1" class="kpi-lbl">VENCIDOS</td>
      <td colspan="1" class="kpi-val" style="background:#FEE2E2;color:#991B1B;"><?= $kpiVencidos ?></td>
    </tr>
    <tr><td colspan="14" style="height: 8px; border: none;"></td></tr>

    <!-- Encabezados de la Tabla Principal -->
    <tr>
      <th class="th-main">PROGRAMA</th>
      <th class="th-main">ÁREA / AMBIENTE / LABORATORIO</th>
      <th class="th-main">CÓDIGO</th>
      <th class="th-main">DENOMINACIÓN / EQUIPO</th>
      <th class="th-main">MARCA</th>
      <th class="th-main">MODELO</th>
      <th class="th-main">N.° DE SERIE</th>
      <th class="th-main">FECHA COMPRA</th>
      <th class="th-main">CONSERV.</th>
      <th class="th-main">ÚLTIMO MANT.</th>
      <th class="th-main">FECHA LÍMITE</th>
      <th class="th-main">ESTADO PLAZO / DÍAS</th>
      <th class="th-main">ESTADO SERVICIO</th>
      <th class="th-main">RESPONSABLE DE ÁREA</th>
    </tr>

    <?php foreach ($equiposDetalle as $idx => $row): ?>
      <?php
        $esp = json_decode((string) ($row['especificaciones'] ?? '{}'), true) ?: [];
        $estSrv = strtoupper((string) ($esp['estado_servicio'] ?? 'PENDIENTE'));
        $zebra = ($idx % 2 === 1) ? ' td-zebra' : '';
        $srvClass = ($estSrv === 'COMPLETO') ? 'badge-ok' : 'badge-warn';

        if ($row['dias_restantes'] === null) {
            $diasTxt = 'No aplica';
            $diasClass = 'center';
        } else {
            $d = (int) $row['dias_restantes'];
            if ($d < 0) {
                $diasTxt = 'VENCIDO (' . abs($d) . ' días)';
                $diasClass = 'badge-err';
            } elseif ($d <= 30) {
                $diasTxt = 'POR VENCER (' . $d . ' días)';
                $diasClass = 'badge-warn';
            } else {
                $diasTxt = 'AL DÍA (' . $d . ' días)';
                $diasClass = 'badge-ok';
            }
        }
      ?>
      <tr>
        <td class="td-data badge-prog"><?= e($row['programa_cod']) ?></td>
        <td class="td-data<?= $zebra ?>"><?= e($row['ambiente_nombre'] ?? '—') ?></td>
        <td class="td-data txt-code center<?= $zebra ?>"><?= e($row['codigo_interno'] ?: $row['codigo_inventario']) ?></td>
        <td class="td-data<?= $zebra ?>" style="font-weight:bold;"><?= e($row['nombre']) ?></td>
        <td class="td-data<?= $zebra ?>"><?= e($row['marca'] ?: '—') ?></td>
        <td class="td-data<?= $zebra ?>"><?= e($row['modelo'] ?: '—') ?></td>
        <td class="td-data txt-plain center<?= $zebra ?>"><?= e($row['numero_serie'] ?: '—') ?></td>
        <td class="td-data center<?= $zebra ?>"><?= $row['fecha_adquisicion'] ? e(date('d/m/Y', strtotime((string) $row['fecha_adquisicion']))) : '—' ?></td>
        <td class="td-data center<?= $zebra ?>" style="font-weight:bold;"><?= e($row['estado_conservacion'] ?: '—') ?></td>
        <td class="td-data center<?= $zebra ?>"><?= $row['ultimo_mantenimiento'] ? e(date('d/m/Y', strtotime((string) $row['ultimo_mantenimiento']))) : '—' ?></td>
        <td class="td-data center<?= $zebra ?>" style="font-weight:bold;"><?= $row['fecha_limite_mantenimiento'] ? e(date('d/m/Y', strtotime((string) $row['fecha_limite_mantenimiento']))) : '—' ?></td>
        <td class="td-data <?= $diasClass ?>"><?= e($diasTxt) ?></td>
        <td class="td-data <?= $srvClass ?>"><?= e($estSrv) ?></td>
        <td class="td-data<?= $zebra ?>"><?= e($row['responsable_bien'] ?: '—') ?></td>
      </tr>
    <?php endforeach; ?>
  </table>
</body>
</html>
    <?php
    exit;
}

// =========================================================================
// EXPORTACIÓN 2: VISTA DEDICADA DE IMPRESIÓN / GUARDAR COMO PDF (A4 HORIZONTAL)
// =========================================================================
if ($exportar === 'pdf') {
    ?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Reporte PDF · Mantenimiento IESTPU</title>
  <style>
    @page { size: A4 landscape; margin: 12mm; }
    * { box-sizing: border-box; }
    body { font-family: Arial, Helvetica, sans-serif; font-size: 9.5pt; color: #0F172A; margin: 0; padding: 16px; background: #fff; }
    .toolbar { display: flex; justify-content: space-between; align-items: center; background: #0F172A; color: #fff; padding: 10px 16px; border-radius: 8px; margin-bottom: 16px; }
    .btn { display: inline-block; padding: 8px 14px; border-radius: 6px; font-weight: bold; text-decoration: none; cursor: pointer; border: none; font-size: 10pt; }
    .btn-print { background: #EF4444; color: #fff; }
    .btn-back  { background: #334155; color: #fff; margin-left: 8px; }
    .header-box { border: 2px solid #1E3A8A; border-radius: 6px; padding: 10px 14px; margin-bottom: 12px; display: flex; justify-content: space-between; align-items: center; background: #F8FAFC; }
    .header-title { color: #1E3A8A; font-size: 13pt; font-weight: bold; margin: 0; }
    .header-sub { font-size: 9.5pt; color: #334155; margin-top: 3px; }
    .kpi-grid { display: flex; gap: 10px; margin-bottom: 14px; }
    .kpi-card { flex: 1; border: 1px solid #CBD5E1; border-radius: 6px; padding: 8px 10px; text-align: center; }
    .kpi-card .num { font-size: 14pt; font-weight: bold; }
    .kpi-card .lbl { font-size: 8.5pt; color: #475569; text-transform: uppercase; }
    table { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
    th { background: #1E3A8A; color: #fff; padding: 6px 5px; font-size: 8.5pt; border: 1px solid #1E3A8A; text-align: left; }
    td { padding: 5px; font-size: 8.5pt; border: 1px solid #CBD5E1; vertical-align: middle; }
    tbody tr:nth-child(even) { background: #F8FAFC; }
    .center { text-align: center; }
    .pill { display: inline-block; padding: 2px 6px; border-radius: 4px; font-weight: bold; font-size: 8pt; }
    .ok   { background: #DCFCE7; color: #166534; }
    .warn { background: #FEF3C7; color: #92400E; }
    .err  { background: #FEE2E2; color: #991B1B; }
    .prog { background: #DBEAFE; color: #1E40AF; }
    .sec-title { font-size: 10.5pt; font-weight: bold; color: #1E3A8A; margin: 12px 0 6px 0; text-transform: uppercase; border-bottom: 2px solid #1E3A8A; padding-bottom: 3px; }
    @media print {
      body { padding: 0; }
      .no-print { display: none !important; }
      thead { display: table-header-group; }
      tr { page-break-inside: avoid; }
    }
  </style>
</head>
<body>
  <div class="toolbar no-print">
    <div><strong>Vista Previa Lista para PDF / Impresión</strong> — Elija <em>"Guardar como PDF"</em> en la ventana de impresión.</div>
    <div>
      <button type="button" class="btn btn-print" id="btnImprimirAhora">Imprimir / Guardar PDF</button>
      <a class="btn btn-back" href="<?= e(url('admin/reportes.php?' . http_build_query(['programa' => $fPrograma, 'ambiente' => $fAmbiente, 'solo_mant' => $fSoloMant]))) ?>">← Volver</a>
    </div>
  </div>

  <div class="header-box">
    <div>
      <div class="header-title">I.E.S.T. PÚBLICO "UTCUBAMBA" · CÓDIGO MODULAR 741744</div>
      <div class="header-sub"><strong>REPORTE DE INVENTARIO Y FECHAS LÍMITE DE MANTENIMIENTO POR PROGRAMA Y ÁREA</strong></div>
    </div>
    <div style="text-align: right; font-size: 8.5pt; color: #334155;">
      <div><strong>Emitido por:</strong> <?= e($u['nombres'] . ' ' . $u['apellidos']) ?></div>
      <div><strong>Perfil:</strong> <?= e($u['rol_nombre']) ?></div>
      <div><strong>Fecha:</strong> <?= e(date('d/m/Y H:i')) ?></div>
    </div>
  </div>

  <div class="kpi-grid">
    <div class="kpi-card"><div class="num" style="color:#1E3A8A;"><?= $kpiTotal ?></div><div class="lbl">Equipos en Reporte</div></div>
    <div class="kpi-card" style="background:#F0FDF4;"><div class="num" style="color:#166534;"><?= $kpiCompletos ?></div><div class="lbl">Servicio Completo</div></div>
    <div class="kpi-card" style="background:#FFFBEB;"><div class="num" style="color:#92400E;"><?= $kpiPendientes ?></div><div class="lbl">Servicio Pendiente</div></div>
    <div class="kpi-card" style="background:#FFFBEB;"><div class="num" style="color:#B45309;"><?= $kpiProximos ?></div><div class="lbl">Por Vencer (≤30 días)</div></div>
    <div class="kpi-card" style="background:#FEF2F2;"><div class="num" style="color:#991B1B;"><?= $kpiVencidos ?></div><div class="lbl">Fecha Límite Vencida</div></div>
  </div>

  <div class="sec-title">1. Resumen Consolidado por Área / Laboratorio</div>
  <table>
    <thead>
      <tr>
        <th>Programa</th>
        <th>Área / Laboratorio / Oficina</th>
        <th class="center">Total Bienes</th>
        <th class="center">Equipos Mant.</th>
        <th class="center">Completo</th>
        <th class="center">Pendiente</th>
        <th class="center">Por Vencer (≤30d)</th>
        <th class="center">Vencidos</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($resumenAreas as $ra): ?>
        <tr>
          <td class="center"><span class="pill prog"><?= e($ra['programa_cod']) ?></span></td>
          <td><strong><?= e($ra['ambiente']) ?></strong></td>
          <td class="center"><?= (int) $ra['total_bienes'] ?></td>
          <td class="center"><strong><?= (int) $ra['equipos_mant'] ?></strong></td>
          <td class="center"><span class="pill ok"><?= (int) $ra['mant_completos'] ?></span></td>
          <td class="center"><span class="pill warn"><?= (int) $ra['mant_pendientes'] ?></span></td>
          <td class="center"><span class="pill warn"><?= (int) $ra['por_vencer_30d'] ?></span></td>
          <td class="center"><span class="pill err"><?= (int) $ra['vencidos'] ?></span></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <div class="sec-title">2. Detalle de Equipos, Fechas de Mantenimiento y Estado de Servicio</div>
  <table>
    <thead>
      <tr>
        <th>Prog.</th>
        <th>Área / Ambiente</th>
        <th>Código</th>
        <th>Dispositivo</th>
        <th>Marca / Modelo</th>
        <th>Serie</th>
        <th class="center">Compra</th>
        <th class="center">Últ. Mant.</th>
        <th class="center">Fecha Límite</th>
        <th class="center">Plazo</th>
        <th class="center">Servicio</th>
        <th>Responsable</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($equiposDetalle as $eq): ?>
        <?php
          $esp = json_decode((string) ($eq['especificaciones'] ?? '{}'), true) ?: [];
          $estSrv = strtoupper((string) ($esp['estado_servicio'] ?? 'PENDIENTE'));
          $d = $eq['dias_restantes'] !== null ? (int) $eq['dias_restantes'] : null;
          $plazoCls = $d === null ? '' : ($d < 0 ? 'err' : ($d <= 30 ? 'warn' : 'ok'));
          $plazoTxt = $d === null ? '—' : ($d < 0 ? 'Vencido (' . abs($d) . 'd)' : ($d . ' días'));
        ?>
        <tr>
          <td class="center"><span class="pill prog"><?= e($eq['programa_cod']) ?></span></td>
          <td><?= e($eq['ambiente_nombre'] ?? '—') ?></td>
          <td><strong><?= e($eq['codigo_interno'] ?: $eq['codigo_inventario']) ?></strong></td>
          <td><strong><?= e($eq['nombre']) ?></strong></td>
          <td><?= e(trim(($eq['marca'] ?? '') . ' ' . ($eq['modelo'] ?? '')) ?: '—') ?></td>
          <td><?= e($eq['numero_serie'] ?: '—') ?></td>
          <td class="center"><?= $eq['fecha_adquisicion'] ? e(date('d/m/Y', strtotime((string) $eq['fecha_adquisicion']))) : '—' ?></td>
          <td class="center"><?= $eq['ultimo_mantenimiento'] ? e(date('d/m/Y', strtotime((string) $eq['ultimo_mantenimiento']))) : '—' ?></td>
          <td class="center"><strong><?= $eq['fecha_limite_mantenimiento'] ? e(date('d/m/Y', strtotime((string) $eq['fecha_limite_mantenimiento']))) : '—' ?></strong></td>
          <td class="center"><span class="pill <?= $plazoCls ?>"><?= e($plazoTxt) ?></span></td>
          <td class="center"><span class="pill <?= $estSrv === 'COMPLETO' ? 'ok' : 'warn' ?>"><?= e($estSrv) ?></span></td>
          <td><?= e($eq['responsable_bien'] ?: '—') ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <script>
    document.getElementById('btnImprimirAhora').addEventListener('click', function () { window.print(); });
    window.addEventListener('load', function () { setTimeout(function () { window.print(); }, 350); });
  </script>
</body>
</html>
    <?php
    exit;
}

$selProg = programas_para_select($u);
[$fAmbSel, $pAmbSel] = filtro_programas('programa_id', $progs);
if ($fPrograma && in_array($fPrograma, $progs, true)) {
    $fAmbSel .= ' AND programa_id = ?';
    $pAmbSel[] = $fPrograma;
}
$selAmbientes = consulta("SELECT id, nombre FROM ambientes WHERE activo = 1 AND $fAmbSel ORDER BY nombre", $pAmbSel)->fetchAll();

cabecera('Reportes por Área y Programa de Estudio', $u);
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2 no-print">
  <div>
    <h1 class="h3 fw-bold mb-0">
      <i class="bi bi-file-earmark-bar-graph text-primary me-2"></i>
      Reportes Profesionales por Programa de Estudio y Área / Ambiente
    </h1>
    <div class="text-muted small">
      <?= $u['rol'] === 'admin_general'
          ? 'Vista de Administrador General: Supervisión global de todas las áreas, laboratorios, oficinas y programas de estudio'
          : 'Vista de Coordinador de Programa: Control de inventario, equipos con mantenimiento realizado y fechas límite de su programa' ?>
    </div>
  </div>
  <div class="d-flex gap-2">
    <a class="btn btn-success fw-semibold" href="<?= e(url('admin/reportes.php?' . http_build_query(['programa' => $fPrograma, 'ambiente' => $fAmbiente, 'solo_mant' => $fSoloMant, 'exportar' => 'excel']))) ?>">
      <i class="bi bi-file-earmark-excel me-1"></i>Exportar a Excel (Con Formato)
    </a>
    <a class="btn btn-danger fw-semibold" target="_blank" href="<?= e(url('admin/reportes.php?' . http_build_query(['programa' => $fPrograma, 'ambiente' => $fAmbiente, 'solo_mant' => $fSoloMant, 'exportar' => 'pdf']))) ?>">
      <i class="bi bi-file-earmark-pdf me-1"></i>Imprimir Reporte / PDF
    </a>
  </div>
</div>

<!-- Filtros del Reporte -->
<form method="get" class="card card-body shadow-sm border-0 mb-4 no-print">
  <div class="row g-2 align-items-end">
    <div class="col-md-3">
      <label class="form-label small fw-bold mb-1">Programa de Estudio / Área</label>
      <select class="form-select form-select-sm" name="programa" onchange="this.form.submit()">
        <option value="">Todos los programas autorizados</option>
        <?php foreach ($selProg as $pg): ?>
          <option value="<?= (int) $pg['id'] ?>" <?= $fPrograma === (int) $pg['id'] ? 'selected' : '' ?>><?= e($pg['codigo'] . ' · ' . $pg['nombre']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-4">
      <label class="form-label small fw-bold mb-1">Oficina / Laboratorio / Aula Específica</label>
      <select class="form-select form-select-sm" name="ambiente" onchange="this.form.submit()">
        <option value="">Todas las áreas / laboratorios (<?= count($selAmbientes) ?>)</option>
        <?php foreach ($selAmbientes as $am): ?>
          <option value="<?= (int) $am['id'] ?>" <?= $fAmbiente === (int) $am['id'] ? 'selected' : '' ?>><?= e($am['nombre']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-3">
      <label class="form-label small fw-bold mb-1">Tipo de Bienes en el Detalle</label>
      <select class="form-select form-select-sm" name="solo_mant" onchange="this.form.submit()">
        <option value="1" <?= $fSoloMant === 1 ? 'selected' : '' ?>>Solo Equipos con Fecha de Mantenimiento (CPUs, Laptops, Impresoras…)</option>
        <option value="0" <?= $fSoloMant === 0 ? 'selected' : '' ?>>Todos los Bienes del Área (Incluye mobiliario y enseres)</option>
      </select>
    </div>
    <div class="col-md-2 d-grid">
      <button class="btn btn-sm btn-primary" type="submit"><i class="bi bi-funnel me-1"></i>Generar Reporte</button>
    </div>
  </div>
</form>

<!-- Tabla 1: Consolidado por Área / Ambiente / Laboratorio -->
<div class="card shadow-sm border-0 mb-4">
  <div class="card-header bg-white py-3">
    <h2 class="h6 fw-bold mb-0 text-uppercase text-primary">
      <i class="bi bi-building-check me-1"></i>1. Resumen Consolidado por Área, Laboratorio y Oficina (<?= count($resumenAreas) ?> ambientes)
    </h2>
  </div>
  <div class="table-responsive">
    <table class="table table-hover table-sm align-middle mb-0">
      <thead class="table-light">
        <tr>
          <th>Programa</th>
          <th>Área / Laboratorio / Oficina</th>
          <th class="text-end">Total Bienes</th>
          <th class="text-end">Equipos con Mant.</th>
          <th class="text-end text-success">Servicio Completo</th>
          <th class="text-end text-warning">Servicio Pendiente</th>
          <th class="text-end">Por Vencer (≤30d)</th>
          <th class="text-end text-danger">Vencidos</th>
          <th class="text-end no-print">Filtrar Detalle</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($resumenAreas as $ra): ?>
          <tr class="<?= $fAmbiente === (int) $ra['ambiente_id'] ? 'table-primary' : '' ?>">
            <td><span class="badge bg-primary"><?= e($ra['programa_cod']) ?></span></td>
            <td class="fw-semibold"><?= e($ra['ambiente']) ?></td>
            <td class="text-end"><?= number_format((int) $ra['total_bienes']) ?></td>
            <td class="text-end fw-bold"><?= number_format((int) $ra['equipos_mant']) ?></td>
            <td class="text-end text-success fw-semibold"><?= (int) $ra['mant_completos'] ?></td>
            <td class="text-end text-warning-emphasis fw-semibold"><?= (int) $ra['mant_pendientes'] ?></td>
            <td class="text-end"><span class="badge text-bg-warning text-dark"><?= (int) $ra['por_vencer_30d'] ?></span></td>
            <td class="text-end"><span class="badge text-bg-danger"><?= (int) $ra['vencidos'] ?></span></td>
            <td class="text-end no-print">
              <a class="btn btn-sm btn-outline-primary py-0" href="<?= e(url('admin/reportes.php?programa=' . (int) $ra['programa_id'] . '&ambiente=' . (int) $ra['ambiente_id'] . '&solo_mant=' . $fSoloMant)) ?>">
                Ver Equipos
              </a>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Tabla 2: Detalle de Equipos, Fechas de Mantenimiento y Tiempo Restante -->
<div class="card shadow-sm border-0">
  <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
    <h2 class="h6 fw-bold mb-0 text-uppercase text-primary">
      <i class="bi bi-list-columns-reverse me-1"></i>2. Detalle de Dispositivos, Estado de Mantenimiento y Fechas Límite (<?= count($equiposDetalle) ?> registros mostrados)
    </h2>
  </div>
  <div class="table-responsive">
    <table class="table table-hover table-sm align-middle mb-0">
      <thead class="table-light">
        <tr>
          <th>Código</th>
          <th>Dispositivo / Descripción</th>
          <th>Marca / Modelo / Serie</th>
          <th>Programa / Área</th>
          <th>Fecha Compra</th>
          <th>Últ. Mant.</th>
          <th>Fecha Límite (A qué tiempo)</th>
          <th>Estado Servicio</th>
          <th class="text-end no-print">Ficha</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($equiposDetalle as $eq): ?>
          <?php $esp = json_decode((string) ($eq['especificaciones'] ?? '{}'), true) ?: []; ?>
          <tr>
            <td class="fw-bold text-nowrap">
              <a href="<?= e(url('admin/buscar_equipo.php?id=' . (int) $eq['id'])) ?>" class="text-decoration-none">
                <?= e($eq['codigo_interno'] ?: $eq['codigo_inventario']) ?>
              </a>
            </td>
            <td class="fw-semibold"><?= e($eq['nombre']) ?></td>
            <td class="small">
              <?= e(trim(($eq['marca'] ?? '') . ' ' . ($eq['modelo'] ?? '')) ?: '—') ?>
              <?php if (!empty($eq['numero_serie'])): ?>
                <div class="text-muted">Serie: <?= e($eq['numero_serie']) ?></div>
              <?php endif; ?>
            </td>
            <td class="small">
              <span class="badge bg-secondary"><?= e($eq['programa_cod']) ?></span>
              <?= e($eq['ambiente_nombre'] ?? '—') ?>
            </td>
            <td class="small text-nowrap"><?= $eq['fecha_adquisicion'] ? e(date('d/m/Y', strtotime((string) $eq['fecha_adquisicion']))) : '—' ?></td>
            <td class="small text-nowrap"><?= $eq['ultimo_mantenimiento'] ? e(date('d/m/Y', strtotime((string) $eq['ultimo_mantenimiento']))) : '—' ?></td>
            <td class="text-nowrap">
              <?= semaforo_mantenimiento($eq['fecha_limite_mantenimiento'], (int) $eq['requiere_mantenimiento']) ?>
            </td>
            <td>
              <?= (int) $eq['requiere_mantenimiento'] === 1 ? insignia_servicio($esp['estado_servicio'] ?? 'pendiente') : '<span class="text-muted small">Mobiliario</span>' ?>
            </td>
            <td class="text-end no-print">
              <a class="btn btn-sm btn-outline-dark py-0" href="<?= e(url('admin/ficha_mantenimiento.php?id=' . (int) $eq['id'])) ?>">
                <i class="bi bi-file-earmark-text"></i> Ficha
              </a>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php pie();
