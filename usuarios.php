<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/includes/db.php';
require_once dirname(__DIR__, 2) . '/includes/layout.php';

$u = require_role(['admin']);
$pdo = getPDO();
$msg = '';
$msgType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $accion = (string)($_POST['accion'] ?? '');

    if ($accion === 'crear') {
        $dni = trim((string)($_POST['dni'] ?? ''));
        $nom = trim((string)($_POST['nombres'] ?? ''));
        $cor = trim((string)($_POST['correo'] ?? ''));
        $cla = (string)($_POST['clave'] ?? '');
        $rol = (string)($_POST['rol'] ?? 'coordinador_apsti');
        $prog = strtoupper(trim((string)($_POST['programa'] ?? 'APSTI')));

        if (strlen($cla) < 6) {
            $msg = 'La contraseña debe tener al menos 6 caracteres.';
            $msgType = 'danger';
        } else {
            $hash = password_hash($cla, PASSWORD_DEFAULT);
            $st = $pdo->prepare("INSERT INTO usuarios (dni, nombres, correo, clave, rol, programa, activo) VALUES (?, ?, ?, ?, ?, ?, 1)");
            $st->execute([$dni, $nom, $cor, $hash, $rol, $prog]);
            registrar_auditoria($pdo, 'CREAR_USUARIO', 'usuarios', $cor, "Nuevo usuario {$nom} ({$rol})");
            $msg = "Usuario [{$nom}] registrado con contraseña cifrada (bcrypt).";
        }
    } elseif ($accion === 'editar') {
        $id = (int)($_POST['id'] ?? 0);
        $dni = trim((string)($_POST['dni'] ?? ''));
        $nom = trim((string)($_POST['nombres'] ?? ''));
        $cor = trim((string)($_POST['correo'] ?? ''));
        $cla = trim((string)($_POST['clave'] ?? ''));
        $rol = (string)($_POST['rol'] ?? 'admin');
        $prog = strtoupper(trim((string)($_POST['programa'] ?? 'TODOS')));

        if ($cla !== '') {
            if (strlen($cla) < 6) {
                $msg = 'La nueva contraseña debe tener al menos 6 caracteres.';
                $msgType = 'danger';
            } else {
                $hash = password_hash($cla, PASSWORD_DEFAULT);
                $st = $pdo->prepare("UPDATE usuarios SET dni=?, nombres=?, correo=?, clave=?, rol=?, programa=? WHERE id=?");
                $st->execute([$dni, $nom, $cor, $hash, $rol, $prog, $id]);
                registrar_auditoria($pdo, 'EDITAR_USUARIO_CLAVE', 'usuarios', $id, "Actualización de datos y restablecimiento de clave para {$cor}");
                $msg = "Datos y contraseña (cifrada) del usuario [{$nom}] actualizados.";
            }
        } else {
            $st = $pdo->prepare("UPDATE usuarios SET dni=?, nombres=?, correo=?, rol=?, programa=? WHERE id=?");
            $st->execute([$dni, $nom, $cor, $rol, $prog, $id]);
            registrar_auditoria($pdo, 'EDITAR_USUARIO', 'usuarios', $id, "Actualización de perfil para {$cor}");
            $msg = "Datos del usuario [{$nom}] actualizados.";
        }
    } elseif ($accion === 'toggle_activo') {
        $id = (int)($_POST['id'] ?? 0);
        $nuevoEstado = (int)($_POST['nuevo_estado'] ?? 1);
        if ($id === (int)$u['id'] && $nuevoEstado === 0) {
            $msg = 'No puedes desactivar tu propia cuenta activa.';
            $msgType = 'danger';
        } elseif ($id === 1 && $nuevoEstado === 0) {
            $msg = 'La cuenta del Administrador principal no puede desactivarse.';
            $msgType = 'danger';
        } else {
            $st = $pdo->prepare("UPDATE usuarios SET activo = ? WHERE id = ?");
            $st->execute([$nuevoEstado, $id]);
            $lbl = $nuevoEstado === 1 ? 'ACTIVAR_USUARIO' : 'DESACTIVAR_USUARIO';
            registrar_auditoria($pdo, $lbl, 'usuarios', $id, "Cambio de estado activo={$nuevoEstado}");
            $msg = $nuevoEstado === 1 ? 'Cuenta reactivada correctamente.' : 'Cuenta desactivada sin eliminar su historial.';
            $msgType = $nuevoEstado === 1 ? 'success' : 'warning';
        }
    }
}

