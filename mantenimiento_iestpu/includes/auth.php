<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

const SESSION_TIMEOUT_SECONDS = 1800; // 30 minutos de inactividad

function h($value): string {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function fmt_fecha(?string $fecha): string {
    if (!$fecha || trim($fecha) === '' || $fecha === '-' || $fecha === '0000-00-00') {
        return '—';
    }
    $clean = substr(trim($fecha), 0, 10);
    $dt = DateTimeImmutable::createFromFormat('Y-m-d', $clean);
    if ($dt) {
        return $dt->format('d/m/Y');
    }
    $dt2 = DateTimeImmutable::createFromFormat('d/m/Y', $clean);
    if ($dt2) {
        return $dt2->format('d/m/Y');
    }
    return h($fecha);
}

function fmt_fecha_hora(?string $fechaHora): string {
    if (!$fechaHora || trim($fechaHora) === '' || $fechaHora === '-') {
        return '—';
    }
    try {
        $dt = new DateTimeImmutable($fechaHora);
        return $dt->format('d/m/Y H:i');
    } catch (Throwable $e) {
        return h($fechaHora);
    }
}

function mask_dni(?string $dni): string {
    $d = trim((string)($dni ?? ''));
    if (strlen($d) <= 4) {
        return '****';
    }
    return str_repeat('*', max(4, strlen($d) - 4)) . substr($d, -4);
}

function clean_tech_value(?string $val): string {
    $v = trim((string)($val ?? ''));
    if ($v === '' || $v === '?' || $v === '-') {
        return 'No registrado';
    }
    return $v;
}

function clean_especificaciones(?string $raw): string {
    $txt = trim((string)($raw ?? ''));
    if ($txt === '' || $txt === '-') {
        return 'No registrado';
    }
    $txt = str_ireplace('NVIDEA', 'NVIDIA', $txt);
    $parts = explode('|', $txt);
    $cleaned = [];
    foreach ($parts as $p) {
        $p = trim($p);
        if ($p === '') continue;
        if (strpos($p, ':') !== false) {
            [$k, $v] = explode(':', $p, 2);
            $vClean = trim($v);
            if ($vClean === '' || $vClean === '?' || $vClean === '-') {
                $vClean = 'No registrado';
            }
            $cleaned[] = trim($k) . ': ' . $vClean;
        } else {
            $cleaned[] = $p;
        }
    }
    return implode(' | ', $cleaned);
}

function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return (string)$_SESSION['csrf_token'];
}

function csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="' . h(csrf_token()) . '">';
}

function verify_csrf(): void {
    $sent = (string)($_POST['csrf_token'] ?? '');
    $expected = (string)($_SESSION['csrf_token'] ?? '');
    if ($sent === '' || $expected === '' || !hash_equals($expected, $sent)) {
        http_response_code(403);
        render_access_denied('Token de seguridad CSRF inválido o expirado. Recarga la página e intenta nuevamente.');
        exit;
    }
}

function get_user_role(?array $u = null): string {
    $u = $u ?? ($_SESSION['usuario'] ?? []);
    return strtolower(trim((string)($u['rol'] ?? '')));
}

function get_user_program_scope(?array $u = null): ?string {
    $u = $u ?? ($_SESSION['usuario'] ?? []);
    $rol = get_user_role($u);
    if ($rol === 'admin' || $rol === 'tecnico') {
        return null; // Acceso global (según permisos del rol)
    }
    $map = [
        'coordinador_apsti' => 'APSTI',
        'coordinador_cont'  => 'CONT',
        'coordinador_enfe'  => 'ENFE',
        'coordinador_agro'  => 'AGRO',
    ];
    return $map[$rol] ?? strtoupper((string)($u['programa'] ?? 'APSTI'));
}

function role_home(string $rol): string {
    $r = strtolower(trim($rol));
    if ($r === 'tecnico') {
        return 'tecnico/trabajos.php';
    }
    return 'admin/index.php';
}

function is_path_allowed_for_role(string $relativePath, string $rol): bool {
    $r = strtolower(trim($rol));
    $p = ltrim(parse_url($relativePath, PHP_URL_PATH) ?: '', '/');
    if ($p === '' || strpos($p, '..') !== false || preg_match('#^https?://#i', $relativePath)) {
        return false;
    }
    if ($r === 'admin') {
        return true;
    }
    $commonCoords = [
        'admin/index.php',
        'admin/equipos.php',
        'docente/reportar.php',
        'admin/ficha_mantenimiento.php',
        'admin/cronograma.php',
        'admin/reportes.php',
        'admin/buscar_equipo.php',
    ];
    if ($r === 'coordinador_apsti') {
        return in_array($p, array_merge($commonCoords, ['admin/inventariar_apsti.php']), true);
    }
    if (in_array($r, ['coordinador_cont', 'coordinador_enfe', 'coordinador_agro'], true)) {
        return in_array($p, $commonCoords, true);
    }
    if ($r === 'tecnico') {
        return in_array($p, [
            'tecnico/trabajos.php',
            'admin/buscar_equipo.php',
            'admin/cronograma.php',
            'admin/ficha_mantenimiento.php',
        ], true);
    }
    return false;
}

