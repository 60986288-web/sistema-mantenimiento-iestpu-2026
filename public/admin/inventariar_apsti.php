<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/layout.php';

$u = require_role(['admin', 'coordinador_apsti']);
$pdo = getPDO();

$msg = '';
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $codigoApsti = strtoupper(trim((string)($_POST['codigo_apsti'] ?? '')));
    $denominacion = trim((string)($_POST['denominacion'] ?? ''));
    $marca = trim((string)($_POST['marca'] ?? '')) ?: '-';
    $modelo = trim((string)($_POST['modelo'] ?? '')) ?: '-';
    $serie = trim((string)($_POST['serie'] ?? '')) ?: '-';
    $ambiente = trim((string)($_POST['area_ambiente'] ?? ''));
    $fechaCompra = trim((string)($_POST['fecha_compra'] ?? '')) ?: date('Y-m-d');
    $estadoCons = trim((string)($_POST['estado_conservacion'] ?? 'B'));
    $especificaciones = clean_especificaciones((string)($_POST['especificaciones'] ?? ''));

    if ($codigoApsti === '' || $denominacion === '' || $ambiente === '') {
        $err = 'Completa todos los campos obligatorios marcados con *.';
    } else {
        $dup = $pdo->prepare('SELECT COUNT(*) FROM bienes_inventario WHERE codigo_apsti = ?');
        $dup->execute([$codigoApsti]);
        if ((int)$dup->fetchColumn() > 0) {
            $err = "El código {$codigoApsti} ya existe en el inventario.";
        } else {
            $corr = str_pad((string)((int)$pdo->query('SELECT COUNT(*) FROM bienes_inventario')->fetchColumn() + 1), 3, '0', STR_PAD_LEFT);
            $st = $pdo->prepare(
                'INSERT INTO bienes_inventario
                 (correlativo, codigo_apsti, codigo_interno, denominacion, marca, modelo, serie, especificaciones, programa, area_ambiente, responsable, fecha_compra, estado_conservacion, requiere_mantenimiento, fecha_limite_mantenimiento, estado_visita, estado_servicio)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?, ?)'
            );
            $st->execute([
                $corr,
                $codigoApsti,
                $codigoApsti,
                $denominacion,
                $marca,
                $modelo,
                $serie,
                $especificaciones,
                'APSTI',
                $ambiente,
                (string)($u['nombres'] ?? 'Coordinación APSTI'),
                $fechaCompra,
                $estadoCons,
                date('Y-m-d', strtotime('+90 days')),
                'Pendiente',
                'Servicio Pendiente',
            ]);
            $newId = (int)$pdo->lastInsertId();
            registrar_auditoria($pdo, 'INVENTARIAR_APSTI', 'bienes_inventario', $newId, "Registro APSTI {$codigoApsti}");
            $msg = "Equipo {$codigoApsti} inventariado correctamente en APSTI.";
        }
    }
}

$equiposApsti = $pdo->query("SELECT * FROM bienes_inventario WHERE programa = 'APSTI' ORDER BY id DESC")->fetchAll();

renderHeader('Formulario de Inventariado — Programa APSTI', 'inv_apsti');
?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div>
        <h4 class="fw-bold mb-0">Inventariado Técnico de Equipos — APSTI</h4>
        <div class="small text-muted">Registro patrimonial de laboratorios de cómputo, ensamblaje y coordinación APSTI.</div>
    </div>
    <span class="badge bg-success fs-6"><?= count($equiposApsti) ?> equipos APSTI registrados</span>
</div>

<?php if ($msg): ?>
    <div class="alert alert-success alert-dismissible fade show py-2 small"><?= h($msg) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>
<?php if ($err): ?>
    <div class="alert alert-danger alert-dismissible fade show py-2 small"><?= h($err) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>

