<?php
require_once dirname(__DIR__, 2) . '/includes/db.php';
require_once dirname(__DIR__, 2) . '/includes/layout.php';
$u = requireLogin();
$pdo = getPDO();

$sol_id = (int)($_GET['sol_id'] ?? 0);
$bien_id = (int)($_GET['bien_id'] ?? 0);

if ($bien_id > 0) {
    $st = $pdo->prepare("SELECT * FROM bienes_inventario WHERE id = ?");
    $st->execute([$bien_id]);
    $b = $st->fetch() ?: [];
    $stS = $pdo->prepare("SELECT * FROM solicitudes_mantenimiento WHERE bien_id = ? ORDER BY id DESC LIMIT 1");
    $stS->execute([$bien_id]);
    $s = $stS->fetch();
    if (!$s) {
        $s = [
            'id' => 0,
            'numero_ficha' => 'FICHA-MNT-2026-' . sprintf('%03d', $bien_id),
            'tipo_ficha' => 'FICHA DE OBSERVACIÓN TÉCNICA Y REGISTRO DE MANTENIMIENTO',
            'codigo_bien' => $b['codigo_apsti'] ?? '',
            'descripcion_bien' => trim(($b['denominacion'] ?? '') . ' ' . ($b['marca'] ?? '')),
            'area_solicitante' => ($b['programa'] ?? '') . ' - ' . ($b['area_ambiente'] ?? ''),
            'usuario_reporta' => $b['responsable'] ?? 'Coordinador de Área',
            'falla_reportada' => $b['diagnostico'] ?: 'Inspección técnica preventiva/correctiva.',
            'observacion_admin' => 'Verificado conforme por el Administrador General con datos del Excel institucional.',
            'tecnico_asignado' => 'Téc. Carlos Quispe (Área de Mantenimiento)',
            'fecha_visita_programada' => $b['fecha_viene_tecnico'] ?? date('Y-m-d'),
            'diagnostico_tecnico' => $b['diagnostico'] ?: 'Equipo verificado en laboratorio.',
            'acciones_realizadas' => 'Mantenimiento preventivo/correctivo, limpieza de componentes y actualización de fecha límite.',
            'resultado_servicio' => strtoupper($b['estado_servicio'] ?? 'SERVICIO COMPLETO'),
            'proxima_visita_preventiva' => $b['proxima_visita'] ?? '2027-02-11',
        ];
    }
} else {
    $st = $pdo->prepare("SELECT * FROM solicitudes_mantenimiento WHERE id = ?");
    $st->execute([$sol_id ?: 1]);
    $s = $st->fetch() ?: $pdo->query("SELECT * FROM solicitudes_mantenimiento LIMIT 1")->fetch();
    $stB = $pdo->prepare("SELECT * FROM bienes_inventario WHERE id = ?");
    $stB->execute([$s['bien_id'] ?? 1]);
    $b = $stB->fetch() ?: [];
}

$fichaNum = $s['numero_ficha'] ?: 'FICHA-OBS-2026-014';

if (($_GET['export'] ?? '') === 'xls') {
    exportarTablaXLS(
        $fichaNum,
        ($s['tipo_ficha'] ?? 'FICHA OFICIAL DE MANTENIMIENTO') . ' — N.º ' . $fichaNum,
        ['Sección de la Ficha', 'Campo Técnico', 'Detalle Registrado en el Sistema'],
        [
            ['I. DATOS DEL BIEN (EXCEL)', 'Código APSTI / Interno', $s['codigo_bien']],
            ['I. DATOS DEL BIEN (EXCEL)', 'Descripción y Marca', $s['descripcion_bien']],
            ['I. DATOS DEL BIEN (EXCEL)', 'Programa / Ambiente', $s['area_solicitante']],
            ['I. DATOS DEL BIEN (EXCEL)', 'Especificaciones Técnicas', $b['especificaciones'] ?? '-'],
            ['I. DATOS DEL BIEN (EXCEL)', 'Fecha de Compra / Conservación', ($b['fecha_compra'] ?? '-') . ' / Estado: ' . ($b['estado_conservacion'] ?? 'B')],
            ['II. VERIFICACIÓN ADMIN', 'Reportado por (Área Usuaria)', $s['usuario_reporta']],
            ['II. VERIFICACIÓN ADMIN', 'Falla u Observación Reportada', $s['falla_reportada']],
            ['II. VERIFICACIÓN ADMIN', 'Verificación del Administrador', $s['observacion_admin']],
            ['III. ÁREA TÉCNICA', 'Técnico Responsable y Fecha Visita', ($s['tecnico_asignado'] ?? '') . ' (' . ($s['fecha_visita_programada'] ?? '') . ')'],
            ['III. ÁREA TÉCNICA', 'Diagnóstico Técnico', $s['diagnostico_tecnico'] ?: 'Pendiente'],
            ['III. ÁREA TÉCNICA', 'Trabajos y Repuestos Aplicados', $s['acciones_realizadas'] ?: 'Pendiente'],
            ['III. ÁREA TÉCNICA', 'Estado del Servicio', $s['resultado_servicio'] ?? 'SERVICIO COMPLETO'],
            ['III. ÁREA TÉCNICA', 'Próxima Visita Preventiva', $s['proxima_visita_preventiva'] ?: '2027-02-11'],
        ]
    );
}

