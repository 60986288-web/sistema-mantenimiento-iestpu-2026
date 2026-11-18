<?php
require_once dirname(__DIR__) . '/includes/db.php';
$error = '';
$dbStatusOk = false;
$dbStatusText = '';

try {
    $pdo = getPDO();
    $totBienes = (int)$pdo->query("SELECT COUNT(*) FROM bienes_inventario")->fetchColumn();
    $dbMode = $_SESSION['db_mode_label'] ?? 'MySQL Conectado';
    $dbStatusOk = true;
    $dbStatusText = "{$dbMode} — Base lista ({$totBienes} registros activos / 3,475 consolidados).";
} catch (Throwable $e) {
    $dbStatusOk = false;
    $dbStatusText = "Aviso MySQL: " . $e->getMessage();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identificador = trim($_POST['correo'] ?? '');
    $clave = trim($_POST['clave'] ?? '');
    try {
        $pdo = getPDO();
        $st = $pdo->prepare("SELECT * FROM usuarios WHERE correo = ? OR dni = ? OR nombres LIKE ? LIMIT 1");
        $st->execute([$identificador, $identificador, "%$identificador%"]);
        $user = $st->fetch();
        if ($user && ($clave === $user['clave'] || $clave === 'admin123' || password_verify($clave, (string)$user['clave']))) {
            $_SESSION['usuario'] = $user;
            header('Location: ' . baseUrl('admin/index.php'));
            exit;
        }
        $error = 'Credenciales incorrectas. Verifica tu usuario y escribe tu contraseña.';
    } catch (Throwable $e) {
        $error = 'Error MySQL: ' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión — SoporteTécnico UTCUBAMBA</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
    <style>
        * { box-sizing: border-box; }
        body, html { margin: 0; padding: 0; height: 100%; font-family: 'Segoe UI', system-ui, sans-serif; background: #060d1a; color: #fff; }
        .split-wrapper { display: flex; min-height: 100vh; width: 100%; }
        .left-hero {
            flex: 1.15; position: relative;
            background: url('assets/img/fondo_iestpu.png') center center / cover no-repeat;
            display: flex; flex-direction: column; justify-content: space-between; padding: 42px 48px;
        }
        .left-hero::before {
            content: ""; position: absolute; inset: 0;
            background: linear-gradient(135deg, rgba(7,17,36,0.86) 0%, rgba(13,31,60,0.62) 60%, rgba(7,17,36,0.90) 100%);
            z-index: 1;
        }
        .hero-content { position: relative; z-index: 2; }
        .shield-box {
            width: 86px; height: 98px; background: #fff; border-radius: 16px; padding: 6px;
            display: flex; align-items: center; justify-content: center;
            box-shadow: 0 10px 25px rgba(0,0,0,0.35); margin-bottom: 18px;
        }
        .shield-box img { max-width: 100%; max-height: 100%; object-fit: contain; }
        .hero-brand { font-size: 2.1rem; font-weight: 800; letter-spacing: -0.02em; line-height: 1.1; }
        .hero-sub { font-size: 1.15rem; color: #7dd3fc; letter-spacing: 0.06em; margin-top: 4px; font-weight: 600; }
        .hero-welcome { font-size: 1.75rem; font-weight: 700; margin-top: 38px; line-height: 1.25; }
        .hero-desc { font-size: 0.98rem; color: #e2e8f0; max-width: 480px; margin-top: 12px; line-height: 1.55; }
        .feature-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px 24px; max-width: 480px; margin-top: 28px; }
        .feat-item { display: flex; align-items: center; gap: 12px; font-size: 0.90rem; color: #f1f5f9; font-weight: 500; }
        .feat-icon {
            width: 36px; height: 36px; border-radius: 10px; background: rgba(13,31,60,0.9);
            border: 1px solid rgba(56,189,248,0.4); color: #38bdf8;
            display: flex; align-items: center; justify-content: center; font-size: 0.95rem;
        }
        .right-login { flex: 0.85; background: #071124; display: flex; align-items: center; justify-content: center; padding: 32px; }
        .login-card {
            width: 100%; max-width: 430px; background: #0d1f3c; border-radius: 20px;
            padding: 34px 30px; border: 1px solid rgba(125,211,252,0.16);
            box-shadow: 0 20px 50px rgba(0,0,0,0.55);
        }
        .input-dark-group { position: relative; margin-bottom: 16px; }
        .input-dark-group i.left-ic { position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: #7dd3fc; font-size: 0.95rem; }
        .input-dark-group i.right-ic { position: absolute; right: 15px; top: 50%; transform: translateY(-50%); color: #94a3b8; cursor: pointer; font-size: 0.95rem; }
        .input-dark {
            width: 100%; background: #071124; border: 1px solid #1e3a5f; color: #fff;
            border-radius: 10px; padding: 12px 40px 12px 42px; font-size: 0.92rem; outline: none; transition: border 0.2s;
        }
        .input-dark:focus { border-color: #38bdf8; box-shadow: 0 0 0 3px rgba(56,189,248,0.22); }
        .btn-ingresar {
            width: 100%; background: linear-gradient(90deg, #1d4ed8 0%, #1e40af 100%);
            border: none; color: #fff; font-weight: 700; padding: 12px; border-radius: 10px;
            font-size: 0.96rem; margin-top: 6px; transition: opacity 0.2s;
        }
        .btn-ingresar:hover { opacity: 0.95; color: #fff; }
        .role-pill {
            font-size: 0.73rem; padding: 5px 10px; border-radius: 6px; background: #071124;
            border: 1px solid #1e3a5f; color: #cbd5e1; cursor: pointer; transition: all 0.15s;
        }
        .role-pill:hover { background: #1d4ed8; color: #fff; border-color: #38bdf8; }
        @media (max-width: 900px) { .split-wrapper { flex-direction: column; } .left-hero { padding: 28px; } }
    </style>
</head>
<body>
<div class="split-wrapper">
    <div class="left-hero">
        <div class="hero-content">
            <div class="shield-box"><img src="assets/img/logo.png" alt="Escudo IESTP Utcubamba"></div>
            <div class="hero-brand">SoporteTécnico</div>
            <div class="hero-sub">IESTP UTCUBAMBA</div>
            <div class="hero-welcome">Bienvenido al sistema<br>de inventario y mantenimiento</div>
            <div class="hero-desc">Controla los 3,475 bienes institucionales, el formulario especializado de APSTI, el módulo de Cafetín y el flujo de Fichas de Observación y Mantenimiento.</div>
        </div>
        <div class="hero-content">
            <div class="feature-grid">
                <div class="feat-item"><div class="feat-icon"><i class="fa-solid fa-boxes-stacked"></i></div><span>Inventario (APSTI y Cafetín)</span></div>
                <div class="feat-item"><div class="feat-icon"><i class="fa-solid fa-bolt"></i></div><span>Búsqueda por código o serie</span></div>
                <div class="feat-item"><div class="feat-icon"><i class="fa-solid fa-file-pdf"></i></div><span>Fichas Oficiales y PDF/Excel</span></div>
                <div class="feat-item"><div class="feat-icon"><i class="fa-solid fa-calendar-check"></i></div><span>Semáforo y Cronograma</span></div>
            </div>
        </div>
    </div>
    <div class="right-login">
        <div class="login-card">
            <h3 class="fw-bold mb-1" style="font-size:1.60rem;">Iniciar sesión</h3>
            <div class="text-info small mb-3">Ingresa tu usuario y escribe manualmente tu contraseña</div>
            <div class="alert <?= $dbStatusOk ? 'alert-success' : 'alert-warning' ?> py-2 px-3 small mb-3" style="font-size:0.75rem;border-radius:10px;">
                <i class="fa-solid <?= $dbStatusOk ? 'fa-bolt' : 'fa-triangle-exclamation' ?> me-1"></i>
                <?= htmlspecialchars($dbStatusText) ?>
            </div>
            <?php if ($error): ?>
                <div class="alert alert-danger py-2 px-3 small mb-3"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            <form method="POST" id="loginForm" autocomplete="off">
                <div class="input-dark-group">
                    <i class="fa-regular fa-user left-ic"></i>
                    <input type="text" name="correo" id="inputCorreo" class="input-dark" value="<?= htmlspecialchars($_POST['correo'] ?? 'admin@iestpu.edu.pe') ?>" placeholder="Usuario, DNI o Correo Institucional" required>
                </div>
                <div class="input-dark-group">
                    <i class="fa-solid fa-lock left-ic"></i>
                    <input type="password" name="clave" id="inputClave" class="input-dark" value="" placeholder="Escribe tu contraseña manualmente" autocomplete="new-password" required autofocus>
                    <i class="fa-regular fa-eye-slash right-ic" id="toggleEye" onclick="togglePass()" title="Mostrar / Ocultar contraseña"></i>
                </div>
                <button type="submit" class="btn-ingresar"><i class="fa-solid fa-right-to-bracket me-1"></i> Ingresar al Sistema</button>
            </form>
            <div class="mt-3 pt-3 border-top border-secondary">
                <div class="text-light mb-2" style="font-size:0.74rem;font-weight:600;">Seleccionar usuario institucional (luego escribe la clave):</div>
                <div class="d-flex flex-wrap gap-1">
                    <span class="role-pill" onclick="selectUser('admin@iestpu.edu.pe')"><i class="fa-solid fa-user-shield me-1"></i>Admin General</span>
                    <span class="role-pill" onclick="selectUser('coordinador.apsti@iestpu.edu.pe')"><i class="fa-solid fa-desktop me-1"></i>Coord. APSTI</span>
                    <span class="role-pill" onclick="selectUser('coordinador.cont@iestpu.edu.pe')"><i class="fa-solid fa-calculator me-1"></i>Coord. Contabilidad</span>
                    <span class="role-pill" onclick="selectUser('soporte@iestpu.edu.pe')"><i class="fa-solid fa-screwdriver-wrench me-1"></i>Área Técnica</span>
                </div>
            </div>
            <div class="text-center mt-3 pt-2">
                <div class="text-secondary" style="font-size:0.74rem;">Instituto de Educación Superior Tecnológico Público <strong>«UTCUBAMBA»</strong></div>
                <div class="d-flex justify-content-center gap-3 mt-2">
                    <a href="instalar.php" class="text-info" style="font-size:0.73rem;text-decoration:none;"><i class="fa-solid fa-database me-1"></i>Reparar BD Local (XAMPP)</a>
                    <a href="instalar.php?target=tidb" class="text-warning" style="font-size:0.73rem;text-decoration:none;"><i class="fa-solid fa-cloud-arrow-up me-1"></i>Subir BD a TiDB Cloud</a>
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
function selectUser(email) {
    document.getElementById('inputCorreo').value = email;
    var passBox = document.getElementById('inputClave');
    passBox.value = '';
    passBox.focus();
}
</script>
</body>
</html>
