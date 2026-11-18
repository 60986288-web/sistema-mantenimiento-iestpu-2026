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
        $dni = trim($_POST['dni'] ?? '');
        $nom = trim($_POST['nombres'] ?? '');
        $cor = trim($_POST['correo'] ?? '');
        $cla = trim($_POST['clave'] ?? 'admin123');
        $rol = $_POST['rol'] ?? 'coordinador_apsti';
        $prog = $_POST['programa'] ?? 'APSTI';
        $st = $pdo->prepare("INSERT INTO usuarios (dni, nombres, correo, clave, rol, programa) VALUES (?, ?, ?, ?, ?, ?)");
        $st->execute([$dni, $nom, $cor, $cla, $rol, $prog]);
        $msg = "Usuario [$nom] registrado correctamente.";
    } elseif ($accion === 'editar') {
        $id = (int)$_POST['id'];
        $dni = trim($_POST['dni'] ?? '');
        $nom = trim($_POST['nombres'] ?? '');
        $cor = trim($_POST['correo'] ?? '');
        $cla = trim($_POST['clave'] ?? '');
        $rol = $_POST['rol'] ?? 'admin';
        $prog = $_POST['programa'] ?? 'TODOS';
        if ($cla !== '') {
            $st = $pdo->prepare("UPDATE usuarios SET dni=?, nombres=?, correo=?, clave=?, rol=?, programa=? WHERE id=?");
            $st->execute([$dni, $nom, $cor, $cla, $rol, $prog, $id]);
        } else {
            $st = $pdo->prepare("UPDATE usuarios SET dni=?, nombres=?, correo=?, rol=?, programa=? WHERE id=?");
            $st->execute([$dni, $nom, $cor, $rol, $prog, $id]);
        }
        $msg = "Datos y contraseña del usuario [$nom] actualizados.";
    } elseif ($accion === 'eliminar') {
        $id = (int)$_POST['id'];
        if ($id > 1) {
            $pdo->prepare("DELETE FROM usuarios WHERE id=?")->execute([$id]);
            $msg = "Usuario eliminado correctamente.";
            $msgType = 'warning';
        } else {
            $msg = "El usuario Administrador principal no puede eliminarse.";
            $msgType = 'danger';
        }
    }
}

$usuarios = $pdo->query("SELECT * FROM usuarios ORDER BY id ASC")->fetchAll();
renderHeader('Gestión de Usuarios y Contraseñas', 'usuarios');
?>
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2 no-print">
    <div>
        <h4 class="fw-bold mb-0"><i class="fa-solid fa-users-gear text-primary me-1"></i> Gestión de Usuarios, Roles y Contraseñas (RBAC)</h4>
        <small class="text-secondary">Administración de credenciales de acceso para Jefatura Académica, Coordinadores de Programa y Soporte Técnico</small>
    </div>
    <button class="btn btn-primary btn-sm fw-bold" data-bs-toggle="modal" data-bs-target="#modalNuevoUsuario">
        <i class="fa-solid fa-user-plus me-1"></i> Nuevo Usuario
    </button>
</div>