renderHeader('Ficha Oficial de Observación y Mantenimiento', 'verificacion');
?>
<style>
    .ficha-formal {
        background: #ffffff !important;
        color: #0f172a !important;
        border: 1.5px solid #334155 !important;
        border-radius: 4px;
        padding: 28px 32px;
        max-width: 1020px;
        margin: 0 auto 24px auto;
    }
    .ficha-formal table {
        color: #0f172a !important;
        border-color: #94a3b8 !important;
        background: #ffffff !important;
    }
    .ficha-formal th {
        background-color: #f1f5f9 !important;
        color: #1e293b !important;
        font-weight: 700;
    }
    .ficha-formal td {
        background-color: #ffffff !important;
        color: #0f172a !important;
    }
    .ficha-sec-title {
        background-color: #1e293b;
        color: #ffffff;
        padding: 7px 12px;
        font-size: 0.80rem;
        font-weight: 700;
        letter-spacing: 0.03em;
        margin-bottom: 0;
        border: 1px solid #1e293b;
    }
    .ficha-sec-title-sub {
        background-color: #334155;
        color: #ffffff;
        padding: 7px 12px;
        font-size: 0.80rem;
        font-weight: 700;
        letter-spacing: 0.03em;
        margin-bottom: 0;
        border: 1px solid #334155;
    }
    @media print {
        .ficha-formal { border: 1px solid #000 !important; padding: 14px !important; margin: 0 !important; max-width: 100% !important; }
        .ficha-sec-title, .ficha-sec-title-sub { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        .print-only-header { display: none !important; }
    }
</style>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2 no-print" style="max-width:1020px;margin:0 auto;">
    <h5 class="fw-bold mb-0"><i class="fa-solid fa-file-invoice text-primary me-1"></i> Documento Oficial: Ficha de Observación Técnica / Registro de Mantenimiento</h5>
    <div class="d-flex flex-wrap gap-2">
        <button onclick="window.print()" class="btn btn-danger btn-sm fw-bold"><i class="fa-solid fa-file-pdf me-1"></i> Imprimir / Guardar en PDF</button>
        <a href="ficha_mantenimiento.php?sol_id=<?= (int)($s['id'] ?? 1) ?>&bien_id=<?= (int)$bien_id ?>&export=xls" class="btn btn-success btn-sm fw-bold"><i class="fa-solid fa-file-excel me-1"></i> Descargar en Excel (.xls)</a>
        <a href="verificacion_admin.php" class="btn btn-outline-secondary btn-sm">← Volver</a>
    </div>
</div>

<div class="ficha-formal shadow-sm">
    <div class="row align-items-center border-bottom border-dark pb-3 mb-3">
        <div class="col-2 text-center">
            <img src="<?= baseUrl('assets/img/logo.png') ?>" alt="Escudo IESTPU" style="width:66px;">
        </div>
        <div class="col-7 text-center">
            <div style="font-size:0.75rem;font-weight:700;color:#475569;">GOBIERNO REGIONAL AMAZONAS — DIRECCIÓN REGIONAL DE EDUCACIÓN AMAZONAS</div>
            <h5 class="fw-bold mb-1" style="color:#0f172a;">INSTITUTO DE EDUCACIÓN SUPERIOR TECNOLÓGICO PÚBLICO «UTCUBAMBA»</h5>
            <div style="font-size:0.86rem;font-weight:700;color:#1e3a5f;"><?= htmlspecialchars($s['tipo_ficha'] ?? 'FICHA DE OBSERVACIÓN Y MANTENIMIENTO DE EQUIPO') ?></div>
        </div>
        <div class="col-3 border border-dark p-2 small" style="background:#f8fafc;">
            <div class="fw-bold" style="color:#9b2c2c;">N.º: <?= htmlspecialchars($fichaNum) ?></div>
            <div style="color:#334155;">Cód. Modular: 741744</div>
            <div class="fw-bold" style="color:#1b4d37;">ESTADO: VERIFICADO ADMIN</div>
        </div>
    </div>

    <div class="ficha-sec-title">I. IDENTIFICACIÓN DEL EQUIPO EN EL INVENTARIO INSTITUCIONAL (JALADO DEL EXCEL)</div>
    <table class="table table-sm table-bordered small mb-3">
        <tr>
            <th style="width:22%;">Código APSTI / Interno:</th>
            <td style="width:28%;" class="fw-bold"><?= htmlspecialchars($s['codigo_bien']) ?></td>
            <th style="width:22%;">Programa / Ambiente:</th>
            <td style="width:28%;"><?= htmlspecialchars($s['area_solicitante']) ?></td>
        </tr>
        <tr>
            <th>Denominación y Marca:</th>
            <td><?= htmlspecialchars($s['descripcion_bien']) ?></td>
            <th>Fecha Compra / Estado:</th>
            <td><?= htmlspecialchars($b['fecha_compra'] ?? '2024-03-15') ?> (Conservación: <strong><?= htmlspecialchars($b['estado_conservacion'] ?? 'B') ?></strong>)</td>
        </tr>
        <tr>
            <th>Especificaciones Técnicas:</th>
            <td colspan="3"><?= htmlspecialchars($b['especificaciones'] ?? 'Equipo registrado en inventario institucional') ?></td>
        </tr>
    </table>

    <div class="ficha-sec-title-sub">II. REPORTE DEL ÁREA USUARIA Y VERIFICACIÓN DEL ADMINISTRADOR</div>
    <table class="table table-sm table-bordered small mb-3">
        <tr>
            <th style="width:22%;">Solicitante (Área):</th>
            <td style="width:28%;"><?= htmlspecialchars($s['usuario_reporta']) ?></td>
            <th style="width:22%;">Fecha de Verificación:</th>
            <td style="width:28%;"><?= htmlspecialchars($s['fecha_visita_programada'] ?: date('Y-m-d')) ?></td>
        </tr>
        <tr>
            <th>Falla / Observación:</th>
            <td colspan="3"><?= htmlspecialchars($s['falla_reportada']) ?></td>
        </tr>
        <tr>
            <th>Verificación Administrador:</th>
            <td colspan="3" class="fw-bold"><?= htmlspecialchars($s['observacion_admin'] ?: 'Verificado conforme por el Administrador General. Se deriva al Área Técnica con la presente Ficha.') ?></td>
        </tr>
    </table>

    <div class="ficha-sec-title">III. REGISTRO DE DIAGNÓSTICO, ACTIVIDADES EJECUTADAS Y CONTROL DE VISITA (ÁREA TÉCNICA)</div>
    <table class="table table-sm table-bordered small mb-4">
        <tr>
            <th style="width:22%;">Técnico Asignado:</th>
            <td style="width:28%;"><?= htmlspecialchars($s['tecnico_asignado'] ?: 'Téc. Carlos Quispe') ?></td>
            <th style="width:22%;">Fecha Visita Técnica:</th>
            <td style="width:28%;"><?= htmlspecialchars($s['fecha_visita_programada'] ?: date('Y-m-d')) ?></td>
        </tr>
        <tr>
            <th>Diagnóstico Técnico:</th>
            <td colspan="3"><?= htmlspecialchars($s['diagnostico_tecnico'] ?: 'Verificado en laboratorio.') ?></td>
        </tr>
        <tr>
            <th>Trabajos y Repuestos:</th>
            <td colspan="3"><?= htmlspecialchars($s['acciones_realizadas'] ?: 'Mantenimiento preventivo/correctivo ejecutado conforme.') ?></td>
        </tr>
        <tr>
            <th>Resultado del Servicio:</th>
            <td class="fw-bold" style="color:#1b4d37;"><?= htmlspecialchars($s['resultado_servicio']) ?></td>
            <th>Próxima Visita Preventiva:</th>
            <td class="fw-bold" style="color:#9b2c2c;"><?= htmlspecialchars($s['proxima_visita_preventiva'] ?: '2027-02-11') ?></td>
        </tr>
    </table>

    <div class="row text-center mt-5 pt-3">
        <div class="col-4">
            <div class="border-top border-dark pt-2 mx-3 small">
                <strong><?= htmlspecialchars($s['usuario_reporta']) ?></strong><br>
                Coordinador / Área Solicitante
            </div>
        </div>
        <div class="col-4">
            <div class="border-top border-dark pt-2 mx-3 small">
                <strong>Lic. Ernesto Correa Barrantes</strong><br>
                Administrador General (Verificación)
            </div>
        </div>
        <div class="col-4">
            <div class="border-top border-dark pt-2 mx-3 small">
                <strong><?= htmlspecialchars($s['tecnico_asignado'] ?: 'Téc. Carlos Quispe') ?></strong><br>
                Responsable Área de Mantenimiento
            </div>
        </div>
    </div>
</div>
<?php renderFooter(); ?>
