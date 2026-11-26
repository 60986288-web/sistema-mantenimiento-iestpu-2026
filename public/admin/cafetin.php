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

<div class="oficial-sheet">
    <div class="d-flex justify-content-between align-items-center border-bottom pb-2 mb-2">
        <img src="<?= h(baseUrl('assets/img/logo.png')) ?>" alt="Escudo IESTPU" style="height:50px;object-fit:contain;">
        <div class="text-center px-2">
            <div class="fw-bold" style="font-size:.88rem;">GOBIERNO REGIONAL AMAZONAS</div>
            <div class="small fw-semibold">DIRECCIÓN REGIONAL DE EDUCACIÓN AMAZONAS</div>
            <div class="small fw-bold">INSTITUTO DE EDUCACIÓN SUPERIOR TECNOLÓGICO PÚBLICO «UTCUBAMBA»</div>
        </div>
        <img src="<?= h(baseUrl('assets/img/logo.png')) ?>" alt="Escudo IESTPU" style="height:50px;object-fit:contain;">
    </div>

    <div class="text-center fw-bold py-1 mb-2 border border-dark bg-light text-dark" style="font-size:.84rem;">
        INVENTARIO GENERAL DE BIENES INSTITUCIONAL - 2026 · MÓDULO CAFETÍN INSTITUCIONAL
    </div>

    <table class="oficial-header-tbl">
        <tr>
            <td style="width:18%;"><strong>NOMBRE DE LA ENTIDAD:</strong></td>
            <td style="width:36%;">INSTITUTO DE EDUCACIÓN SUPERIOR TECNOLÓGICO PÚBLICO UTCUBAMBA</td>
            <td style="width:16%;"><strong>LUGAR / DISTRITO:</strong></td>
            <td style="width:30%;">BAGUA GRANDE — PROV. UTCUBAMBA</td>
        </tr>
        <tr>
            <td><strong>CÓDIGO MODULAR:</strong></td>
            <td>741744</td>
            <td><strong>COMITÉ DE INVENTARIOS:</strong></td>
            <td>Sandoval Dávila Carlos Enrique · Cruz Bobadilla Stalin · Occ Briceño Lluvitza</td>
        </tr>
        <tr>
            <td><strong>ÁREA / PROGRAMA:</strong></td>
            <td><strong>MÓDULO CAFETÍN INSTITUCIONAL</strong></td>
            <td><strong>FECHA DE EMISIÓN:</strong></td>
            <td><?= date('d/m/Y') ?> (<?= count($bienesCafetin) ?> registros)</td>
        </tr>
    </table>

    <div class="table-responsive">
        <table class="oficial-bienes-tbl mb-0">
            <thead>
                <tr>
                    <th rowspan="2" style="width:3.5%;">N.°</th>
                    <th colspan="11">DESCRIPCIÓN DE LOS BIENES</th>
                    <th rowspan="2" style="width:6%;">FICHA</th>
                </tr>
                <tr>
                    <th style="width:7%;">CÓDIGO PATRIMONIAL</th>
                    <th style="width:10.5%;">CÓDIGO INTERNO</th>
                    <th style="width:14%;">DENOMINACIÓN</th>
                    <th style="width:7.5%;">MARCA</th>
                    <th style="width:8.5%;">MODELO</th>
                    <th style="width:7%;">TIPO</th>
                    <th style="width:6.5%;">COLOR</th>
                    <th style="width:9.5%;">SERIE</th>
                    <th style="width:8%;">DIMENSIONES (Medidas)</th>
                    <th style="width:5.5%;">SITUACIÓN (1)</th>
                    <th style="width:6.5%;">ESTADO DE CONSERVACIÓN (2)</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($bienesCafetin)): ?>
                    <tr><td colspan="13" class="text-center text-muted py-3">No hay bienes registrados en Cafetín.</td></tr>
                <?php else: ?>
                    <?php $nCaf = 1; foreach ($bienesCafetin as $b):
                        $estadoC = strtoupper(trim((string)($b['estado_conservacion'] ?? 'B')));
                        $situacion = $estadoC === 'M' ? 'D' : 'U';
                    ?>
                        <tr>
                            <td class="text-center fw-semibold"><?= $nCaf++ ?></td>
                            <td><?= h(clean_tech_value($b['correlativo'] ?? '')) ?></td>
                            <td class="fw-bold text-primary"><?= h(clean_tech_value($b['codigo_apsti'] ?? $b['codigo_interno'] ?? '')) ?></td>
                            <td class="fw-semibold"><?= h(clean_tech_value($b['denominacion'] ?? '')) ?></td>
                            <td><?= h(clean_tech_value($b['marca'] ?? '')) ?></td>
                            <td><?= h(clean_tech_value($b['modelo'] ?? '')) ?></td>
                            <td><?= h(clean_tech_value($b['tipo_material'] ?? '')) ?></td>
                            <td><?= h(clean_tech_value($b['color'] ?? '')) ?></td>
                            <td><?= h(clean_tech_value($b['serie'] ?? '')) ?></td>
                            <td><?= h(clean_tech_value($b['dimensiones'] ?? '')) ?></td>
                            <td class="text-center fw-bold"><?= h($situacion) ?></td>
                            <td class="text-center fw-bold"><?= h($estadoC) ?></td>
                            <td class="text-center">
                                <a href="<?= h(baseUrl('admin/ficha_mantenimiento.php?bien_id=' . (int)$b['id'])) ?>" target="_blank" class="btn btn-sm btn-outline-success py-0 px-1" title="Ver Ficha PDF">
                                    <i class="fa-solid fa-file-pdf"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <div class="small text-muted mt-2">
        <strong>Leyenda:</strong> (1) Situación: <code>U</code> = En Uso, <code>D</code> = En Desuso. (2) Estado de Conservación: <code>N</code> = Nuevo, <code>MB</code> = Muy Bueno, <code>B</code> = Bueno, <code>R</code> = Regular, <code>M</code> = Malo.
    </div>
</div>

<?php renderFooter(); ?>
