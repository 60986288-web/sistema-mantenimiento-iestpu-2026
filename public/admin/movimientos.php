<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/layout.php';

$u = require_role(['admin']);
$pdo = getPDO();

// Vista imprimible de Acta de Movimiento Patrimonial
if (isset($_GET['acta_id']) && (int)$_GET['acta_id'] > 0) {
    $st = $pdo->prepare('SELECT * FROM movimientos_bienes WHERE id = ? LIMIT 1');
    $st->execute([(int)$_GET['acta_id']]);
    $m = $st->fetch();
    if (!$m) {
        http_response_code(404);
        render_access_denied('El acta de movimiento solicitada no existe.');
    }
    ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title><?= h($m['codigo_acta']) ?> — Acta de Movimiento Patrimonial</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background:#f1f5f9; padding:24px; font-family:'Segoe UI',Arial,sans-serif; }
        .acta { max-width:820px; margin:0 auto; background:#fff; padding:28px; border:1px solid #cbd5e1; border-radius:6px; }
        @media print { body{background:#fff;padding:0;} .no-print{display:none!important;} .acta{border:none;} }
    </style>
</head>
<body>
    <div class="no-print mb-3 text-center">
        <button onclick="window.print()" class="btn btn-sm btn-success">Imprimir / Guardar PDF</button>
        <a href="<?= h(baseUrl('admin/movimientos.php')) ?>" class="btn btn-sm btn-secondary">Volver</a>
    </div>
    <div class="acta">
        <div class="text-center border-bottom pb-3 mb-3">
            <div class="small fw-bold text-uppercase">I.E.S.T. Público «Utcubamba» — Bagua Grande</div>
            <h4 class="fw-bold mb-1">ACTA DE <?= h(strtoupper($m['tipo_movimiento'])) ?> DE BIEN PATRIMONIAL</h4>
            <div class="badge bg-dark"><?= h($m['codigo_acta']) ?> · Fecha: <?= h(fmt_fecha($m['fecha_movimiento'])) ?></div>
        </div>
        <table class="table table-bordered small">
            <tr><th style="width:28%;">Código Patrimonial</th><td><strong><?= h($m['codigo_bien']) ?></strong></td></tr>
            <tr><th>Descripción del Bien</th><td><?= h($m['descripcion_bien']) ?></td></tr>
            <tr><th>Área de Origen</th><td><?= h($m['area_origen']) ?></td></tr>
            <tr><th>Área de Destino</th><td><?= h($m['area_destino']) ?></td></tr>
            <tr><th>Responsable de Entrega</th><td><?= h($m['responsable_entrega']) ?></td></tr>
            <tr><th>Responsable de Recepción</th><td><?= h($m['responsable_recibe']) ?></td></tr>
            <tr><th>Motivo / Sustento</th><td><?= h($m['motivo']) ?></td></tr>
            <tr><th>Estado del Movimiento</th><td><span class="badge bg-success"><?= h($m['estado_movimiento']) ?></span></td></tr>
        </table>
        <div class="row text-center mt-5 pt-4 small">
            <div class="col-4"><div class="border-top pt-2 fw-bold">Entrega</div><div><?= h($m['responsable_entrega']) ?></div></div>
            <div class="col-4"><div class="border-top pt-2 fw-bold">Recibe Conforme</div><div><?= h($m['responsable_recibe']) ?></div></div>
            <div class="col-4"><div class="border-top pt-2 fw-bold">V.° B.° Administración</div><div>IESTP Utcubamba</div></div>
        </div>
    </div>
</body>
</html>
<?php
    exit;
}

$msg = '';
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $bienId = (int)($_POST['bien_id'] ?? 0);
    $tipo = strtoupper(trim((string)($_POST['tipo_movimiento'] ?? 'TRASLADO')));
    $destino = trim((string)($_POST['area_destino'] ?? ''));
    $recibe = trim((string)($_POST['responsable_recibe'] ?? ''));
    $fecha = trim((string)($_POST['fecha_movimiento'] ?? '')) ?: date('Y-m-d');
    $motivo = trim((string)($_POST['motivo'] ?? ''));

    if (!in_array($tipo, ['TRASLADO', 'PRESTAMO', 'DEVOLUCION'], true)) {
        $tipo = 'TRASLADO';
    }
    if ($bienId <= 0 || $destino === '' || $recibe === '' || $motivo === '') {
        $err = 'Completa todos los campos obligatorios del movimiento.';
    } else {
        $stB = $pdo->prepare('SELECT * FROM bienes_inventario WHERE id = ? LIMIT 1');
        $stB->execute([$bienId]);
        $bien = $stB->fetch();
        if (!$bien) {
            $err = 'El bien seleccionado no existe.';
        } else {
            $num = (int)$pdo->query('SELECT COUNT(*) FROM movimientos_bienes')->fetchColumn() + 1;
            $codigoActa = 'ACTA-MOV-2026-' . str_pad((string)$num, 3, '0', STR_PAD_LEFT);

            $pdo->beginTransaction();
            $stIns = $pdo->prepare(
                'INSERT INTO movimientos_bienes
                 (codigo_acta, bien_id, codigo_bien, descripcion_bien, tipo_movimiento, area_origen, area_destino, responsable_entrega, responsable_recibe, fecha_movimiento, motivo, estado_movimiento)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stIns->execute([
                $codigoActa,
                (int)$bien['id'],
                (string)$bien['codigo_apsti'],
                (string)$bien['denominacion'],
                $tipo,
                (string)$bien['area_ambiente'],
                $destino,
                (string)($u['nombres'] ?? 'Administración'),
                $recibe,
                $fecha,
                $motivo,
                'EJECUTADO',
            ]);
            $movId = (int)$pdo->lastInsertId();

            // Actualizar ubicación actual del bien en inventario
            $stUpd = $pdo->prepare('UPDATE bienes_inventario SET area_ambiente = ? WHERE id = ?');
            $stUpd->execute([$destino, (int)$bien['id']]);
            $pdo->commit();

            registrar_auditoria($pdo, 'MOVIMIENTO_BIEN', 'movimientos_bienes', $movId, "{$codigoActa}: {$tipo} de {$bien['codigo_apsti']} hacia {$destino}");
            $msg = "Movimiento {$codigoActa} registrado y ubicación actualizada en inventario.";
        }
    }
}

$bienes = $pdo->query('SELECT id, codigo_apsti, denominacion, area_ambiente, programa FROM bienes_inventario ORDER BY codigo_apsti ASC')->fetchAll();
$movimientos = $pdo->query('SELECT * FROM movimientos_bienes ORDER BY id DESC')->fetchAll();

renderHeader('Traslados, Préstamos y Devoluciones (P2)', 'movimientos');
?>

<div class="mb-3">
    <h4 class="fw-bold mb-0">Control de Traslados, Préstamos y Devoluciones de Bienes</h4>
    <div class="small text-muted">Registra movimientos patrimoniales con actualización automática de ambiente y emisión de Acta imprimible.</div>
</div>

<?php if ($msg): ?>
    <div class="alert alert-success alert-dismissible fade show py-2 small"><?= h($msg) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>
<?php if ($err): ?>
    <div class="alert alert-danger alert-dismissible fade show py-2 small"><?= h($err) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>

<div class="card-clean p-3 p-md-4 mb-4">
    <h6 class="fw-bold mb-3"><i class="fa-solid fa-right-left text-success me-2"></i>Registrar Nuevo Movimiento Patrimonial</h6>
    <form method="POST" class="row g-3">
        <?= csrf_field() ?>
        <div class="col-md-5">
            <label class="form-label small fw-semibold">Bien Patrimonial <span class="text-danger">*</span></label>
            <select name="bien_id" class="form-select form-select-sm" required>
                <option value="">-- Seleccionar bien --</option>
                <?php foreach ($bienes as $b): ?>
                    <option value="<?= (int)$b['id'] ?>"><?= h($b['codigo_apsti']) ?> — <?= h($b['denominacion']) ?> (Actual: <?= h($b['area_ambiente']) ?>)</option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label small fw-semibold">Tipo</label>
            <select name="tipo_movimiento" class="form-select form-select-sm">
                <option value="TRASLADO">Traslado</option>
                <option value="PRESTAMO">Préstamo</option>
                <option value="DEVOLUCION">Devolución</option>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label small fw-semibold">Área / Ambiente Destino <span class="text-danger">*</span></label>
            <input type="text" name="area_destino" class="form-control form-control-sm" placeholder="Ej.: Laboratorio de Cómputo II" required>
        </div>
        <div class="col-md-2">
            <label class="form-label small fw-semibold">Fecha</label>
            <input type="date" name="fecha_movimiento" value="<?= date('Y-m-d') ?>" class="form-control form-control-sm" required>
        </div>
        <div class="col-md-4">
            <label class="form-label small fw-semibold">Responsable que Recibe <span class="text-danger">*</span></label>
            <input type="text" name="responsable_recibe" class="form-control form-control-sm" placeholder="Nombre del docente o coordinador" required>
        </div>
        <div class="col-md-6">
            <label class="form-label small fw-semibold">Motivo / Sustento <span class="text-danger">*</span></label>
            <input type="text" name="motivo" class="form-control form-control-sm" placeholder="Ej.: Reubicación por taller práctico 2026-II" required>
        </div>
        <div class="col-md-2 d-flex align-items-end">
            <button type="submit" class="btn btn-sm btn-emerald w-100"><i class="fa-solid fa-floppy-disk me-1"></i>Registrar</button>
        </div>
    </form>
</div>

<div class="card-clean p-3">
    <h6 class="fw-bold mb-3">Historial de Movimientos Registrados</h6>
    <div class="table-responsive">
        <table class="table table-hover align-middle small mb-0">
            <thead>
                <tr>
                    <th class="nowrap-col">Acta</th>
                    <th class="nowrap-col">Tipo</th>
                    <th>Bien</th>
                    <th>Origen &rarr; Destino</th>
                    <th>Recibe</th>
                    <th class="nowrap-col">Fecha</th>
                    <th class="nowrap-col text-end">Imprimir</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($movimientos)): ?>
                    <tr><td colspan="7" class="text-center text-muted py-4">No existen movimientos registrados aún.</td></tr>
                <?php else: ?>
                    <?php foreach ($movimientos as $m): ?>
                        <tr>
                            <td class="nowrap-col fw-bold"><?= h($m['codigo_acta']) ?></td>
                            <td class="nowrap-col"><span class="badge bg-info text-dark"><?= h($m['tipo_movimiento']) ?></span></td>
                            <td><strong><?= h($m['codigo_bien']) ?></strong><div class="text-muted"><?= h($m['descripcion_bien']) ?></div></td>
                            <td><?= h($m['area_origen']) ?> &rarr; <strong><?= h($m['area_destino']) ?></strong></td>
                            <td><?= h($m['responsable_recibe']) ?></td>
                            <td class="nowrap-col"><?= h(fmt_fecha($m['fecha_movimiento'] ?? null)) ?></td>
                            <td class="nowrap-col text-end">
                                <a href="<?= h(baseUrl('admin/movimientos.php?acta_id=' . (int)$m['id'])) ?>" target="_blank" class="btn btn-sm btn-outline-success">
                                    <i class="fa-solid fa-print me-1"></i>Acta
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
