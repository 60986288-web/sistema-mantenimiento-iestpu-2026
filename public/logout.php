<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

if (!empty($_SESSION['usuario'])) {
    try {
        $pdo = getDB();
        registrar_auditoria($pdo, 'LOGOUT', 'usuarios', (int)($_SESSION['usuario']['id'] ?? 0), 'Cierre de sesión');
    } catch (Throwable $e) {
        // Continuar cierre aunque no haya conexión
    }
}

$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], (bool)$params['secure'], (bool)$params['httponly']);
}
session_destroy();

header('Location: ' . baseUrl('login.php'));
exit;
