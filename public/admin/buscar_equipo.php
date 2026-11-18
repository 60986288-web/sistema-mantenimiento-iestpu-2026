<?php
require_once dirname(__DIR__, 2) . '/includes/db.php';
require_once dirname(__DIR__, 2) . '/includes/layout.php';
$u = requireLogin();
$pdo = getPDO();
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['actualizar_visita'])) {
    $id = (int)$_POST['bien_id'];
    $e_vis = $_POST['estado_visita'] ?? 'Visitado';
    $f_tec = $_POST['fecha_viene_tecnico'] ?: date('Y-m-d');
    $e_srv = $_POST['estado_servicio'] ?? 'Servicio Completo';
    $p_vis = $_POST['proxima_visita'] ?: '2027-02-11';
    $f_lim = $_POST['fecha_limite_mantenimiento'] ?: '2026-11-30';
    $diag  = trim($_POST['diagnostico'] ?? '');
    $st = $pdo->prepare("UPDATE bienes_inventario SET estado_visita=?, fecha_viene_tecnico=?, estado_servicio=?, proxima_visita=?, fecha_limite_mantenimiento=?, diagnostico=? WHERE id=?");
    $st->execute([$e_vis, $f_tec, $e_srv, $p_vis, $f_lim, $diag, $id]);
    $msg = "Estado de visita, diagnóstico y fecha límite actualizados en tiempo real.";
}

$q = trim($_GET['q'] ?? '002');
$st = $pdo->prepare("SELECT * FROM bienes_inventario WHERE correlativo = ? OR codigo_apsti LIKE ? OR codigo_interno LIKE ? OR serie LIKE ? LIMIT 1");
$st->execute([$q, "%$q%", "%$q%", "%$q%"]);
$bien = $st->fetch();

renderHeader('Módulo 2: Buscador por Código o Serie', 'buscar');
?>
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2 no-print">
    <div>
        <h4 class="fw-bold mb-0"><i class="fa-solid fa-magnifying-glass text-primary me-1"></i> Módulo 2: Buscador por Código o Serie y Ventana de Reporte Técnico Instantáneo</h4>
        <small class="text-secondary">Consulta cualquier equipo por su correlativo (001, 002), código interno (APSTI-CPU-LC1-002, AAR-IMP01, CAF-C) o número de serie</small>
    </div>
</div>

<?php if ($msg): ?>
    <div class="alert alert-success py-2 small no-print"><i class="fa-solid fa-circle-check me-1"></i> <?= htmlspecialchars($msg) ?></div>
<?php endif; ?>

<form method="GET" class="card p-3 mb-3 shadow-sm border-primary no-print">
    <label class="fw-bold small text-primary mb-1">BUSCAR BIEN POR N.º CORRELATIVO (001, 002), CÓDIGO APSTI/INTERNO O SERIE DE FÁBRICA:</label>
    <div class="input-group">
        <input type="text" name="q" class="form-control" value="<?= htmlspecialchars($q) ?>" placeholder="Escribe el código o serie (ej. 002, APSTI-CPU-LC1-002, AAR-IMP01, CAF-C)...">
        <button class="btn btn-primary fw-bold px-4" type="submit"><i class="fa-solid fa-search me-1"></i> Consultar Bien →</button>
    </div>
    <div class="mt-2 small text-secondary d-flex flex-wrap gap-1 align-items-center">
        <strong>Prueba rápida (1 clic):</strong>
        <a href="buscar_equipo.php?q=001" class="badge bg-light text-dark border text-decoration-none">001 (CPU Teros Core i7 OK)</a>
        <a href="buscar_equipo.php?q=002" class="badge bg-primary text-white text-decoration-none">002 (CPU Teros con Ficha-014)</a>
        <a href="buscar_equipo.php?q=022" class="badge bg-warning text-dark text-decoration-none">022 (CPU DataOne sin red)</a>
        <a href="buscar_equipo.php?q=AAR-IMP01" class="badge bg-info text-dark text-decoration-none">AAR-IMP01 (Impresora Epson APSTI)</a>
        <a href="buscar_equipo.php?q=SE-CPU01" class="badge bg-secondary text-white text-decoration-none">SE-CPU01 (Sala Ensamblaje)</a>
        <a href="buscar_equipo.php?q=CAF-C" class="badge bg-success text-white text-decoration-none">CAF-C (Congelador Cafetín)</a>
    </div>
</form>

