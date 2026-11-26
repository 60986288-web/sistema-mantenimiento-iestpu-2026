<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/layout.php';

$u = require_role(['admin', 'coordinador_apsti', 'coordinador_cont', 'coordinador_enfe', 'coordinador_agro']);
$rol = get_user_role($u);
$scope = get_user_program_scope($u);
$pdo = getPDO();

$msg = '';
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $accion = (string)($_POST['accion'] ?? '');

    if ($accion === 'eliminar' && $rol === 'admin') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $st = $pdo->prepare('DELETE FROM bienes_inventario WHERE id = ?');
            $st->execute([$id]);
            registrar_auditoria($pdo, 'ELIMINAR_BIEN', 'bienes_inventario', $id, 'Eliminación de bien patrimonial');
            $msg = 'Bien eliminado del inventario activo.';
        }
    } elseif ($accion === 'crear' && $rol === 'admin') {
        $codigo = strtoupper(trim((string)($_POST['codigo_apsti'] ?? '')));
        $denom = trim((string)($_POST['denominacion'] ?? ''));
        $programa = strtoupper(trim((string)($_POST['programa'] ?? 'APSTI')));
        $ambiente = trim((string)($_POST['area_ambiente'] ?? ''));
        $marca = trim((string)($_POST['marca'] ?? '-')) ?: '-';
        $modelo = trim((string)($_POST['modelo'] ?? '-')) ?: '-';
        $serie = trim((string)($_POST['serie'] ?? '-')) ?: '-';
        $estadoCons = trim((string)($_POST['estado_conservacion'] ?? 'B'));
        $reqMant = isset($_POST['requiere_mantenimiento']) ? 1 : 0;
        $estServ = $reqMant === 1 ? 'Servicio Pendiente' : 'No Aplica';

        if ($codigo === '' || $denom === '' || $ambiente === '') {
            $err = 'Completa los campos obligatorios: Código, Denominación y Ambiente.';
        } else {
            $corr = str_pad((string)((int)$pdo->query('SELECT COUNT(*) FROM bienes_inventario')->fetchColumn() + 1), 3, '0', STR_PAD_LEFT);
            $st = $pdo->prepare(
                'INSERT INTO bienes_inventario (correlativo, codigo_apsti, codigo_interno, denominacion, marca, modelo, serie, programa, area_ambiente, estado_conservacion, requiere_mantenimiento, estado_servicio, fecha_limite_mantenimiento)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $st->execute([$corr, $codigo, $codigo, $denom, $marca, $modelo, $serie, $programa, $ambiente, $estadoCons, $reqMant, $estServ, date('Y-m-d', strtotime('+90 days'))]);
            $newId = (int)$pdo->lastInsertId();
            registrar_auditoria($pdo, 'CREAR_BIEN', 'bienes_inventario', $newId, "Nuevo bien {$codigo} en {$programa}");
            $msg = "Bien {$codigo} registrado correctamente.";
        }
    }
}

$filtroProg = $scope !== null ? $scope : strtoupper(trim((string)($_GET['programa'] ?? '')));
$q = trim((string)($_GET['q'] ?? ''));

$where = [];
$params = [];
if ($filtroProg !== '') {
    $where[] = 'programa = ?';
    $params[] = $filtroProg;
}
if ($q !== '') {
    $where[] = '(codigo_apsti LIKE ? OR codigo_interno LIKE ? OR denominacion LIKE ? OR marca LIKE ? OR serie LIKE ? OR area_ambiente LIKE ?)';
    $like = '%' . $q . '%';
    array_push($params, $like, $like, $like, $like, $like, $like);
}

