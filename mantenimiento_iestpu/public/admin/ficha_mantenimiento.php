<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

$u = require_login();
$rol = get_user_role($u);
$scope = get_user_program_scope($u);
$pdo = getPDO();

$solId = isset($_GET['sol_id']) ? (int)$_GET['sol_id'] : 0;
$bienIdParam = isset($_GET['bien_id']) ? (int)$_GET['bien_id'] : 0;

$sol = null;
$bien = null;

if ($solId > 0) {
    $stmt = $pdo->prepare(
        'SELECT s.*,
                b.id AS b_id, b.correlativo, b.codigo_apsti, b.codigo_interno, b.denominacion,
                b.marca, b.modelo, b.tipo_material, b.color, b.serie, b.dimensiones,
                b.especificaciones, b.programa, b.area_ambiente, b.responsable,
                b.fecha_compra, b.estado_conservacion, b.requiere_mantenimiento,
                b.fecha_limite_mantenimiento, b.estado_visita, b.estado_servicio AS bien_estado_servicio,
                b.fecha_viene_tecnico, b.proxima_visita, b.diagnostico AS bien_diagnostico
         FROM solicitudes_mantenimiento s
         INNER JOIN bienes_inventario b ON b.id = s.bien_id
         WHERE s.id = ?
         LIMIT 1'
    );
    $stmt->execute([$solId]);
    $row = $stmt->fetch();
    if (!$row) {
        http_response_code(404);
        render_access_denied('La solicitud solicitada no existe en la base de datos.');
    }
    if ($scope !== null && strtoupper((string)$row['programa']) !== $scope) {
        http_response_code(403);
        render_access_denied('No tienes permisos para ver fichas pertenecientes a otro programa de estudios.');
    }

    // BLOQUEO CRÍTICO P0: No emitir ficha si sigue en Paso 1 o sin número de ficha emitido
    $etapa = (string)($row['estado_flujo'] ?? '1_REPORTADO_AREA');
    $numFicha = trim((string)($row['numero_ficha'] ?? ''));
    if ($etapa === '1_REPORTADO_AREA' || $numFicha === '') {
        http_response_code(409);
        ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ficha no emitida — SoporteTécnico UTCUBAMBA</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
</head>
<body style="background:#f3f6f8;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px;font-family:'Segoe UI',system-ui,sans-serif;">
    <div class="card border-0 shadow-sm p-4" style="max-width:540px;width:100%;border-radius:16px;border-top:4px solid #f59e0b !important;">
        <div class="text-center mb-3">
            <div class="d-inline-flex align-items-center justify-content-center rounded-circle mb-2" style="width:62px;height:62px;background:#fef3c7;color:#b45309;">
                <i class="fa-solid fa-file-circle-exclamation fa-xl"></i>
            </div>
            <h1 class="h5 fw-bold mb-1">Ficha aún no emitida por Administración</h1>
            <div class="badge bg-warning text-dark">Solicitud <?= h($row['codigo_solicitud']) ?> · Paso 1 (Reportado)</div>
        </div>
        <p class="small text-secondary mb-3">
            El equipo <strong><?= h($row['codigo_bien']) ?></strong> (<?= h($row['denominacion']) ?>) se encuentra actualmente en etapa
            <strong>Reportado por el Área</strong> y todavía no cuenta con verificación administrativa ni número de ficha oficial asignado.
        </p>
        <div class="d-flex justify-content-center gap-2 flex-wrap">
            <?php if ($rol === 'admin'): ?>
                <a href="<?= h(baseUrl('admin/verificacion_admin.php?sol_id=' . (int)$row['id'])) ?>" class="btn btn-success btn-sm">
                    <i class="fa-solid fa-clipboard-check me-1"></i>Ir a Paso 2 (Verificar y Emitir Ficha)
                </a>
            <?php endif; ?>
            <a href="<?= h(baseUrl(role_home($rol))) ?>" class="btn btn-outline-secondary btn-sm">
                <i class="fa-solid fa-arrow-left me-1"></i>Volver al Panel
            </a>
        </div>
    </div>
</body>
</html>
<?php
        exit;
    }

    $sol = $row;
    $bien = $row;
    $bienId = (int)$row['b_id'];
} elseif ($bienIdParam > 0) {
    $stmt = $pdo->prepare('SELECT * FROM bienes_inventario WHERE id = ? LIMIT 1');
    $stmt->execute([$bienIdParam]);
    $bien = $stmt->fetch();
    if (!$bien) {
        http_response_code(404);
        render_access_denied('El equipo patrimonial solicitado no existe.');
    }
    if ($scope !== null && strtoupper((string)$bien['programa']) !== $scope) {
        http_response_code(403);
        render_access_denied('No tienes permisos para ver la ficha de un equipo de otra área.');
    }
    $bienId = (int)$bien['id'];
    // Buscar la última solicitud verificada de este bien (si existe)
    $stSol = $pdo->prepare("SELECT * FROM solicitudes_mantenimiento WHERE bien_id = ? AND numero_ficha IS NOT NULL AND numero_ficha != '' ORDER BY id DESC LIMIT 1");
    $stSol->execute([$bienId]);
    $sol = $stSol->fetch() ?: null;
} else {
    http_response_code(400);
    render_access_denied('Debe especificar un identificador válido de solicitud (sol_id) o equipo (bien_id).');
}

