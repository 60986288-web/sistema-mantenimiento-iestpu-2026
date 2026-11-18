<?php
require_once dirname(__DIR__, 2) . '/includes/db.php';
require_once dirname(__DIR__, 2) . '/includes/layout.php';
$u = requireLogin();
$pdo = getPDO();
$msg = '';
$msgType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    if ($accion === 'crear') {
        $corr = trim($_POST['correlativo'] ?? '040');
        $cod = trim($_POST['codigo_apsti'] ?? '');
        $den = trim($_POST['denominacion'] ?? '');
        $mar = trim($_POST['marca'] ?? '-');
        $mod = trim($_POST['modelo'] ?? '-');
        $col = trim($_POST['color'] ?? 'NEGRO');
        $ser = trim($_POST['serie'] ?? '-');
        $prog = trim($_POST['programa'] ?? 'APSTI');
        $area = trim($_POST['area_ambiente'] ?? 'Laboratorio de Cómputo I');
        $resp = trim($_POST['responsable'] ?? 'Ing. Carlos Sandoval Dávila');
        $fcomp = $_POST['fecha_compra'] ?: date('Y-m-d');
        $est = $_POST['estado_conservacion'] ?? 'B';
        $req = (int)($_POST['requiere_mantenimiento'] ?? 1);
        $flim = $_POST['fecha_limite_mantenimiento'] ?: '2026-11-30';
        $spec = trim($_POST['especificaciones'] ?? '');
        $diag = trim($_POST['diagnostico'] ?? 'Operativo');

        $st = $pdo->prepare("INSERT INTO bienes_inventario (correlativo, codigo_apsti, codigo_interno, denominacion, marca, modelo, color, serie, especificaciones, programa, area_ambiente, responsable, fecha_compra, estado_conservacion, requiere_mantenimiento, fecha_limite_mantenimiento, diagnostico) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $st->execute([$corr, $cod, $cod, $den, $mar, $mod, $col, $ser, $spec, $prog, $area, $resp, $fcomp, $est, $req, $flim, $diag]);
        $msg = "Bien [$cod - $den] registrado correctamente en el Inventario General.";
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
        $msg = "Datos del bien [$cod] actualizados correctamente.";
    } elseif ($accion === 'eliminar') {
        $id = (int)$_POST['id'];
        $pdo->prepare("DELETE FROM bienes_inventario WHERE id=?")->execute([$id]);
        $msg = "Bien eliminado del inventario.";
        $msgType = 'warning';
    } elseif ($accion === 'reportar_falla') {
        $bien_id = (int)$_POST['bien_id'];
        $falla = trim($_POST['falla_reportada'] ?? 'Falla técnica reportada desde inventario.');
        $prio = $_POST['prioridad'] ?? 'ALTA';
        $stB = $pdo->prepare("SELECT * FROM bienes_inventario WHERE id=?");
        $stB->execute([$bien_id]);
        $b = $stB->fetch();
        if ($b) {
            $codSol = 'SOL-2026-' . rand(100, 999);
            $st = $pdo->prepare("INSERT INTO solicitudes_mantenimiento (codigo_solicitud, bien_id, codigo_bien, descripcion_bien, area_solicitante, usuario_reporta, falla_reportada, prioridad, estado_flujo) VALUES (?, ?, ?, ?, ?, ?, ?, ?, '1_REPORTADO_AREA')");
            $st->execute([$codSol, $b['id'], $b['codigo_apsti'], $b['denominacion'] . ' ' . $b['marca'], $b['programa'] . ' - ' . $b['area_ambiente'], $u['nombres'], $falla, $prio]);
            $msg = "Falla reportada con código [$codSol] para el equipo {$b['codigo_apsti']}. Ahora puedes verificarla en el Paso 2.";
        }
    }
}

$prog = $_GET['programa'] ?? '';
$q = trim($_GET['q'] ?? '');
$sql = "SELECT * FROM bienes_inventario WHERE 1=1";
$params = [];
if ($prog !== '') {
    $sql .= " AND programa = ?";
    $params[] = $prog;
}
if ($q !== '') {
    $sql .= " AND (correlativo = ? OR codigo_apsti LIKE ? OR denominacion LIKE ? OR area_ambiente LIKE ? OR especificaciones LIKE ?)";
    array_push($params, $q, "%$q%", "%$q%", "%$q%", "%$q%");
}
$sql .= " ORDER BY id ASC";
$st = $pdo->prepare($sql);
$st->execute($params);
$todosLosBienes = $st->fetchAll();
$totalFiltrados = count($todosLosBienes);
$porPagina = isset($_GET['ver']) && $_GET['ver'] === 'todos' ? 5000 : 50;
$paginaActual = max(1, (int)($_GET['p'] ?? 1));
$totalPaginas = max(1, (int)ceil($totalFiltrados / $porPagina));
if ($paginaActual > $totalPaginas) { $paginaActual = $totalPaginas; }
$offset = ($paginaActual - 1) * $porPagina;
$bienes = (isset($_GET['export']) && $_GET['export'] === 'xls') ? $todosLosBienes : array_slice($todosLosBienes, $offset, $porPagina);

