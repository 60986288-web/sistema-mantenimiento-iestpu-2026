<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/layout.php';

$u = require_role(['admin', 'tecnico', 'coordinador_apsti', 'coordinador_cont', 'coordinador_enfe', 'coordinador_agro']);
$scope = get_user_program_scope($u);
$pdo = getPDO();

$prog = $scope !== null ? $scope : strtoupper(trim((string)($_GET['programa'] ?? '')));
$q = trim((string)($_GET['q'] ?? ''));
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;

$where = ['requiere_mantenimiento = 1'];
$params = [];
if ($prog !== '') {
    $where[] = 'programa = ?';
    $params[] = $prog;
}
if ($q !== '') {
    $where[] = '(codigo_apsti LIKE ? OR denominacion LIKE ? OR area_ambiente LIKE ?)';
    $like = '%' . $q . '%';
    array_push($params, $like, $like, $like);
}

$whereSql = ' WHERE ' . implode(' AND ', $where);
$stCount = $pdo->prepare("SELECT COUNT(*) FROM bienes_inventario {$whereSql}");
$stCount->execute($params);
$totalRows = (int)$stCount->fetchColumn();
$totalPages = max(1, (int)ceil($totalRows / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

$st = $pdo->prepare("SELECT * FROM bienes_inventario {$whereSql} ORDER BY fecha_limite_mantenimiento ASC, id ASC LIMIT {$perPage} OFFSET {$offset}");
$st->execute($params);
$equipos = $st->fetchAll();

renderHeader('Cronograma Preventivo de Mantenimiento', 'cronograma');
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-3">
    <div>
        <h4 class="fw-bold mb-0">Cronograma Preventivo con Semáforo de Alertas</h4>
        <div class="small text-muted">Mostrando <?= count($equipos) ?> de <?= $totalRows ?> equipos sujetos a mantenimiento (Página <?= $page ?> de <?= $totalPages ?>).</div>
    </div>
</div>

<div class="card-clean p-3 mb-3">
    <form method="GET" class="row g-2 align-items-end">
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
</div>

<div class="card-clean p-3">
    <div class="table-responsive">
        <table class="table table-hover align-middle small mb-0">
            <thead>
                <tr>
                    <th class="nowrap-col">Código</th>
                    <th>Equipo</th>
                    <th>Programa / Ambiente</th>
                    <th class="nowrap-col">Fecha Límite</th>
                    <th class="nowrap-col">Próxima Visita</th>
                    <th class="nowrap-col">Semáforo</th>
                    <th class="nowrap-col">Estado Servicio</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($equipos)): ?>
                    <tr><td colspan="7" class="text-center text-muted py-4">No hay equipos programados para los filtros seleccionados.</td></tr>
                <?php else: ?>
                    <?php foreach ($equipos as $e):
                        $sem = calcularSemaforo((string)($e['fecha_limite_mantenimiento'] ?? ''), 1);
                    ?>
                        <tr>
                            <td class="nowrap-col fw-bold text-primary"><?= h($e['codigo_apsti']) ?></td>
                            <td class="fw-semibold"><?= h($e['denominacion']) ?></td>
                            <td>
                                <span class="badge bg-light text-dark border"><?= h($e['programa']) ?></span>
                                <span class="text-muted ms-1"><?= h($e['area_ambiente']) ?></span>
                            </td>
                            <td class="nowrap-col"><?= h(fmt_fecha($e['fecha_limite_mantenimiento'] ?? null)) ?></td>
                            <td class="nowrap-col"><?= h(fmt_fecha($e['proxima_visita'] ?? null)) ?></td>
                            <td class="nowrap-col">
                                <span class="badge bg-<?= h($sem['clase']) ?>">
                                    <i class="fa-solid <?= h($sem['icono']) ?> me-1"></i><?= h($sem['texto']) ?>
                                </span>
                            </td>
                            <td class="nowrap-col">
                                <span class="badge <?= ($e['estado_servicio'] ?? '') === 'Servicio Completo' ? 'bg-success' : 'bg-warning text-dark' ?>">
                                    <?= h($e['estado_servicio'] ?? 'Servicio Pendiente') ?>
                                </span>
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
                    $qs = http_build_query(['programa' => $prog, 'q' => $q, 'page' => $p]);
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