$sql = 'SELECT * FROM bienes_inventario' . (!empty($where) ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY id ASC';
$st = $pdo->prepare($sql);
$st->execute($params);
$bienes = $st->fetchAll();

renderHeader($scope ? "Inventario Activo — {$scope}" : 'Inventario General de Bienes (BD Activa)', 'equipos');
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-3">
    <div>
        <h4 class="fw-bold mb-0"><?= $scope ? h("Inventario Patrimonial — Área {$scope}") : 'Inventario Patrimonial Activo (Base de Datos)' ?></h4>
        <div class="small text-muted">
            Mostrando <strong><?= count($bienes) ?></strong> bienes activos en BD<?= $scope ? '' : ' (Padrón histórico referencial en Excel: 3,475 registros)' ?>.
        </div>
    </div>
    <?php if ($rol === 'admin'): ?>
        <button type="button" class="btn btn-sm btn-emerald" data-bs-toggle="modal" data-bs-target="#modalNuevoBien">
            <i class="fa-solid fa-plus me-1"></i>Registrar Nuevo Bien
        </button>
    <?php endif; ?>
</div>

<?php if ($msg): ?>
    <div class="alert alert-success alert-dismissible fade show py-2 small"><?= h($msg) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>
<?php if ($err): ?>
    <div class="alert alert-danger alert-dismissible fade show py-2 small"><?= h($err) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>

<div class="card-clean p-3 mb-3">
    <form method="GET" class="row g-2 align-items-end">
        <div class="col-md-6">
            <label class="form-label small fw-semibold mb-1">Buscar por código, denominación, marca, serie o ambiente</label>
            <input type="text" name="q" value="<?= h($q) ?>" class="form-control form-control-sm" placeholder="Ej.: APSTI-CPU-LC1-002, Epson, Teros...">
        </div>
        <?php if ($scope === null): ?>
            <div class="col-md-3">
                <label class="form-label small fw-semibold mb-1">Programa / Área</label>
                <select name="programa" class="form-select form-select-sm">
                    <option value="">Todos los programas</option>
                    <?php foreach (['APSTI', 'CONT', 'ENFE', 'AGRO', 'CAFETIN', 'ADMIN'] as $p): ?>
                        <option value="<?= h($p) ?>" <?= $filtroProg === $p ? 'selected' : '' ?>><?= h($p) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php endif; ?>
        <div class="col-md-3 d-flex gap-2">
            <button type="submit" class="btn btn-sm btn-petrol flex-grow-1"><i class="fa-solid fa-filter me-1"></i>Filtrar</button>
            <a href="<?= h(baseUrl('admin/equipos.php')) ?>" class="btn btn-sm btn-outline-secondary">Limpiar</a>
        </div>
    </form>
</div>

<?php
$mapaAreasOficial = [
    ''        => 'TODOS LOS PROGRAMAS Y ÁREAS',
    'APSTI'   => 'PROGRAMA DE ESTUDIO APSTI',
    'CONT'    => 'PROGRAMA DE ESTUDIO CONTABILIDAD',
    'ENFE'    => 'PROGRAMA DE ESTUDIO ENFERMERÍA TÉCNICA',
    'AGRO'    => 'PROGRAMA DE ESTUDIO PRODUCCIÓN AGROPECUARIA',
    'CAFETIN' => 'MÓDULO CAFETÍN INSTITUCIONAL',
    'ADMIN'   => 'DIRECCIÓN GENERAL Y ADMINISTRACIÓN',
];
$nombreAreaEquipos = $mapaAreasOficial[$filtroProg] ?? ($filtroProg !== '' ? $filtroProg : 'TODOS LOS PROGRAMAS Y ÁREAS');
?>
<div class="oficial-sheet">
    <div class="d-flex justify-content-between align-items-center border-bottom pb-2 mb-2">
        <img src="<?= h(baseUrl('assets/img/logo.png')) ?>" alt="Escudo IESTPU" style="height:54px;object-fit:contain;">
        <div class="text-center px-2">
            <div class="fw-bold" style="font-size:.9rem;">GOBIERNO REGIONAL AMAZONAS</div>
            <div class="small fw-semibold">DIRECCIÓN REGIONAL DE EDUCACIÓN AMAZONAS</div>
            <div class="small fw-bold">INSTITUTO DE EDUCACIÓN SUPERIOR TECNOLÓGICO PÚBLICO «UTCUBAMBA»</div>
        </div>
        <img src="<?= h(baseUrl('assets/img/logo.png')) ?>" alt="Escudo IESTPU" style="height:54px;object-fit:contain;">
    </div>

    <div class="text-center fw-bold py-1 mb-2 border border-dark bg-light text-dark" style="font-size:.85rem;">
        INVENTARIO GENERAL DE BIENES INSTITUCIONAL - 2026 · <?= h($nombreAreaEquipos) ?>
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
            <td><strong><?= h($nombreAreaEquipos) ?></strong></td>
            <td><strong>FECHA DE EMISIÓN:</strong></td>
            <td><?= date('d/m/Y') ?> (<?= count($bienes) ?> registros)</td>
        </tr>
    </table>

    <div class="table-responsive">
        <table class="oficial-bienes-tbl mb-0">
            <thead>
                <tr>
                    <th rowspan="2" style="width:3.5%;">N.°</th>
                    <th colspan="11">DESCRIPCIÓN DE LOS BIENES</th>
                    <th rowspan="2" style="width:7%;">ACCIONES</th>
                </tr>
                <tr>
                    <th style="width:7%;">CÓDIGO PATRIMONIAL</th>
                    <th style="width:10.5%;">CÓDIGO INTERNO</th>
                    <th style="width:13.5%;">DENOMINACIÓN</th>
                    <th style="width:7.5%;">MARCA</th>
                    <th style="width:8.5%;">MODELO</th>
                    <th style="width:7%;">TIPO</th>
                    <th style="width:6.5%;">COLOR</th>
                    <th style="width:9.5%;">SERIE</th>
                    <th style="width:8%;">DIMENSIONES (Medidas)</th>
                    <th style="width:5.5%;">SITUACIÓN (1)</th>
                    <th style="width:6%;">ESTADO DE CONSERVACIÓN (2)</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($bienes)): ?>
                    <tr><td colspan="13" class="text-center text-muted py-4">No se encontraron bienes con el filtro aplicado.</td></tr>
                <?php else: ?>
                    <?php $nEq = 1; foreach ($bienes as $b):
                        $estadoC = strtoupper(trim((string)($b['estado_conservacion'] ?? 'B')));
                        $situacion = $estadoC === 'M' ? 'D' : 'U';
                    ?>
                        <tr>
                            <td class="text-center fw-semibold"><?= $nEq++ ?></td>
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
                                <div class="d-flex justify-content-center gap-1 flex-wrap">
                                    <a href="<?= h(baseUrl('admin/ficha_mantenimiento.php?bien_id=' . (int)$b['id'])) ?>" target="_blank" class="btn btn-sm btn-outline-success py-0 px-1" title="Ver Ficha PDF">
                                        <i class="fa-solid fa-file-pdf"></i>
                                    </a>
                                    <?php if ($rol === 'admin'): ?>
                                        <form method="POST" class="d-inline" onsubmit="return confirm('¿Eliminar este bien del inventario activo?');">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="accion" value="eliminar">
                                            <input type="hidden" name="id" value="<?= (int)$b['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger py-0 px-1" title="Eliminar"><i class="fa-solid fa-trash"></i></button>
                                        </form>
                                    <?php endif; ?>
                                </div>
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

