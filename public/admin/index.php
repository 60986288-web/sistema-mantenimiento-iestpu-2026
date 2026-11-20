<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/layout.php';

$u = require_login();
$rol = get_user_role($u);
if ($rol === 'tecnico') {
    header('Location: ' . baseUrl('tecnico/trabajos.php'));
    exit;
}

require_role(['admin', 'coordinador_apsti', 'coordinador_cont', 'coordinador_enfe', 'coordinador_agro']);
$pdo = getPDO();
$scope = get_user_program_scope($u);

// Métricas filtradas según el rol/programa
if ($scope !== null) {
    $st = $pdo->prepare('SELECT COUNT(*) FROM bienes_inventario WHERE programa = ?');
    $st->execute([$scope]);
    $totalBienes = (int)$st->fetchColumn();

    $st = $pdo->prepare('SELECT COUNT(*) FROM bienes_inventario WHERE programa = ? AND requiere_mantenimiento = 1');
    $st->execute([$scope]);
    $totalConMant = (int)$st->fetchColumn();

    $st = $pdo->prepare("SELECT COUNT(*) FROM bienes_inventario WHERE programa = ? AND requiere_mantenimiento = 1 AND estado_servicio = 'Servicio Completo'");
    $st->execute([$scope]);
    $totalCompletos = (int)$st->fetchColumn();

    $st = $pdo->prepare("SELECT s.*, b.programa AS bien_programa, b.denominacion AS bien_denominacion FROM solicitudes_mantenimiento s INNER JOIN bienes_inventario b ON b.id = s.bien_id WHERE b.programa = ? ORDER BY s.id DESC");
    $st->execute([$scope]);
    $solicitudes = $st->fetchAll();
} else {
    $totalBienes = (int)$pdo->query('SELECT COUNT(*) FROM bienes_inventario')->fetchColumn();
    $totalConMant = (int)$pdo->query('SELECT COUNT(*) FROM bienes_inventario WHERE requiere_mantenimiento = 1')->fetchColumn();
    $totalCompletos = (int)$pdo->query("SELECT COUNT(*) FROM bienes_inventario WHERE requiere_mantenimiento = 1 AND estado_servicio = 'Servicio Completo'")->fetchColumn();
    $totalApsti = (int)$pdo->query("SELECT COUNT(*) FROM bienes_inventario WHERE programa = 'APSTI'")->fetchColumn();
    $totalCafetin = (int)$pdo->query("SELECT COUNT(*) FROM bienes_inventario WHERE programa = 'CAFETIN'")->fetchColumn();
    $solicitudes = $pdo->query('SELECT s.*, b.programa AS bien_programa, b.denominacion AS bien_denominacion FROM solicitudes_mantenimiento s LEFT JOIN bienes_inventario b ON b.id = s.bien_id ORDER BY s.id DESC')->fetchAll();
}

$porcCumplimiento = $totalConMant > 0 ? round(($totalCompletos / $totalConMant) * 100, 1) : 0.0;
$tituloPanel = $scope ? "Panel de Control — Área {$scope}" : 'Panel de Control Institucional';

renderHeader($tituloPanel, 'inicio');
?>

<!-- Banner Institucional con fotografía del campus -->
<div class="rounded-4 overflow-hidden mb-4 position-relative shadow-sm" style="background: linear-gradient(95deg, rgba(15,59,60,.92) 0%, rgba(15,59,60,.78) 58%, rgba(16,185,129,.42) 100%), url('<?= h(baseUrl('assets/img/fondo_iestpu.jpg')) ?>') center/cover no-repeat; min-height: 148px;">
    <div class="p-4 text-white d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
        <div>
            <span class="badge bg-success bg-opacity-75 mb-2">
                <?= $scope ? h("VISTA FILTRADA · PROGRAMA {$scope}") : 'ADMINISTRACIÓN GENERAL · IESTP UTCUBAMBA' ?>
            </span>
            <h2 class="h4 fw-bold mb-1"><?= h($tituloPanel) ?></h2>
            <p class="mb-0 small opacity-75">
                Gestión integrada de inventario patrimonial activo en BD, solicitudes de mantenimiento y emisión de fichas técnicas oficiales.
            </p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <?php if ($rol === 'admin' || $rol === 'coordinador_apsti'): ?>
                <a href="<?= h(baseUrl('admin/inventariar_apsti.php')) ?>" class="btn btn-sm btn-light fw-semibold">
                    <i class="fa-solid fa-desktop me-1 text-success"></i>Inventariar APSTI
                </a>
            <?php endif; ?>
            <a href="<?= h(baseUrl('docente/reportar.php')) ?>" class="btn btn-sm btn-warning fw-semibold text-dark">
                <i class="fa-solid fa-triangle-exclamation me-1"></i>Reportar Falla
            </a>
            <?php if ($rol === 'admin'): ?>
                <a href="<?= h(baseUrl('admin/verificacion_admin.php')) ?>" class="btn btn-sm btn-emerald fw-semibold">
                    <i class="fa-solid fa-clipboard-check me-1"></i>Verificar y Emitir Ficha
                </a>
            <?php endif; ?>
            <a href="<?= h(baseUrl('admin/reportes.php?export=excel')) ?>" class="btn btn-sm btn-outline-light fw-semibold">
                <i class="fa-solid fa-file-excel me-1"></i>Exportar Excel
            </a>
        </div>
    </div>
