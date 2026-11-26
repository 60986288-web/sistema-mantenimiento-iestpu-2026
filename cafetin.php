<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/layout.php';

require_role(['admin']);
$pdo = getPDO();

$bienesCafetin = $pdo->query("SELECT * FROM bienes_inventario WHERE programa = 'CAFETIN' ORDER BY id ASC")->fetchAll();
$conMant = 0;
$completos = 0;
foreach ($bienesCafetin as $b) {
    if ((int)($b['requiere_mantenimiento'] ?? 0) === 1) {
        $conMant++;
        if (($b['estado_servicio'] ?? '') === 'Servicio Completo') {
            $completos++;
        }
    }
}

renderHeader('Módulo de Inventario — Cafetín Institucional', 'cafetin');
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-3">
    <div>
        <h4 class="fw-bold mb-0">Módulo Cafetín Institucional — IESTP Utcubamba</h4>
        <div class="small text-muted">
            Control patrimonial de equipos eléctricos y mobiliario del Cafetín (Responsable: Administración Institucional).
        </div>
    </div>
    <div class="d-flex gap-2">
        <span class="badge bg-primary fs-6"><?= count($bienesCafetin) ?> bienes totales</span>
        <span class="badge bg-success fs-6"><?= $completos ?>/<?= $conMant ?> equipos con mantenimiento</span>
    </div>
</div>

<div class="card-clean p-3">
    <div class="table-responsive" style="overflow-x:auto;">
        <table class="table table-hover table-grid align-middle small mb-0">
            <thead>
                <tr>
                    <th class="text-center" style="width:38px;">N.°</th>
                    <th class="nowrap-col">Código</th>
                    <th>Denominación</th>
                    <th>Marca / Modelo</th>
                    <th>Ambiente</th>
                    <th class="nowrap-col">Estado Físico</th>
                    <th class="nowrap-col">Tipo Control</th>
                    <th class="nowrap-col">Estado Servicio</th>
                    <th class="nowrap-col text-end">Ficha</th>
                </tr>
            </thead>
            <tbody>
                <?php $nCaf = 1; foreach ($bienesCafetin as $b):
                    $reqM = (int)($b['requiere_mantenimiento'] ?? 0);
                ?>
                    <tr>
                        <td class="text-center fw-semibold text-muted"><?= $nCaf++ ?></td>
                        <td class="nowrap-col fw-bold text-primary"><?= h($b['codigo_apsti']) ?></td>
                        <td class="fw-semibold"><?= h($b['denominacion']) ?></td>
                        <td><?= h(clean_tech_value($b['marca'] ?? '')) ?> / <?= h(clean_tech_value($b['modelo'] ?? '')) ?></td>
                        <td><?= h($b['area_ambiente']) ?></td>
                        <td class="nowrap-col"><span class="badge bg-info text-dark"><?= h($b['estado_conservacion'] ?? 'B') ?></span></td>
                        <td class="nowrap-col">
                            <?= $reqM === 1
                                ? '<span class="badge bg-primary bg-opacity-10 text-primary border border-primary">Equipo con Mant.</span>'
                                : '<span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary">Mobiliario</span>' ?>
                        </td>
                        <td class="nowrap-col">
                            <?php if ($reqM === 0): ?>
                                <span class="badge bg-secondary">No Aplica</span>
                            <?php elseif (($b['estado_servicio'] ?? '') === 'Servicio Completo'): ?>
                                <span class="badge bg-success">Servicio Completo</span>
                            <?php else: ?>
                                <span class="badge bg-warning text-dark">Servicio Pendiente</span>
                            <?php endif; ?>
                        </td>
                        <td class="nowrap-col text-end">
                            <a href="<?= h(baseUrl('admin/ficha_mantenimiento.php?bien_id=' . (int)$b['id'])) ?>" target="_blank" class="btn btn-sm btn-outline-success">
                                <i class="fa-solid fa-file-pdf"></i>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php renderFooter(); ?>