<?php if ($rol === 'admin'): ?>
<div class="modal fade" id="modalNuevoBien" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <form method="POST" class="modal-content">
            <?= csrf_field() ?>
            <input type="hidden" name="accion" value="crear">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">Registrar Nuevo Bien Patrimonial</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body row g-3">
                <div class="col-md-4">
                    <label class="form-label small fw-semibold">Código Patrimonial / Interno *</label>
                    <input type="text" name="codigo_apsti" class="form-control form-control-sm" placeholder="Ej.: CONT-PC-017" required>
                </div>
                <div class="col-md-8">
                    <label class="form-label small fw-semibold">Denominación del Bien *</label>
                    <input type="text" name="denominacion" class="form-control form-control-sm" placeholder="Ej.: Computadora de Escritorio" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label small fw-semibold">Programa / Área *</label>
                    <select name="programa" class="form-select form-select-sm" required>
                        <?php foreach (['APSTI', 'CONT', 'ENFE', 'AGRO', 'CAFETIN', 'ADMIN'] as $p): ?>
                            <option value="<?= h($p) ?>"><?= h($p) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-8">
                    <label class="form-label small fw-semibold">Ambiente / Oficina *</label>
                    <input type="text" name="area_ambiente" class="form-control form-control-sm" placeholder="Ej.: Laboratorio de Cómputo I" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label small fw-semibold">Marca</label>
                    <input type="text" name="marca" class="form-control form-control-sm" placeholder="Ej.: HP, Epson, Lenovo">
                </div>
                <div class="col-md-4">
                    <label class="form-label small fw-semibold">Modelo</label>
                    <input type="text" name="modelo" class="form-control form-control-sm" placeholder="Ej.: ProDesk 400">
                </div>
                <div class="col-md-4">
                    <label class="form-label small fw-semibold">Serie</label>
                    <input type="text" name="serie" class="form-control form-control-sm" placeholder="Ej.: SN-2026-001">
                </div>
                <div class="col-md-4">
                    <label class="form-label small fw-semibold">Estado de Conservación</label>
                    <select name="estado_conservacion" class="form-select form-select-sm">
                        <option value="N">Nuevo (N)</option>
                        <option value="MB">Muy Bueno (MB)</option>
                        <option value="B" selected>Bueno (B)</option>
                        <option value="R">Regular (R)</option>
                        <option value="M">Malo (M)</option>
                    </select>
                </div>
                <div class="col-md-8 d-flex align-items-end">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="requiere_mantenimiento" id="chkReqMant" checked>
                        <label class="form-check-label small" for="chkReqMant">Este bien requiere mantenimiento técnico periódico</label>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-sm btn-emerald">Guardar Bien</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php renderFooter(); ?>
