<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/layout.php';

$u = require_role(['admin', 'tecnico', 'coordinador_apsti', 'coordinador_cont', 'coordinador_enfe', 'coordinador_agro']);
$rol = get_user_role($u);
$scope = get_user_program_scope($u);
$pdo = getDB();

$msg = '';
$puedeEditarMant = in_array($rol, ['admin', 'tecnico'], true);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $puedeEditarMant) {
    verify_csrf();
    $bienId = (int)($_POST['bien_id'] ?? 0);
    $estadoServ = trim((string)($_POST['estado_servicio'] ?? 'Servicio Pendiente'));
    $proxVisita = trim((string)($_POST['proxima_visita'] ?? ''));
    $diagnostico = trim((string)($_POST['diagnostico'] ?? ''));

    if ($bienId > 0) {
        $st = $pdo->prepare('UPDATE bienes_inventario SET estado_servicio = ?, proxima_visita = ?, fecha_viene_tecnico = ?, diagnostico = ? WHERE id = ?');
        $st->execute([$estadoServ, $proxVisita !== '' ? $proxVisita : null, date('Y-m-d'), $diagnostico, $bienId]);
        registrar_auditoria($pdo, 'ACTUALIZAR_ESTADO_EQUIPO', 'bienes_inventario', $bienId, "Estado: {$estadoServ}");
        $msg = 'Estado de mantenimiento del equipo actualizado correctamente.';
    }
}

$q = trim((string)($_GET['q'] ?? ''));
$resultados = [];

if ($q !== '') {
    $like = '%' . $q . '%';
    if ($scope !== null) {
        $st = $pdo->prepare('SELECT * FROM bienes_inventario WHERE programa = ? AND (codigo_apsti LIKE ? OR codigo_interno LIKE ? OR serie LIKE ? OR denominacion LIKE ?) ORDER BY id ASC LIMIT 30');
        $st->execute([$scope, $like, $like, $like, $like]);
    } else {
        $st = $pdo->prepare('SELECT * FROM bienes_inventario WHERE (codigo_apsti LIKE ? OR codigo_interno LIKE ? OR serie LIKE ? OR denominacion LIKE ?) ORDER BY id ASC LIMIT 30');
        $st->execute([$like, $like, $like, $like]);
    }
    $resultados = $st->fetchAll();
} else {
    if ($scope !== null) {
        $st = $pdo->prepare('SELECT * FROM bienes_inventario WHERE programa = ? ORDER BY id ASC LIMIT 12');
        $st->execute([$scope]);
    } else {
        $st = $pdo->query('SELECT * FROM bienes_inventario ORDER BY id ASC LIMIT 12');
    }
    $resultados = $st->fetchAll();
}

renderHeader('Buscar Equipo por Código o Serie', 'buscar');
?>

<div class="card-clean p-3 p-md-4 mb-4">
    <h5 class="fw-bold mb-2"><i class="fa-solid fa-magnifying-glass text-success me-2"></i>Buscador Rápido de Equipos Patrimoniales</h5>
    <form method="GET" class="row g-2">
        <div class="col-md-9">
            <input type="text" name="q" value="<?= h($q) ?>" class="form-control" placeholder="Ingresa código (ej.: APSTI-CPU-LC1-002), serie o denominación...">
        </div>
        <div class="col-md-3 d-grid">
            <button type="submit" class="btn btn-petrol"><i class="fa-solid fa-search me-1"></i>Buscar Equipo</button>
        </div>
    </form>
</div>

<?php if ($msg): ?>
    <div class="alert alert-success alert-dismissible fade show py-2 small"><?= h($msg) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>

<div class="card-clean p-3">
    <div class="table-responsive">
        <table class="table table-hover align-middle small mb-0">
            <thead>
                <tr>
                    <th class="nowrap-col">Código</th>
                    <th>Equipo / Especificaciones</th>
                    <th>Área / Programa</th>
                    <th class="nowrap-col">Últ. Visita / Próx.</th>
                    <th style="min-width:210px;">Estado de Servicio</th>
                    <th class="nowrap-col text-end">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($resultados)): ?>
                    <tr><td colspan="6" class="text-center text-muted py-4">No se encontraron equipos para «<?= h($q) ?>».</td></tr>
                <?php else: ?>
                    <?php foreach ($resultados as $b):
                        $reqM = (int)($b['requiere_mantenimiento'] ?? 1);
                    ?>
                        <tr>
                            <td class="nowrap-col fw-bold text-primary"><?= h($b['codigo_apsti']) ?></td>
                            <td>
                                <div class="fw-semibold"><?= h($b['denominacion']) ?></div>
                                <div class="text-muted" style="font-size:.75rem;">
                                    Marca: <?= h(clean_tech_value($b['marca'] ?? '')) ?> · Serie: <?= h(clean_tech_value($b['serie'] ?? '')) ?>
                                </div>
                            </td>
                            <td>
                                <span class="badge bg-light text-dark border"><?= h($b['programa']) ?></span>
                                <div class="text-muted" style="font-size:.75rem;"><?= h($b['area_ambiente']) ?></div>
                            </td>
                            <td class="nowrap-col">
                                <div>Últ.: <?= h(fmt_fecha($b['fecha_viene_tecnico'] ?? null)) ?></div>
                                <div class="text-muted">Próx.: <?= h(fmt_fecha($b['proxima_visita'] ?? null)) ?></div>
                            </td>
                            <td>
                                <?php if ($puedeEditarMant && $reqM === 1): ?>
                                    <form method="POST" class="d-flex flex-column gap-1">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="bien_id" value="<?= (int)$b['id'] ?>">
                                        <select name="estado_servicio" class="form-select form-select-sm" style="min-width:195px;">
                                            <option value="Servicio Completo" <?= ($b['estado_servicio'] ?? '') === 'Servicio Completo' ? 'selected' : '' ?>>Servicio Completo</option>
                                            <option value="Servicio Pendiente" <?= ($b['estado_servicio'] ?? '') === 'Servicio Pendiente' ? 'selected' : '' ?>>Servicio Pendiente</option>
                                        </select>
                                        <div class="d-flex gap-1">
                                            <input type="date" name="proxima_visita" value="<?= h((string)($b['proxima_visita'] ?? '')) ?>" class="form-control form-control-sm" style="min-width:130px;">
                                            <button type="submit" class="btn btn-sm btn-emerald" title="Guardar estado"><i class="fa-solid fa-check"></i></button>
                                        </div>
                                        <input type="hidden" name="diagnostico" value="<?= h((string)($b['diagnostico'] ?? '')) ?>">
                                    </form>
                                <?php else: ?>
                                    <span class="badge <?= $reqM === 0 ? 'bg-secondary' : (($b['estado_servicio'] ?? '') === 'Servicio Completo' ? 'bg-success' : 'bg-warning text-dark') ?>">
                                        <?= h($reqM === 0 ? 'No Aplica' : ($b['estado_servicio'] ?? 'Servicio Pendiente')) ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="nowrap-col text-end">
                                <a href="<?= h(baseUrl('admin/ficha_mantenimiento.php?bien_id=' . (int)$b['id'])) ?>" target="_blank" class="btn btn-sm btn-outline-success">
                                    <i class="fa-solid fa-file-pdf me-1"></i>Ficha
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php renderFooter(); ?>
