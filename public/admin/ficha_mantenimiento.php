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
            'numero_ficha' => 'FICHA-OBS-2026-' . sprintf('%03d', $bien_id),
            'tipo_ficha' => 'FICHA OFICIAL DE OBSERVACIÓN TÉCNICA Y REGISTRO DE MANTENIMIENTO DE EQUIPO',
            'codigo_bien' => $b['codigo_apsti'] ?? '',
            'descripcion_bien' => trim(($b['denominacion'] ?? '') . ' / ' . ($b['marca'] ?? '') . (!empty($b['color']) && $b['color'] !== '-' ? ' (Color ' . $b['color'] . ')' : '')),
            'area_solicitante' => ($b['programa'] ?? 'APSTI') . ' — ' . ($b['area_ambiente'] ?? 'Laboratorio de Cómputo I'),
            'usuario_reporta' => $b['responsable'] ?? 'Ing. Carlos Enrique Sandoval Dávila (Coordinador APSTI)',
            'fecha_reporte' => ($b['fecha_viene_tecnico'] ?? date('Y-m-d')) . ' 08:30',
            'fecha_verificacion' => ($b['fecha_viene_tecnico'] ?? date('Y-m-d')) . ' 09:15',
            'falla_reportada' => $b['diagnostico'] ?: 'Inspección técnica preventiva y verificación general de operatividad.',
            'observacion_admin' => 'VERIFICADO CONFORME -> Se deriva al Área Técnica con la presente Ficha.',
            'tecnico_asignado' => 'Téc. Carlos Quispe (Área de Mantenimiento y Soporte)',
            'fecha_visita_programada' => $b['fecha_viene_tecnico'] ?? date('Y-m-d'),
            'diagnostico_tecnico' => $b['diagnostico'] ?: 'Equipo inspeccionado en laboratorio, sin anomalías críticas.',
            'acciones_realizadas' => 'Limpieza interna preventiva, verificación de conectores y prueba de operatividad conforme.',
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

// Parse specifications & peripherals cleanly from $b['especificaciones']
$specRaw = (string)($b['especificaciones'] ?? 'Equipo registrado en el inventario institucional');
$specMain = $specRaw;
$perifericosTxt = 'Registrados en ficha patrimonial del ambiente';
if (stripos($specRaw, 'Monitor:') !== false) {
    $partsSpec = preg_split('/\|\s*(?=Monitor:)/i', $specRaw, 2);
    $specMain = trim(str_replace(' | ', ', ', $partsSpec[0] ?? $specRaw));
    $perifericosTxt = trim(str_replace(' | ', ', ', $partsSpec[1] ?? $perifericosTxt));
}

$estadoMap = ['N' => 'Nuevo (N)', 'MB' => 'Muy Bueno (MB)', 'B' => 'Bueno (B)', 'R' => 'Regular (R)', 'M' => 'Malo (M)', 'MM' => 'Muy Malo (MM)'];
$estCod = $b['estado_conservacion'] ?? 'B';
$estTexto = ($estadoMap[$estCod] ?? $estCod) . ' / En Uso (U)';
$progNombreMap = [
    'APSTI' => 'APSTI (Arquitectura de Plataformas y Servicios de TI)',
    'CONT' => 'Contabilidad',
    'ENFE' => 'Enfermería Técnica',
    'AGRO' => 'Producción Agropecuaria',
    'CAFETIN' => 'Módulo de Servicios — Cafetín Institucional',
    'ADMIN' => 'Administración y Dirección General',
];
$progFull = $progNombreMap[$b['programa'] ?? 'APSTI'] ?? ($b['programa'] ?? 'APSTI');
$esCompleto = stripos((string)($s['resultado_servicio'] ?? ''), 'COMPLETO') !== false;
$fichaNum = $s['numero_ficha'] ?: 'FICHA-OBS-2026-014';

