<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/layout.php';

$u = require_role(['admin', 'coordinador_apsti']);
$pdo = getDB();

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

<div class="card-clean p-3">
    <h6 class="fw-bold mb-3">Equipos Registrados en APSTI</h6>
    <div class="table-responsive">
        <table class="table table-hover align-middle small mb-0">
            <thead>
                <tr>
                    <th class="nowrap-col">Código APSTI</th>
                    <th>Denominación</th>
                    <th>Ambiente</th>
                    <th>Especificaciones</th>
                    <th class="nowrap-col">Compra</th>
                    <th class="nowrap-col">Estado Serv.</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($equiposApsti as $e): ?>
                    <tr>
                        <td class="nowrap-col fw-bold text-primary"><?= h($e['codigo_apsti']) ?></td>
                        <td class="fw-semibold"><?= h($e['denominacion']) ?></td>
                        <td><?= h($e['area_ambiente']) ?></td>
                        <td class="text-muted"><?= h(clean_especificaciones($e['especificaciones'] ?? '')) ?></td>
                        <td class="nowrap-col"><?= h(fmt_fecha($e['fecha_compra'] ?? null)) ?></td>
                        <td class="nowrap-col">
                            <span class="badge <?= ($e['estado_servicio'] ?? '') === 'Servicio Completo' ? 'bg-success' : 'bg-warning text-dark' ?>">
                                <?= h($e['estado_servicio'] ?? 'Servicio Pendiente') ?>
                            </span>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php renderFooter(); ?>
