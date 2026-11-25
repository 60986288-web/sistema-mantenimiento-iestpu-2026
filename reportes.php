<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/includes/bootstrap.php';
require dirname(__DIR__, 2) . '/includes/layout.php';
require dirname(__DIR__, 2) . '/includes/catalogos.php';

// Reemplazar cabecera CSP para que el botón de Imprimir / PDF funcione siempre sin bloqueos
header("Content-Security-Policy: default-src 'self'; style-src 'self' https://cdn.jsdelivr.net 'unsafe-inline'; script-src 'self' https://cdn.jsdelivr.net 'unsafe-inline'; img-src 'self' data:; font-src 'self' https://cdn.jsdelivr.net; frame-ancestors 'none'", true);

$u = requiere_rol('admin_general', 'admin_programa', 'tecnico');
$progs = programas_del_usuario((int) $u['id'], $u['rol']);
if (!$progs) {
    $progs = [0];
}

$fPrograma = get_int('programa');
$fAmbiente = get_int('ambiente');
$fSoloMant = isset($_GET['solo_mant']) ? (int) $_GET['solo_mant'] : 0; // 0 = Todos los bienes, 1 = Solo mantenibles

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
            e.nombre, e.marca, e.modelo, e.tipo_fisico, e.color, e.numero_serie, e.dimensiones,
            e.fecha_adquisicion, e.estado, e.estado_conservacion, e.situacion,
            e.requiere_mantenimiento, e.ultimo_mantenimiento, e.fecha_limite_mantenimiento,
            e.responsable_bien, e.especificaciones,
            pr.codigo AS programa_cod, pr.nombre AS programa_nombre,
            a.nombre AS ambiente_nombre,
            DATEDIFF(e.fecha_limite_mantenimiento, CURDATE()) AS dias_restantes
       FROM equipos e
       JOIN programas pr ON pr.id = e.programa_id
       LEFT JOIN ambientes a ON a.id = e.ambiente_id
      WHERE $whereEq AND e.estado <> 'dado_de_baja'
      ORDER BY pr.codigo, a.nombre, e.codigo_inventario
      LIMIT 1500",
    $paramsEq
)->fetchAll();

// Texto del Área / Programa filtrado para el encabezado oficial
$selProg = programas_para_select($u);
[$fAmbSel, $pAmbSel] = filtro_programas('programa_id', $progs);
if ($fPrograma && in_array($fPrograma, $progs, true)) {
    $fAmbSel .= ' AND programa_id = ?';
    $pAmbSel[] = $fPrograma;
}
$selAmbientes = consulta("SELECT id, nombre FROM ambientes WHERE activo = 1 AND $fAmbSel ORDER BY nombre", $pAmbSel)->fetchAll();

$tituloFiltro = 'TODOS LOS PROGRAMAS Y ÁREAS';
if ($fAmbiente) {
    foreach ($selAmbientes as $am) {
        if ((int) $am['id'] === $fAmbiente) {
            $tituloFiltro = mb_strtoupper((string) $am['nombre'], 'UTF-8');
            break;
        }
    }
} elseif ($fPrograma) {
    foreach ($selProg as $pg) {
        if ((int) $pg['id'] === $fPrograma) {
            $tituloFiltro = mb_strtoupper($pg['codigo'] . ' - ' . $pg['nombre'], 'UTF-8');
            break;
        }
    }
}