// Determine DNI for solicitante
$solicitanteNombre = $s['usuario_reporta'] ?? 'Ing. Carlos Sandoval Dávila';
$solicitanteDni = '44839017';
if (stripos($solicitanteNombre, 'Stalin') !== false) $solicitanteDni = '41839578';
elseif (stripos($solicitanteNombre, 'Lluvitza') !== false) $solicitanteDni = '47233585';
elseif (stripos($solicitanteNombre, 'Ernesto') !== false) $solicitanteDni = '27722958';

if (($_GET['export'] ?? '') === 'xls') {
    exportarTablaXLS(
        $fichaNum,
        'FICHA OFICIAL DE OBSERVACIÓN TÉCNICA Y REGISTRO DE MANTENIMIENTO — N.º ' . $fichaNum,
        ['Sección de la Ficha', 'Campo Técnico', 'Detalle Registrado en el Sistema'],
        [
            ['I. IDENTIFICACIÓN DEL EQUIPO', 'Código APSTI / Interno', $s['codigo_bien']],
            ['I. IDENTIFICACIÓN DEL EQUIPO', 'Denominación / Marca', $s['descripcion_bien']],
            ['I. IDENTIFICACIÓN DEL EQUIPO', 'Programa de Estudios', $progFull],
            ['I. IDENTIFICACIÓN DEL EQUIPO', 'Área / Laboratorio', $b['area_ambiente'] ?? $s['area_solicitante']],
            ['I. IDENTIFICACIÓN DEL EQUIPO', 'Especificaciones Técnicas', $specMain],
            ['I. IDENTIFICACIÓN DEL EQUIPO', 'Periféricos Asociados', $perifericosTxt],
            ['I. IDENTIFICACIÓN DEL EQUIPO', 'Fecha Compra / Conservación', ($b['fecha_compra'] ?? '-') . ' — ' . $estTexto],
            ['II. OBSERVACIÓN Y VERIFICACIÓN', 'Solicitante (Área)', $solicitanteNombre],
            ['II. OBSERVACIÓN Y VERIFICACIÓN', 'Falla / Observación Reportada', $s['falla_reportada']],
            ['II. OBSERVACIÓN Y VERIFICACIÓN', 'Verificación del Administrador', $s['observacion_admin']],
            ['III. ÁREA TÉCNICA', 'Técnico Asignado y Fecha Visita', ($s['tecnico_asignado'] ?? '') . ' (' . ($s['fecha_visita_programada'] ?? '') . ')'],
            ['III. ÁREA TÉCNICA', 'Diagnóstico Técnico', $s['diagnostico_tecnico'] ?: 'Verificado en laboratorio.'],
            ['III. ÁREA TÉCNICA', 'Trabajos y Repuestos Aplicados', $s['acciones_realizadas'] ?: 'Mantenimiento ejecutado conforme.'],
            ['III. ÁREA TÉCNICA', 'Resultado del Servicio', $s['resultado_servicio'] ?? 'SERVICIO COMPLETO'],
            ['III. ÁREA TÉCNICA', 'Próxima Visita Preventiva', $s['proxima_visita_preventiva'] ?: '2027-02-11'],
        ]
    );
}

