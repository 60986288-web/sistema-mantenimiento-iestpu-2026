<?php
require_once dirname(__DIR__, 2) . '/includes/db.php';
require_once dirname(__DIR__, 2) . '/includes/layout.php';
$u = requireLogin();
$pdo = getPDO();
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? 'crear_caf';
    if ($accion === 'crear_caf') {
        $cod = trim($_POST['codigo'] ?? 'CAF-N01');
        $den = trim($_POST['denominacion'] ?? '');
        $mar = trim($_POST['marca'] ?? '-');
        $tip = trim($_POST['tipo_material'] ?? 'METAL');
        $col = trim($_POST['color'] ?? 'BLANCO');
        $dim = trim($_POST['dimensiones'] ?? '-');
        $est = $_POST['estado_conservacion'] ?? 'B';
        $req = (int)($_POST['requiere_mantenimiento'] ?? 1);
        $cond = trim($_POST['condicion'] ?? 'Cafetín Institucional (En Uso)');
        $flim = $_POST['fecha_limite'] ?: '2026-11-30';
        $st = $pdo->prepare("INSERT INTO bienes_inventario (correlativo, codigo_apsti, codigo_interno, denominacion, marca, tipo_material, color, dimensiones, especificaciones, programa, area_ambiente, estado_conservacion, requiere_mantenimiento, fecha_limite_mantenimiento, diagnostico) VALUES ('331', ?, ?, ?, ?, ?, ?, ?, ?, 'CAFETIN', 'Cafetín Institucional', ?, ?, ?, ?)");
        $st->execute([$cod, $cod, $den, $mar, $tip, $col, $dim, $cond, $est, $req, $flim, "Registrado en Módulo de Cafetín ($cond)"]);
        $msg = "Bien [$cod - $den] agregado correctamente al Módulo de Cafetín.";
    } elseif ($accion === 'editar') {
        $id = (int)$_POST['id'];
        $cod = trim($_POST['codigo_apsti'] ?? '');
        $den = trim($_POST['denominacion'] ?? '');
        $mar = trim($_POST['marca'] ?? '-');
        $prog = trim($_POST['programa'] ?? 'CAFETIN');
        $area = trim($_POST['area_ambiente'] ?? 'Cafetín Institucional');
        $est = $_POST['estado_conservacion'] ?? 'B';
        $flim = $_POST['fecha_limite_mantenimiento'] ?: '2026-11-30';
        $spec = trim($_POST['especificaciones'] ?? '');
        $diag = trim($_POST['diagnostico'] ?? '');
        $st = $pdo->prepare("UPDATE bienes_inventario SET codigo_apsti=?, codigo_interno=?, denominacion=?, marca=?, programa=?, area_ambiente=?, estado_conservacion=?, fecha_limite_mantenimiento=?, especificaciones=?, diagnostico=? WHERE id=?");
        $st->execute([$cod, $cod, $den, $mar, $prog, $area, $est, $flim, $spec, $diag, $id]);
        $msg = "Registro de Cafetín [$cod] actualizado correctamente.";
    } elseif ($accion === 'reportar_falla') {
        $bien_id = (int)$_POST['bien_id'];
        $falla = trim($_POST['falla_reportada'] ?? 'Falla reportada desde Módulo de Cafetín.');
        $prio = $_POST['prioridad'] ?? 'ALTA';
        $stB = $pdo->prepare("SELECT * FROM bienes_inventario WHERE id=?");
        $stB->execute([$bien_id]);
        $b = $stB->fetch();
        if ($b) {
            $codSol = 'SOL-2026-' . rand(100, 999);
            $st = $pdo->prepare("INSERT INTO solicitudes_mantenimiento (codigo_solicitud, bien_id, codigo_bien, descripcion_bien, area_solicitante, usuario_reporta, falla_reportada, prioridad, estado_flujo) VALUES (?, ?, ?, ?, ?, ?, ?, ?, '1_REPORTADO_AREA')");
            $st->execute([$codSol, $b['id'], $b['codigo_apsti'], $b['denominacion'] . ' ' . $b['marca'], 'CAFETÍN - ' . $b['area_ambiente'], $u['nombres'], $falla, $prio]);
            $msg = "Falla reportada con código [$codSol] para el bien de Cafetín {$b['codigo_apsti']}.";
        }
    } elseif ($accion === 'eliminar_caf') {
        $id = (int)$_POST['id'];
        $pdo->prepare("DELETE FROM bienes_inventario WHERE id=?")->execute([$id]);
        $msg = "Registro eliminado del Módulo de Cafetín.";
    }
}