if (($_GET['export'] ?? '') === 'xls') {
    $rowsXls = [];
    foreach ($bienes as $b) {
        $rowsXls[] = [
            $b['correlativo'], $b['codigo_apsti'], $b['denominacion'], $b['marca'],
            $b['programa'], $b['area_ambiente'], $b['fecha_compra'], $b['estado_conservacion'],
            $b['fecha_limite_mantenimiento'], $b['estado_servicio'], $b['especificaciones'], $b['diagnostico']
        ];
    }
    exportarTablaXLS(
        'Inventario_Bienes_IESTPU_' . ($prog ?: 'General'),
        'REPORTE OFICIAL DE INVENTARIO DE BIENES PATRIMONIALES — FILTRO: ' . ($prog ?: 'TODOS'),
        ['N.º', 'Código APSTI / Interno', 'Denominación', 'Marca', 'Programa', 'Área / Ambiente', 'Fecha Compra', 'Estado', 'Fecha Límite Mant.', 'Estado Servicio', 'Especificaciones Técnicas', 'Diagnóstico / Observación'],
        $rowsXls
    );
}

renderHeader('Módulo 1: Inventario General de Bienes', 'inventario');
?>
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2 no-print">
    <div>
        <h4 class="fw-bold mb-0">Módulo 1: Inventario General de Bienes Patrimoniales (3,475 Bienes / <?= $totalFiltrados ?> en vista)</h4>
        <small class="text-secondary">Control por áreas, especificaciones técnicas y semáforo automático de fechas límite de mantenimiento</small>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <button class="btn btn-primary btn-sm fw-bold" data-bs-toggle="modal" data-bs-target="#modalCrearBien"><i class="fa-solid fa-plus me-1"></i> Registrar Nuevo Bien</button>
        <a href="inventariar_apsti.php" class="btn btn-info btn-sm fw-bold text-dark"><i class="fa-solid fa-desktop me-1"></i> Formulario APSTI</a>
        <a href="cafetin.php" class="btn btn-warning btn-sm fw-bold"><i class="fa-solid fa-mug-hot me-1"></i> Módulo Cafetín</a>
        <a href="equipos.php?programa=<?= urlencode($prog) ?>&q=<?= urlencode($q) ?>&export=xls" class="btn btn-success btn-sm fw-bold"><i class="fa-solid fa-file-excel me-1"></i> Exportar Excel (.xls)</a>
        <button onclick="window.print()" class="btn btn-danger btn-sm fw-bold"><i class="fa-solid fa-file-pdf me-1"></i> Imprimir / PDF</button>
    </div>
</div>

<?php if ($msg): ?>
    <div class="alert alert-<?= $msgType ?> alert-dismissible fade show py-2 small no-print">
        <i class="fa-solid fa-circle-check me-1"></i> <?= htmlspecialchars($msg) ?>
        <button type="button" class="btn-close py-2" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="card p-3 mb-3 shadow-sm no-print">
    <div class="row g-2 align-items-center">
        <div class="col-md-6 d-flex flex-wrap gap-1">
            <a href="equipos.php" class="btn btn-sm <?= $prog===''?'btn-dark':'btn-outline-dark' ?>">Todos</a>
            <a href="equipos.php?programa=APSTI" class="btn btn-sm <?= $prog==='APSTI'?'btn-primary':'btn-outline-primary' ?>">APSTI (Lab. 1 / Ensamblaje)</a>
            <a href="equipos.php?programa=CAFETIN" class="btn btn-sm <?= $prog==='CAFETIN'?'btn-warning text-dark fw-bold':'btn-outline-warning text-dark' ?>">Módulo Cafetín</a>
            <a href="equipos.php?programa=CONT" class="btn btn-sm <?= $prog==='CONT'?'btn-success':'btn-outline-success' ?>">Contabilidad</a>
            <a href="equipos.php?programa=ADMIN" class="btn btn-sm <?= $prog==='ADMIN'?'btn-secondary':'btn-outline-secondary' ?>">Dirección / Admin</a>
        </div>
        <div class="col-md-6">
            <form method="GET" class="input-group input-group-sm">
                <input type="hidden" name="programa" value="<?= htmlspecialchars($prog) ?>">
                <input type="text" name="q" class="form-control" value="<?= htmlspecialchars($q) ?>" placeholder="Buscar por código (ej. 002, APSTI-CPU-LC1-002, CAF-C), marca o área...">
                <button class="btn btn-primary fw-bold" type="submit"><i class="fa-solid fa-search me-1"></i> Buscar</button>
                <?php if ($q !== ''): ?><a href="equipos.php?programa=<?= urlencode($prog) ?>" class="btn btn-outline-secondary">Limpiar</a><?php endif; ?>
            </form>
        </div>
    </div>