renderHeader('Ficha Oficial de Observación y Mantenimiento', 'verificacion');
?>
<style>
    .ficha-hoja {
        background: #ffffff !important;
        color: #1e293b !important;
        border: 1.5px solid #94a3b8 !important;
        border-radius: 6px;
        padding: 24px 26px;
        max-width: 1050px;
        margin: 0 auto 24px auto;
        box-shadow: 0 6px 24px rgba(0,0,0,0.08);
    }
    .ficha-top-box {
        display: flex;
        align-items: stretch;
        border: 1.5px solid #334155;
        background: #ffffff;
        margin-bottom: 16px;
    }
    .ficha-top-left {
        flex: 1;
        display: flex;
        align-items: center;
        gap: 16px;
        padding: 12px 16px;
    }
    .ficha-top-right {
        width: 255px;
        border-left: 1.5px solid #334155;
        padding: 12px 14px;
        display: flex;
        flex-direction: column;
        justify-content: center;
        font-size: 0.80rem;
        line-height: 1.55;
        background: #f8fafc;
    }
    .ficha-sec-box {
        border: 1px solid #94a3b8;
        border-radius: 4px;
        margin-bottom: 14px;
        overflow: hidden;
        background: #ffffff;
    }
    .ficha-sec-hdr {
        color: #ffffff;
        font-weight: 700;
        font-size: 0.80rem;
        letter-spacing: 0.02em;
        padding: 7px 14px;
        text-transform: uppercase;
    }
    .ficha-hdr-navy   { background: #16377a; }
    .ficha-hdr-purple { background: #6d1ab7; }
    .ficha-hdr-teal   { background: #0d8c78; }
    .ficha-sec-body {
        padding: 12px 16px;
        font-size: 0.84rem;
        color: #1e293b;
        background: #ffffff;
    }
    .ficha-grid-2 {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 7px 26px;
    }
    .ficha-kv {
        display: flex;
        gap: 8px;
        line-height: 1.45;
    }
    .ficha-k {
        font-weight: 700;
        color: #334155;
        min-width: 155px;
        flex-shrink: 0;
    }
    .ficha-v {
        color: #0f172a;
    }
    .ficha-bullet {
        margin-bottom: 7px;
        line-height: 1.48;
    }
    .ficha-bullet:last-child { margin-bottom: 0; }
    .ficha-firmas-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 18px;
        margin-top: 20px;
    }
    .ficha-firma-card {
        background: #f8fafc;
        border: 1px solid #cbd5e1;
        border-radius: 6px;
        padding: 36px 14px 14px 14px;
        text-align: center;
        color: #1e293b;
    }
    .ficha-firma-linea {
        border-top: 1.2px solid #334155;
        width: 72%;
        margin: 0 auto 8px auto;
    }
    @media (max-width: 768px) {
        .ficha-top-box { flex-direction: column; }
        .ficha-top-right { width: 100%; border-left: none; border-top: 1.5px solid #334155; }
        .ficha-grid-2, .ficha-firmas-grid { grid-template-columns: 1fr; }
    }
    @media print {
        .ficha-hoja {
            border: 1px solid #334155 !important;
            box-shadow: none !important;
            padding: 14px !important;
            margin: 0 !important;
            max-width: 100% !important;
        }
        .ficha-hdr-navy   { background: #16377a !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        .ficha-hdr-purple { background: #6d1ab7 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        .ficha-hdr-teal   { background: #0d8c78 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        .ficha-firma-card { background: #f8fafc !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        .print-only-header { display: none !important; }
    }
</style>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2 no-print" style="max-width:1050px;margin:0 auto;">
    <h5 class="fw-bold mb-0"><i class="fa-solid fa-file-invoice text-primary me-1"></i> Documento Oficial: Ficha de Observación Técnica / Registro de Mantenimiento</h5>
    <div class="d-flex flex-wrap gap-2">
        <button onclick="window.print()" class="btn btn-danger btn-sm fw-bold"><i class="fa-solid fa-file-pdf me-1"></i> Imprimir / Guardar en PDF</button>
        <a href="ficha_mantenimiento.php?sol_id=<?= (int)($s['id'] ?? 1) ?>&bien_id=<?= (int)$bien_id ?>&export=xls" class="btn btn-success btn-sm fw-bold"><i class="fa-solid fa-file-excel me-1"></i> Descargar Excel (.xls)</a>
        <a href="verificacion_admin.php" class="btn btn-outline-secondary btn-sm">← Volver</a>
    </div>
</div>

<div class="ficha-hoja">
    <!-- ENCABEZADO OFICIAL -->
    <div class="ficha-top-box">
        <div class="ficha-top-left">
            <img src="<?= baseUrl('assets/img/logo.png') ?>" alt="Escudo IESTPU" style="width:62px;height:70px;object-fit:contain;">
            <div>
                <div style="font-size:0.75rem;font-weight:700;color:#475569;letter-spacing:0.03em;">GOBIERNO REGIONAL AMAZONAS — DIRECCIÓN REGIONAL DE EDUCACIÓN AMAZONAS</div>
                <div style="font-size:1.08rem;font-weight:800;color:#0f172a;margin:2px 0;">INSTITUTO DE EDUCACIÓN SUPERIOR TECNOLÓGICO PÚBLICO «UTCUBAMBA»</div>
                <div style="font-size:0.86rem;font-weight:700;color:#1d4ed8;">FICHA OFICIAL DE OBSERVACIÓN TÉCNICA Y REGISTRO DE MANTENIMIENTO DE EQUIPO</div>
            </div>
        </div>
        <div class="ficha-top-right">
            <div style="font-weight:800;color:#dc2626;">FICHA N.º: <?= htmlspecialchars($fichaNum) ?></div>
            <div style="font-weight:600;color:#334155;">CÓD. MODULAR: 741744</div>
            <div style="font-weight:800;color:#15803d;">ESTADO: VERIFICADO POR ADMIN</div>
        </div>
    </div>

    <!-- SECCIÓN I: AZUL MARINO -->
    <div class="ficha-sec-box">
        <div class="ficha-sec-hdr ficha-hdr-navy">I. IDENTIFICACIÓN DEL EQUIPO EN EL INVENTARIO INSTITUCIONAL (JALADO AUTOMÁTICAMENTE DEL EXCEL)</div>
        <div class="ficha-sec-body">
            <div class="ficha-grid-2">
                <div class="ficha-kv"><span class="ficha-k">Código APSTI / Interno:</span><span class="ficha-v fw-bold"><?= htmlspecialchars($s['codigo_bien']) ?></span></div>
                <div class="ficha-kv"><span class="ficha-k">Programa de Estudios:</span><span class="ficha-v"><?= htmlspecialchars($progFull) ?></span></div>

                <div class="ficha-kv"><span class="ficha-k">Denominación / Marca:</span><span class="ficha-v"><?= htmlspecialchars($s['descripcion_bien']) ?></span></div>
                <div class="ficha-kv"><span class="ficha-k">Área / Laboratorio:</span><span class="ficha-v"><?= htmlspecialchars($b['area_ambiente'] ?? $s['area_solicitante']) ?></span></div>

                <div class="ficha-kv"><span class="ficha-k">Especificaciones Técnicas:</span><span class="ficha-v"><?= htmlspecialchars($specMain) ?></span></div>
                <div class="ficha-kv"><span class="ficha-k">Periféricos Asociados:</span><span class="ficha-v"><?= htmlspecialchars($perifericosTxt) ?></span></div>

                <div class="ficha-kv"><span class="ficha-k">Fecha de Compra / Alta:</span><span class="ficha-v"><?= htmlspecialchars($b['fecha_compra'] ?? '15/03/2024') ?></span></div>
                <div class="ficha-kv"><span class="ficha-k">Estado Conservación:</span><span class="ficha-v"><?= htmlspecialchars($estTexto) ?></span></div>
            </div>
        </div>
    </div>

    <!-- SECCIÓN II: MORADO -->
    <div class="ficha-sec-box">
        <div class="ficha-sec-hdr ficha-hdr-purple">II. FICHA DE OBSERVACIÓN INICIAL (REPORTE DEL COORDINADOR DE ÁREA Y VERIFICACIÓN DEL ADMINISTRADOR)</div>
        <div class="ficha-sec-body">
            <div class="ficha-bullet">
                <strong>• Solicitante (Área): <?= htmlspecialchars($solicitanteNombre) ?></strong>
                &nbsp;&nbsp;&nbsp;
                <strong>• Fecha de Reporte: <?= htmlspecialchars($s['fecha_reporte'] ?? date('d/m/Y H:i')) ?></strong>
            </div>
            <div class="ficha-bullet">
                • Falla / Observación Reportada: <?= htmlspecialchars($s['falla_reportada']) ?>
            </div>
            <div class="ficha-bullet" style="color:#6d1ab7;font-weight:700;">
                • Verificación del Administrador (Lic. Ernesto Correa Barrantes): <?= htmlspecialchars($s['observacion_admin'] ?: 'VERIFICADO CONFORME -> Se deriva al Área Técnica con la presente Ficha.') ?>
            </div>
        </div>
    </div>

    <!-- SECCIÓN III: VERDE TURQUESA -->
    <div class="ficha-sec-box">
        <div class="ficha-sec-hdr ficha-hdr-teal">III. REGISTRO DE DIAGNÓSTICO, ACTIVIDADES EJECUTADAS Y CONTROL DE VISITA (ÁREA TÉCNICA DE MANTENIMIENTO)</div>
        <div class="ficha-sec-body">
            <div class="ficha-bullet">
                <strong>• Técnico Asignado: <?= htmlspecialchars($s['tecnico_asignado'] ?: 'Téc. Carlos Quispe (Área de Mantenimiento y Soporte)') ?></strong>
                &nbsp;&nbsp;
                <strong>• Fecha Visita Técnica: <?= htmlspecialchars($s['fecha_visita_programada'] ?: date('d/m/Y')) ?></strong>
                &nbsp;&nbsp;
                <strong>• Tipo: [ X ] Preventivo &nbsp; [ X ] Correctivo</strong>
            </div>
            <div class="ficha-bullet">
                • Diagnóstico Técnico: <?= htmlspecialchars($s['diagnostico_tecnico'] ?: 'Se inspeccionó el equipo verificando conectividad, componentes internos y estabilidad operativa.') ?>
            </div>
            <div class="ficha-bullet">
                • Trabajos y Repuestos Aplicados: <?= htmlspecialchars($s['acciones_realizadas'] ?: 'Mantenimiento preventivo y correctivo integral, limpieza interna y prueba de operatividad.') ?>
            </div>
            <div class="ficha-bullet" style="color:#15803d;font-weight:700;">
                • Resultado del Servicio:
                <?= $esCompleto ? '[ X ] SERVICIO COMPLETO (Equipo Operativo) &nbsp;&nbsp; [ &nbsp; ] SERVICIO PENDIENTE' : '[ &nbsp; ] SERVICIO COMPLETO (Equipo Operativo) &nbsp;&nbsp; [ X ] SERVICIO PENDIENTE' ?>
            </div>
            <div class="ficha-bullet" style="color:#1d4ed8;font-weight:700;">
                • Programación Automática en Cronograma Preventivo: Próxima Visita Técnica programada para el <?= htmlspecialchars($s['proxima_visita_preventiva'] ?: '11/02/2027') ?> (Frecuencia Trimestral: 90 días).
            </div>
        </div>
    </div>

    <!-- BLOQUE DE 3 FIRMAS EN TARJETAS -->
    <div class="ficha-firmas-grid">
        <div class="ficha-firma-card">
            <div class="ficha-firma-linea"></div>
            <div style="font-weight:700;font-size:0.86rem;color:#0f172a;"><?= htmlspecialchars($solicitanteNombre) ?></div>
            <div style="font-size:0.76rem;color:#475569;">Coordinador / Área Solicitante</div>
            <div style="font-size:0.74rem;color:#64748b;margin-top:2px;">DNI: <?= htmlspecialchars($solicitanteDni) ?></div>
        </div>
        <div class="ficha-firma-card">
            <div class="ficha-firma-linea"></div>
            <div style="font-weight:700;font-size:0.86rem;color:#0f172a;">Lic. Ernesto Correa Barrantes</div>
            <div style="font-size:0.76rem;color:#475569;">Administrador General (Verificación)</div>
            <div style="font-size:0.74rem;color:#64748b;margin-top:2px;">DNI: 27722958</div>
        </div>
        <div class="ficha-firma-card">
            <div class="ficha-firma-linea"></div>
            <div style="font-weight:700;font-size:0.86rem;color:#0f172a;"><?= htmlspecialchars($s['tecnico_asignado'] ?: 'Téc. Carlos Quispe') ?></div>
            <div style="font-size:0.76rem;color:#475569;">Responsable Área de Mantenimiento</div>
            <div style="font-size:0.74rem;color:#64748b;margin-top:2px;">DNI: 46998877</div>
        </div>
    </div>
</div>
<?php renderFooter(); ?>