<?php if ($msg): ?>
<div class="alert alert-<?= $msgType ?> alert-dismissible fade show py-2 small">
    <i class="fa-solid fa-circle-check me-1"></i> <?= htmlspecialchars($msg) ?>
    <button type="button" class="btn-close py-2" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="card p-3 shadow-sm">
    <div class="table-responsive">
        <table class="table table-bordered table-hover align-middle small mb-0">
            <thead class="table-dark">
                <tr>
                    <th>ID</th>
                    <th>DNI</th>
                    <th>Nombres y Cargo Institucional</th>
                    <th>Correo Institucional (Usuario)</th>
                    <th>Rol en el Sistema</th>
                    <th>Programa / Área</th>
                    <th>Contraseña Actual</th>
                    <th class="no-print">Acciones</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($usuarios as $usr): ?>
                <tr>
                    <td class="fw-bold text-center"><?= (int)$usr['id'] ?></td>
                    <td><?= htmlspecialchars($usr['dni']) ?></td>
                    <td class="fw-bold"><?= htmlspecialchars($usr['nombres']) ?></td>
                    <td class="text-primary fw-semibold"><?= htmlspecialchars($usr['correo']) ?></td>
                    <td><span class="badge bg-primary"><?= htmlspecialchars($usr['rol']) ?></span></td>
                    <td><span class="badge bg-secondary"><?= htmlspecialchars($usr['programa']) ?></span></td>
                    <td><code><?= htmlspecialchars($usr['clave']) ?></code></td>
                    <td class="no-print">
                        <div class="d-flex gap-1">
                            <button class="btn btn-warning btn-sm py-0 px-2" data-bs-toggle="modal" data-bs-target="#modalEditUsr<?= (int)$usr['id'] ?>">
                                <i class="fa-solid fa-pen"></i> Editar / Clave
                            </button>
                            <?php if ((int)$usr['id'] > 1): ?>
                            <form method="POST" onsubmit="return confirm('¿Eliminar este usuario?');">
                                <input type="hidden" name="accion" value="eliminar">
                                <input type="hidden" name="id" value="<?= (int)$usr['id'] ?>">
                                <button class="btn btn-danger btn-sm py-0 px-2"><i class="fa-solid fa-trash"></i></button>
                            </form>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>

                <div class="modal fade" id="modalEditUsr<?= (int)$usr['id'] ?>" tabindex="-1">
                    <div class="modal-dialog">
                        <form method="POST" class="modal-content">
                            <div class="modal-header bg-dark text-white py-2">
                                <h6 class="modal-title fw-bold">Editar Usuario y Contraseña</h6>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body small">
                                <input type="hidden" name="accion" value="editar">
                                <input type="hidden" name="id" value="<?= (int)$usr['id'] ?>">
                                <div class="mb-2">
                                    <label class="fw-bold">DNI:</label>
                                    <input type="text" name="dni" class="form-control form-control-sm" value="<?= htmlspecialchars($usr['dni']) ?>" required>
                                </div>
                                <div class="mb-2">
                                    <label class="fw-bold">Nombres y Cargo:</label>
                                    <input type="text" name="nombres" class="form-control form-control-sm" value="<?= htmlspecialchars($usr['nombres']) ?>" required>
                                </div>
                                <div class="mb-2">
                                    <label class="fw-bold">Correo Institucional:</label>
                                    <input type="email" name="correo" class="form-control form-control-sm" value="<?= htmlspecialchars($usr['correo']) ?>" required>
                                </div>
                                <div class="row g-2 mb-2">
                                    <div class="col-6">
                                        <label class="fw-bold">Rol:</label>
                                        <select name="rol" class="form-select form-select-sm">
                                            <?php foreach (['admin','coordinador_apsti','coordinador_cont','coordinador_enfe','coordinador_agro','tecnico'] as $rOpt): ?>
                                                <option value="<?= $rOpt ?>" <?= $usr['rol']===$rOpt?'selected':'' ?>><?= $rOpt ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-6">
                                        <label class="fw-bold">Programa:</label>
                                        <input type="text" name="programa" class="form-control form-control-sm" value="<?= htmlspecialchars($usr['programa']) ?>" required>
                                    </div>
                                </div>
                                <div class="mb-2">
                                    <label class="fw-bold text-primary">Nueva Contraseña:</label>
                                    <input type="text" name="clave" class="form-control form-control-sm" value="<?= htmlspecialchars($usr['clave']) ?>" required>
                                </div>
                            </div>
                            <div class="modal-footer py-2">
                                <button type="submit" class="btn btn-primary btn-sm fw-bold">Guardar Cambios</button>
                            </div>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="modalNuevoUsuario" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <div class="modal-header bg-primary text-white py-2">
                <h6 class="modal-title fw-bold">Registrar Nuevo Usuario Institucional</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body small">
                <input type="hidden" name="accion" value="crear">
                <div class="mb-2">
                    <label class="fw-bold">DNI:</label>
                    <input type="text" name="dni" class="form-control form-control-sm" placeholder="Ej. 60986288" required>
                </div>
                <div class="mb-2">
                    <label class="fw-bold">Nombres y Cargo:</label>
                    <input type="text" name="nombres" class="form-control form-control-sm" placeholder="Ej. Angie Delgado Gonzales (Admin)" required>
                </div>
                <div class="mb-2">
                    <label class="fw-bold">Correo Institucional:</label>
                    <input type="email" name="correo" class="form-control form-control-sm" placeholder="angie@iestpu.edu.pe" required>
                </div>
                <div class="row g-2 mb-2">
                    <div class="col-6">
                        <label class="fw-bold">Rol:</label>
                        <select name="rol" class="form-select form-select-sm">
                            <option value="admin">admin (Administrador General)</option>
                            <option value="coordinador_apsti">coordinador_apsti (APSTI)</option>
                            <option value="coordinador_cont">coordinador_cont (Contabilidad)</option>
                            <option value="coordinador_enfe">coordinador_enfe (Enfermería)</option>
                            <option value="coordinador_agro">coordinador_agro (Agropecuaria)</option>
                            <option value="tecnico">tecnico (Soporte Técnico)</option>
                        </select>
                    </div>
                    <div class="col-6">
                        <label class="fw-bold">Programa / Área:</label>
                        <input type="text" name="programa" class="form-control form-control-sm" value="APSTI" required>
                    </div>
                </div>
                <div class="mb-2">
                    <label class="fw-bold">Contraseña:</label>
                    <input type="text" name="clave" class="form-control form-control-sm" value="admin123" required>
                </div>
            </div>
            <div class="modal-footer py-2">
                <button type="submit" class="btn btn-primary btn-sm fw-bold">Registrar Usuario</button>
            </div>
        </form>
    </div>
</div>
<?php renderFooter(); ?>
