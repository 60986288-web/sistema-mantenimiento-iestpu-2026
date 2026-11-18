<?php
require_once dirname(__DIR__, 2) . '/includes/db.php';
require_once dirname(__DIR__, 2) . '/includes/layout.php';
$u = requireLogin();
$pdo = getPDO();
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? 'crear_apsti';
    if ($accion === 'crear_apsti') {
        $corr = trim($_POST['correlativo'] ?? '040');
        $cod_apsti = trim($_POST['codigo_apsti'] ?? '');
        $cod_int = trim($_POST['codigo_interno'] ?? $cod_apsti);
        $den = trim($_POST['denominacion'] ?? 'CPU');
        $mar = trim($_POST['marca'] ?? 'TEROS');
        $col = trim($_POST['color'] ?? 'NEGRO');
        $amb = trim($_POST['area_ambiente'] ?? 'Laboratorio de Cómputo I');
        $proc = trim($_POST['procesador'] ?? 'Intel Core i7-14700F 2.10 GHz');
        $ram = trim($_POST['ram'] ?? '16GB DDR4');
        $disco = trim($_POST['disco'] ?? '500 GB SSD');
        $video = trim($_POST['video'] ?? 'NVIDIA GeForce GT 730 4GB');
        $perif = trim($_POST['perifericos'] ?? 'Mon: LC1-M01 | Tec: LC1-T01 | Mou: LC1-MU01 | Est: LC1-E01');
        $so = trim($_POST['so'] ?? 'Windows 11 Pro');
        $red = trim($_POST['red'] ?? 'ok');
        $spec = "Procesador: {$proc} | RAM: {$ram} | Disco: {$disco} | Video: {$video} | {$perif} | SO: {$so} | Red: {$red}";
        $fcomp = $_POST['fecha_compra'] ?: '2024-03-15';
        $flim = $_POST['fecha_limite'] ?: '2026-11-30';
        $est = $_POST['estado_conservacion'] ?? 'B';
        $obs = trim($_POST['diagnostico'] ?? 'Operativo');

        $st = $pdo->prepare("INSERT INTO bienes_inventario (correlativo, codigo_apsti, codigo_interno, denominacion, marca, color, especificaciones, programa, area_ambiente, responsable, fecha_compra, estado_conservacion, requiere_mantenimiento, fecha_limite_mantenimiento, diagnostico) VALUES (?, ?, ?, ?, ?, ?, ?, 'APSTI', ?, 'Ing. Carlos Sandoval Dávila', ?, ?, 1, ?, ?)");
        $st->execute([$corr, $cod_apsti, $cod_int, $den, $mar, $col, $spec, $amb, $fcomp, $est, $flim, $obs]);
        $msg = "¡Equipo de APSTI [$cod_apsti] inventariado con éxito y añadido al reporte inferior!";
    } elseif ($accion === 'editar') {
        $id = (int)$_POST['id'];
        $cod = trim($_POST['codigo_apsti'] ?? '');
        $den = trim($_POST['denominacion'] ?? '');
        $mar = trim($_POST['marca'] ?? '-');
        $prog = trim($_POST['programa'] ?? 'APSTI');
        $area = trim($_POST['area_ambiente'] ?? '');
        $est = $_POST['estado_conservacion'] ?? 'B';
        $flim = $_POST['fecha_limite_mantenimiento'] ?: '2026-11-30';
        $spec = trim($_POST['especificaciones'] ?? '');
        $diag = trim($_POST['diagnostico'] ?? '');
        $st = $pdo->prepare("UPDATE bienes_inventario SET codigo_apsti=?, codigo_interno=?, denominacion=?, marca=?, programa=?, area_ambiente=?, estado_conservacion=?, fecha_limite_mantenimiento=?, especificaciones=?, diagnostico=? WHERE id=?");
        $st->execute([$cod, $cod, $den, $mar, $prog, $area, $est, $flim, $spec, $diag, $id]);
        $msg = "Datos del equipo [$cod] actualizados correctamente.";
    } elseif ($accion === 'reportar_falla') {
        $bien_id = (int)$_POST['bien_id'];
        $falla = trim($_POST['falla_reportada'] ?? 'Falla técnica reportada desde APSTI.');
        $prio = $_POST['prioridad'] ?? 'ALTA';
        $stB = $pdo->prepare("SELECT * FROM bienes_inventario WHERE id=?");
        $stB->execute([$bien_id]);
        $b = $stB->fetch();
        if ($b) {
            $codSol = 'SOL-2026-' . rand(100, 999);
            $st = $pdo->prepare("INSERT INTO solicitudes_mantenimiento (codigo_solicitud, bien_id, codigo_bien, descripcion_bien, area_solicitante, usuario_reporta, falla_reportada, prioridad, estado_flujo) VALUES (?, ?, ?, ?, ?, ?, ?, ?, '1_REPORTADO_AREA')");
            $st->execute([$codSol, $b['id'], $b['codigo_apsti'], $b['denominacion'] . ' ' . $b['marca'], $b['programa'] . ' - ' . $b['area_ambiente'], $u['nombres'], $falla, $prio]);
            $msg = "Falla reportada con código [$codSol] para el equipo {$b['codigo_apsti']}. Ahora pasa a Verificación del Administrador.";
        }
    } elseif ($accion === 'eliminar_apsti') {
        $id = (int)$_POST['id'];
        $pdo->prepare("DELETE FROM bienes_inventario WHERE id=?")->execute([$id]);
        $msg = "Equipo eliminado del listado de APSTI.";
    }
}