// Historial real de órdenes verificadas/atendidas del equipo (Tabla 3 del formato oficial)
$stHist = $pdo->prepare(
    "SELECT * FROM solicitudes_mantenimiento
     WHERE bien_id = ? AND numero_ficha IS NOT NULL AND numero_ficha != ''
     ORDER BY id ASC"
);
$stHist->execute([$bienId]);
$ordenes = $stHist->fetchAll();

// Normalizar título oficial con tilde correcta
$rawTipoFicha = $sol ? strtoupper(trim((string)($sol['tipo_ficha'] ?? 'FICHA DE MANTENIMIENTO'))) : 'FICHA DE REGISTRO DE MANTENIMIENTO';
$tipoFichaOficial = str_replace('OBSERVACION', 'OBSERVACIÓN', $rawTipoFicha);

$numeroFichaMostrar = $sol && !empty($sol['numero_ficha'])
    ? (string)$sol['numero_ficha']
    : ('FM-' . str_pad((string)$bienId, 4, '0', STR_PAD_LEFT) . '-2026');

$fechaEmision = $sol && !empty($sol['fecha_verificacion'])
    ? fmt_fecha((string)$sol['fecha_verificacion'])
    : fmt_fecha(date('Y-m-d'));

$estadoFisico = strtoupper(trim((string)($bien['estado_conservacion'] ?? 'B')));
$chk = static function (string $code) use ($estadoFisico): string {
    return $estadoFisico === $code ? '[X]' : '[ ]';
};

$esPaso2OMas = $sol !== null && ((int)($sol['verificado_admin'] ?? 0) === 1 || ($sol['estado_flujo'] ?? '') !== '1_REPORTADO_AREA');
$esPaso3 = $sol !== null && (($sol['estado_flujo'] ?? '') === '3_ATENDIDO_AREA_TECNICA');
$resultadoServ = $sol ? strtoupper(trim((string)($sol['resultado_servicio'] ?? 'PENDIENTE'))) : strtoupper((string)($bien['estado_servicio'] ?? 'PENDIENTE'));