</div>

<!-- Tarjetas KPI -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="kpi-card">
            <div class="text-muted small fw-semibold text-uppercase">Bienes Activos en BD</div>
            <div class="fs-2 fw-bold mt-1"><?= $totalBienes ?></div>
            <div class="small text-muted">
                <?= $scope ? h("Registrados en el área {$scope}") : 'Base activa (Padrón Excel ref.: 3,475)' ?>
            </div>
            <a href="<?= h(baseUrl('admin/equipos.php')) ?>" class="btn btn-sm btn-outline-secondary w-100 mt-2">
                Abrir Inventario &rarr;
            </a>
        </div>
    </div>

    <div class="col-sm-6 col-xl-3">
        <div class="kpi-card" style="border-left-color:#0ea5e9;">
            <div class="text-muted small fw-semibold text-uppercase">Sujetos a Mantenimiento</div>
            <div class="fs-2 fw-bold mt-1"><?= $totalConMant ?></div>
            <div class="small text-muted">
                <?= $totalCompletos ?> completos (<?= number_format($porcCumplimiento, 1) ?>%) · <?= max(0, $totalConMant - $totalCompletos) ?> pendientes
            </div>
            <a href="<?= h(baseUrl('admin/reportes.php')) ?>" class="btn btn-sm btn-outline-secondary w-100 mt-2">
                Ver Reporte Consolidado &rarr;
            </a>
        </div>
    </div>

    <?php if ($rol === 'admin'): ?>
        <div class="col-sm-6 col-xl-3">
            <div class="kpi-card" style="border-left-color:#f59e0b;">
                <div class="text-muted small fw-semibold text-uppercase">APSTI / Módulo Cafetín</div>
                <div class="fs-2 fw-bold mt-1"><?= $totalApsti ?> <span class="fs-6 text-muted fw-normal">/ <?= $totalCafetin ?></span></div>
                <div class="small text-muted">Equipos APSTI registrados / Bienes Cafetín</div>
                <a href="<?= h(baseUrl('admin/cafetin.php')) ?>" class="btn btn-sm btn-outline-secondary w-100 mt-2">
                    Abrir Módulo Cafetín &rarr;
                </a>
            </div>
        </div>
    <?php else: ?>
        <div class="col-sm-6 col-xl-3">
            <div class="kpi-card" style="border-left-color:#f59e0b;">
                <div class="text-muted small fw-semibold text-uppercase">Cronograma Preventivo</div>
                <div class="fs-2 fw-bold mt-1"><?= max(0, $totalConMant - $totalCompletos) ?></div>
                <div class="small text-muted">Equipos pendientes en <?= h((string)$scope) ?></div>
                <a href="<?= h(baseUrl('admin/cronograma.php')) ?>" class="btn btn-sm btn-outline-secondary w-100 mt-2">
                    Ver Cronograma &rarr;
                </a>
            </div>
        </div>
    <?php endif; ?>

    <div class="col-sm-6 col-xl-3">
        <div class="kpi-card" style="border-left-color:#6366f1;">
            <div class="text-muted small fw-semibold text-uppercase">Solicitudes de Flujo</div>
            <div class="fs-2 fw-bold mt-1"><?= count($solicitudes) ?></div>
            <div class="small text-muted">Paso 1 Reporte &rarr; Paso 2 Admin &rarr; Paso 3 Técnica</div>
            <a href="<?= h(baseUrl($rol === 'admin' ? 'admin/verificacion_admin.php' : 'docente/reportar.php')) ?>" class="btn btn-sm btn-outline-secondary w-100 mt-2">
                Gestionar Solicitudes &rarr;
            </a>
        </div>
    </div>
</div>