$filtro = $_GET['filtro'] ?? 'todos';
$sql = "SELECT * FROM bienes_inventario WHERE programa='CAFETIN'";
if ($filtro === 'activos') {
    $sql .= " AND estado_conservacion != 'MM'";
} elseif ($filtro === 'movimiento') {
    $sql .= " AND estado_conservacion = 'MM'";
}
$sql .= " ORDER BY id ASC";
$bienesCaf = $pdo->query($sql)->fetchAll();

if (($_GET['export'] ?? '') === 'xls') {
    $rowsXls = [];
    foreach ($bienesCaf as $idx => $c) {
        $rowsXls[] = [
            $idx + 1, $c['codigo_interno'], $c['denominacion'], $c['marca'],
            $c['tipo_material'], $c['color'], $c['dimensiones'], $c['estado_conservacion'],
            $c['especificaciones'], $c['fecha_limite_mantenimiento']
        ];
    }
    exportarTablaXLS(
        'Reporte_Modulo_Cafetin_IESTPU_2026',
        'REPORTE OFICIAL DEL MÓDULO DE BIENES DE CAFETÍN INSTITUCIONAL (' . count($bienesCaf) . ' BIENES)',
        ['Ítem', 'Código Interno', 'Denominación del Bien', 'Marca', 'Tipo / Material', 'Color', 'Dimensiones / Medidas', 'Estado', 'Ubicación / Condición en Excel', 'Fecha Límite Mant.'],
        $rowsXls
    );
}

renderHeader('Módulo de Bienes de Cafetín', 'cafetin');
?>
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2 no-print">
    <div>
        <h4 class="fw-bold mb-0"><i class="fa-solid fa-mug-hot text-warning me-1"></i> Módulo de Control de Bienes Patrimoniales — Área: CAFETÍN</h4>
        <small class="text-secondary">Responsable en Excel: Lic. Ernesto Correa Barrantes (DNI 27722958) · 18 bienes activos en hoja «Cafetin» + 12 bienes en «Movimiento de Bienes»</small>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <button onclick="window.print()" class="btn btn-danger btn-sm fw-bold"><i class="fa-solid fa-file-pdf me-1"></i> Imprimir Reporte Cafetín (PDF)</button>
        <a href="cafetin.php?filtro=<?= urlencode($filtro) ?>&export=xls" class="btn btn-success btn-sm fw-bold"><i class="fa-solid fa-file-excel me-1"></i> Exportar Cafetín en Excel (.xls)</a>
    </div>
</div>

<?php if ($msg): ?>
    <div class="alert alert-success alert-dismissible fade show py-2 small no-print">
        <i class="fa-solid fa-circle-check me-1"></i> <?= htmlspecialchars($msg) ?>
        <button type="button" class="btn-close py-2" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="card p-3 mb-3 border-warning shadow-sm no-print">
    <h6 class="fw-bold text-dark mb-2"><i class="fa-solid fa-plus-circle text-warning me-1"></i> Agregar Bien al Inventario de Cafetín</h6>
    <form method="POST" class="row g-2 small">
        <input type="hidden" name="accion" value="crear_caf">
        <div class="col-md-2"><label class="fw-bold">Código Interno *</label><input type="text" name="codigo" class="form-control form-control-sm" placeholder="Ej. CAF-EX02" required></div>
        <div class="col-md-2"><label class="fw-bold">Denominación *</label><input type="text" name="denominacion" class="form-control form-control-sm" placeholder="Ej. Congelador / Mesa" required></div>
        <div class="col-md-2"><label class="fw-bold">Marca</label><input type="text" name="marca" class="form-control form-control-sm" placeholder="Ej. DAEWOO / QPLAST"></div>
        <div class="col-md-2"><label class="fw-bold">Tipo / Material</label><input type="text" name="tipo_material" class="form-control form-control-sm" value="METAL / PLÁSTICO"></div>
        <div class="col-md-1"><label class="fw-bold">Color</label><input type="text" name="color" class="form-control form-control-sm" value="BLANCO"></div>
        <div class="col-md-3"><label class="fw-bold">Dimensiones / Medidas</label><input type="text" name="dimensiones" class="form-control form-control-sm" placeholder="Ej. 220 LITROS / 3 HORNILLAS"></div>
        <div class="col-md-4"><label class="fw-bold">Condición en Inventario</label>
            <select name="condicion" class="form-select form-select-sm">
                <option value="Cafetín Institucional (En Uso)">Activo en Cafetín Institucional (En Uso)</option>
                <option value="Trasladado de Cafetín a Almacén General">En Movimiento de Bienes (Trasladado a Almacén)</option>
            </select>
        </div>
        <div class="col-md-2"><label class="fw-bold">Conservación</label>
            <select name="estado_conservacion" class="form-select form-select-sm"><option value="B">Bueno (B)</option><option value="R">Regular (R)</option><option value="MM">Muy Malo (MM)</option></select>
        </div>
        <div class="col-md-2"><label class="fw-bold">¿Requiere Mant.?</label>
            <select name="requiere_mantenimiento" class="form-select form-select-sm"><option value="1">Sí (Equipo)</option><option value="0">No (Mobiliario)</option></select>
        </div>
        <div class="col-md-2"><label class="fw-bold">Fecha Límite</label><input type="date" name="fecha_limite" class="form-control form-control-sm" value="2026-11-25"></div>
        <div class="col-md-2 d-flex align-items-end"><button class="btn btn-warning btn-sm w-100 fw-bold" type="submit"><i class="fa-solid fa-floppy-disk me-1"></i> Registrar</button></div>
    </form>