</div>

<div class="card p-3 shadow-sm">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-2 gap-2 no-print">
        <div class="small text-secondary">
            Mostrando <strong><?= count($bienes) ?></strong> de <strong><?= $totalFiltrados ?></strong> registros cargados (Página <?= $paginaActual ?> de <?= $totalPaginas ?> · Universo: 3,475 bienes en 40 hojas Excel)
        </div>
        <div class="d-flex flex-wrap gap-1 align-items-center">
            <input type="text" id="filtroVivoInput" class="form-control form-control-sm" style="width:230px;" placeholder="⚡ Filtrar rápido en esta tabla..." onkeyup="filtrarTablaEnVivo(this.value)">
            <?php for ($pg = 1; $pg <= $totalPaginas; $pg++): ?>
                <a href="equipos.php?programa=<?= urlencode($prog) ?>&q=<?= urlencode($q) ?>&p=<?= $pg ?>" class="btn btn-sm <?= $pg === $paginaActual ? 'btn-primary fw-bold' : 'btn-outline-secondary' ?> py-0 px-2"><?= $pg ?></a>
            <?php endfor; ?>
            <a href="equipos.php?programa=<?= urlencode($prog) ?>&q=<?= urlencode($q) ?>&ver=todos" class="btn btn-sm btn-outline-info py-0 px-2">Ver todos</a>
        </div>
    </div>
    <script>
    function filtrarTablaEnVivo(texto) {
        var q = texto.toLowerCase();
        var filas = document.querySelectorAll('table tbody tr');
        filas.forEach(function(tr) {
            tr.style.display = tr.innerText.toLowerCase().indexOf(q) > -1 ? '' : 'none';
        });
    }
    </script>
    <div class="table-responsive">
        <table class="table table-sm table-bordered table-hover align-middle small mb-0">
            <thead class="table-dark">
                <tr>
                    <th>N.º</th>
                    <th>Código / Serie</th>
                    <th>Denominación y Especificaciones</th>
                    <th>Programa / Área</th>
                    <th>Compra</th>
                    <th>Est.</th>
                    <th>Semáforo Fecha Límite</th>
                    <th class="no-print" style="width:210px;">Acciones (CRUD / Ficha)</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($bienes as $b):
                $sem = calcularSemaforo((string)$b['fecha_limite_mantenimiento'], (int)$b['requiere_mantenimiento']);
            ?>
                <tr>
                    <td class="fw-bold text-center"><?= htmlspecialchars($b['correlativo']) ?></td>
                    <td>
                        <a href="buscar_equipo.php?q=<?= urlencode($b['codigo_apsti']) ?>" class="fw-bold text-primary text-decoration-none"><?= htmlspecialchars($b['codigo_apsti']) ?></a>
                        <?php if ($b['serie'] && $b['serie'] !== '-'): ?><div class="text-muted" style="font-size:0.72rem;">Serie: <?= htmlspecialchars($b['serie']) ?></div><?php endif; ?>
                    </td>
                    <td>
                        <div class="fw-bold"><?= htmlspecialchars($b['denominacion'] . ' ' . ($b['marca'] !== '-' ? $b['marca'] : '')) ?></div>
                        <div class="text-muted" style="font-size:0.75rem;"><?= htmlspecialchars($b['especificaciones']) ?></div>
                    </td>
                    <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($b['programa']) ?></span><br><small><?= htmlspecialchars($b['area_ambiente']) ?></small></td>
                    <td><?= htmlspecialchars($b['fecha_compra']) ?></td>
                    <td class="text-center"><span class="badge bg-secondary"><?= htmlspecialchars($b['estado_conservacion']) ?></span></td>
                    <td><span class="badge <?= $sem['clase'] ?>"><?= htmlspecialchars($sem['texto']) ?></span></td>
                    <td class="no-print">
                        <div class="d-flex flex-wrap gap-1">
                            <a href="buscar_equipo.php?q=<?= urlencode($b['codigo_apsti']) ?>" class="btn btn-primary btn-sm py-0 px-2" title="Abrir Ventana Técnica y Ficha"><i class="fa-solid fa-eye"></i> Ficha</a>
                            <button type="button" class="btn btn-warning btn-sm py-0 px-2" title="Editar Bien"
                                onclick='abrirModalEditar(<?= json_encode($b, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>
                                <i class="fa-solid fa-pen"></i>
                            </button>
                            <button type="button" class="btn btn-danger btn-sm py-0 px-2" title="Reportar Falla de este Equipo"
                                onclick='abrirModalFalla(<?= (int)$b["id"] ?>, <?= json_encode($b["codigo_apsti"] . " — " . $b["denominacion"]) ?>)'>
                                <i class="fa-solid fa-triangle-exclamation"></i>
                            </button>
                            <form method="POST" class="d-inline" onsubmit="return confirm('¿Seguro que deseas eliminar este bien del inventario?');">
                                <input type="hidden" name="accion" value="eliminar">
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