$usuarios = $pdo->query("SELECT id, dni, nombres, correo, rol, programa, activo FROM usuarios ORDER BY id ASC")->fetchAll();
renderHeader('Gestión de Usuarios y Roles', 'usuarios');
?>
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2 no-print">
    <div>
        <h4 class="fw-bold mb-0"><i class="fa-solid fa-users-gear text-primary me-1"></i> Gestión de Usuarios, Roles (RBAC) y Seguridad</h4>
        <small class="text-secondary">Contraseñas cifradas con <code>password_hash (bcrypt)</code>, DNIs enmascarados y desactivación segura sin pérdida de historial.</small>
    </div>
    <button class="btn btn-primary btn-sm fw-bold" data-bs-toggle="modal" data-bs-target="#modalNuevoUsuario">
        <i class="fa-solid fa-user-plus me-1"></i> Nuevo Usuario
    </button>
</div>

<?php if ($msg): ?>
<div class="alert alert-<?= h($msgType) ?> alert-dismissible fade show py-2 small">
    <i class="fa-solid fa-circle-check me-1"></i> <?= h($msg) ?>
    <button type="button" class="btn-close py-2" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="card p-3 shadow-sm">
    <div class="table-responsive">
        <table class="table table-bordered table-hover align-middle small mb-0">
            <thead class="table-dark">
                <tr>
                    <th class="text-center">ID</th>
                    <th>DNI (Protegido)</th>
                    <th>Nombres y Cargo Institucional</th>
                    <th>Correo Institucional</th>
                    <th>Rol RBAC</th>
                    <th>Programa / Área</th>
                    <th class="text-center">Estado Cuenta</th>
                    <th class="no-print">Acciones</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($usuarios as $usr):
                $esActivo = !isset($usr['activo']) || (int)$usr['activo'] === 1;
            ?>
                <tr class="<?= $esActivo ? '' : 'opacity-75' ?>">
                    <td class="fw-bold text-center"><?= (int)$usr['id'] ?></td>
                    <td style="font-family:monospace;"><?= h(mask_dni($usr['dni'])) ?></td>
                    <td class="fw-bold"><?= h($usr['nombres']) ?></td>
                    <td class="text-primary fw-semibold"><?= h($usr['correo']) ?></td>
                    <td><span class="badge bg-primary"><?= h(strtoupper($usr['rol'])) ?></span></td>
                    <td><span class="badge bg-secondary"><?= h($usr['programa']) ?></span></td>
                    <td class="text-center">
                        <span class="badge <?= $esActivo ? 'bg-success' : 'bg-danger' ?>">
                            <?= $esActivo ? 'Activa' : 'Desactivada' ?>
                        </span>
                    </td>
                    <td class="no-print">
                        <div class="d-flex flex-wrap gap-1">
                            <button class="btn btn-outline-primary btn-sm py-0 px-2" data-bs-toggle="modal" data-bs-target="#modalEditUsr<?= (int)$usr['id'] ?>">
                                <i class="fa-solid fa-key me-1"></i> Editar / Restablecer Clave
                            </button>
                            <?php if ((int)$usr['id'] > 1): ?>
                            <form method="POST" class="d-inline" onsubmit="return confirm('¿Confirmas cambiar el estado de esta cuenta?');">
                                <?= csrf_field() ?>
                                <input type="hidden" name="accion" value="toggle_activo">
                                <input type="hidden" name="id" value="<?= (int)$usr['id'] ?>">
                                <input type="hidden" name="nuevo_estado" value="<?= $esActivo ? 0 : 1 ?>">
                                <button class="btn <?= $esActivo ? 'btn-outline-danger' : 'btn-outline-success' ?> btn-sm py-0 px-2">
                                    <i class="fa-solid <?= $esActivo ? 'fa-user-slash' : 'fa-user-check' ?>"></i> <?= $esActivo ? 'Desactivar' : 'Activar' ?>
                                </button>
                            </form>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>

                <div class="modal fade" id="modalEditUsr<?= (int)$usr['id'] ?>" tabindex="-1">
                    <div class="modal-dialog">
                        <form method="POST" class="modal-content">
                            <?= csrf_field() ?>
                            <div class="modal-header bg-dark text-white py-2">
                                <h6 class="modal-title fw-bold">Editar Usuario / Restablecer Contraseña</h6>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body small">
                                <input type="hidden" name="accion" value="editar">
                                <input type="hidden" name="id" value="<?= (int)$usr['id'] ?>">
                                <div class="mb-2">
                                    <label class="fw-bold">DNI *</label>
                                    <input type="text" name="dni" class="form-control form-control-sm" value="<?= h($usr['dni']) ?>" required>
                                </div>
                                <div class="mb-2">
                                    <label class="fw-bold">Nombres y Cargo *</label>
                                    <input type="text" name="nombres" class="form-control form-control-sm" value="<?= h($usr['nombres']) ?>" required>
                                </div>
                                <div class="mb-2">
                                    <label class="fw-bold">Correo Institucional *</label>
                                    <input type="email" name="correo" class="form-control form-control-sm" value="<?= h($usr['correo']) ?>" required>
                                </div>
                                <div class="row g-2 mb-2">
                                    <div class="col-6">
                                        <label class="fw-bold">Rol *</label>
                                        <select name="rol" class="form-select form-select-sm">
                                            <?php foreach (['admin','coordinador_apsti','coordinador_cont','coordinador_enfe','coordinador_agro','tecnico'] as $rOpt): ?>
                                                <option value="<?= h($rOpt) ?>" <?= $usr['rol']===$rOpt?'selected':'' ?>><?= h($rOpt) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-6">
                                        <label class="fw-bold">Programa / Área *</label>
                                        <select name="programa" class="form-select form-select-sm">
                                            <?php foreach (['TODOS','APSTI','CONT','ENFE','AGRO','SOPORTE'] as $pOpt): ?>
                                                <option value="<?= h($pOpt) ?>" <?= $usr['programa']===$pOpt?'selected':'' ?>><?= h($pOpt) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="mb-2">
                                    <label class="fw-bold text-primary">Nueva Contraseña (opcional — dejar vacío para mantener la actual)</label>
                                    <input type="password" name="clave" class="form-control form-control-sm" placeholder="Mínimo 6 caracteres (se guardará cifrada con bcrypt)" minlength="6" autocomplete="new-password">
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
            <?= csrf_field() ?>
            <div class="modal-header bg-primary text-white py-2">
                <h6 class="modal-title fw-bold">Registrar Nuevo Usuario Institucional</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body small">
                <input type="hidden" name="accion" value="crear">
                <div class="mb-2">
                    <label class="fw-bold">DNI *</label>
                    <input type="text" name="dni" class="form-control form-control-sm" placeholder="8 dígitos" required>
                </div>
                <div class="mb-2">
                    <label class="fw-bold">Nombres y Cargo *</label>
                    <input type="text" name="nombres" class="form-control form-control-sm" placeholder="Nombre completo y cargo" required>
                </div>
                <div class="mb-2">
                    <label class="fw-bold">Correo Institucional *</label>
                    <input type="email" name="correo" class="form-control form-control-sm" placeholder="usuario@iestpu.edu.pe" required>
                </div>
                <div class="row g-2 mb-2">
                    <div class="col-6">
                        <label class="fw-bold">Rol *</label>
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
                        <label class="fw-bold">Programa / Área *</label>
                        <select name="programa" class="form-select form-select-sm">
                            <option value="APSTI">APSTI</option>
                            <option value="CONT">CONT</option>
                            <option value="ENFE">ENFE</option>
                            <option value="AGRO">AGRO</option>
                            <option value="SOPORTE">SOPORTE</option>
                            <option value="TODOS">TODOS</option>
                        </select>
                    </div>
                </div>
                <div class="mb-2">
                    <label class="fw-bold">Contraseña Inicial *</label>
                    <input type="password" name="clave" class="form-control form-control-sm" placeholder="Mínimo 6 caracteres" minlength="6" autocomplete="new-password" required>
                </div>
            </div>
            <div class="modal-footer py-2">
                <button type="submit" class="btn btn-primary btn-sm fw-bold">Registrar Usuario</button>
            </div>
        </form>
    </div>
</div>
<?php renderFooter(); ?>
