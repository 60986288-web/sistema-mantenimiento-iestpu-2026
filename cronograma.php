<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/layout.php';

$u = require_role(['admin', 'tecnico', 'coordinador_apsti', 'coordinador_cont', 'coordinador_enfe', 'coordinador_agro']);
$scope = get_user_program_scope($u);
$pdo = getPDO();

$prog = $scope !== null ? $scope : strtoupper(trim((string)($_GET['programa'] ?? '')));
$alerta = strtolower(trim((string)($_GET['alerta'] ?? 'todos')));
$q = trim((string)($_GET['q'] ?? ''));
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;

// 1. Obtener base de equipos mantenibles para KPIs reales del ámbito
$baseWhere = ['requiere_mantenimiento = 1'];
$baseParams = [];
if ($prog !== '') {
    $baseWhere[] = 'programa = ?';
    $baseParams[] = $prog;
}
if ($q !== '') {
    $baseWhere[] = '(codigo_apsti LIKE ? OR denominacion LIKE ? OR area_ambiente LIKE ?)';
    $like = '%' . $q . '%';
    array_push($baseParams, $like, $like, $like);
}
$baseSql = ' WHERE ' . implode(' AND ', $baseWhere);
$stAll = $pdo->prepare("SELECT * FROM bienes_inventario {$baseSql} ORDER BY fecha_limite_mantenimiento ASC, id ASC");
$stAll->execute($baseParams);
$todosEquipos = $stAll->fetchAll();

$cntTotal = count($todosEquipos);
$cntVencidos = 0;
$cntProximos = 0;
$cntVigentes = 0;
$hoyDt = new DateTimeImmutable('today');

$filtrados = [];
foreach ($todosEquipos as $item) {
    $fl = trim((string)($item['fecha_limite_mantenimiento'] ?? ''));
    $cat = 'vigente';
    if ($fl !== '' && $fl !== '0000-00-00') {
        try {
            $diff = (int)$hoyDt->diff(new DateTimeImmutable($fl))->format('%r%a');
            if ($diff < 0) {
                $cat = 'vencido';
                $cntVencidos++;
            } elseif ($diff <= 15) {
                $cat = 'proximo';
                $cntProximos++;
            } else {
                $cat = 'vigente';
                $cntVigentes++;
            }
        } catch (Throwable $ex) {
            $cntVigentes++;
        }
    } else {
        $cntVigentes++;
    }

    if ($alerta === 'todos'
        || ($alerta === 'vencido' && $cat === 'vencido')
        || ($alerta === 'proximo' && $cat === 'proximo')
        || ($alerta === 'vigente' && $cat === 'vigente')
        || ($alerta === 'completo' && ($item['estado_servicio'] ?? '') === 'Servicio Completo')
    ) {
        $filtrados[] = $item;
    }
}