<div class="card-clean p-3 p-md-4 mb-4">
    <h6 class="fw-bold mb-3"><i class="fa-solid fa-plus-circle text-success me-2"></i>Registrar Nuevo Equipo APSTI</h6>
    <form method="POST" class="row g-3">
        <?= csrf_field() ?>
        <div class="col-md-4">
            <label class="form-label small fw-semibold">Código APSTI <span class="text-danger">*</span></label>
            <input type="text" name="codigo_apsti" class="form-control" placeholder="Ej.: APSTI-CPU-LC1-038" required>
        </div>
        <div class="col-md-5">
            <label class="form-label small fw-semibold">Denominación del Equipo <span class="text-danger">*</span></label>
            <input type="text" name="denominacion" class="form-control" placeholder="Ej.: CPU Escritorio Laboratorio de Cómputo I" required>
        </div>
        <div class="col-md-3">
            <label class="form-label small fw-semibold">Fecha de Compra</label>
            <input type="date" name="fecha_compra" class="form-control">
        </div>
        <div class="col-md-4">
            <label class="form-label small fw-semibold">Ambiente / Laboratorio <span class="text-danger">*</span></label>
            <input type="text" name="area_ambiente" class="form-control" placeholder="Ej.: Laboratorio de Cómputo I" required>
        </div>
        <div class="col-md-3">
            <label class="form-label small fw-semibold">Marca</label>
            <input type="text" name="marca" class="form-control" placeholder="Ej.: TEROS, HP, Lenovo">
        </div>
        <div class="col-md-3">
            <label class="form-label small fw-semibold">Modelo</label>
            <input type="text" name="modelo" class="form-control" placeholder="Ej.: Intel Core i7-14700F">
        </div>
        <div class="col-md-2">
            <label class="form-label small fw-semibold">Estado Físico <span class="text-danger">*</span></label>
            <select name="estado_conservacion" class="form-select" required>
                <option value="N">Nuevo (N)</option>
                <option value="MB">Muy Bueno (MB)</option>
                <option value="B" selected>Bueno (B)</option>
                <option value="R">Regular (R)</option>
                <option value="M">Malo (M)</option>
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label small fw-semibold">Número de Serie</label>
            <input type="text" name="serie" class="form-control" placeholder="Ej.: SN-2026-APSTI-01">
        </div>
        <div class="col-md-8">
            <label class="form-label small fw-semibold">Especificaciones Técnicas</label>
            <input type="text" name="especificaciones" class="form-control" placeholder="Ej.: Procesador, RAM, SSD, GPU NVIDIA, monitor, teclado, estabilizador">
        </div>
        <div class="col-12 d-flex justify-content-end">
            <button type="submit" class="btn btn-emerald px-4">
                <i class="fa-solid fa-floppy-disk me-1"></i>Guardar en Inventario APSTI
            </button>
        </div>
    </form>
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
        INVENTARIO GENERAL DE BIENES INSTITUCIONAL - 2026 · PROGRAMA DE ESTUDIO APSTI
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
            <td><strong>PROGRAMA DE ESTUDIO APSTI</strong></td>
            <td><strong>FECHA DE EMISIÓN:</strong></td>
            <td><?= date('d/m/Y') ?> (<?= count($equiposApsti) ?> registros)</td>
        </tr>
    </table>

    <div class="table-responsive">
        <table class="oficial-bienes-tbl mb-0">
            <thead>
                <tr>
                    <th rowspan="2" style="width:3.5%;">N.°</th>
                    <th colspan="11">DESCRIPCIÓN DE LOS BIENES</th>
                </tr>
                <tr>
                    <th style="width:7.5%;">CÓDIGO PATRIMONIAL</th>
                    <th style="width:11%;">CÓDIGO INTERNO</th>
                    <th style="width:14.5%;">DENOMINACIÓN</th>
                    <th style="width:8%;">MARCA</th>
                    <th style="width:9%;">MODELO</th>
                    <th style="width:7.5%;">TIPO</th>
                    <th style="width:7%;">COLOR</th>
                    <th style="width:10%;">SERIE</th>
                    <th style="width:8.5%;">DIMENSIONES (Medidas)</th>
                    <th style="width:6%;">SITUACIÓN (1)</th>
                    <th style="width:7.5%;">ESTADO DE CONSERVACIÓN (2)</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($equiposApsti)): ?>
                    <tr><td colspan="12" class="text-center text-muted py-3">No hay equipos registrados en APSTI.</td></tr>
                <?php else: ?>
                    <?php $nAp = 1; foreach ($equiposApsti as $e):
                        $estadoC = strtoupper(trim((string)($e['estado_conservacion'] ?? 'B')));
                        $situacion = $estadoC === 'M' ? 'D' : 'U';
                    ?>
                        <tr>
                            <td class="text-center fw-semibold"><?= $nAp++ ?></td>
                            <td><?= h(clean_tech_value($e['correlativo'] ?? '')) ?></td>
                            <td class="fw-bold text-primary"><?= h(clean_tech_value($e['codigo_apsti'] ?? $e['codigo_interno'] ?? '')) ?></td>
                            <td class="fw-semibold"><?= h(clean_tech_value($e['denominacion'] ?? '')) ?></td>
                            <td><?= h(clean_tech_value($e['marca'] ?? '')) ?></td>
                            <td><?= h(clean_tech_value($e['modelo'] ?? '')) ?></td>
                            <td><?= h(clean_tech_value($e['tipo_material'] ?? '')) ?></td>
                            <td><?= h(clean_tech_value($e['color'] ?? '')) ?></td>
                            <td><?= h(clean_tech_value($e['serie'] ?? '')) ?></td>
                            <td><?= h(clean_tech_value($e['dimensiones'] ?? '')) ?></td>
                            <td class="text-center fw-bold"><?= h($situacion) ?></td>
                            <td class="text-center fw-bold"><?= h($estadoC) ?></td>
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