$bienesApsti = $pdo->query("SELECT * FROM bienes_inventario WHERE programa='APSTI' ORDER BY id ASC")->fetchAll();

if (($_GET['export'] ?? '') === 'xls') {
    $rowsXls = [];
    foreach ($bienesApsti as $b) {
        $rowsXls[] = [
            $b['correlativo'], $b['codigo_apsti'], $b['codigo_interno'], $b['denominacion'],
            $b['marca'], $b['color'], $b['area_ambiente'], $b['especificaciones'],
            $b['fecha_compra'], $b['estado_conservacion'], $b['fecha_limite_mantenimiento'], $b['diagnostico']
        ];
    }
    exportarTablaXLS(
        'Reporte_Inventario_APSTI_2026',
        'REPORTE OFICIAL DE TOMA DE INVENTARIO — PROGRAMA DE ESTUDIOS DE APSTI (' . count($bienesApsti) . ' EQUIPOS)',
        ['N.º', 'Código APSTI', 'Código IESTPU', 'Denominación', 'Marca', 'Color', 'Ambiente APSTI', 'Especificaciones de Hardware y Periféricos', 'Fecha Compra', 'Conservación', 'Fecha Límite Mant.', 'Diagnóstico / Red'],
        $rowsXls
    );
}

renderHeader('Formulario de Toma de Inventario APSTI', 'apsti');
?>
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2 no-print">
    <div>
        <h4 class="fw-bold mb-0"><i class="fa-solid fa-desktop text-primary me-1"></i> Formulario Oficial de Toma de Inventario — Programa de Estudios APSTI</h4>
        <small class="text-secondary">Permite inventariar los bienes de APSTI (Lab. Cómputo I, Lab. II, Sala de Ensamblaje, Coordinación) y lanza su reporte inmediato (<?= count($bienesApsti) ?> equipos)</small>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <button onclick="window.print()" class="btn btn-danger btn-sm fw-bold"><i class="fa-solid fa-file-pdf me-1"></i> Imprimir / Guardar Reporte APSTI (PDF)</button>
        <a href="inventariar_apsti.php?export=xls" class="btn btn-success btn-sm fw-bold"><i class="fa-solid fa-file-excel me-1"></i> Descargar Reporte APSTI en Excel (.xls)</a>
        <a href="reportes.php?programa=APSTI&export=csv" class="btn btn-outline-success btn-sm fw-bold">CSV</a>
    </div>
</div>