</div>

<div class="d-flex gap-2 mb-2 no-print">
    <a href="cafetin.php?filtro=todos" class="btn btn-sm <?= $filtro==='todos'?'btn-dark':'btn-outline-dark' ?>">Todos los Bienes de Cafetín (30)</a>
    <a href="cafetin.php?filtro=activos" class="btn btn-sm <?= $filtro==='activos'?'btn-success':'btn-outline-success' ?>">18 Bienes Activos en Hoja «Cafetin»</a>
    <a href="cafetin.php?filtro=movimiento" class="btn btn-sm <?= $filtro==='movimiento'?'btn-danger':'btn-outline-danger' ?>">12 Bienes en «Movimiento de Bienes» (Almacén)</a>
</div>

<div class="card p-3 shadow-sm">
    <div class="table-responsive">
        <table class="table table-sm table-bordered table-hover align-middle small mb-0">
            <thead class="table-dark">
                <tr>
                    <th>N.º</th>
                    <th>Código Interno</th>
                    <th>Denominación del Bien</th>
                    <th>Marca</th>
                    <th>Tipo / Material</th>
                    <th>Color</th>
                    <th>Dimensiones / Capacidad</th>
                    <th>Est.</th>
                    <th>Ubicación / Condición</th>
                    <th>Control Mantenimiento</th>
                    <th class="no-print" style="width:185px;">Acciones (CRUD / Ficha)</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($bienesCaf as $idx => $c): ?>
                <tr>
                    <td class="fw-bold text-center"><?= sprintf('%02d', $idx + 1) ?></td>
                    <td class="fw-bold text-primary"><?= htmlspecialchars($c['codigo_interno']) ?></td>
                    <td class="fw-bold"><?= htmlspecialchars($c['denominacion']) ?></td>
                    <td><?= htmlspecialchars($c['marca']) ?></td>
                    <td><?= htmlspecialchars($c['tipo_material']) ?></td>
                    <td><?= htmlspecialchars($c['color']) ?></td>
                    <td><?= htmlspecialchars($c['dimensiones']) ?></td>
                    <td class="text-center"><span class="badge <?= $c['estado_conservacion']==='MM'?'bg-danger':'bg-secondary' ?>"><?= htmlspecialchars($c['estado_conservacion']) ?></span></td>
                    <td><?= htmlspecialchars($c['especificaciones']) ?></td>
                    <td>
                        <?php if ($c['estado_conservacion'] === 'MM'): ?>
                            <span class="badge bg-danger">Para Baja / Almacén General</span>
                        <?php elseif ((int)$c['requiere_mantenimiento'] === 1): ?>
                            <span class="badge bg-warning text-dark">Preventivo: <?= htmlspecialchars($c['fecha_limite_mantenimiento']) ?></span>
                        <?php else: ?>
                            <span class="badge bg-success">Mobiliario Operativo</span>
                        <?php endif; ?>
                    </td>
                    <td class="no-print">
                        <div class="d-flex gap-1">
                            <a href="buscar_equipo.php?q=<?= urlencode($c['codigo_interno']) ?>" class="btn btn-primary btn-sm py-0 px-2" title="Ver Ficha"><i class="fa-solid fa-eye"></i></a>
                            <button type="button" class="btn btn-warning btn-sm py-0 px-2" title="Editar Bien de Cafetín"
                                onclick='abrirModalEditar(<?= json_encode($c, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>
                                <i class="fa-solid fa-pen"></i>
                            </button>
                            <button type="button" class="btn btn-danger btn-sm py-0 px-2" title="Reportar Falla"
                                onclick='abrirModalFalla(<?= (int)$c["id"] ?>, <?= json_encode($c["codigo_interno"] . " — " . $c["denominacion"]) ?>)'>
                                <i class="fa-solid fa-triangle-exclamation"></i>
                            </button>
                            <form method="POST" class="d-inline" onsubmit="return confirm('¿Eliminar este registro de Cafetín?');">
                                <input type="hidden" name="accion" value="eliminar_caf">
                                <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                                <button type="submit" class="btn btn-outline-danger btn-sm py-0 px-2" title="Eliminar"><i class="fa-solid fa-trash"></i></button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- MODAL EDITAR BIEN -->