// =========================================================================
// EXPORTACIÓN A EXCEL ORDENADO CON COLORES, ENCABEZADOS Y ANCHOS FIJOS (.xls)
// =========================================================================
$exportar = (string) ($_GET['exportar'] ?? '');
if ($exportar === 'excel' || $exportar === 'csv') {
    $filename = 'Inventario_Mantenimiento_IESTPU_' . date('Ymd_His') . '.xls';
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
          <x:Name>Inventario IESTPU</x:Name>
          <x:WorksheetOptions>
            <x:DisplayGridlines/>
            <x:Print>
              <x:ValidPrinterInfo/>
              <x:PaperSizeIndex>9</x:PaperSizeIndex>
              <x:Scale>75</x:Scale>
            </x:Print>
            <x:FreezePanes/>
            <x:FrozenNoSplit/>
            <x:SplitHorizontal>7</x:SplitHorizontal>
            <x:TopRowBottomPane>7</x:TopRowBottomPane>
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
    .banner-1 { background: #1E3A8A; color: #FFFFFF; font-size: 14pt; font-weight: bold; text-align: center; height: 28px; }
    .banner-2 { background: #1D4ED8; color: #FFFFFF; font-size: 11pt; font-weight: bold; text-align: center; height: 24px; }
    .meta-lbl { background: #E2E8F0; color: #0F172A; font-weight: bold; border: 1px solid #94A3B8; padding: 4px; }
    .meta-val { background: #FFFFFF; color: #0F172A; border: 1px solid #94A3B8; padding: 4px; }
    .th-main  { background: #0F172A; color: #FFFFFF; font-weight: bold; text-align: center; border: 1px solid #334155; height: 28px; vertical-align: middle; }
    .td-data  { border: 1px solid #CBD5E1; padding: 5px 6px; vertical-align: middle; height: 22px; }
    .zebra    { background: #F8FAFC; }
    .code     { mso-number-format:"\@"; font-weight: bold; color: #1E3A8A; text-align: center; }
    .plain    { mso-number-format:"\@"; text-align: center; }
    .center   { text-align: center; }
    .ok       { background: #DCFCE7; color: #166534; font-weight: bold; text-align: center; }
    .warn     { background: #FEF3C7; color: #92400E; font-weight: bold; text-align: center; }
    .err      { background: #FEE2E2; color: #991B1B; font-weight: bold; text-align: center; }
    .prog     { background: #DBEAFE; color: #1E40AF; font-weight: bold; text-align: center; }
  </style>
</head>
<body>
  <table>
    <col width="85"><col width="180"><col width="120"><col width="125"><col width="200">
    <col width="110"><col width="145"><col width="115"><col width="105"><col width="145">
    <col width="135"><col width="95"><col width="115"><col width="115">
    <tr><td colspan="14" class="banner-1">INSTITUTO DE EDUCACIÓN SUPERIOR TECNOLÓGICO PÚBLICO "UTCUBAMBA" · CÓD. MODULAR 741744</td></tr>
    <tr><td colspan="14" class="banner-2">INVENTARIO GENERAL DE BIENES INSTITUCIONAL - <?= date('Y') ?> · <?= e($tituloFiltro) ?></td></tr>
    <tr>
      <td colspan="2" class="meta-lbl">NOMBRE DE LA ENTIDAD:</td>
      <td colspan="5" class="meta-val">INSTITUTO DE EDUCACIÓN SUPERIOR TECNOLÓGICO PÚBLICO UTCUBAMBA</td>
      <td colspan="2" class="meta-lbl">LUGAR / DISTRITO:</td>
      <td colspan="5" class="meta-val">BAGUA GRANDE — PROV. UTCUBAMBA</td>
    </tr>
    <tr>
      <td colspan="2" class="meta-lbl">ÁREA / PROGRAMA:</td>
      <td colspan="5" class="meta-val"><strong><?= e($tituloFiltro) ?></strong></td>
      <td colspan="2" class="meta-lbl">COMITÉ DE INVENTARIOS:</td>
      <td colspan="5" class="meta-val">Sandoval Dávila Carlos Enrique · Cruz Bobadilla Stalin · Occ Briceño Lluvitza</td>
    </tr>
    <tr>
      <td colspan="2" class="meta-lbl">EMITIDO POR:</td>
      <td colspan="5" class="meta-val"><?= e($u['nombres'] . ' ' . $u['apellidos'] . ' (' . $u['rol_nombre'] . ')') ?></td>
      <td colspan="2" class="meta-lbl">FECHA DE EMISIÓN:</td>
      <td colspan="5" class="meta-val"><?= e(date('d/m/Y H:i')) ?> (<?= count($equiposDetalle) ?> registros)</td>
    </tr>
    <tr><td colspan="14" style="height:8px;border:none;"></td></tr>
    <tr>
      <th class="th-main">PROGRAMA</th>
      <th class="th-main">ÁREA / AMBIENTE</th>
      <th class="th-main">CÓD. PATRIMONIAL</th>
      <th class="th-main">CÓD. INTERNO</th>
      <th class="th-main">DENOMINACIÓN</th>
      <th class="th-main">MARCA</th>
      <th class="th-main">MODELO</th>
      <th class="th-main">TIPO</th>
      <th class="th-main">COLOR</th>
      <th class="th-main">SERIE</th>
      <th class="th-main">DIMENSIONES</th>
      <th class="th-main">SITUACIÓN (1)</th>
      <th class="th-main">CONSERVACIÓN (2)</th>
      <th class="th-main">FECHA LÍMITE MANT.</th>
    </tr>
    <?php foreach ($equiposDetalle as $idx => $eq): ?>
      <?php
        $z = ($idx % 2 === 1) ? ' zebra' : '';
        $sitTxt = ($eq['situacion'] === 'almacen') ? 'A' : 'U';
        $cons = strtoupper((string) ($eq['estado_conservacion'] ?: 'B'));
        $consCls = in_array($cons, ['N', 'MB', 'B'], true) ? 'ok' : ($cons === 'R' ? 'warn' : 'err');
      ?>
      <tr>
        <td class="td-data prog"><?= e($eq['programa_cod']) ?></td>
        <td class="td-data<?= $z ?>"><?= e($eq['ambiente_nombre'] ?? '—') ?></td>
        <td class="td-data plain<?= $z ?>"><?= e($eq['codigo_patrimonial'] ?: str_pad((string) $eq['id'], 3, '0', STR_PAD_LEFT)) ?></td>
        <td class="td-data code<?= $z ?>"><?= e($eq['codigo_interno'] ?: $eq['codigo_inventario']) ?></td>
        <td class="td-data<?= $z ?>" style="font-weight:bold;"><?= e($eq['nombre']) ?></td>
        <td class="td-data<?= $z ?>"><?= e($eq['marca'] ?: 'No registrado') ?></td>
        <td class="td-data<?= $z ?>"><?= e($eq['modelo'] ?: 'No registrado') ?></td>
        <td class="td-data<?= $z ?>"><?= e($eq['tipo_fisico'] ?: 'No registrado') ?></td>
        <td class="td-data<?= $z ?>"><?= e($eq['color'] ?: 'No registrado') ?></td>
        <td class="td-data plain<?= $z ?>"><?= e($eq['numero_serie'] ?: 'No registrado') ?></td>
        <td class="td-data<?= $z ?>"><?= e($eq['dimensiones'] ?: 'No registrado') ?></td>
        <td class="td-data center<?= $z ?>" style="font-weight:bold;"><?= e($sitTxt) ?></td>
        <td class="td-data <?= $consCls ?>"><?= e($cons) ?></td>
        <td class="td-data center<?= $z ?>"><?= $eq['fecha_limite_mantenimiento'] ? e(date('d/m/Y', strtotime((string) $eq['fecha_limite_mantenimiento']))) : 'No aplica' ?></td>
      </tr>
    <?php endforeach; ?>
  </table>
</body>
</html>
    <?php
    exit;
}

cabecera('Reportes Oficiales (PDF / Excel)', $u);
?>

<style>
  /* Fuerza hoja A4 HORIZONTAL automática al presionar Imprimir / PDF */
  @page {
    size: A4 landscape;
    margin: 8mm;
  }

  .reporte-oficial {
    background: #ffffff;
    color: #0F172A;
    padding: 18px;
    border-radius: 8px;
  }

  .encabezado-inst {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 10px;
  }

  .encabezado-inst img {
    width: 58px;
    height: auto;
  }

  .encabezado-centro {
    text-align: center;
    line-height: 1.3;
  }

  .banda-titulo {
    border: 1.5px solid #0F172A;
    background: #1E3A8A;
    color: #FFFFFF;
    text-align: center;
    font-weight: 700;
    font-size: 11pt;
    padding: 5px;
    margin-bottom: 8px;
    text-transform: uppercase;
  }

  .tabla-meta {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 10px;
    font-size: 9pt;
  }

  .tabla-meta td {
    border: 1px solid #475569;
    padding: 4px 7px;
  }

  .tabla-meta .m-lbl {
    background: #F1F5F9;
    font-weight: 700;
    width: 16%;
  }

  .tabla-bienes {
    width: 100%;
    border-collapse: collapse;
    table-layout: fixed;
    font-size: 8.5pt;
  }

  .tabla-bienes th {
    background: #0F172A;
    color: #FFFFFF;
    border: 1px solid #334155;
    padding: 5px 4px;
    text-align: center;
    font-size: 7.8pt;
    vertical-align: middle;
    word-wrap: break-word;
  }

  .tabla-bienes td {
    border: 1px solid #94A3B8;
    padding: 4px 5px;
    vertical-align: middle;
    word-wrap: break-word;
    overflow-wrap: break-word;
  }

  .tabla-bienes tbody tr:nth-child(even) {
    background: #F8FAFC;
  }

  .cons-ok   { background: #DCFCE7 !important; color: #166534; font-weight: 700; text-align: center; }
  .cons-warn { background: #FEF3C7 !important; color: #92400E; font-weight: 700; text-align: center; }
  .cons-err  { background: #FEE2E2 !important; color: #991B1B; font-weight: 700; text-align: center; }

  @media print {
    * {
      -webkit-print-color-adjust: exact !important;
      print-color-adjust: exact !important;
      box-sizing: border-box !important;
    }

    html, body {
      width: 100% !important;
      margin: 0 !important;
      padding: 0 !important;
      background: #FFFFFF !important;
    }

    .sidebar, nav.navbar, .no-print, .offcanvas {
      display: none !important;
    }

    .flex-grow-1, main.container-fluid, .card, .card-body, .table-responsive, .reporte-oficial {
      width: 100% !important;
      max-width: 100% !important;
      margin: 0 !important;
      padding: 0 !important;
      overflow: visible !important;
      border: none !important;
      box-shadow: none !important;
    }

    .tabla-bienes {
      width: 100% !important;
      max-width: 100% !important;
      table-layout: fixed !important;
    }

    .tabla-bienes th, .tabla-bienes td {
      font-size: 7.2pt !important;
      padding: 3px 3px !important;
      line-height: 1.15 !important;
    }

    thead {
      display: table-header-group !important;
    }

    tr {
      page-break-inside: avoid !important;
      break-inside: avoid !important;
    }
  }
</style>

<!-- Barra de acciones y filtros (no sale al imprimir) -->
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2 no-print">
  <div>
    <h1 class="h3 fw-bold mb-0">
      <i class="bi bi-file-earmark-bar-graph text-primary me-2"></i>
      Reportes Oficiales por Programa de Estudio y Área
    </h1>
    <div class="text-muted small">Impresión automática en hoja A4 Horizontal (todas las columnas completas) y exportación a Excel con colores</div>
  </div>
  <div class="d-flex gap-2">
    <a class="btn btn-success fw-semibold" href="<?= e(url('admin/reportes.php?' . http_build_query(['programa' => $fPrograma, 'ambiente' => $fAmbiente, 'solo_mant' => $fSoloMant, 'exportar' => 'excel']))) ?>">
      <i class="bi bi-file-earmark-excel me-1"></i>Exportar a Excel (Con Colores)
    </a>
    <button type="button" class="btn btn-danger fw-semibold" id="btnImprimirHorizontal">
      <i class="bi bi-file-earmark-pdf me-1"></i>Imprimir / Guardar PDF (Horizontal)
    </button>
  </div>
</div>

<form method="get" class="card card-body shadow-sm border-0 mb-3 no-print">
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
      <label class="form-label small fw-bold mb-1">Oficina / Laboratorio / Aula</label>
      <select class="form-select form-select-sm" name="ambiente" onchange="this.form.submit()">
        <option value="">Todas las áreas / laboratorios (<?= count($selAmbientes) ?>)</option>
        <?php foreach ($selAmbientes as $am): ?>
          <option value="<?= (int) $am['id'] ?>" <?= $fAmbiente === (int) $am['id'] ? 'selected' : '' ?>><?= e($am['nombre']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-3">
      <label class="form-label small fw-bold mb-1">Bienes a Mostrar</label>
      <select class="form-select form-select-sm" name="solo_mant" onchange="this.form.submit()">
        <option value="0" <?= $fSoloMant === 0 ? 'selected' : '' ?>>Todos los Bienes del Área (Acta completa)</option>
        <option value="1" <?= $fSoloMant === 1 ? 'selected' : '' ?>>Solo Equipos con Fecha de Mantenimiento</option>
      </select>
    </div>
    <div class="col-md-2 d-grid">
      <button class="btn btn-sm btn-primary" type="submit"><i class="bi bi-funnel me-1"></i>Filtrar</button>
    </div>
  </div>
</form>

<!-- REPORTE OFICIAL INSTITUCIONAL (VISIBLE EN PANTALLA Y LISTO PARA PDF HORIZONTAL) -->
<div class="reporte-oficial shadow-sm">
  <div class="encabezado-inst">
    <img src="<?= e(url('assets/img/logo-iestp.png')) ?>" alt="Logo IESTPU">
    <div class="encabezado-centro">
      <div style="font-weight:700;font-size:10pt;">GOBIERNO REGIONAL AMAZONAS</div>
      <div style="font-size:9pt;">DIRECCIÓN REGIONAL DE EDUCACIÓN AMAZONAS</div>
      <div style="font-weight:800;font-size:11pt;color:#1E3A8A;">INSTITUTO DE EDUCACIÓN SUPERIOR TECNOLÓGICO PÚBLICO "UTCUBAMBA"</div>
    </div>
    <img src="<?= e(url('assets/img/logo-iestp.png')) ?>" alt="Logo IESTPU">
  </div>

  <div class="banda-titulo">
    INVENTARIO GENERAL DE BIENES INSTITUCIONAL - <?= date('Y') ?> · <?= e($tituloFiltro) ?>
  </div>

  <table class="tabla-meta">
    <tr>
      <td class="m-lbl">NOMBRE DE LA ENTIDAD:</td>
      <td style="width:34%;">INSTITUTO DE EDUCACIÓN SUPERIOR TECNOLÓGICO PÚBLICO UTCUBAMBA</td>
      <td class="m-lbl">LUGAR / DISTRITO:</td>
      <td style="width:34%;">BAGUA GRANDE — PROV. UTCUBAMBA</td>
    </tr>
    <tr>
      <td class="m-lbl">CÓDIGO MODULAR:</td>
      <td>741744</td>
      <td class="m-lbl">COMITÉ DE INVENTARIOS:</td>
      <td>Sandoval Dávila Carlos Enrique · Cruz Bobadilla Stalin · Occ Briceño Lluvitza</td>
    </tr>
    <tr>
      <td class="m-lbl">ÁREA / PROGRAMA:</td>
      <td><strong><?= e($tituloFiltro) ?></strong></td>
      <td class="m-lbl">FECHA DE EMISIÓN:</td>
      <td><?= e(date('d/m/Y')) ?> (<?= count($equiposDetalle) ?> registros)</td>
    </tr>
  </table>

  <div class="table-responsive">
    <table class="tabla-bienes">
      <colgroup>
        <col style="width: 7%;">
        <col style="width: 9%;">
        <col style="width: 14%;">
        <col style="width: 8%;">
        <col style="width: 10%;">
        <col style="width: 9%;">
        <col style="width: 8%;">
        <col style="width: 11%;">
        <col style="width: 10%;">
        <col style="width: 6%;">
        <col style="width: 8%;">
      </colgroup>
      <thead>
        <tr>
          <th colspan="11" style="background:#1E3A8A;font-size:8.5pt;">DESCRIPCIÓN DE LOS BIENES</th>
        </tr>
        <tr>
          <th>CÓDIGO PATRIMONIAL</th>
          <th>CÓDIGO INTERNO</th>
          <th>DENOMINACIÓN</th>
          <th>MARCA</th>
          <th>MODELO</th>
          <th>TIPO</th>
          <th>COLOR</th>
          <th>SERIE</th>
          <th>DIMENSIONES (Medidas)</th>
          <th>SITUACIÓN (1)</th>
          <th>ESTADO DE CONSERVACIÓN (2)</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($equiposDetalle as $eq): ?>
          <?php
            $sitTxt = ($eq['situacion'] === 'almacen') ? 'A' : 'U';
            $cons = strtoupper((string) ($eq['estado_conservacion'] ?: 'B'));
            $consCls = in_array($cons, ['N', 'MB', 'B'], true) ? 'cons-ok' : ($cons === 'R' ? 'cons-warn' : 'cons-err');
          ?>
          <tr>
            <td class="text-center"><?= e($eq['codigo_patrimonial'] ?: str_pad((string) $eq['id'], 3, '0', STR_PAD_LEFT)) ?></td>
            <td class="text-center fw-bold" style="color:#1D4ED8;"><?= e($eq['codigo_interno'] ?: $eq['codigo_inventario']) ?></td>
            <td class="fw-semibold"><?= e($eq['nombre']) ?></td>
            <td><?= e($eq['marca'] ?: 'No registrado') ?></td>
            <td><?= e($eq['modelo'] ?: 'No registrado') ?></td>
            <td><?= e($eq['tipo_fisico'] ?: 'No registrado') ?></td>
            <td><?= e($eq['color'] ?: 'No registrado') ?></td>
            <td><?= e($eq['numero_serie'] ?: 'No registrado') ?></td>
            <td><?= e($eq['dimensiones'] ?: 'No registrado') ?></td>
            <td class="text-center fw-bold"><?= e($sitTxt) ?></td>
            <td class="<?= $consCls ?>"><?= e($cons) ?></td>
          </tr>
        <?php endforeach; if (!$equiposDetalle): ?>
          <tr><td colspan="11" class="text-center text-muted py-4">No se encontraron registros con los filtros seleccionados.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<script>
  document.getElementById('btnImprimirHorizontal').addEventListener('click', function () {
    window.print();
  });
</script>

<?php pie();