<?php if ($msg): ?>
    <div class="alert alert-success alert-dismissible fade show py-2 small no-print">
        <i class="fa-solid fa-circle-check me-1"></i> <?= htmlspecialchars($msg) ?>
        <button type="button" class="btn-close py-2" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="card p-3 mb-4 shadow-sm border-primary no-print">
    <div class="bg-primary text-white px-3 py-2 rounded mb-3 d-flex justify-content-between align-items-center">
        <span class="fw-bold small"><i class="fa-solid fa-keyboard me-1"></i> REGISTRAR / INVENTARIAR BIEN DEL PROGRAMA DE ESTUDIOS DE APSTI</span>
        <span class="badge bg-light text-primary">Muestra activa: <?= count($bienesApsti) ?> equipos (Supera el mínimo de 10 equipos)</span>
    </div>
    <form method="POST" class="row g-2 small">
        <input type="hidden" name="accion" value="crear_apsti">
        <div class="col-md-1"><label class="fw-bold">N.º</label><input type="text" name="correlativo" class="form-control form-control-sm" value="<?= sprintf('%03d', count($bienesApsti)+1) ?>" required></div>
        <div class="col-md-2"><label class="fw-bold">Código APSTI *</label><input type="text" name="codigo_apsti" class="form-control form-control-sm" value="APSTI-CPU-LC1-<?= sprintf('%03d', count($bienesApsti)+1) ?>" required></div>
        <div class="col-md-2"><label class="fw-bold">Código IESTPU</label><input type="text" name="codigo_interno" class="form-control form-control-sm" value="LC1-CPU<?= count($bienesApsti)+1 ?>"></div>
        <div class="col-md-2"><label class="fw-bold">Denominación *</label><input type="text" name="denominacion" class="form-control form-control-sm" value="CPU" required></div>
        <div class="col-md-2"><label class="fw-bold">Marca / Color</label><input type="text" name="marca" class="form-control form-control-sm" value="TEROS"></div>
        <div class="col-md-3"><label class="fw-bold">Ambiente APSTI</label>
            <select name="area_ambiente" class="form-select form-select-sm">
                <option value="Laboratorio de Cómputo I">Laboratorio de Cómputo I</option>
                <option value="Laboratorio de Cómputo II">Laboratorio de Cómputo II</option>
                <option value="Sala de Ensamblaje">Sala de Ensamblaje</option>
                <option value="Unidad Académica APSTI">Unidad Académica APSTI</option>
            </select>
        </div>

        <div class="col-md-3"><label class="fw-bold">Procesador</label><input type="text" name="procesador" class="form-control form-control-sm" value="Intel Core i7-14700F 2.10 GHz"></div>
        <div class="col-md-2"><label class="fw-bold">Memoria RAM</label><input type="text" name="ram" class="form-control form-control-sm" value="16GB DDR4"></div>
        <div class="col-md-2"><label class="fw-bold">Disco Duro</label><input type="text" name="disco" class="form-control form-control-sm" value="500 GB SSD"></div>
        <div class="col-md-2"><label class="fw-bold">Tarjeta de Video</label><input type="text" name="video" class="form-control form-control-sm" value="NVIDIA GT 730 4GB"></div>
        <div class="col-md-3"><label class="fw-bold">Periféricos (Mon / Tec / Mou / Est)</label><input type="text" name="perifericos" class="form-control form-control-sm" value="Mon: LC1-M37 | Tec: LC1-T25 | Est: LC1-E33"></div>

        <div class="col-md-2"><label class="fw-bold">Sistema Operativo</label><input type="text" name="so" class="form-control form-control-sm" value="Windows 11 Pro"></div>
        <div class="col-md-2"><label class="fw-bold">Estado Internet / Red</label>
            <select name="red" class="form-select form-select-sm"><option value="ok">OK (Conectado)</option><option value="problemas conexion a internet">Con problemas de red</option></select>
        </div>
        <div class="col-md-2"><label class="fw-bold">Fecha Compra</label><input type="date" name="fecha_compra" class="form-control form-control-sm" value="2024-03-15"></div>
        <div class="col-md-2"><label class="fw-bold">Fecha Límite Mant.</label><input type="date" name="fecha_limite" class="form-control form-control-sm" value="2026-11-30"></div>
        <div class="col-md-1"><label class="fw-bold">Estado</label><select name="estado_conservacion" class="form-select form-select-sm"><option value="B">B</option><option value="R">R</option><option value="M">M</option></select></div>
        <div class="col-md-3"><label class="fw-bold">Observación y Guardado</label>
            <div class="input-group input-group-sm">
                <input type="text" name="diagnostico" class="form-control" value="Operativo">
                <button class="btn btn-success fw-bold" type="submit"><i class="fa-solid fa-floppy-disk me-1"></i> + Guardar Bien</button>
            </div>
        </div>
    </form>