<!-- Tabla de Flujo de Mantenimiento con máquina de estados estricta -->
<div class="card-clean p-3 p-md-4">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-3">
        <div>
            <h5 class="fw-bold mb-0"><i class="fa-solid fa-code-branch text-success me-2"></i>Estado en Tiempo Real del Flujo de Mantenimiento</h5>
            <div class="small text-muted">Las acciones se habilitan estrictamente según la etapa actual de cada solicitud.</div>
        </div>
        <a href="<?= h(baseUrl('admin/buscar_equipo.php')) ?>" class="btn btn-sm btn-outline-secondary">
            <i class="fa-solid fa-magnifying-glass me-1"></i>Buscar Equipo por Código/Serie
        </a>
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th class="nowrap-col">Solicitud</th>
                    <th>Código / Equipo</th>
                    <th>Área Solicitante</th>
                    <th>Falla Reportada</th>
                    <th class="nowrap-col">Etapa del Flujo</th>
                    <th class="nowrap-col">N.º Ficha Emitida</th>
                    <th class="nowrap-col">Estado Servicio</th>
                    <th class="nowrap-col text-end">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($solicitudes)): ?>
                    <tr>
                        <td colspan="8" class="text-center text-muted py-4">No hay solicitudes registradas en este ámbito.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($solicitudes as $sol):
                        $etapa = (string)($sol['estado_flujo'] ?? '1_REPORTADO_AREA');
                        $numFicha = trim((string)($sol['numero_ficha'] ?? ''));
                        $resServ = strtoupper(trim((string)($sol['resultado_servicio'] ?? 'PENDIENTE')));
                        $esPaso1 = ($etapa === '1_REPORTADO_AREA' || $numFicha === '');
                        $esPaso2 = ($etapa === '2_VERIFICADO_CON_FICHA' && $numFicha !== '');
                        $esPaso3 = ($etapa === '3_ATENDIDO_AREA_TECNICA');

                        $badgeEtapa = match ($etapa) {
                            '1_REPORTADO_AREA' => '<span class="badge bg-warning text-dark">1. Reportado (Pendiente Verificar)</span>',
                            '2_VERIFICADO_CON_FICHA' => '<span class="badge bg-info text-dark">2. Verificado Admin (Con Ficha)</span>',
                            '3_ATENDIDO_AREA_TECNICA' => '<span class="badge bg-success">3. Atendido por Área Técnica</span>',
                            default => '<span class="badge bg-secondary">' . h($etapa) . '</span>',
                        };
                        $badgeEstado = $resServ === 'SERVICIO COMPLETO'
                            ? '<span class="badge bg-success bg-opacity-10 text-success border border-success">SERVICIO COMPLETO</span>'
                            : '<span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary">PENDIENTE</span>';
                    ?>
                        <tr>
                            <td class="nowrap-col fw-bold"><?= h($sol['codigo_solicitud']) ?></td>
                            <td>
                                <div class="fw-bold text-primary"><?= h($sol['codigo_bien']) ?></div>
                                <div class="small text-muted"><?= h($sol['descripcion_bien']) ?></div>
                            </td>
                            <td class="small"><?= h($sol['area_solicitante']) ?></td>
                            <td class="small" style="max-width:260px;"><?= h($sol['falla_reportada']) ?></td>
                            <td class="nowrap-col"><?= $badgeEtapa ?></td>
                            <td class="nowrap-col">
                                <?php if ($numFicha !== ''): ?>
                                    <span class="fw-bold text-danger"><?= h($numFicha) ?></span>
                                <?php else: ?>
                                    <span class="text-muted small fst-italic">Sin emitir aún</span>
                                <?php endif; ?>
                            </td>
                            <td class="nowrap-col"><?= $badgeEstado ?></td>
                            <td class="nowrap-col text-end">
                                <div class="btn-group btn-group-sm" role="group">
                                    <?php if ($rol === 'admin'): ?>
                                        <?php if ($esPaso1): ?>
                                            <a href="<?= h(baseUrl('admin/verificacion_admin.php?sol_id=' . (int)$sol['id'])) ?>" class="btn btn-petrol" title="Verificar y emitir ficha">
                                                <i class="fa-solid fa-check me-1"></i>Verificar
                                            </a>
                                        <?php else: ?>
                                            <button type="button" class="btn btn-outline-secondary" disabled title="Ya verificada o cerrada">
                                                <i class="fa-solid fa-check-double"></i>
                                            </button>
                                        <?php endif; ?>

                                        <?php if ($esPaso2): ?>
                                            <a href="<?= h(baseUrl('tecnico/trabajos.php?sol_id=' . (int)$sol['id'])) ?>" class="btn btn-info text-dark" title="Registrar atención técnica">
                                                <i class="fa-solid fa-wrench me-1"></i>Técnica
                                            </a>
                                        <?php else: ?>
                                            <button type="button" class="btn btn-outline-secondary" disabled title="<?= $esPaso1 ? 'Requiere verificación previa en Paso 2' : 'Atención cerrada' ?>">
                                                <i class="fa-solid fa-wrench"></i>
                                            </button>
                                        <?php endif; ?>
                                    <?php endif; ?>

                                    <?php if (!$esPaso1): ?>
                                        <a href="<?= h(baseUrl('admin/ficha_mantenimiento.php?sol_id=' . (int)$sol['id'])) ?>" target="_blank" class="btn btn-emerald" title="Ver Ficha Oficial">
                                            <i class="fa-solid fa-file-pdf me-1"></i>Ficha
                                        </a>
                                    <?php else: ?>
                                        <button type="button" class="btn btn-outline-secondary" disabled title="La ficha se habilita tras la verificación administrativa">
                                            <i class="fa-solid fa-lock me-1"></i>Ficha
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php renderFooter(); ?>