// URL canónica de verificación para código QR
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'sistema-mantenimiento-iestpu.onrender.com';
$qrPayload = $scheme . '://' . $host . baseUrl('admin/ficha_mantenimiento.php?' . ($sol ? ('sol_id=' . (int)$sol['id']) : ('bien_id=' . $bienId))) . '&ficha=' . rawurlencode($numeroFichaMostrar);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($numeroFichaMostrar) ?> — Ficha Técnica de Mantenimiento IESTPU</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: #e2e8f0;
            font-family: 'Segoe UI', Arial, sans-serif;
            color: #111827;
            margin: 0;
            padding: 18px;
        }
        .sheet-page {
            max-width: 960px;
            margin: 0 auto;
            background: #ffffff;
            padding: 24px 28px;
            border-radius: 6px;
            box-shadow: 0 10px 30px rgba(15,23,42,.12);
        }
        .doc-header {
            display: grid;
            grid-template-columns: 78px 1fr 220px 76px;
            gap: 10px;
            align-items: center;
            border: 1.5px solid #0f3b3c;
            border-radius: 4px;
            padding: 10px 12px;
            margin-bottom: 12px;
        }
        .doc-header img.escudo { width: 68px; height: 68px; object-fit: contain; }
        .doc-title-block { text-align: center; }
        .doc-title-block .gov { font-size: 10.5px; font-weight: 700; color: #0f3b3c; letter-spacing: .04em; }
        .doc-title-block .inst { font-size: 14px; font-weight: 800; color: #0a2829; }
        .doc-title-block .docname {
            display: inline-block;
            margin-top: 4px;
            background: #0f3b3c;
            color: #fff;
            padding: 3px 10px;
            font-size: 11px;
            font-weight: 700;
            border-radius: 3px;
        }
        .meta-box {
            border-left: 1px solid #cbd5e1;
            padding-left: 10px;
            font-size: 11px;
            line-height: 1.45;
        }
        .qr-box {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }
        .sec-title {
            background: #0f3b3c;
            color: #ffffff;
            font-size: 11px;
            font-weight: 700;
            padding: 5px 10px;
            letter-spacing: .03em;
            margin-top: 10px;
            margin-bottom: 0;
        }
        table.ficha-tbl {
            width: 100%;
            border-collapse: collapse;
            font-size: 11.2px;
            margin-bottom: 8px;
        }
        table.ficha-tbl th, table.ficha-tbl td {
            border: 1px solid #475569;
            padding: 5px 7px;
            vertical-align: top;
        }
        table.ficha-tbl th {
            background: #f1f5f9;
            color: #0f172a;
            font-weight: 700;
            font-size: 10.2px;
            text-transform: uppercase;
        }
        .firmas-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 22px;
            margin-top: 34px;
            text-align: center;
            font-size: 10.5px;
        }
        .firma-linea {
            border-top: 1px solid #1e293b;
            padding-top: 5px;
            margin-top: 32px;
            font-weight: 700;
        }
        .no-print-bar {
            max-width: 960px;
            margin: 0 auto 12px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
        }
        @media print {
            body { background: #fff; padding: 0; }
            .no-print-bar { display: none !important; }
            .sheet-page { box-shadow: none; max-width: 100%; padding: 8px 12px; }
        }
    </style>
</head>
<body>
<div class="no-print-bar">
    <a href="<?= h(baseUrl(role_home($rol))) ?>" class="btn btn-sm btn-secondary">
        <i class="fa-solid fa-arrow-left me-1"></i>Volver al Sistema
    </a>
    <div class="d-flex gap-2">
        <button type="button" class="btn btn-sm btn-success" onclick="window.print()">
            <i class="fa-solid fa-print me-1"></i>Imprimir / Guardar PDF
        </button>
    </div>
</div>

<div class="sheet-page">
    <!-- Encabezado oficial igual a FichaTecnicaMantenimientoIESTPU.pdf + QR -->
    <div class="doc-header">
        <div>
            <img src="<?= h(baseUrl('assets/img/logo.png')) ?>" alt="Escudo IESTPU" class="escudo">
        </div>
        <div class="doc-title-block">
            <div class="gov">GOBIERNO REGIONAL AMAZONAS · DRE AMAZONAS</div>
            <div class="inst">I.E.S.T. PÚBLICO «UTCUBAMBA»</div>
            <div class="docname">FICHA DE REGISTRO DE MANTENIMIENTO DE EQUIPO</div>
            <div style="font-size:10px;color:#0f3b3c;font-weight:700;margin-top:3px;">
                <?= h($tipoFichaOficial) ?>
            </div>
        </div>
        <div class="meta-box">
            <div><strong>CÓD. MODULAR:</strong> 741744</div>
            <div><strong>FICHA N.°:</strong> <span class="text-danger fw-bold"><?= h($numeroFichaMostrar) ?></span></div>
            <div><strong>FECHA:</strong> <?= h($fechaEmision) ?></div>
            <div><strong>SEDE:</strong> Bagua Grande</div>
            <?php if ($sol): ?>
                <div><strong>SOLICITUD:</strong> <?= h($sol['codigo_solicitud']) ?></div>
            <?php endif; ?>
        </div>
        <div class="qr-box">
            <div id="qrContainer" style="width:66px;height:66px;"></div>
            <span style="font-size:8.5px;color:#475569;margin-top:2px;">Verificación QR</span>
        </div>
    </div>

    <!-- SECCIÓN 1: IDENTIFICACIÓN DEL EQUIPO Y REGISTRO PATRIMONIAL -->
    <div class="sec-title">SECCIÓN I: DATOS DEL EQUIPO PATRIMONIAL (INVENTARIO IESTPU)</div>
    <table class="ficha-tbl">
        <tr>
            <th style="width:18%;">NOMBRE EQUIPO</th>
            <td style="width:32%;"><?= h(clean_tech_value($bien['denominacion'] ?? '')) ?></td>
            <th style="width:18%;">TIPO / MATERIAL</th>
            <td style="width:32%;"><?= h(clean_tech_value($bien['tipo_material'] ?? '')) ?></td>
        </tr>
        <tr>
            <th>PROGRAMA / ÁREA</th>
            <td><?= h(clean_tech_value($bien['programa'] ?? '')) ?></td>
            <th>OFICINA / AMBIENTE</th>
            <td><?= h(clean_tech_value($bien['area_ambiente'] ?? '')) ?></td>
        </tr>
        <tr>
            <th>MARCA</th>
            <td><?= h(clean_tech_value($bien['marca'] ?? '')) ?></td>
            <th>MODELO / N.° DE SERIE</th>
            <td><?= h(clean_tech_value($bien['modelo'] ?? '')) ?> · Serie: <?= h(clean_tech_value($bien['serie'] ?? '')) ?></td>
        </tr>
        <tr>
            <th>CÓD. INTERNO / APSTI</th>
            <td><strong><?= h(clean_tech_value($bien['codigo_apsti'] ?? '')) ?></strong> / <?= h(clean_tech_value($bien['codigo_interno'] ?? '')) ?></td>
            <th>CÓD. PATRIMONIAL / PROY.</th>
            <td><?= h(clean_tech_value($bien['correlativo'] ?? '')) ?> · MOD-741744</td>
        </tr>
        <tr>
            <th>FECHA COMPRA</th>
            <td><?= h(fmt_fecha($bien['fecha_compra'] ?? null)) ?></td>
            <th>COLOR / MEDIDAS</th>
            <td><?= h(clean_tech_value($bien['color'] ?? '')) ?> / <?= h(clean_tech_value($bien['dimensiones'] ?? '')) ?></td>
        </tr>
        <tr>
            <th>ESTADO FÍSICO</th>
            <td colspan="3">
                N <?= $chk('N') ?> &nbsp;&nbsp;
                MB <?= $chk('MB') ?> &nbsp;&nbsp;
                B <?= $chk('B') ?> &nbsp;&nbsp;
                R <?= $chk('R') ?> &nbsp;&nbsp;
                M <?= $chk('M') ?>
            </td>
        </tr>
        <tr>
            <th>ESPECIFICACIONES TÉCNICAS</th>
            <td colspan="3"><?= h(clean_especificaciones($bien['especificaciones'] ?? '')) ?></td>
        </tr>
    </table>

    <!-- SECCIÓN 2: DATOS DEL SOLICITANTE, VERIFICACIÓN ADMIN Y DIAGNÓSTICO -->
    <div class="sec-title">SECCIÓN II: DATOS DEL SOLICITANTE, VERIFICACIÓN ADMINISTRATIVA Y PROGRAMACIÓN</div>
    <table class="ficha-tbl">
        <tr>
            <th style="width:18%;">USUARIO / RESPONSABLE</th>
            <td style="width:32%;"><?= h($sol ? clean_tech_value($sol['usuario_reporta'] ?? '') : clean_tech_value($bien['responsable'] ?? '')) ?></td>
            <th style="width:18%;">ÁREA SOLICITANTE</th>
            <td style="width:32%;"><?= h($sol ? clean_tech_value($sol['area_solicitante'] ?? '') : clean_tech_value($bien['area_ambiente'] ?? '')) ?></td>
        </tr>
        <tr>
            <th>FALLA REPORTADA</th>
            <td colspan="3"><?= h($sol ? clean_tech_value($sol['falla_reportada'] ?? '') : 'Mantenimiento preventivo programado') ?></td>
        </tr>
        <tr>
            <th>VERIFICACIÓN ADMIN (PASO 2)</th>
            <td>
                <?php if ($esPaso2OMas && $sol): ?>
                    <?= h(clean_tech_value($sol['observacion_admin'] ?? '')) ?>
                <?php else: ?>
                    —
                <?php endif; ?>
            </td>
            <th>FECHA VERIFICACIÓN</th>
            <td><?= ($esPaso2OMas && $sol && !empty($sol['fecha_verificacion'])) ? h(fmt_fecha((string)$sol['fecha_verificacion'])) : '—' ?></td>
        </tr>
        <tr>
            <th>TÉCNICO ASIGNADO</th>
            <td><?= ($esPaso2OMas && $sol) ? h(clean_tech_value($sol['tecnico_asignado'] ?? '')) : '—' ?></td>
            <th>RESULTADO DEL SERVICIO</th>
            <td>
                <strong><?= h($resultadoServ) ?></strong>
                &nbsp;(Completo <?= $resultadoServ === 'SERVICIO COMPLETO' ? '[X]' : '[ ]' ?> · Pend. <?= $resultadoServ !== 'SERVICIO COMPLETO' ? '[X]' : '[ ]' ?>)
            </td>
        </tr>
    </table>

    <!-- SECCIÓN 3: INTERVENCIÓN TÉCNICA (SOLO DATOS REALES GUARDADOS EN BD) -->
    <div class="sec-title">SECCIÓN III: INTERVENCIÓN DEL ÁREA TÉCNICA Y DIAGNÓSTICO</div>
    <table class="ficha-tbl">
        <tr>
            <th style="width:18%;">FECHA VISITA TÉCNICA</th>
            <td style="width:32%;"><?= ($esPaso3 && !empty($sol['fecha_visita_programada'])) ? h(fmt_fecha((string)$sol['fecha_visita_programada'])) : '—' ?></td>
            <th style="width:18%;">PRÓXIMA VISITA PREV.</th>
            <td style="width:32%;"><?= ($esPaso3 && !empty($sol['proxima_visita_preventiva'])) ? h(fmt_fecha((string)$sol['proxima_visita_preventiva'])) : '—' ?></td>
        </tr>
        <tr>
            <th>DIAGNÓSTICO TÉCNICO</th>
            <td colspan="3"><?= ($esPaso3 && !empty(trim((string)($sol['diagnostico_tecnico'] ?? '')))) ? h((string)$sol['diagnostico_tecnico']) : '—' ?></td>
        </tr>
        <tr>
            <th>TRABAJOS Y REPUESTOS</th>
            <td colspan="3">
                <?php if ($esPaso3 && !empty(trim((string)($sol['acciones_realizadas'] ?? '')))): ?>
                    <?= h((string)$sol['acciones_realizadas']) ?>
                    <?php if (!empty(trim((string)($sol['repuestos_usados'] ?? '')))): ?>
                        <br><strong>Repuestos:</strong> <?= h((string)$sol['repuestos_usados']) ?>
                    <?php endif; ?>
                <?php else: ?>
                    —
                <?php endif; ?>
            </td>
        </tr>
    </table>

    <!-- TABLA 3 OFICIAL: REGISTRO DE ÓRDENES DE MANTENIMIENTO (7 FILAS) -->
    <div class="sec-title">3. REGISTRO DE ÓRDENES DE MANTENIMIENTO (PREVENTIVO / CORRECTIVO)</div>
    <table class="ficha-tbl">
        <thead>
            <tr>
                <th style="width:4%;text-align:center;">N.°</th>
                <th style="width:11%;">Fecha (D/M/A)</th>
                <th style="width:11%;">Tipo</th>
                <th style="width:14%;">Orden / Ficha</th>
                <th style="width:28%;">Descripción de la Actividad / Diagnóstico</th>
                <th style="width:11%;">Estado Serv.</th>
                <th style="width:13%;">Técnico / Resp.</th>
                <th style="width:8%;text-align:right;">Costo (S/)</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $totalFilas = max(7, count($ordenes));
            for ($i = 0; $i < $totalFilas; $i++):
                $o = $ordenes[$i] ?? null;
                if ($o):
                    $oCerrada = ($o['estado_flujo'] ?? '') === '3_ATENDIDO_AREA_TECNICA';
                    $descAct = $oCerrada
                        ? trim((string)($o['diagnostico_tecnico'] ?? '') . ' ' . (string)($o['acciones_realizadas'] ?? ''))
                        : (string)($o['falla_reportada'] ?? '');
            ?>
                <tr>
                    <td style="text-align:center;"><?= $i + 1 ?></td>
                    <td><?= h(fmt_fecha($o['fecha_visita_programada'] ?: $o['fecha_verificacion'])) ?></td>
                    <td><?= h(clean_tech_value($o['tipo_mantenimiento'] ?? 'CORRECTIVO')) ?></td>
                    <td style="white-space:nowrap;"><?= h($o['numero_ficha']) ?></td>
                    <td><?= h(clean_tech_value($descAct)) ?></td>
                    <td><?= h(clean_tech_value($o['resultado_servicio'] ?? 'PENDIENTE')) ?></td>
                    <td><?= h(clean_tech_value($o['tecnico_asignado'] ?? '')) ?></td>
                    <td style="text-align:right;"><?= number_format((float)($o['costo_estimado'] ?? 0), 2) ?></td>
                </tr>
            <?php else: ?>
                <tr>
                    <td style="text-align:center;"><?= $i + 1 ?></td>
                    <td>&nbsp;</td>
                    <td>&nbsp;</td>
                    <td>&nbsp;</td>
                    <td>&nbsp;</td>
                    <td>&nbsp;</td>
                    <td>&nbsp;</td>
                    <td>&nbsp;</td>
                </tr>
            <?php endif; endfor; ?>
        </tbody>
    </table>

    <!-- BLOQUE DE FIRMAS OFICIAL -->
    <div class="firmas-grid">
        <div>
            <div class="firma-linea">Colaborador / Técnico</div>
            <div class="text-muted">Área de Soporte y Mantenimiento</div>
        </div>
        <div>
            <div class="firma-linea">Coordinador / Usuario del Área</div>
            <div class="text-muted">Conformidad de Servicio</div>
        </div>
        <div>
            <div class="firma-linea">V.° B.° Administración</div>
            <div class="text-muted">IESTP «Utcubamba»</div>
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script>
    if (window.QRCode) {
        new QRCode(document.getElementById('qrContainer'), {
            text: <?= json_encode($qrPayload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>,
            width: 64,
            height: 64,
            correctLevel: QRCode.CorrectLevel.M
        });
    }
</script>
</body>
</html>