</div>

<div class="card p-3 shadow-sm">
    <div class="bg-dark text-white px-3 py-2 rounded mb-2 d-flex justify-content-between align-items-center">
        <span class="fw-bold small">REPORTE INMEDIATO DE BIENES INVENTARIADOS EN APSTI (Total: <?= count($bienesApsti) ?> equipos registrados del Excel + Nuevos)</span>
        <span class="badge bg-info text-dark">Listo para Imprimir PDF / Exportar Excel</span>
    </div>
    <div class="table-responsive">
        <table class="table table-sm table-bordered table-striped align-middle small mb-0">
            <thead class="table-secondary">
                <tr>
                    <th>N.º</th>
                    <th>Código APSTI</th>
                    <th>Cód. IESTPU</th>
                    <th>Equipo / Marca</th>
                    <th>Ambiente APSTI</th>
                    <th>Especificaciones de Hardware y Periféricos</th>
                    <th>Est.</th>
                    <th>Semáforo Límite</th>
                    <th>Diagnóstico</th>
                    <th class="no-print" style="width:185px;">Acciones (CRUD / Ficha)</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($bienesApsti as $b):
                $sem = calcularSemaforo((string)$b['fecha_limite_mantenimiento'], 1);
            ?>
                <tr>
                    <td class="fw-bold text-center"><?= htmlspecialchars($b['correlativo']) ?></td>
                    <td class="fw-bold text-primary"><?= htmlspecialchars($b['codigo_apsti']) ?></td>
                    <td><?= htmlspecialchars($b['codigo_interno']) ?></td>
                    <td><?= htmlspecialchars($b['denominacion'] . ' ' . $b['marca']) ?></td>
                    <td><?= htmlspecialchars($b['area_ambiente']) ?></td>
                    <td style="font-size:0.75rem;"><?= htmlspecialchars($b['especificaciones']) ?></td>
                    <td class="text-center"><span class="badge bg-secondary"><?= htmlspecialchars($b['estado_conservacion']) ?></span></td>
                    <td><span class="badge <?= $sem['clase'] ?>"><?= htmlspecialchars($sem['texto']) ?></span></td>
                    <td><?= htmlspecialchars($b['diagnostico']) ?></td>
                    <td class="no-print">
                        <div class="d-flex gap-1">
                            <a href="buscar_equipo.php?q=<?= urlencode($b['codigo_apsti']) ?>" class="btn btn-primary btn-sm py-0 px-2" title="Ver Ventana y Ficha"><i class="fa-solid fa-eye"></i></a>
                            <button type="button" class="btn btn-warning btn-sm py-0 px-2" title="Editar Equipo"
                                onclick='abrirModalEditar(<?= json_encode($b, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>
                                <i class="fa-solid fa-pen"></i>
                            </button>
                            <button type="button" class="btn btn-danger btn-sm py-0 px-2" title="Reportar Falla"
                                onclick='abrirModalFalla(<?= (int)$b["id"] ?>, <?= json_encode($b["codigo_apsti"] . " — " . $b["denominacion"]) ?>)'>
                                <i class="fa-solid fa-triangle-exclamation"></i>
                            </button>
                            <form method="POST" class="d-inline" onsubmit="return confirm('¿Eliminar este equipo de APSTI?');">
                                <input type="hidden" name="accion" value="eliminar_apsti">
                                <input type="hidden" name="id" value="<?= (int)$b['id'] ?>">
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
