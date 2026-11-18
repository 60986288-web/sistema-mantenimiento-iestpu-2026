<?php
function calcularSemaforo(string $fechaLimite, int $requiereMant = 1): array {
    if (!$requiereMant) {
        return ['texto' => 'No requiere (Mobiliario)', 'clase' => 'bg-secondary'];
    }
    $hoy = new DateTimeImmutable(date('Y-m-d'));
    $lim = DateTimeImmutable::createFromFormat('Y-m-d', $fechaLimite) ?: $hoy;
    $diff = (int)$hoy->diff($lim)->format('%r%a');
    if ($diff < 0) {
        return ['texto' => $fechaLimite . ' (Vencido)', 'clase' => 'bg-danger'];
    } elseif ($diff <= 15) {
        return ['texto' => $fechaLimite . ' (Por vencer)', 'clase' => 'bg-warning text-dark'];
    }
    return ['texto' => $fechaLimite . ' (En regla)', 'clase' => 'bg-success'];
}

function renderHeader(string $titulo, string $activeMenu = 'inicio'): void {
    $u = $_SESSION['usuario'] ?? ['nombres' => 'Lic. Ernesto Correa Barrantes (Admin)', 'rol' => 'admin', 'programa' => 'TODOS'];
    $dbMode = $_SESSION['db_mode_label'] ?? 'XAMPP Local Ultrarrápido';
    ?>
<!DOCTYPE html>
<html lang="es" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($titulo) ?> — SoporteTécnico UTCUBAMBA</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --night-blue-dark: #071124;
            --night-blue-mid: #0d1f3c;
            --night-blue-accent: #1d4ed8;
            --bg-main: #f1f5f9;
            --bg-card: #ffffff;
            --text-main: #0f172a;
            --text-muted: #475569;
            --border-col: #cbd5e1;
        }
        html[data-theme="dark"] {
            --bg-main: #060d1a;
            --bg-card: #0f1e36;
            --text-main: #f1f5f9;
            --text-muted: #94a3b8;
            --border-col: #1e3a5f;
        }
        body {
            background-color: var(--bg-main);
            color: var(--text-main);
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            transition: background-color 0.2s, color 0.2s;
        }
        .sidebar {
            width: 265px; min-height: 100vh;
            background: linear-gradient(180deg, var(--night-blue-dark) 0%, var(--night-blue-mid) 100%);
            color: #fff; position: fixed; top: 0; left: 0; z-index: 1000; overflow-y: auto;
            border-right: 1px solid rgba(255,255,255,0.08);
        }
        .main-content { margin-left: 265px; padding: 18px 26px; min-height: 100vh; }
        .top-utility-bar {
            background: var(--bg-card); border: 1px solid var(--border-col);
            border-radius: 12px; padding: 10px 16px; margin-bottom: 18px;
            display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.04);
        }
        .card {
            background-color: var(--bg-card) !important;
            color: var(--text-main) !important;
            border-color: var(--border-col) !important;
        }
        html[data-theme="dark"] .table {
            color: #e2e8f0 !important;
            border-color: #1e3a5f !important;
        }
        html[data-theme="dark"] .table-light, html[data-theme="dark"] .bg-light {
            background-color: #15294a !important;
            color: #f8fafc !important;
        }
        html[data-theme="dark"] .form-control, html[data-theme="dark"] .form-select {
            background-color: #091528 !important;
            border-color: #264673 !important;
            color: #f1f5f9 !important;
        }
        html[data-theme="dark"] .modal-content {
            background-color: #0f1e36 !important;
            color: #f1f5f9 !important;
            border: 1px solid #264673 !important;
        }
        html[data-theme="dark"] .text-secondary, html[data-theme="dark"] .text-muted {
            color: #94a3b8 !important;
        }
        .nav-section { font-size: 0.69rem; text-transform: uppercase; letter-spacing: 0.07em; color: #7dd3fc; padding: 10px 18px 3px; font-weight: 700; }
        .sidebar a.nav-link { color: #cbd5e1; padding: 8px 15px; border-radius: 8px; margin: 2px 10px; font-size: 0.85rem; display: flex; align-items: center; gap: 10px; text-decoration: none; transition: all 0.15s; }
        .sidebar a.nav-link:hover, .sidebar a.nav-link.active { background: #1d4ed8; color: #fff; font-weight: 600; box-shadow: 0 2px 8px rgba(29,78,216,0.45); }
        .theme-toggle-btn {
            border-radius: 20px; font-size: 0.80rem; font-weight: 600; padding: 5px 14px;
            background: #0d1f3c; color: #fff; border: 1px solid #2563eb; cursor: pointer;
            display: inline-flex; align-items: center; gap: 6px;
        }
        html[data-theme="dark"] .theme-toggle-btn {
            background: #f59e0b; color: #0f172a; border-color: #fbbf24;
        }
        .print-only-header { display: none; }
        @media (max-width: 991px) {
            .sidebar { position: static; width: 100%; min-height: auto; }
            .main-content { margin-left: 0; padding: 15px; }
        }
        @media print {
            .sidebar, .no-print, .top-utility-bar, .modal, .btn { display: none !important; }
            .main-content { margin-left: 0 !important; padding: 0 !important; background: #fff !important; }
            body { background: #fff !important; color: #000 !important; }
            .print-only-header { display: flex !important; align-items: center; justify-content: space-between; border-bottom: 2px solid #071124; padding-bottom: 10px; margin-bottom: 15px; }
            .card { border: 1px solid #000 !important; box-shadow: none !important; background: #fff !important; color: #000 !important; }
        }
    </style>
    <script>
        (function() {
            var savedTheme = localStorage.getItem('iestpu_theme') || 'light';
            document.documentElement.setAttribute('data-theme', savedTheme);
        })();
        function toggleThemeMode() {
            var cur = document.documentElement.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
            document.documentElement.setAttribute('data-theme', cur);
            localStorage.setItem('iestpu_theme', cur);
            updateThemeBtnLabel();
        }
        function updateThemeBtnLabel() {
            var btn = document.getElementById('btnThemeToggle');
            if (!btn) return;
            var isDark = document.documentElement.getAttribute('data-theme') === 'dark';
            btn.innerHTML = isDark
                ? '<i class="fa-solid fa-sun"></i> Modo Claro'
                : '<i class="fa-solid fa-moon"></i> Modo Azul Noche (Oscuro)';
        }
        document.addEventListener('DOMContentLoaded', updateThemeBtnLabel);
    </script>
</head>
<body>
<div class="sidebar d-flex flex-column justify-content-between">
    <div>
        <div class="p-3 d-flex align-items-center gap-2 border-bottom border-secondary">
            <img src="<?= baseUrl('assets/img/logo.png') ?>" alt="Escudo IESTPU" style="width:40px;height:46px;background:#fff;padding:3px;border-radius:8px;">
            <div>
                <div class="fw-bold text-white" style="font-size:0.96rem;line-height:1.1;">SoporteTécnico</div>
                <div class="text-info" style="font-size:0.72rem;font-weight:600;">IESTP UTCUBAMBA</div>
            </div>
        </div>
        <div class="p-2 mx-2 my-2 rounded" style="background:rgba(255,255,255,0.06);border:1px solid rgba(255,255,255,0.12);">
            <div class="fw-semibold text-white" style="font-size:0.80rem;"><?= htmlspecialchars($u['nombres']) ?></div>
            <span class="badge bg-primary mt-1" style="font-size:0.68rem;"><?= htmlspecialchars(strtoupper($u['rol'])) ?> · <?= htmlspecialchars($u['programa']) ?></span>
        </div>
        <div class="nav-section">Principal</div>
        <a href="<?= baseUrl('admin/index.php') ?>" class="nav-link <?= $activeMenu==='inicio'?'active':'' ?>"><i class="fa-solid fa-gauge-high fa-fw"></i> Inicio / Panel</a>
        <div class="nav-section">Módulos de Inventario</div>
        <a href="<?= baseUrl('admin/equipos.php') ?>" class="nav-link <?= $activeMenu==='inventario'?'active':'' ?>"><i class="fa-solid fa-boxes-stacked fa-fw"></i> 1. Inventario Bienes (3,475)</a>
        <a href="<?= baseUrl('admin/inventariar_apsti.php') ?>" class="nav-link <?= $activeMenu==='apsti'?'active':'' ?>"><i class="fa-solid fa-desktop fa-fw"></i> Formulario Inv. APSTI</a>
        <a href="<?= baseUrl('admin/cafetin.php') ?>" class="nav-link <?= $activeMenu==='cafetin'?'active':'' ?>"><i class="fa-solid fa-mug-hot fa-fw"></i> Módulo Cafetín (30 Bienes)</a>
        <a href="<?= baseUrl('admin/buscar_equipo.php') ?>" class="nav-link <?= $activeMenu==='buscar'?'active':'' ?>"><i class="fa-solid fa-magnifying-glass fa-fw"></i> 2. Mantenimiento / Buscar</a>
        <div class="nav-section">Flujo de 3 Pasos y Fichas</div>
        <a href="<?= baseUrl('docente/reportar.php') ?>" class="nav-link <?= $activeMenu==='reportar'?'active':'' ?>"><i class="fa-solid fa-triangle-exclamation fa-fw"></i> Paso 1: Reportar Falla</a>
        <a href="<?= baseUrl('admin/verificacion_admin.php') ?>" class="nav-link <?= $activeMenu==='verificacion'?'active':'' ?>"><i class="fa-solid fa-clipboard-check fa-fw"></i> Paso 2: Verificar + Ficha</a>
        <a href="<?= baseUrl('tecnico/trabajos.php') ?>" class="nav-link <?= $activeMenu==='tecnico'?'active':'' ?>"><i class="fa-solid fa-screwdriver-wrench fa-fw"></i> Paso 3: Área Técnica</a>
        <a href="<?= baseUrl('admin/cronograma.php') ?>" class="nav-link <?= $activeMenu==='cronograma'?'active':'' ?>"><i class="fa-solid fa-calendar-check fa-fw"></i> Cronograma Preventivo</a>
        <a href="<?= baseUrl('admin/reportes.php') ?>" class="nav-link <?= $activeMenu==='reportes'?'active':'' ?>"><i class="fa-solid fa-file-pdf fa-fw"></i> Reportes (PDF / Excel)</a>
        <div class="nav-section">Seguridad y Cuentas</div>
        <a href="<?= baseUrl('admin/usuarios.php') ?>" class="nav-link <?= $activeMenu==='usuarios'?'active':'' ?>"><i class="fa-solid fa-users-gear fa-fw"></i> Usuarios y Contraseñas</a>
    </div>
    <div class="p-3 border-top border-secondary">
        <a href="<?= baseUrl('instalar.php') ?>" class="btn btn-outline-info btn-sm w-100 mb-2" style="font-size:0.75rem;"><i class="fa-solid fa-database"></i> Reparar / Sincronizar BD</a>
        <a href="<?= baseUrl('logout.php') ?>" class="btn btn-outline-danger btn-sm w-100" style="font-size:0.78rem;"><i class="fa-solid fa-right-from-bracket"></i> Cerrar Sesión</a>
    </div>
</div>
<div class="main-content">
    <div class="top-utility-bar no-print">
        <div class="d-flex align-items-center gap-2">
            <span class="badge bg-success"><i class="fa-solid fa-bolt me-1"></i> <?= htmlspecialchars($dbMode) ?></span>
            <span class="small text-secondary d-none d-md-inline">IESTP «Utcubamba» · Bagua Grande 2026</span>
        </div>
        <div class="d-flex align-items-center gap-2">
            <button type="button" id="btnThemeToggle" class="theme-toggle-btn" onclick="toggleThemeMode()">
                <i class="fa-solid fa-moon"></i> Modo Azul Noche (Oscuro)
            </button>
            <a href="<?= baseUrl('admin/buscar_equipo.php') ?>" class="btn btn-outline-primary btn-sm" style="border-radius:20px;font-size:0.78rem;">
                <i class="fa-solid fa-magnifying-glass me-1"></i> Buscar Código / Serie
            </a>
        </div>
    </div>
    <div class="print-only-header">
        <div class="d-flex align-items-center gap-3">
            <img src="<?= baseUrl('assets/img/logo.png') ?>" style="width:55px;">
            <div>
                <div style="font-size:10pt;font-weight:bold;">INSTITUTO DE EDUCACIÓN SUPERIOR TECNOLÓGICO PÚBLICO «UTCUBAMBA»</div>
                <div style="font-size:9pt;">Cód. Modular: 741744 · Bagua Grande – Utcubamba – Amazonas · Sistema Web de Inventario y Mantenimiento 2026</div>
            </div>
        </div>
        <div style="font-size:9pt;text-align:right;">
            <strong>Fecha:</strong> <?= date('d/m/Y H:i') ?><br>
            <strong>Usuario:</strong> <?= htmlspecialchars($u['nombres']) ?>
        </div>
    </div>
<?php
}

function renderFooter(): void {
    ?>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php
}
