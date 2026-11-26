<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/db.php';

if (!empty($_SESSION['usuario'])) {
    header('Location: ' . baseUrl(role_home(get_user_role($_SESSION['usuario']))));
    exit;
}

$error = '';
$aviso = '';
if (!empty($_GET['expired'])) {
    $aviso = 'Tu sesión expiró por inactividad (30 minutos). Vuelve a iniciar sesión.';
} elseif (!empty($_GET['disabled'])) {
    $aviso = 'Tu cuenta ha sido desactivada por el Administrador.';
}

$redirectRaw = trim((string)($_GET['redirect'] ?? $_POST['redirect'] ?? ''));
$isLocalDemo = (getenv('APP_ENV') === 'local');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $identificador = trim((string)($_POST['correo'] ?? ''));
    $clave = (string)($_POST['clave'] ?? '');

    if ($identificador === '' || $clave === '') {
        $error = 'Ingresa tu correo institucional (o DNI) y tu contraseña.';
    } else {
        try {
            $pdo = getPDO();
            $st = $pdo->prepare("SELECT * FROM usuarios WHERE correo = ? OR dni = ? LIMIT 1");
            $st->execute([$identificador, $identificador]);
            $user = $st->fetch();

            $passwordOk = false;
            if ($user) {
                $storedHash = (string)($user['clave'] ?? '');
                $passwordOk = password_verify($clave, $storedHash);
            }

            if ($user && $passwordOk) {
                if (isset($user['activo']) && (int)$user['activo'] === 0) {
                    $error = 'Esta cuenta se encuentra desactivada. Comunícate con el Administrador.';
                } else {
                    session_regenerate_id(true);
                    unset($user['clave']); // Nunca almacenar hash ni clave en la sesión
                    $_SESSION['usuario'] = $user;
                    $_SESSION['rol'] = get_user_role($user);
                    $_SESSION['last_activity'] = time();

                    registrar_auditoria($pdo, 'LOGIN', 'usuarios', $user['id'], 'Inicio de sesión exitoso');

                    $rolUser = get_user_role($user);
                    if ($redirectRaw !== '' && is_path_allowed_for_role($redirectRaw, $rolUser)) {
                        header('Location: ' . baseUrl($redirectRaw));
                    } else {
                        header('Location: ' . baseUrl(role_home($rolUser)));
                    }
                    exit;
                }
            } else {
                $error = 'Credenciales incorrectas. Verifica tu usuario y contraseña.';
            }
        } catch (Throwable $e) {
            $error = 'No se pudo validar el acceso en este momento. Intenta nuevamente.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión — SoporteTécnico IESTP Utcubamba</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
    <style>
        * { box-sizing: border-box; }
        body, html {
            margin: 0; padding: 0; min-height: 100vh;
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            background: #0b2426; color: #f8fafc;
        }
        .login-bg {
            min-height: 100vh; width: 100%;
            background: linear-gradient(120deg, rgba(11, 45, 48, 0.72) 0%, rgba(15, 59, 60, 0.52) 55%, rgba(9, 28, 32, 0.85) 100%),
                        url('assets/img/fondo_iestpu.jpg') center center / cover no-repeat fixed;
            display: flex; align-items: center; justify-content: center;
            padding: 28px 20px;
        }
        .login-container {
            width: 100%; max-width: 1040px;
            display: grid; grid-template-columns: 1.15fr 0.85fr;
            border-radius: 18px; overflow: hidden;
            box-shadow: 0 24px 60px rgba(0, 0, 0, 0.45);
            border: 1px solid rgba(255, 255, 255, 0.14);
        }
        .brand-panel {
            background: linear-gradient(160deg, rgba(15, 59, 60, 0.82) 0%, rgba(11, 36, 38, 0.90) 100%);
            backdrop-filter: blur(4px);
            padding: 44px 40px;
            display: flex; flex-direction: column; justify-content: space-between;
        }
        .shield-box {
            width: 78px; height: 88px; background: #ffffff; border-radius: 14px; padding: 6px;
            display: flex; align-items: center; justify-content: center;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.28); margin-bottom: 20px;
        }
        .shield-box img { max-width: 100%; max-height: 100%; object-fit: contain; }
        .inst-tag {
            display: inline-block; font-size: 0.72rem; font-weight: 700; letter-spacing: 0.08em;
            text-transform: uppercase; color: #6ee7b7; margin-bottom: 8px;
        }
        .hero-title { font-size: 1.85rem; font-weight: 800; line-height: 1.2; margin: 0 0 12px 0; color: #ffffff; }
        .hero-desc { font-size: 0.94rem; color: #d1fae5; line-height: 1.55; margin: 0; max-width: 440px; }
        .feat-list { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-top: 28px; }
        .feat-chip {
            display: flex; align-items: center; gap: 10px;
            background: rgba(255, 255, 255, 0.08); border: 1px solid rgba(167, 243, 208, 0.22);
            border-radius: 10px; padding: 10px 12px; font-size: 0.82rem; color: #ecfdf5;
        }
        .feat-chip i { color: #34d399; font-size: 1rem; }

        .form-panel {
            background: #ffffff; color: #1e293b;
            padding: 44px 36px;
            display: flex; flex-direction: column; justify-content: center;
        }
        .form-label-sm { font-size: 0.80rem; font-weight: 700; color: #334155; margin-bottom: 5px; display: block; }
        .input-group-custom { position: relative; margin-bottom: 16px; }
        .input-group-custom i.left-ic { position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: #64748b; }
        .input-group-custom i.right-ic { position: absolute; right: 14px; top: 50%; transform: translateY(-50%); color: #64748b; cursor: pointer; }
        .input-clean {
            width: 100%; background: #f8fafc; border: 1.5px solid #cbd5e1; color: #0f172a;
            border-radius: 10px; padding: 11px 38px 11px 40px; font-size: 0.92rem; outline: none; transition: all 0.2s;
        }
        .input-clean:focus { border-color: #10b981; background: #ffffff; box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.16); }
        .btn-login-main {
            width: 100%; background: #0f3b3c; border: none; color: #ffffff;
            font-weight: 700; padding: 12px; border-radius: 10px; font-size: 0.95rem;
            transition: background 0.15s; cursor: pointer;
        }
        .btn-login-main:hover { background: #164e4f; }
        .local-pill {
            font-size: 0.73rem; padding: 4px 9px; border-radius: 6px;
            background: #f1f5f9; border: 1px solid #cbd5e1; color: #334155; cursor: pointer;
        }
        .local-pill:hover { background: #ecfdf5; border-color: #10b981; color: #047857; }
        @media (max-width: 860px) {
            .login-container { grid-template-columns: 1fr; }
            .brand-panel { padding: 28px; }
            .form-panel { padding: 30px 24px; }
        }
    </style>
</head>
<body>
<div class="login-bg">
    <div class="login-container">
        <div class="brand-panel">
            <div>
                <div class="shield-box"><img src="assets/img/logo.png" alt="Escudo IESTP Utcubamba"></div>
                <span class="inst-tag">IESTP «Utcubamba» · Bagua Grande</span>
                <h1 class="hero-title">Sistema de Inventario y Mantenimiento de Equipos</h1>
                <p class="hero-desc">Plataforma institucional para el control patrimonial, reporte de incidencias, emisión de fichas oficiales de mantenimiento y cronograma preventivo.</p>
            </div>
            <div class="feat-list">
                <div class="feat-chip"><i class="fa-solid fa-boxes-stacked"></i><span>Control por Programas y Áreas</span></div>
                <div class="feat-chip"><i class="fa-solid fa-file-signature"></i><span>Fichas Oficiales con QR</span></div>
                <div class="feat-chip"><i class="fa-solid fa-calendar-check"></i><span>Cronograma Preventivo</span></div>
                <div class="feat-chip"><i class="fa-solid fa-shield-halved"></i><span>Acceso Seguro por Roles</span></div>
            </div>
        </div>

        <div class="form-panel">
            <h3 class="fw-bold mb-1" style="color:#0f3b3c;">Iniciar sesión</h3>
            <p class="text-secondary small mb-4">Ingresa tus credenciales institucionales para continuar.</p>

            <?php if ($aviso): ?>
                <div class="alert alert-warning py-2 px-3 small mb-3"><?= h($aviso) ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-danger py-2 px-3 small mb-3"><i class="fa-solid fa-circle-exclamation me-1"></i> <?= h($error) ?></div>
            <?php endif; ?>

            <form method="POST" autocomplete="off">
                <?= csrf_field() ?>
                <?php if ($redirectRaw !== ''): ?>
                    <input type="hidden" name="redirect" value="<?= h($redirectRaw) ?>">
                <?php endif; ?>

                <label class="form-label-sm" for="inputCorreo">Correo institucional o DNI</label>
                <div class="input-group-custom">
                    <i class="fa-regular fa-user left-ic"></i>
                    <input type="text" name="correo" id="inputCorreo" class="input-clean"
                           value="<?= h($_POST['correo'] ?? '') ?>"
                           placeholder="usuario@iestpu.edu.pe o DNI" required autofocus>
                </div>

                <label class="form-label-sm" for="inputClave">Contraseña</label>
                <div class="input-group-custom">
                    <i class="fa-solid fa-lock left-ic"></i>
                    <input type="password" name="clave" id="inputClave" class="input-clean"
                           placeholder="Ingresa tu contraseña" autocomplete="current-password" required>
                    <i class="fa-regular fa-eye-slash right-ic" id="toggleEye" onclick="togglePass()" title="Mostrar u ocultar contraseña"></i>
                </div>

                <button type="submit" class="btn-login-main mt-1">
                    <i class="fa-solid fa-right-to-bracket me-1"></i> Ingresar al Sistema
                </button>
            </form>

            <?php if ($isLocalDemo): ?>
            <div class="mt-4 pt-3 border-top">
                <div class="text-secondary mb-2" style="font-size:0.73rem;font-weight:600;">Entorno local (APP_ENV=local) — Rellenar correo:</div>
                <div class="d-flex flex-wrap gap-1">
                    <span class="local-pill" onclick="fillEmail('admin@iestpu.edu.pe')">Admin</span>
                    <span class="local-pill" onclick="fillEmail('coordinador.apsti@iestpu.edu.pe')">APSTI</span>
                    <span class="local-pill" onclick="fillEmail('coordinador.cont@iestpu.edu.pe')">Contabilidad</span>
                    <span class="local-pill" onclick="fillEmail('coordinador.enfe@iestpu.edu.pe')">Enfermería</span>
                    <span class="local-pill" onclick="fillEmail('soporte@iestpu.edu.pe')">Técnico</span>
                </div>
            </div>
            <?php endif; ?>

            <div class="text-center mt-4 pt-2 border-top">
                <div class="text-secondary" style="font-size:0.75rem;">
                    Instituto de Educación Superior Tecnológico Público <strong>«UTCUBAMBA»</strong><br>
                    Cód. Modular: 741744 · Bagua Grande – Amazonas
                </div>
            </div>
        </div>
    </div>
</div>
<script>
function togglePass() {
    var inp = document.getElementById('inputClave');
    var ic = document.getElementById('toggleEye');
    if (inp.type === 'password') { inp.type = 'text'; ic.className = 'fa-regular fa-eye right-ic'; }
    else { inp.type = 'password'; ic.className = 'fa-regular fa-eye-slash right-ic'; }
}
function fillEmail(em) {
    document.getElementById('inputCorreo').value = em;
    document.getElementById('inputClave').focus();
}
</script>
</body>
</html>
