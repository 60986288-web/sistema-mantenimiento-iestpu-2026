<?php
require_once dirname(__DIR__, 2) . '/includes/db.php';
require_once dirname(__DIR__, 2) . '/includes/layout.php';
$u = requireLogin();
$pdo = getPDO();
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['programar_visita'])) {
    $id = (int)$_POST['bien_id'];
    $f_tec = $_POST['fecha_viene_tecnico'] ?: '2026-11-15';
    $p_vis = $_POST['proxima_visita'] ?: '2027-02-15';
    $f_lim = $_POST['fecha_limite_mantenimiento'] ?: '2026-11-30';
    $st = $pdo->prepare("UPDATE bienes_inventario SET estado_visita='Programado', fecha_viene_tecnico=?, proxima_visita=?, fecha_limite_mantenimiento=? WHERE id=?");
    $st->execute([$f_tec, $p_vis, $f_lim, $id]);
    $msg = "Fechas del Cronograma Preventivo actualizadas correctamente.";
}

$prog = $_GET['programa'] ?? '';
$sql = "SELECT * FROM bienes_inventario WHERE requiere_mantenimiento=1";
$params = [];
if ($prog !== '') {
    $sql .= " AND programa = ?";
    $params[] = $prog;
}
$sql .= " ORDER BY fecha_limite_mantenimiento ASC";
$st = $pdo->prepare($sql);
$st->execute($params);
$bienes = $st->fetchAll();

if (($_GET['export'] ?? '') === 'xls') {
    $rowsXls = [];
    foreach ($bienes as $b) {
        $rowsXls[] = [
            $b['codigo_apsti'], $b['denominacion'] . ' ' . $b['marca'],
            $b['programa'], $b['area_ambiente'], $b['responsable'],
            $b['fecha_viene_tecnico'], $b['proxima_visita'], $b['fecha_limite_mantenimiento'], $b['estado_servicio']
        ];
    }
    exportarTablaXLS(
        'Cronograma_Preventivo_IESTPU_2026',
        'CRONOGRAMA OFICIAL DE VERIFICACIÓN Y MANTENIMIENTO PREVENTIVO — IESTP UTCUBAMBA',
        ['Código del Bien', 'Equipo / Marca', 'Programa', 'Área / Ambiente', 'Responsable', 'Fecha Viene Técnico', 'Próxima Visita Preventiva', 'Fecha Límite Mant.', 'Estado del Servicio'],
        $rowsXls
    );
}

renderHeader('Cronograma de Mantenimiento Preventivo', 'cronograma');
?>
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2 no-print">
    <div>
        <h4 class="fw-bold mb-0"><i class="fa-solid fa-calendar-check text-primary me-1"></i> Cronograma de Verificación y Mantenimiento Preventivo</h4>
        <small class="text-secondary">Sincronizado automáticamente por el Área Técnica y visible para los Coordinadores de cada Programa de Estudios</small>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <a href="cronograma.php" class="btn btn-sm <?= $prog===''?'btn-dark':'btn-outline-dark' ?>">Todos</a>
        <a href="cronograma.php?programa=APSTI" class="btn btn-sm <?= $prog==='APSTI'?'btn-primary':'btn-outline-primary' ?>">APSTI</a>
        <a href="cronograma.php?programa=CAFETIN" class="btn btn-sm <?= $prog==='CAFETIN'?'btn-warning text-dark fw-bold':'btn-outline-warning text-dark' ?>">Cafetín</a>
        <a href="cronograma.php?programa=CONT" class="btn btn-sm <?= $prog==='CONT'?'btn-success':'btn-outline-success' ?>">Contabilidad</a>
        <a href="cronograma.php?programa=ENFE" class="btn btn-sm <?= $prog==='ENFE'?'btn-info fw-bold':'btn-outline-info' ?>">Enfermería</a>
        <a href="cronograma.php?programa=AGRO" class="btn btn-sm <?= $prog==='AGRO'?'btn-success':'btn-outline-success' ?>">Agropecuaria</a>
        <a href="cronograma.php?programa=<?= urlencode($prog) ?>&export=xls" class="btn btn-success btn-sm fw-bold"><i class="fa-solid fa-file-excel me-1"></i> Exportar Cronograma (.xls)</a>
        <button onclick="window.print()" class="btn btn-danger btn-sm fw-bold"><i class="fa-solid fa-file-pdf me-1"></i> Imprimir PDF</button>
    </div>
</div>

<?php if ($msg): ?>
    <div class="alert alert-success py-2 small no-print"><i class="fa-solid fa-circle-check me-1"></i> <?= htmlspecialchars($msg) ?></div>
<?php endif; ?>

<div class="card p-3 shadow-sm">
    <div class="table-responsive">
        <table class="table table-sm table-bordered table-striped align-middle small mb-0">
            <thead class="table-dark">
                <tr>
                    <th>Código</th>
                    <th>Equipo / Bien</th>
                    <th>Programa / Área</th>
                    <th>Fecha Viene Técnico</th>
                    <th>Próxima Visita Preventiva</th>
                    <th>Semáforo Fecha Límite</th>
                    <th>Estado Servicio</th>
                    <th class="no-print">Reprogramar / Ficha</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($bienes as $b):
                $sem = calcularSemaforo((string)$b['fecha_limite_mantenimiento'], 1);
            ?>
                <tr>
                    <td class="fw-bold text-primary"><?= htmlspecialchars($b['codigo_apsti']) ?></td>
                    <td><?= htmlspecialchars($b['denominacion'] . ' ' . $b['marca']) ?></td>
                    <td><?= htmlspecialchars($b['programa'] . ' — ' . $b['area_ambiente']) ?></td>
                    <td><?= htmlspecialchars($b['fecha_viene_tecnico']) ?></td>
                    <td class="fw-bold"><?= htmlspecialchars($b['proxima_visita']) ?></td>
                    <td><span class="badge <?= $sem['clase'] ?>"><?= htmlspecialchars($sem['texto']) ?></span></td>
                    <td><span class="badge <?= $b['estado_servicio']==='Servicio Completo'?'bg-success':'bg-warning text-dark' ?>"><?= htmlspecialchars($b['estado_servicio']) ?></span></td>
                    <td class="no-print">
                        <form method="POST" class="d-flex gap-1 align-items-center">
                            <input type="hidden" name="programar_visita" value="1">
                            <input type="hidden" name="bien_id" value="<?= (int)$b['id'] ?>">
                            <input type="hidden" name="fecha_viene_tecnico" value="<?= htmlspecialchars($b['fecha_viene_tecnico']) ?>">
                            <input type="hidden" name="proxima_visita" value="<?= htmlspecialchars($b['proxima_visita']) ?>">
                            <input type="date" name="fecha_limite_mantenimiento" class="form-control form-control-sm py-0" style="width:125px;" value="<?= htmlspecialchars($b['fecha_limite_mantenimiento']) ?>">
                            <button type="submit" class="btn btn-primary btn-sm py-0 px-2" title="Actualizar Fecha"><i class="fa-solid fa-floppy-disk"></i></button>
                            <a href="ficha_mantenimiento.php?bien_id=<?= (int)$b['id'] ?>" class="btn btn-dark btn-sm py-0 px-2" title="Ver Ficha"><i class="fa-solid fa-file-pdf"></i></a>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php renderFooter(); ?>
