<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/layout.php';

require_role(['admin']);
$pdo = getDB();

$fAccion = trim((string)($_GET['accion'] ?? ''));
$fRol = trim((string)($_GET['rol'] ?? ''));

$where = [];
$params = [];
if ($fAccion !== '') {
    $where[] = 'accion LIKE ?';
    $params[] = '%' . $fAccion . '%';
}
if ($fRol !== '') {
    $where[] = 'rol = ?';
    $params[] = $fRol;
}

$sql = 'SELECT * FROM auditoria_logs' . (!empty($where) ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY id DESC LIMIT 150';
$st = $pdo->prepare($sql);
$st->execute($params);
$logs = $st->fetchAll();

renderHeader('Bitácora de Auditoría del Sistema', 'auditoria');
?>

<div class="mb-3">
    <h4 class="fw-bold mb-0">Bitácora de Auditoría y Trazabilidad (P2)</h4>
    <div class="small text-muted">Registro inmutable de inicios de sesión, emisiones de fichas, cierres técnicos, cambios de usuarios y movimientos.</div>
</div>

<div class="card-clean p-3 mb-3">
    <form method="GET" class="row g-2 align-items-end">
        <div class="col-md-5">
            <label class="form-label small fw-semibold mb-1">Filtrar por acción</label>
            <input type="text" name="accion" value="<?= h($fAccion) ?>" class="form-control form-control-sm" placeholder="Ej.: LOGIN, VERIFICAR, ATENCION_TECNICA...">
        </div>
        <div class="col-md-4">
            <label class="form-label small fw-semibold mb-1">Rol</label>
            <select name="rol" class="form-select form-select-sm">
                <option value="">Todos los roles</option>
                <?php foreach (['admin', 'coordinador_apsti', 'coordinador_cont', 'coordinador_enfe', 'coordinador_agro', 'tecnico'] as $r): ?>
                    <option value="<?= h($r) ?>" <?= $fRol === $r ? 'selected' : '' ?>><?= h(strtoupper($r)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3 d-flex gap-2">
            <button type="submit" class="btn btn-sm btn-petrol flex-grow-1"><i class="fa-solid fa-filter me-1"></i>Filtrar</button>
            <a href="<?= h(baseUrl('admin/auditoria.php')) ?>" class="btn btn-sm btn-outline-secondary">Limpiar</a>
        </div>
    </form>
</div>

<div class="card-clean p-3">
    <div class="table-responsive">
        <table class="table table-hover align-middle small mb-0">
            <thead>
                <tr>
                    <th class="nowrap-col">Fecha y Hora</th>
                    <th class="nowrap-col">Usuario ID / Rol</th>
                    <th class="nowrap-col">Acción</th>
                    <th class="nowrap-col">Tabla / Registro</th>
                    <th>Detalle</th>
                    <th class="nowrap-col">IP</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($logs)): ?>
                    <tr><td colspan="6" class="text-center text-muted py-4">No hay eventos registrados en la bitácora todavía.</td></tr>
                <?php else: ?>
                    <?php foreach ($logs as $l): ?>
                        <tr>
                            <td class="nowrap-col"><?= h(fmt_fecha_hora($l['fecha_hora'] ?? null)) ?></td>
                            <td class="nowrap-col">
                                <span class="badge bg-dark">#<?= (int)$l['usuario_id'] ?></span>
                                <span class="badge bg-light text-dark border"><?= h(strtoupper((string)$l['rol'])) ?></span>
                            </td>
                            <td class="nowrap-col fw-bold text-primary"><?= h($l['accion']) ?></td>
                            <td class="nowrap-col"><?= h($l['tabla_afectada']) ?> <?= !empty($l['registro_id']) ? ('#' . h((string)$l['registro_id'])) : '' ?></td>
                            <td><?= h((string)($l['detalle'] ?? '')) ?></td>
                            <td class="nowrap-col text-muted"><?= h((string)($l['ip'] ?? '')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php renderFooter(); ?>