function render_access_denied(string $motivo = 'No tienes permisos para acceder a este módulo.'): void {
    $u = $_SESSION['usuario'] ?? null;
    $homeUrl = $u ? baseUrl(role_home(get_user_role($u))) : baseUrl('login.php');
    ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>403 Acceso Denegado — SoporteTécnico UTCUBAMBA</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
</head>
<body style="background:#f3f6f8;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px;font-family:'Segoe UI',system-ui,sans-serif;">
    <div class="card shadow-sm p-4 text-center" style="max-width:480px;width:100%;border-radius:14px;border-top:4px solid #b91c1c;">
        <div class="mb-3"><i class="fa-solid fa-shield-halved text-danger" style="font-size:3rem;"></i></div>
        <h4 class="fw-bold text-dark mb-2">HTTP 403 — Acceso Denegado</h4>
        <p class="text-secondary small mb-4"><?= h($motivo) ?></p>
        <div class="d-flex justify-content-center gap-2">
            <a href="<?= h($homeUrl) ?>" class="btn btn-dark btn-sm px-3 fw-semibold"><i class="fa-solid fa-house me-1"></i> Ir a mi Panel</a>
            <a href="<?= h(baseUrl('logout.php')) ?>" class="btn btn-outline-danger btn-sm px-3">Cerrar Sesión</a>
        </div>
    </div>
</body>
</html>
    <?php
}

function require_login(): array {
    if (empty($_SESSION['usuario'])) {
        $reqUri = $_SERVER['REQUEST_URI'] ?? '';
        $base = rtrim((string)(require dirname(__DIR__) . '/config/config.php')['app']['base_url'], '/');
        $rel = $reqUri;
        if ($base !== '' && strpos($rel, $base) === 0) {
            $rel = substr($rel, strlen($base));
        }
        $rel = ltrim($rel, '/');
        $redirParam = ($rel !== '' && strpos($rel, 'login.php') === false) ? ('?redirect=' . urlencode($rel)) : '';
        header('Location: ' . baseUrl('login.php') . $redirParam);
        exit;
    }

    // Expiración de sesión por inactividad (30 min)
    $now = time();
    if (!empty($_SESSION['last_activity']) && ($now - (int)$_SESSION['last_activity']) > SESSION_TIMEOUT_SECONDS) {
        session_unset();
        session_destroy();
        header('Location: ' . baseUrl('login.php?expired=1'));
        exit;
    }
    $_SESSION['last_activity'] = $now;
    $_SESSION['rol'] = get_user_role($_SESSION['usuario']);

    // Verificar que la cuenta siga activa
    if (isset($_SESSION['usuario']['activo']) && (int)$_SESSION['usuario']['activo'] === 0) {
        session_unset();
        session_destroy();
        header('Location: ' . baseUrl('login.php?disabled=1'));
        exit;
    }

    return $_SESSION['usuario'];
}

function require_role(array $rolesPermitidos): array {
    $u = require_login();
    $rolActual = get_user_role($u);
    $allowedLower = array_map(fn($r) => strtolower(trim((string)$r)), $rolesPermitidos);
    if (!in_array($rolActual, $allowedLower, true)) {
        http_response_code(403);
        render_access_denied('Tu perfil (' . strtoupper($rolActual) . ') no está autorizado para acceder a esta sección.');
        exit;
    }
    return $u;
}

function registrar_auditoria($pdo, string $accion, string $tabla, $registroId = null, string $detalle = ''): void {
    try {
        $u = $_SESSION['usuario'] ?? [];
        $uid = (int)($u['id'] ?? 0);
        $rol = get_user_role($u) ?: 'anonimo';
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');
        $ip = trim(explode(',', (string)$ip)[0]);
        $st = $pdo->prepare("INSERT INTO auditoria_logs (usuario_id, rol, accion, tabla_afectada, registro_id, detalle, fecha_hora, ip) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $st->execute([$uid, $rol, $accion, $tabla, (string)($registroId ?? ''), $detalle, date('Y-m-d H:i:s'), $ip]);
    } catch (Throwable $e) {
        // Nunca interrumpir operación principal si falla el log
    }
}

function procesar_foto_evidencia(string $fieldName, string $prefix): ?string {
    if (empty($_FILES[$fieldName]) || ($_FILES[$fieldName]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if (($_FILES[$fieldName]['error'] ?? 0) !== UPLOAD_ERR_OK) {
        return null;
    }
    $maxBytes = 3 * 1024 * 1024; // 3 MB máx
    if ((int)($_FILES[$fieldName]['size'] ?? 0) > $maxBytes) {
        throw new RuntimeException('La imagen supera el tamaño máximo permitido (3 MB).');
    }
    $tmpPath = $_FILES[$fieldName]['tmp_name'];
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($tmpPath);
    $extMap = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    ];
    if (!isset($extMap[$mime])) {
        throw new RuntimeException('Formato de imagen no permitido. Usa JPG, PNG o WEBP.');
    }
    $destDir = dirname(__DIR__) . '/public/uploads/evidencias';
    if (!is_dir($destDir)) {
        @mkdir($destDir, 0775, true);
    }
    $fname = $prefix . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $extMap[$mime];
    $destPath = $destDir . '/' . $fname;
    if (!move_uploaded_file($tmpPath, $destPath)) {
        throw new RuntimeException('No se pudo guardar la imagen de evidencia.');
    }
    return 'uploads/evidencias/' . $fname;
}
