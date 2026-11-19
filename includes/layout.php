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
    $dbMode = $_SESSION['db_mode_label'] ?? 'MySQL Conectado';
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
        /* Paleta Institucional Ergonómica (baja fatiga visual para uso prolongado diario) */
        :root {
            --sidebar-bg: #162235;
            --sidebar-hover: #243754;
            --sidebar-active: #2c4b78;
            --bg-main: #f4f6f9;
            --bg-card: #ffffff;
            --bg-subtle: #f8fafc;
            --text-main: #1e293b;
            --text-muted: #64748b;
            --border-col: #e2e8f0;
            --accent-navy: #274c77;
            --accent-navy-hover: #1d3a5c;
        }
        html[data-theme="dark"] {
            --bg-main: #0f172a;
            --bg-card: #182334;
            --bg-subtle: #1e2c42;
            --text-main: #e2e8f0;
            --text-muted: #94a3b8;
            --border-col: #2b3b52;
        }
        body {
            background-color: var(--bg-main);
            color: var(--text-main);
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            font-size: 0.92rem;
            transition: background-color 0.2s, color 0.2s;
        }
        .sidebar {
            width: 258px; min-height: 100vh;
            background: var(--sidebar-bg);
            color: #e2e8f0; position: fixed; top: 0; left: 0; z-index: 1000; overflow-y: auto;
            border-right: 1px solid rgba(255,255,255,0.06);
        }
        .main-content { margin-left: 258px; padding: 18px 26px; min-height: 100vh; }
        .top-utility-bar {
            background: var(--bg-card); border: 1px solid var(--border-col);
            border-radius: 10px; padding: 9px 16px; margin-bottom: 18px;
            display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px;
        }
        .card {
            background-color: var(--bg-card) !important;
            color: var(--text-main) !important;
            border: 1px solid var(--border-col) !important;
            border-radius: 10px;
            box-shadow: 0 1px 3px rgba(15,23,42,0.04) !important;
        }

        /* Suavizado ergonómico de botones Bootstrap (adiós colores neón/chillones) */
        .btn-primary { background-color: #274c77 !important; border-color: #274c77 !important; color: #fff !important; }
        .btn-primary:hover { background-color: #1d3a5c !important; border-color: #1d3a5c !important; }
        .btn-outline-primary { color: #274c77 !important; border-color: #cbd5e1 !important; background: transparent; }
        .btn-outline-primary:hover { background-color: #edf2f7 !important; color: #1d3a5c !important; border-color: #94a3b8 !important; }

        .btn-success { background-color: #2d6a4f !important; border-color: #2d6a4f !important; color: #fff !important; }
        .btn-success:hover { background-color: #21523c !important; }
        .btn-outline-success { color: #2d6a4f !important; border-color: #cbd5e1 !important; }
        .btn-outline-success:hover { background-color: #ecfdf5 !important; color: #1b4332 !important; }

        .btn-warning { background-color: #f3e8d2 !important; border-color: #d9b878 !important; color: #5c4014 !important; }
        .btn-warning:hover { background-color: #ead7b2 !important; color: #422c0c !important; }
        .btn-outline-warning { color: #7c581d !important; border-color: #cbd5e1 !important; }
        .btn-outline-warning:hover { background-color: #fef9ee !important; }

        .btn-info { background-color: #e0edf7 !important; border-color: #b4cfe4 !important; color: #1d4466 !important; }
        .btn-info:hover { background-color: #cde2f2 !important; color: #132f48 !important; }
        .btn-outline-info { color: #274c77 !important; border-color: #cbd5e1 !important; }
        .btn-outline-info:hover { background-color: #eef4fa !important; }

        .btn-danger { background-color: #9b2c2c !important; border-color: #9b2c2c !important; color: #fff !important; }
        .btn-danger:hover { background-color: #7f2222 !important; }
        .btn-outline-danger { color: #9b2c2c !important; border-color: #e2c5c5 !important; }
        .btn-outline-danger:hover { background-color: #fef2f2 !important; }

        .btn-dark { background-color: #334155 !important; border-color: #334155 !important; color: #fff !important; }
        .btn-dark:hover { background-color: #1e293b !important; }

        /* Suavizado de colores de texto y bordes KPI */
        .text-primary { color: #274c77 !important; }
        .text-info { color: #3b6b94 !important; }
        .text-warning { color: #9a6715 !important; }
        .text-success { color: #2d6a4f !important; }
        .text-danger { color: #9b2c2c !important; }
        .border-primary { border-color: #274c77 !important; }
        .border-info { border-color: #4a7c9b !important; }
        .border-warning { border-color: #b78938 !important; }
        .border-success { border-color: #2d6a4f !important; }

        /* Badges en tonos pastel suaves (mucho más descansados de leer) */
        .badge { font-weight: 600; padding: 0.42em 0.72em; border-radius: 6px; letter-spacing: 0.01em; }
        .badge.bg-primary { background-color: #e2ecf7 !important; color: #1e3f66 !important; border: 1px solid #c3d7ec; }
        .badge.bg-success { background-color: #e2f2ea !important; color: #1b4d37 !important; border: 1px solid #b9dfc9; }
        .badge.bg-warning { background-color: #fbf1da !important; color: #6e4b10 !important; border: 1px solid #e9d29d; }
        .badge.bg-danger  { background-color: #fae6e6 !important; color: #822222 !important; border: 1px solid #e8bdbd; }
        .badge.bg-secondary { background-color: #edf1f5 !important; color: #475569 !important; border: 1px solid #d5dde6; }
        .badge.bg-dark { background-color: #334155 !important; color: #f8fafc !important; }

        /* Cabeceras de tabla sobrias (pizarra en vez de negro puro) */
        .table-dark {
            --bs-table-bg: #24344d !important;
            --bs-table-color: #f1f5f9 !important;
            --bs-table-border-color: #334663 !important;
        }
        .table { border-color: var(--border-col) !important; }

        /* Ajustes Modo Oscuro descansado */
        html[data-theme="dark"] .table { color: #dbe4f0 !important; border-color: #2b3b52 !important; }
        html[data-theme="dark"] .table-light, html[data-theme="dark"] .bg-light { background-color: #203047 !important; color: #e2e8f0 !important; }
        html[data-theme="dark"] .form-control, html[data-theme="dark"] .form-select {
            background-color: #101a2c !important; border-color: #31445e !important; color: #e2e8f0 !important;
        }
        html[data-theme="dark"] .modal-content { background-color: #182334 !important; color: #e2e8f0 !important; border: 1px solid #2b3b52 !important; }
        html[data-theme="dark"] .text-secondary, html[data-theme="dark"] .text-muted { color: #94a3b8 !important; }
        html[data-theme="dark"] .text-primary { color: #7db0e8 !important; }
        html[data-theme="dark"] .text-info { color: #7dc4e4 !important; }
        html[data-theme="dark"] .text-warning { color: #e5b567 !important; }
        html[data-theme="dark"] .text-success { color: #68b892 !important; }
        html[data-theme="dark"] .btn-outline-primary,
        html[data-theme="dark"] .btn-outline-info,
        html[data-theme="dark"] .btn-outline-warning,
        html[data-theme="dark"] .btn-outline-success { color: #cbd5e1 !important; border-color: #3b4f6b !important; }

        .nav-section { font-size: 0.68rem; text-transform: uppercase; letter-spacing: 0.08em; color: #8fa6c2; padding: 8px 16px 2px; font-weight: 700; }
        .sidebar a.nav-link { color: #cbd5e1; padding: 6px 14px; border-radius: 7px; margin: 1px 10px; font-size: 0.83rem; display: flex; align-items: center; gap: 10px; text-decoration: none; transition: all 0.15s; }
        .sidebar a.nav-link:hover { background: var(--sidebar-hover); color: #fff; }
        .sidebar a.nav-link.active { background: var(--sidebar-active); color: #fff; font-weight: 600; }

        .theme-toggle-btn {
            border-radius: 8px; font-size: 0.79rem; font-weight: 600; padding: 5px 12px;
            background: #eef2f6; color: #1e293b; border: 1px solid #cbd5e1; cursor: pointer;
            display: inline-flex; align-items: center; gap: 6px;
        }
        html[data-theme="dark"] .theme-toggle-btn {
            background: #24344d; color: #e2e8f0; border-color: #3b4f6b;
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
            .print-only-header { display: flex !important; align-items: center; justify-content: space-between; border-bottom: 2px solid #1e293b; padding-bottom: 10px; margin-bottom: 15px; }
            .card { border: 1px solid #94a3b8 !important; box-shadow: none !important; background: #fff !important; color: #000 !important; }
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
                : '<i class="fa-solid fa-moon"></i> Modo Oscuro';
        }
        document.addEventListener('DOMContentLoaded', updateThemeBtnLabel);
    </script>
</head>
<body>
<div class="sidebar d-flex flex-column justify-content-between">
    <div>
        <div class="p-3 d-flex align-items-center gap-2 border-bottom" style="border-color:rgba(255,255,255,0.08)!important;">
            <img src="<?= baseUrl('assets/img/logo.png') ?>" alt="Escudo IESTPU" style="width:38px;height:44px;background:#fff;padding:3px;border-radius:7px;">
            <div>
                <div class="fw-bold text-white" style="font-size:0.94rem;line-height:1.15;">SoporteTécnico</div>
                <div style="font-size:0.71rem;font-weight:600;color:#93c5fd;">IESTP UTCUBAMBA</div>
            </div>
        </div>
        <div class="p-2 mx-2 my-2 rounded" style="background:rgba(255,255,255,0.05);border:1px solid rgba(255,255,255,0.08);">
            <div class="fw-semibold text-white" style="font-size:0.79rem;"><?= htmlspecialchars($u['nombres']) ?></div>
            <span class="badge mt-1" style="font-size:0.67rem;background:#243754;color:#bfdbfe;border:1px solid #355078;"><?= htmlspecialchars(strtoupper($u['rol'])) ?> · <?= htmlspecialchars($u['programa']) ?></span>
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
        <a href="<?= baseUrl('admin/reportes.php') ?>" class="nav-link <?= $activeMenu==='reportes'?'active':'' ?>"><i class="fa-solid fa-chart-pie fa-fw"></i> Reportes (PDF / Excel)</a>
        <div class="nav-section">Seguridad y Cuentas</div>
        <a href="<?= baseUrl('admin/usuarios.php') ?>" class="nav-link <?= $activeMenu==='usuarios'?'active':'' ?>"><i class="fa-solid fa-users-gear fa-fw"></i> Usuarios y Contraseñas</a>
    </div>
    <div class="p-2 px-3 border-top" style="border-color:rgba(255,255,255,0.08)!important;">
        <a href="<?= baseUrl('logout.php') ?>" class="btn btn-sm w-100 fw-semibold" style="font-size:0.80rem;background:rgba(155,44,44,0.22);color:#fecaca;border:1px solid rgba(252,165,165,0.35);"><i class="fa-solid fa-right-from-bracket me-1"></i> Cerrar Sesión</a>
    </div>
</div>
<div class="main-content">
    <div class="top-utility-bar no-print">
        <div class="d-flex align-items-center gap-2">
            <span class="badge bg-success"><i class="fa-solid fa-circle-check me-1"></i> <?= htmlspecialchars($dbMode) ?></span>
            <span class="small text-secondary d-none d-md-inline">IESTP «Utcubamba» · Bagua Grande 2026</span>
        </div>
        <div class="d-flex align-items-center gap-2">
            <button type="button" id="btnThemeToggle" class="theme-toggle-btn" onclick="toggleThemeMode()">
                <i class="fa-solid fa-moon"></i> Modo Oscuro
            </button>
            <a href="<?= baseUrl('admin/buscar_equipo.php') ?>" class="btn btn-outline-primary btn-sm" style="border-radius:8px;font-size:0.78rem;">
                <i class="fa-solid fa-magnifying-glass me-1"></i> Buscar Código / Serie
            </a>
            <a href="<?= baseUrl('logout.php') ?>" class="btn btn-outline-danger btn-sm" style="border-radius:8px;font-size:0.78rem;">
                <i class="fa-solid fa-right-from-bracket me-1"></i> Cerrar Sesión
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