<!-- MODAL CREAR NUEVO BIEN -->
<div class="modal fade" id="modalCrearBien" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form method="POST" class="modal-content">
            <input type="hidden" name="accion" value="crear">
            <div class="modal-header bg-primary text-white py-2">
                <h6 class="modal-title fw-bold"><i class="fa-solid fa-plus-circle me-1"></i> Registrar Nuevo Bien en el Inventario Institucional</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body row g-2 small">
                <div class="col-md-2"><label class="fw-bold">N.º Correlativo</label><input type="text" name="correlativo" class="form-control form-control-sm" value="<?= sprintf('%03d', count($bienes)+1) ?>" required></div>
                <div class="col-md-4"><label class="fw-bold">Código APSTI / Interno *</label><input type="text" name="codigo_apsti" class="form-control form-control-sm" placeholder="Ej. APSTI-CPU-LC1-036" required></div>
                <div class="col-md-3"><label class="fw-bold">Denominación *</label><input type="text" name="denominacion" class="form-control form-control-sm" placeholder="Ej. CPU / Proyector" required></div>
                <div class="col-md-3"><label class="fw-bold">Marca</label><input type="text" name="marca" class="form-control form-control-sm" value="TEROS"></div>
                <div class="col-md-3"><label class="fw-bold">Programa</label>
                    <select name="programa" class="form-select form-select-sm">
                        <option value="APSTI">APSTI</option>
                        <option value="CAFETIN">CAFETIN</option>
                        <option value="CONT">CONTABILIDAD</option>
                        <option value="ENFE">ENFERMERÍA</option>
                        <option value="AGRO">AGROPECUARIA</option>
                        <option value="ADMIN">DIRECCIÓN / ADMIN</option>
                    </select>
                </div>
                <div class="col-md-5"><label class="fw-bold">Área / Ambiente Físico</label><input type="text" name="area_ambiente" class="form-control form-control-sm" value="Laboratorio de Cómputo I" required></div>
                <div class="col-md-4"><label class="fw-bold">Responsable del Área</label><input type="text" name="responsable" class="form-control form-control-sm" value="Ing. Carlos Sandoval Dávila"></div>
                <div class="col-md-3"><label class="fw-bold">Fecha de Compra</label><input type="date" name="fecha_compra" class="form-control form-control-sm" value="2024-03-15"></div>
                <div class="col-md-3"><label class="fw-bold">Estado Conservación</label>
                    <select name="estado_conservacion" class="form-select form-select-sm"><option value="B">Bueno (B)</option><option value="R">Regular (R)</option><option value="M">Malo (M)</option><option value="N">Nuevo (N)</option></select>
                </div>
                <div class="col-md-3"><label class="fw-bold">¿Requiere Mant.?</label>
                    <select name="requiere_mantenimiento" class="form-select form-select-sm"><option value="1">Sí (Equipo Técnico)</option><option value="0">No (Mobiliario)</option></select>
                </div>
                <div class="col-md-3"><label class="fw-bold">Fecha Límite Mant.</label><input type="date" name="fecha_limite_mantenimiento" class="form-control form-control-sm" value="2026-11-30"></div>
                <div class="col-md-12"><label class="fw-bold">Especificaciones Técnicas (Procesador, RAM, Disco, Dimensiones, Periféricos)</label><input type="text" name="especificaciones" class="form-control form-control-sm" placeholder="Ej. Intel Core i7-14700F 2.10 GHz | RAM: 16GB | Disco: 500GB | Video: GT 730 4GB"></div>
                <div class="col-md-12"><label class="fw-bold">Diagnóstico / Observación Inicial</label><input type="text" name="diagnostico" class="form-control form-control-sm" value="Operativo en red LAN"></div>
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary btn-sm fw-bold">Guardar Bien en Inventario</button>
            </div>
        </form>
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
                <div class="col-md-12"><label class="fw-bold">Especificaciones Técnicas</label><input type="text" name="especificaciones" id="edit_spec" class="form-control form-control-sm"></div>
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