<?php if ($bien): ?>
<div class="card shadow border-dark mb-4">
    <div class="card-header bg-dark text-white d-flex flex-wrap justify-content-between align-items-center py-2 gap-2">
        <span class="fw-bold">VENTANA DE REPORTE TÉCNICO INSTANTÁNEO — BIEN N.º <?= htmlspecialchars($bien['correlativo']) ?> | CÓDIGO: <?= htmlspecialchars($bien['codigo_apsti']) ?></span>
        <div class="d-flex gap-2 no-print">
            <a href="../docente/reportar.php?bien_id=<?= (int)$bien['id'] ?>" class="btn btn-warning btn-sm fw-bold"><i class="fa-solid fa-triangle-exclamation me-1"></i> Reportar Falla</a>
            <a href="ficha_mantenimiento.php?bien_id=<?= (int)$bien['id'] ?>" class="btn btn-success btn-sm fw-bold"><i class="fa-solid fa-file-invoice me-1"></i> Emitir / Abrir Ficha Oficial (PDF / Excel)</a>
        </div>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-4">
                <div class="card h-100 border-primary">
                    <div class="card-header bg-primary text-white fw-bold small py-2">1. DATOS DEL PRODUCTO / BIEN</div>
                    <div class="card-body small">
                        <div class="mb-1"><strong>N.º Correlativo:</strong> <?= htmlspecialchars($bien['correlativo']) ?></div>
                        <div class="mb-1"><strong>Código APSTI / Interno:</strong> <span class="text-primary fw-bold"><?= htmlspecialchars($bien['codigo_apsti']) ?></span></div>
                        <div class="mb-1"><strong>Denominación:</strong> <?= htmlspecialchars($bien['denominacion']) ?></div>
                        <div class="mb-1"><strong>Marca / Color:</strong> <?= htmlspecialchars($bien['marca'] . ' / ' . $bien['color']) ?></div>
                        <div class="mb-1"><strong>Serie / Dimensiones:</strong> <?= htmlspecialchars($bien['serie'] . ' / ' . $bien['dimensiones']) ?></div>
                        <div class="mb-1"><strong>Fecha de Compra:</strong> <?= htmlspecialchars($bien['fecha_compra']) ?></div>
                        <div class="mb-2"><strong>Estado Conservación:</strong> <span class="badge bg-secondary"><?= htmlspecialchars($bien['estado_conservacion']) ?></span></div>
                        <div class="p-2 bg-light rounded border">
                            <strong>Especificaciones Técnicas:</strong><br>
                            <?= htmlspecialchars($bien['especificaciones']) ?>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card h-100 border-success">
                    <div class="card-header bg-success text-white fw-bold small py-2">2. DATOS DEL CLIENTE / OFICINA / USUARIO</div>
                    <div class="card-body small">
                        <div class="mb-2"><strong>Entidad Pública:</strong> IESTP «Utcubamba» (Cód. Modular 741744)</div>
                        <div class="mb-2"><strong>Programa de Estudios:</strong> <span class="badge bg-dark"><?= htmlspecialchars($bien['programa']) ?></span></div>
                        <div class="mb-2"><strong>Oficina / Laboratorio / Ambiente:</strong><br><span class="fw-bold text-success"><?= htmlspecialchars($bien['area_ambiente']) ?></span></div>
                        <div class="mb-2"><strong>Coordinador / Responsable:</strong><br><?= htmlspecialchars($bien['responsable']) ?></div>
                        <div class="mb-2"><strong>Ubicación Institucional:</strong> Sede Central Bagua Grande – Utcubamba</div>
                        <div class="p-2 bg-light rounded border mt-3">
                            <strong>Control Patrimonial:</strong><br>
                            Registrado en el Inventario Institucional Excel y vinculado al Cronograma Preventivo.
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card h-100 border-dark">
                    <div class="card-header bg-secondary text-white fw-bold small py-2">3. ESTADO DE VISITA Y MANTENIMIENTO</div>
                    <div class="card-body small">
                        <form method="POST">
                            <input type="hidden" name="actualizar_visita" value="1">
                            <input type="hidden" name="bien_id" value="<?= (int)$bien['id'] ?>">
                            <div class="row g-2">
                                <div class="col-6">
                                    <label class="fw-bold">Estado Visita:</label>
                                    <select name="estado_visita" class="form-select form-select-sm">
                                        <option value="Visitado" <?= $bien['estado_visita']==='Visitado'?'selected':'' ?>>Visitado</option>
                                        <option value="Por Visitar" <?= $bien['estado_visita']==='Por Visitar'?'selected':'' ?>>Por Visitar</option>
                                        <option value="Programado" <?= $bien['estado_visita']==='Programado'?'selected':'' ?>>Programado</option>
                                    </select>
                                </div>
                                <div class="col-6">
                                    <label class="fw-bold">Estado Servicio:</label>
                                    <select name="estado_servicio" class="form-select form-select-sm">
                                        <option value="Servicio Completo" <?= $bien['estado_servicio']==='Servicio Completo'?'selected':'' ?>>Servicio Completo</option>
                                        <option value="Servicio Pendiente" <?= $bien['estado_servicio']==='Servicio Pendiente'?'selected':'' ?>>Servicio Pendiente</option>
                                    </select>
                                </div>
                                <div class="col-6">
                                    <label class="fw-bold">Fecha Viene Técnico:</label>
                                    <input type="date" name="fecha_viene_tecnico" class="form-control form-control-sm" value="<?= htmlspecialchars($bien['fecha_viene_tecnico']) ?>">
                                </div>
                                <div class="col-6">
                                    <label class="fw-bold">Próxima Visita:</label>
                                    <input type="date" name="proxima_visita" class="form-control form-control-sm" value="<?= htmlspecialchars($bien['proxima_visita']) ?>">
                                </div>
                                <div class="col-12">
                                    <label class="fw-bold">Fecha Límite Mantenimiento:</label>
                                    <input type="date" name="fecha_limite_mantenimiento" class="form-control form-control-sm" value="<?= htmlspecialchars($bien['fecha_limite_mantenimiento']) ?>">
                                </div>
                                <div class="col-12">
                                    <label class="fw-bold">Diagnóstico Técnico Registrado:</label>
                                    <textarea name="diagnostico" class="form-control form-control-sm" rows="2"><?= htmlspecialchars($bien['diagnostico']) ?></textarea>
                                </div>
                                <div class="col-12 no-print">
                                    <button type="submit" class="btn btn-primary btn-sm w-100 fw-bold"><i class="fa-solid fa-floppy-disk me-1"></i> Guardar Estado y Diagnóstico</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php else: ?>
    <div class="alert alert-warning">No se encontró ningún bien con el criterio <strong><?= htmlspecialchars($q) ?></strong>. Intenta con <code>001</code>, <code>002</code>, <code>AAR-IMP01</code> o <code>CAF-C</code>.</div>
<?php endif; ?>
<?php renderFooter(); ?>