$totalRows = count($filtrados);
$totalPages = max(1, (int)ceil($totalRows / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;
$equipos = array_slice($filtrados, $offset, $perPage);

renderHeader('Cronograma Preventivo de Mantenimiento', 'cronograma');
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-3">
    <div>
        <h4 class="fw-bold mb-1"><i class="fa-solid fa-calendar-check text-success me-2"></i>Cronograma Preventivo con Semáforo de Alertas</h4>
        <div class="small text-muted">Visualiza de forma ordenada qué equipos tienen mantenimiento vencido, próximo a vencer o al día, y accede directo a su ficha o reporte.</div>
    </div>
    <a href="<?= h(baseUrl('admin/reportes.php')) ?>" class="btn btn-sm btn-outline-secondary">
        <i class="fa-solid fa-file-excel me-1"></i>Ir a Reportes PDF / Excel
    </a>
</div>

<!-- 4 Tarjetas KPI interactivas -->
<div class="row g-3 mb-3">
    <div class="col-6 col-xl-3">
        <div class="kpi-card">
            <div class="small text-muted fw-semibold text-uppercase">Total Programados</div>
            <div class="fs-3 fw-bold mt-1"><?= $cntTotal ?></div>
            <div class="small text-muted">Equipos sujetos a mantenimiento</div>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="kpi-card" style="border-left-color:#dc3545;">
            <div class="small text-muted fw-semibold text-uppercase">Vencidos (Urgente)</div>
            <div class="fs-3 fw-bold text-danger mt-1"><?= $cntVencidos ?></div>
            <div class="small text-muted">Requieren visita prioritaria</div>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="kpi-card" style="border-left-color:#f59e0b;">
            <div class="small text-muted fw-semibold text-uppercase">Próximos (≤ 15 días)</div>
            <div class="fs-3 fw-bold text-warning mt-1"><?= $cntProximos ?></div>
            <div class="small text-muted">Programar atención pronto</div>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="kpi-card" style="border-left-color:#10b981;">
            <div class="small text-muted fw-semibold text-uppercase">Vigentes / Al Día</div>
            <div class="fs-3 fw-bold text-success mt-1"><?= $cntVigentes ?></div>
            <div class="small text-muted">Dentro del plazo preventivo</div>
        </div>
    </div>
</div>

<!-- Filtros de búsqueda + botones rápidos de semáforo -->
<div class="card-clean p-3 mb-3">
    <form method="GET" class="row g-2 align-items-end mb-3">
        <input type="hidden" name="alerta" value="<?= h($alerta) ?>">
        <div class="col-md-5">
            <label class="form-label small fw-semibold mb-1">Buscar código, equipo o ambiente</label>
            <input type="text" name="q" value="<?= h($q) ?>" class="form-control form-control-sm" placeholder="Ej.: APSTI-CPU, Impresora, Lab...">
        </div>
        <?php if ($scope === null): ?>
            <div class="col-md-4">
                <label class="form-label small fw-semibold mb-1">Programa / Área</label>
                <select name="programa" class="form-select form-select-sm">
                    <option value="">Todas las áreas</option>
                    <option value="APSTI" <?= $prog === 'APSTI' ? 'selected' : '' ?>>APSTI</option>
                    <option value="CONT" <?= $prog === 'CONT' ? 'selected' : '' ?>>Contabilidad</option>
                    <option value="ENFE" <?= $prog === 'ENFE' ? 'selected' : '' ?>>Enfermería Técnica</option>
                    <option value="AGRO" <?= $prog === 'AGRO' ? 'selected' : '' ?>>Producción Agropecuaria</option>
                    <option value="CAFETIN" <?= $prog === 'CAFETIN' ? 'selected' : '' ?>>Cafetín</option>
                    <option value="ADMIN" <?= $prog === 'ADMIN' ? 'selected' : '' ?>>Dirección / Admin</option>
                </select>
            </div>
        <?php endif; ?>
        <div class="col-md-3 d-flex gap-2">
            <button type="submit" class="btn btn-sm btn-petrol flex-grow-1"><i class="fa-solid fa-filter me-1"></i>Filtrar</button>
            <a href="<?= h(baseUrl('admin/cronograma.php')) ?>" class="btn btn-sm btn-outline-secondary">Limpiar</a>
        </div>
    </form>

    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 pt-2 border-top">
        <div class="d-flex flex-wrap gap-2">
            <?php
            $pills = [
                'todos'    => ['label' => "Todos ({$cntTotal})", 'cls' => 'btn-petrol'],
                'vencido'  => ['label' => "Vencidos ({$cntVencidos})", 'cls' => 'btn-danger'],
                'proximo'  => ['label' => "Próximos ({$cntProximos})", 'cls' => 'btn-warning text-dark'],
                'vigente'  => ['label' => "Vigentes ({$cntVigentes})", 'cls' => 'btn-success'],
                'completo' => ['label' => 'Solo Servicio Completo', 'cls' => 'btn-emerald'],
            ];
            foreach ($pills as $k => $meta):
                $qsPill = http_build_query(['programa' => $prog, 'q' => $q, 'alerta' => $k]);
            ?>
                <a href="<?= h(baseUrl('admin/cronograma.php?' . $qsPill)) ?>"
                   class="btn btn-sm <?= $alerta === $k ? $meta['cls'] . ' fw-bold' : 'btn-outline-secondary' ?>">
                    <?= h($meta['label']) ?>
                </a>
            <?php endforeach; ?>
        </div>
        <div class="small text-muted">Mostrando <strong><?= count($equipos) ?></strong> de <?= $totalRows ?> (Pág. <?= $page ?>/<?= $totalPages ?>)</div>
    </div>
</div>

<!-- Tabla ordenada con cuadrícula y acciones directas -->
<div class="card-clean p-3">
    <div class="table-responsive">
        <table class="table table-hover table-grid align-middle small mb-0">
            <thead>
                <tr>
                    <th class="text-center" style="width:38px;">N.°</th>
                    <th class="nowrap-col">Código</th>
                    <th>Equipo</th>
                    <th>Programa / Ambiente</th>
                    <th class="nowrap-col text-center">Fecha Límite</th>
                    <th class="nowrap-col text-center">Próxima Visita</th>
                    <th class="nowrap-col text-center">Semáforo</th>
                    <th class="nowrap-col text-center">Estado Servicio</th>
                    <th class="nowrap-col text-center">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($equipos)): ?>
                    <tr><td colspan="9" class="text-center text-muted py-4">No hay equipos programados para los filtros seleccionados.</td></tr>
                <?php else: ?>
                    <?php $nRow = $offset + 1; foreach ($equipos as $e):
                        $sem = calcularSemaforo((string)($e['fecha_limite_mantenimiento'] ?? ''), 1);
                    ?>
                        <tr>
                            <td class="text-center fw-semibold text-muted"><?= $nRow++ ?></td>
                            <td class="nowrap-col fw-bold text-primary"><?= h($e['codigo_apsti']) ?></td>
                            <td class="fw-semibold"><?= h($e['denominacion']) ?></td>
                            <td>
                                <span class="badge bg-light text-dark border"><?= h($e['programa']) ?></span>
                                <span class="text-muted ms-1"><?= h($e['area_ambiente']) ?></span>
                            </td>
                            <td class="nowrap-col text-center fw-semibold"><?= h(fmt_fecha($e['fecha_limite_mantenimiento'] ?? null)) ?></td>
                            <td class="nowrap-col text-center"><?= h(fmt_fecha($e['proxima_visita'] ?? null)) ?></td>
                            <td class="nowrap-col text-center">
                                <span class="badge bg-<?= h($sem['clase']) ?>">
                                    <i class="fa-solid <?= h($sem['icono']) ?> me-1"></i><?= h($sem['texto']) ?>
                                </span>
                            </td>
                            <td class="nowrap-col text-center">
                                <span class="badge <?= ($e['estado_servicio'] ?? '') === 'Servicio Completo' ? 'bg-success' : 'bg-warning text-dark' ?>">
                                    <?= h($e['estado_servicio'] ?? 'Servicio Pendiente') ?>
                                </span>
                            </td>
                            <td class="nowrap-col text-center">
                                <a href="<?= h(baseUrl('admin/ficha_mantenimiento.php?bien_id=' . (int)$e['id'])) ?>" target="_blank" class="btn btn-sm btn-outline-success py-0 px-2" title="Abrir Ficha Técnica">
                                    <i class="fa-solid fa-file-pdf me-1"></i>Ficha
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($totalPages > 1): ?>
        <nav class="mt-3 d-flex justify-content-center">
            <ul class="pagination pagination-sm mb-0">
                <?php for ($p = 1; $p <= $totalPages; $p++):
                    $qs = http_build_query(['programa' => $prog, 'q' => $q, 'alerta' => $alerta, 'page' => $p]);
                ?>
                    <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                        <a class="page-link" href="<?= h(baseUrl('admin/cronograma.php?' . $qs)) ?>"><?= $p ?></a>
                    </li>
                <?php endfor; ?>
            </ul>
        </nav>
    <?php endif; ?>
</div>

<?php renderFooter(); ?>
