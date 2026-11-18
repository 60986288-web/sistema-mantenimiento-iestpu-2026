<?php
require_once dirname(__DIR__, 2) . '/includes/db.php';
require_once dirname(__DIR__, 2) . '/includes/layout.php';
$u = requireLogin();
$pdo = getPDO();

$totBienes = (int)$pdo->query("SELECT COUNT(*) FROM bienes_inventario")->fetchColumn();
$totApsti = (int)$pdo->query("SELECT COUNT(*) FROM bienes_inventario WHERE programa='APSTI'")->fetchColumn();
$totCafetin = (int)$pdo->query("SELECT COUNT(*) FROM bienes_inventario WHERE programa='CAFETIN'")->fetchColumn();
$totSolicitudes = (int)$pdo->query("SELECT COUNT(*) FROM solicitudes_mantenimiento")->fetchColumn();
$ultimasSolicitudes = $pdo->query("SELECT * FROM solicitudes_mantenimiento ORDER BY id DESC LIMIT 6")->fetchAll();

renderHeader('Panel Principal', 'inicio');
?>
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2 no-print">
    <div>
        <h4 class="fw-bold mb-0">Panel de Control Institucional — IESTP Utcubamba</h4>
        <small class="text-secondary">Gestión integrada de Inventario Patrimonial, Formulario APSTI, Módulo Cafetín y Flujo de Fichas</small>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <a href="inventariar_apsti.php" class="btn btn-primary btn-sm fw-bold"><i class="fa-solid fa-desktop me-1"></i> Formulario Inventariar APSTI</a>
        <a href="cafetin.php" class="btn btn-warning btn-sm fw-bold"><i class="fa-solid fa-mug-hot me-1"></i> Módulo Cafetín</a>
        <a href="../docente/reportar.php" class="btn btn-danger btn-sm fw-bold"><i class="fa-solid fa-triangle-exclamation me-1"></i> Reportar Falla</a>
        <a href="verificacion_admin.php" class="btn btn-dark btn-sm fw-bold"><i class="fa-solid fa-clipboard-check me-1"></i> Verificar y Emitir Ficha</a>
        <a href="reportes.php?export=xls" class="btn btn-success btn-sm fw-bold"><i class="fa-solid fa-file-excel me-1"></i> Descargar Excel (.xls)</a>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card p-3 border-start border-4 border-primary shadow-sm h-100">
            <div class="small text-secondary fw-bold">TOTAL BIENES EN SISTEMA</div>
            <div class="fs-2 fw-bold text-primary"><?= $totBienes ?></div>
            <small class="text-muted d-block mb-2">Lab. 1 APSTI, Cafetín y Áreas</small>
            <a href="equipos.php" class="btn btn-outline-primary btn-sm mt-auto">Abrir Inventario General →</a>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card p-3 border-start border-4 border-info shadow-sm h-100">
            <div class="small text-secondary fw-bold">EQUIPOS INVENTARIADOS APSTI</div>
            <div class="fs-2 fw-bold text-info"><?= $totApsti ?></div>
            <small class="text-muted d-block mb-2">35 CPUs Lab. 1 + Ensamblaje + Coord.</small>
            <a href="inventariar_apsti.php" class="btn btn-outline-info btn-sm mt-auto text-dark fw-semibold">Inventariar / Reporte APSTI →</a>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card p-3 border-start border-4 border-warning shadow-sm h-100">
            <div class="small text-secondary fw-bold">BIENES MÓDULO CAFETÍN</div>
            <div class="fs-2 fw-bold text-warning"><?= $totCafetin ?></div>
            <small class="text-muted d-block mb-2">18 activos en Cafetín + 12 en Almacén</small>
            <a href="cafetin.php" class="btn btn-outline-warning btn-sm mt-auto text-dark fw-semibold">Abrir Módulo Cafetín →</a>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card p-3 border-start border-4 border-success shadow-sm h-100">
            <div class="small text-secondary fw-bold">SOLICITUDES Y FICHAS ACTIVAS</div>
            <div class="fs-2 fw-bold text-success"><?= $totSolicitudes ?></div>
            <small class="text-muted d-block mb-2">APSTI → Admin Verifica → Técnica</small>
            <a href="verificacion_admin.php" class="btn btn-outline-success btn-sm mt-auto fw-semibold">Gestionar Fichas →</a>
        </div>
    </div>
</div>

<div class="card p-3 shadow-sm mb-4">
    <div class="d-flex justify-content-between align-items-center mb-2">
        <h6 class="fw-bold mb-0"><i class="fa-solid fa-diagram-project text-primary me-1"></i> Estado en Tiempo Real del Flujo de Mantenimiento (APSTI → Administrador con Ficha → Área Técnica)</h6>
        <a href="buscar_equipo.php?q=002" class="btn btn-outline-dark btn-sm"><i class="fa-solid fa-magnifying-glass me-1"></i> Probar Buscador por Código/Serie (002)</a>
    </div>
    <div class="table-responsive">
        <table class="table table-sm table-bordered table-hover align-middle small mb-0">
            <thead class="table-dark">
                <tr>
                    <th>Solicitud</th>
                    <th>Código / Equipo</th>
                    <th>Área Solicitante</th>
                    <th>Falla Reportada</th>
                    <th>Etapa del Flujo</th>
                    <th>N.º Ficha Emitida</th>
                    <th>Estado Servicio</th>
                    <th class="no-print">Acciones Rápidas</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($ultimasSolicitudes as $s): ?>
                <tr>
                    <td class="fw-bold"><?= htmlspecialchars($s['codigo_solicitud']) ?></td>
                    <td class="fw-bold text-primary"><?= htmlspecialchars($s['codigo_bien']) ?><br><small class="text-muted"><?= htmlspecialchars($s['descripcion_bien']) ?></small></td>
                    <td><?= htmlspecialchars($s['area_solicitante']) ?></td>
                    <td><?= htmlspecialchars($s['falla_reportada']) ?></td>
                    <td>
                        <?php if ($s['estado_flujo'] === '1_REPORTADO_AREA'): ?>
                            <span class="badge bg-warning text-dark">1. Reportado (Pendiente Verificar)</span>
                        <?php elseif ($s['estado_flujo'] === '2_VERIFICADO_CON_FICHA'): ?>
                            <span class="badge bg-primary">2. Verificado Admin (Con Ficha)</span>
                        <?php else: ?>
                            <span class="badge bg-success">3. Atendido por Área Técnica</span>
                        <?php endif; ?>
                    </td>
                    <td class="fw-bold text-danger"><?= htmlspecialchars($s['numero_ficha'] ?: 'Sin emitir aún') ?></td>
                    <td><span class="badge <?= $s['resultado_servicio']==='SERVICIO COMPLETO'?'bg-success':'bg-secondary' ?>"><?= htmlspecialchars($s['resultado_servicio']) ?></span></td>
                    <td class="no-print">
                        <div class="d-flex gap-1">
                            <a href="verificacion_admin.php" class="btn btn-primary btn-sm py-0 px-2" title="Verificar / Emitir Ficha"><i class="fa-solid fa-check"></i> Verificar</a>
                            <a href="../tecnico/trabajos.php" class="btn btn-info btn-sm py-0 px-2 text-dark" title="Atender en Área Técnica"><i class="fa-solid fa-wrench"></i> Técnica</a>
                            <a href="ficha_mantenimiento.php?sol_id=<?= (int)$s['id'] ?>" class="btn btn-dark btn-sm py-0 px-2" title="Ver e Imprimir Ficha"><i class="fa-solid fa-file-pdf"></i> Ficha</a>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php renderFooter(); ?>