<div class="modal fade" id="modalEditarBien" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form method="POST" class="modal-content">
            <input type="hidden" name="accion" value="editar">
            <input type="hidden" name="id" id="edit_id">
            <div class="modal-header bg-warning py-2">
                <h6 class="modal-title fw-bold text-dark"><i class="fa-solid fa-pen-to-square me-1"></i> Editar Datos del Bien Patrimonial</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body row g-2 small">
                <div class="col-md-4"><label class="fw-bold">Código APSTI / Interno</label><input type="text" name="codigo_apsti" id="edit_codigo" class="form-control form-control-sm" required></div>
                <div class="col-md-4"><label class="fw-bold">Denominación</label><input type="text" name="denominacion" id="edit_denominacion" class="form-control form-control-sm" required></div>
                <div class="col-md-4"><label class="fw-bold">Marca</label><input type="text" name="marca" id="edit_marca" class="form-control form-control-sm"></div>
                <div class="col-md-3"><label class="fw-bold">Programa</label><input type="text" name="programa" id="edit_programa" class="form-control form-control-sm"></div>
                <div class="col-md-4"><label class="fw-bold">Área / Ambiente</label><input type="text" name="area_ambiente" id="edit_area" class="form-control form-control-sm"></div>
                <div class="col-md-2"><label class="fw-bold">Estado</label><input type="text" name="estado_conservacion" id="edit_estado" class="form-control form-control-sm"></div>
                <div class="col-md-3"><label class="fw-bold">Fecha Límite Mant.</label><input type="date" name="fecha_limite_mantenimiento" id="edit_flim" class="form-control form-control-sm"></div>
                <div class="col-md-12"><label class="fw-bold">Especificaciones Técnicas / Ubicación</label><input type="text" name="especificaciones" id="edit_spec" class="form-control form-control-sm"></div>
                <div class="col-md-12"><label class="fw-bold">Diagnóstico / Observación</label><input type="text" name="diagnostico" id="edit_diag" class="form-control form-control-sm"></div>
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-warning btn-sm fw-bold">Guardar Cambios</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL REPORTAR FALLA DIRECTA -->
<div class="modal fade" id="modalReportarFalla" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <input type="hidden" name="accion" value="reportar_falla">
            <input type="hidden" name="bien_id" id="falla_bien_id">
            <div class="modal-header bg-danger text-white py-2">
                <h6 class="modal-title fw-bold"><i class="fa-solid fa-triangle-exclamation me-1"></i> Reportar Falla o Avería de Equipo</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body small">
                <div class="mb-2"><strong>Equipo seleccionado:</strong> <span id="falla_equipo_lbl" class="text-danger fw-bold"></span></div>
                <div class="mb-2">
                    <label class="fw-bold">Descripción de la Falla Detectada *</label>
                    <textarea name="falla_reportada" class="form-control form-control-sm" rows="3" required placeholder="Describe el desperfecto técnico para que el Administrador lo verifique y emita la Ficha..."></textarea>
                </div>
                <div class="mb-2">
                    <label class="fw-bold">Prioridad de Atención</label>
                    <select name="prioridad" class="form-select form-select-sm"><option value="ALTA">ALTA</option><option value="CRITICA">CRÍTICA</option><option value="MEDIA">MEDIA</option></select>
                </div>
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-danger btn-sm fw-bold">Enviar a Verificación del Administrador</button>
            </div>
        </form>
    </div>
</div>

<script>
function abrirModalEditar(b) {
    document.getElementById('edit_id').value = b.id;
    document.getElementById('edit_codigo').value = b.codigo_apsti;
    document.getElementById('edit_denominacion').value = b.denominacion;
    document.getElementById('edit_marca').value = b.marca;
    document.getElementById('edit_programa').value = b.programa;
    document.getElementById('edit_area').value = b.area_ambiente;
    document.getElementById('edit_estado').value = b.estado_conservacion;
    document.getElementById('edit_flim').value = b.fecha_limite_mantenimiento;
    document.getElementById('edit_spec').value = b.especificaciones;
    document.getElementById('edit_diag').value = b.diagnostico;
    new bootstrap.Modal(document.getElementById('modalEditarBien')).show();
}
function abrirModalFalla(id, label) {
    document.getElementById('falla_bien_id').value = id;
    document.getElementById('falla_equipo_lbl').textContent = label;
    new bootstrap.Modal(document.getElementById('modalReportarFalla')).show();
}
</script>

<?php renderFooter(); ?>
