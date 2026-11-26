<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';

function calcularSemaforo(string $fechaLimite, int $requiereMant = 1): array {
    if ($requiereMant === 0 || trim($fechaLimite) === '' || $fechaLimite === '0000-00-00') {
        return ['clase' => 'secondary', 'texto' => 'No Aplica', 'icono' => 'fa-minus-circle'];
    }
    try {
        $hoy = new DateTimeImmutable('today');
        $limite = new DateTimeImmutable($fechaLimite);
        $diff = (int)$hoy->diff($limite)->format('%r%a');
        if ($diff < 0) {
            return ['clase' => 'danger', 'texto' => 'Vencido (' . abs($diff) . ' d)', 'icono' => 'fa-exclamation-triangle'];
        }
        if ($diff <= 15) {
            return ['clase' => 'warning text-dark', 'texto' => 'Próximo (' . $diff . ' d)', 'icono' => 'fa-clock'];
        }
        return ['clase' => 'success', 'texto' => 'Vigente (' . $diff . ' d)', 'icono' => 'fa-check-circle'];
    } catch (Throwable $e) {
        return ['clase' => 'secondary', 'texto' => 'Sin fecha', 'icono' => 'fa-calendar'];
    }
}

function renderHeader(string $titulo, string $activeMenu = 'inicio'): void {
    $u = require_login();
    $rol = get_user_role($u);
    $scope = get_user_program_scope($u);
    $nombreUsuario = (string)($u['nombres'] ?? 'Usuario');
    $programaLabel = $scope ? $scope : ($rol === 'tecnico' ? 'SOPORTE' : 'TODOS');

    // Construir menú estrictamente según matriz RBAC
    $menuSections = [];

    if ($rol === 'admin') {
        $menuSections[] = [
            'label' => 'PRINCIPAL',
            'items' => [
                ['id' => 'inicio', 'label' => 'Panel Principal', 'icon' => 'fa-gauge-high', 'href' => baseUrl('admin/index.php')],
            ],
        ];
        $menuSections[] = [
            'label' => 'INVENTARIO Y BIENES',
            'items' => [
                ['id' => 'equipos', 'label' => 'Inventario Activo (BD)', 'icon' => 'fa-boxes-stacked', 'href' => baseUrl('admin/equipos.php')],
                ['id' => 'inv_apsti', 'label' => 'Inventariar APSTI', 'icon' => 'fa-desktop', 'href' => baseUrl('admin/inventariar_apsti.php')],
                ['id' => 'cafetin', 'label' => 'Módulo Cafetín', 'icon' => 'fa-mug-hot', 'href' => baseUrl('admin/cafetin.php')],
                ['id' => 'buscar', 'label' => 'Buscar por Código/Serie', 'icon' => 'fa-magnifying-glass', 'href' => baseUrl('admin/buscar_equipo.php')],
                ['id' => 'movimientos', 'label' => 'Traslados y Préstamos', 'icon' => 'fa-right-left', 'href' => baseUrl('admin/movimientos.php')],
            ],
        ];
        $menuSections[] = [
            'label' => 'FLUJO DE MANTENIMIENTO',
            'items' => [
                ['id' => 'reportar', 'label' => 'Paso 1: Reportar Falla', 'icon' => 'fa-triangle-exclamation', 'href' => baseUrl('docente/reportar.php')],
                ['id' => 'verificar', 'label' => 'Paso 2: Verificar + Ficha', 'icon' => 'fa-clipboard-check', 'href' => baseUrl('admin/verificacion_admin.php')],
                ['id' => 'tecnico', 'label' => 'Paso 3: Área Técnica', 'icon' => 'fa-screwdriver-wrench', 'href' => baseUrl('tecnico/trabajos.php')],
                ['id' => 'cronograma', 'label' => 'Cronograma Preventivo', 'icon' => 'fa-calendar-days', 'href' => baseUrl('admin/cronograma.php')],
                ['id' => 'reportes', 'label' => 'Reportes (PDF / Excel)', 'icon' => 'fa-chart-pie', 'href' => baseUrl('admin/reportes.php')],
            ],
        ];
        $menuSections[] = [
            'label' => 'SEGURIDAD Y AUDITORÍA',
            'items' => [
                ['id' => 'usuarios', 'label' => 'Gestión de Usuarios', 'icon' => 'fa-users-gear', 'href' => baseUrl('admin/usuarios.php')],
                ['id' => 'auditoria', 'label' => 'Bitácora de Auditoría', 'icon' => 'fa-shield-halved', 'href' => baseUrl('admin/auditoria.php')],
            ],
        ];
    } elseif ($rol === 'coordinador_apsti') {
        $menuSections[] = [
            'label' => 'MI ÁREA · APSTI',
            'items' => [
                ['id' => 'inicio', 'label' => 'Panel APSTI', 'icon' => 'fa-gauge-high', 'href' => baseUrl('admin/index.php')],
                ['id' => 'equipos', 'label' => 'Inventario APSTI', 'icon' => 'fa-boxes-stacked', 'href' => baseUrl('admin/equipos.php')],
                ['id' => 'inv_apsti', 'label' => 'Inventariar APSTI', 'icon' => 'fa-desktop', 'href' => baseUrl('admin/inventariar_apsti.php')],
                ['id' => 'buscar', 'label' => 'Buscar Equipo APSTI', 'icon' => 'fa-magnifying-glass', 'href' => baseUrl('admin/buscar_equipo.php')],
            ],
        ];
        $menuSections[] = [
            'label' => 'SOLICITUDES Y REPORTES',
            'items' => [
                ['id' => 'reportar', 'label' => 'Reportar Falla (Paso 1)', 'icon' => 'fa-triangle-exclamation', 'href' => baseUrl('docente/reportar.php')],
                ['id' => 'cronograma', 'label' => 'Cronograma APSTI', 'icon' => 'fa-calendar-days', 'href' => baseUrl('admin/cronograma.php')],
                ['id' => 'reportes', 'label' => 'Reportes APSTI', 'icon' => 'fa-chart-pie', 'href' => baseUrl('admin/reportes.php')],
            ],
        ];
    } elseif (in_array($rol, ['coordinador_cont', 'coordinador_enfe', 'coordinador_agro'], true)) {
        $areaNombre = match ($scope) {
            'CONT' => 'Contabilidad',
            'ENFE' => 'Enfermería',
            'AGRO' => 'Prod. Agropecuaria',
            default => (string)$scope,
        };
        $menuSections[] = [
            'label' => 'MI ÁREA · ' . strtoupper($areaNombre),
            'items' => [
                ['id' => 'inicio', 'label' => 'Panel ' . $areaNombre, 'icon' => 'fa-gauge-high', 'href' => baseUrl('admin/index.php')],
                ['id' => 'equipos', 'label' => 'Inventario ' . $areaNombre, 'icon' => 'fa-boxes-stacked', 'href' => baseUrl('admin/equipos.php')],
                ['id' => 'buscar', 'label' => 'Buscar Equipo', 'icon' => 'fa-magnifying-glass', 'href' => baseUrl('admin/buscar_equipo.php')],
            ],
        ];
        $menuSections[] = [
            'label' => 'SOLICITUDES Y REPORTES',
            'items' => [
                ['id' => 'reportar', 'label' => 'Reportar Falla (Paso 1)', 'icon' => 'fa-triangle-exclamation', 'href' => baseUrl('docente/reportar.php')],
                ['id' => 'cronograma', 'label' => 'Cronograma ' . $areaNombre, 'icon' => 'fa-calendar-days', 'href' => baseUrl('admin/cronograma.php')],
                ['id' => 'reportes', 'label' => 'Reportes ' . $areaNombre, 'icon' => 'fa-chart-pie', 'href' => baseUrl('admin/reportes.php')],
            ],
        ];
    } elseif ($rol === 'tecnico') {
        $menuSections[] = [
            'label' => 'ÁREA TÉCNICA',
            'items' => [
                ['id' => 'tecnico', 'label' => 'Órdenes Técnicas (Paso 3)', 'icon' => 'fa-screwdriver-wrench', 'href' => baseUrl('tecnico/trabajos.php')],
                ['id' => 'buscar', 'label' => 'Buscar / Consultar Equipo', 'icon' => 'fa-magnifying-glass', 'href' => baseUrl('admin/buscar_equipo.php')],
                ['id' => 'cronograma', 'label' => 'Cronograma Preventivo', 'icon' => 'fa-calendar-days', 'href' => baseUrl('admin/cronograma.php')],
            ],
        ];
    }
    ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($titulo) ?> — SoporteTécnico UTCUBAMBA</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --petrol: #0f3b3c;
            --petrol-dark: #0a2829;
            --emerald: #10b981;
            --emerald-dark: #059669;
            --canvas: #f3f6f8;
            --surface: #ffffff;
            --border-soft: #e2e8f0;
            --text-main: #0f172a;
            --text-muted: #64748b;
        }
        body {
            background: var(--canvas);
            color: var(--text-main);
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            min-height: 100vh;
            margin: 0;
        }
        .app-shell { display: flex; min-height: 100vh; }
        .app-sidebar {
            width: 262px;
            flex-shrink: 0;
            background: linear-gradient(180deg, var(--petrol) 0%, var(--petrol-dark) 100%);
            color: #e2e8f0;
            display: flex;
            flex-direction: column;
            border-right: 3px solid var(--emerald);
            position: sticky;
            top: 0;
            height: 100vh;
            overflow-y: auto;
        }
        .sidebar-brand {
            padding: 18px 16px;
            border-bottom: 1px solid rgba(255,255,255,.1);
            display: flex;
            align-items: center;
            gap: 11px;
        }
        .sidebar-brand img {
            width: 40px; height: 40px; border-radius: 9px;
            background: #fff; padding: 3px; object-fit: contain;
        }
        .sidebar-user {
            margin: 12px 14px 6px;
            padding: 10px 12px;
            border-radius: 10px;
            background: rgba(255,255,255,.07);
            border: 1px solid rgba(16,185,129,.22);
        }
        .sidebar-section-label {
            font-size: .68rem;
            font-weight: 700;
            letter-spacing: .08em;
            color: rgba(167,243,208,.75);
            padding: 12px 16px 4px;
            text-transform: uppercase;
        }
        .sidebar-link {
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 2px 10px;
            padding: 9px 12px;
            border-radius: 9px;
            color: #cbd5e1;
            text-decoration: none;
            font-size: .88rem;
            font-weight: 500;
            transition: all .15s ease;
        }
        .sidebar-link i { width: 18px; text-align: center; font-size: .9rem; }
        .sidebar-link:hover { background: rgba(255,255,255,.1); color: #fff; }
        .sidebar-link.active {
            background: linear-gradient(90deg, var(--emerald-dark), var(--emerald));
            color: #fff;
            font-weight: 600;
            box-shadow: 0 4px 12px rgba(16,185,129,.28);
        }
        .app-main { flex: 1; min-width: 0; display: flex; flex-direction: column; }
        .app-topbar {
            background: var(--surface);
            border-bottom: 1px solid var(--border-soft);
            padding: 10px 22px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            position: sticky;
            top: 0;
            z-index: 1020;
        }
        .app-content { padding: 22px; flex: 1; }
        .card-clean {
            background: var(--surface);
            border: 1px solid var(--border-soft);
            border-radius: 14px;
            box-shadow: 0 2px 10px rgba(15,23,42,.04);
        }
        .kpi-card {
            background: var(--surface);
            border: 1px solid var(--border-soft);
            border-left: 4px solid var(--emerald);
            border-radius: 13px;
            padding: 16px 18px;
            box-shadow: 0 2px 8px rgba(15,23,42,.04);
            height: 100%;
        }
        /* Estilo unificado de tablas con cuadrícula ordenada (como Reportes) en todos los módulos */
        .table-grid {
            width: 100%;
            border-collapse: collapse !important;
            border: 1px solid #94a3b8 !important;
            background: #ffffff;
            font-size: .82rem;
        }
        .table thead th,
        .table-grid thead th {
            background: var(--petrol) !important;
            color: #fff !important;
            font-size: .75rem !important;
            font-weight: 700 !important;
            text-transform: uppercase;
            letter-spacing: .02em;
            border: 1px solid #1b5254 !important;
            padding: 8px 8px !important;
            vertical-align: middle !important;
        }
        .table tbody td,
        .table-grid tbody td {
            border: 1px solid #cbd5e1 !important;
            padding: 7px 8px !important;
            vertical-align: middle !important;
        }
        .table tbody tr:nth-child(even) td {
            background-color: #f8fafc;
        }
        .table-hover tbody tr:hover td {
            background-color: #ecfdf5 !important;
        }
        .step-card {
            background: var(--surface);
            border: 1px solid #cbd5e1;
            border-left: 5px solid var(--petrol);
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(15,23,42,.05);
            overflow: hidden;
        }
        .step-card.is-pending { border-left-color: #f59e0b; }
        .step-card.is-verified { border-left-color: #0ea5e9; }
        .step-card.is-closed { border-left-color: #10b981; }
        .step-section-box {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 9px;
            padding: 12px 14px;
        }
        body.dark-mode .step-section-box {
            background: #0e1b22 !important;
            border-color: #243b49 !important;
        }
        body.dark-mode .table-grid { border-color: #243b49 !important; }
        .nowrap-col { white-space: nowrap !important; }
        .btn-petrol { background: var(--petrol); color: #fff; border: none; }
        .btn-petrol:hover { background: var(--petrol-dark); color: #fff; }
        .btn-emerald { background: var(--emerald-dark); color: #fff; border: none; }
        .btn-emerald:hover { background: #047857; color: #fff; }

        /* Modo Oscuro completo y legible */
        body.dark-mode {
            --canvas: #0b161c;
            --surface: #13222b;
            --border-soft: #223845;
            --text-main: #e2e8f0;
            --text-muted: #94a3b8;
            background: var(--canvas);
            color: var(--text-main);
        }
        body.dark-mode .app-topbar,
        body.dark-mode .card,
        body.dark-mode .card-clean,
        body.dark-mode .kpi-card,
        body.dark-mode .modal-content {
            background-color: var(--surface) !important;
            color: var(--text-main) !important;
            border-color: var(--border-soft) !important;
        }
        body.dark-mode .bg-white,
        body.dark-mode .bg-light {
            background-color: #182b36 !important;
            color: var(--text-main) !important;
        }
        body.dark-mode .text-dark,
        body.dark-mode .text-body { color: #f1f5f9 !important; }
        body.dark-mode .text-muted { color: #94a3b8 !important; }
        body.dark-mode .table {
            --bs-table-bg: #13222b;
            --bs-table-color: #e2e8f0;
            --bs-table-striped-bg: #172a35;
            --bs-table-striped-color: #e2e8f0;
            --bs-table-hover-bg: #1d3543;
            --bs-table-hover-color: #ffffff;
            --bs-table-border-color: #243b49;
            color: #e2e8f0 !important;
        }
        body.dark-mode .table tbody tr,
        body.dark-mode .table tbody td {
            background-color: #13222b !important;
            color: #e2e8f0 !important;
            border-color: #243b49 !important;
        }
        body.dark-mode .table-hover tbody tr:hover td {
            background-color: #1c3340 !important;
        }
        body.dark-mode .form-control,
        body.dark-mode .form-select,
        body.dark-mode textarea {
            background-color: #0e1b22 !important;
            color: #f1f5f9 !important;
            border-color: #2d4857 !important;
        }
        body.dark-mode .form-control::placeholder,
        body.dark-mode textarea::placeholder { color: #64748b !important; }
        body.dark-mode .btn-outline-secondary,
        body.dark-mode .btn-outline-dark {
            color: #cbd5e1 !important;
            border-color: #3a5566 !important;
        }
        @media (max-width: 991.98px) {
            .app-sidebar {
                position: fixed; left: -270px; top: 0; bottom: 0; z-index: 1050;
                transition: left .22s ease;
            }
            .app-sidebar.open { left: 0; }
        }
    </style>
</head>
<body>
<script>
    if (localStorage.getItem('iestpu_theme') === 'dark') {
        document.body.classList.add('dark-mode');
    }
</script>
<div class="app-shell">
    <aside class="app-sidebar" id="appSidebar">
        <div class="sidebar-brand">
            <img src="<?= h(baseUrl('assets/img/logo.png')) ?>" alt="Escudo IESTPU">
            <div>
                <div class="fw-bold text-white" style="font-size:.95rem;line-height:1.15;">SoporteTécnico</div>
                <div style="font-size:.7rem;color:#a7f3d0;letter-spacing:.05em;">IESTP UTCUBAMBA · 2026</div>
            </div>
        </div>
        <div class="sidebar-user">
            <div class="fw-semibold text-white text-truncate" style="font-size:.82rem;" title="<?= h($nombreUsuario) ?>">
                <?= h($nombreUsuario) ?>
            </div>
            <div class="mt-1 d-flex align-items-center gap-1 flex-wrap">
                <span class="badge bg-success bg-opacity-25 text-white border border-success border-opacity-50" style="font-size:.66rem;">
                    <?= h(strtoupper($rol)) ?>
                </span>
                <span class="badge bg-light bg-opacity-10 text-light" style="font-size:.66rem;">
                    ÁREA: <?= h($programaLabel) ?>
                </span>
            </div>
        </div>

        <nav class="pb-3 flex-grow-1">
            <?php foreach ($menuSections as $sec): ?>
                <div class="sidebar-section-label"><?= h($sec['label']) ?></div>
                <?php foreach ($sec['items'] as $item): ?>
                    <a class="sidebar-link <?= $activeMenu === $item['id'] ? 'active' : '' ?>" href="<?= h($item['href']) ?>">
                        <i class="fa-solid <?= h($item['icon']) ?>"></i>
                        <span><?= h($item['label']) ?></span>
                    </a>
                <?php endforeach; ?>
            <?php endforeach; ?>
        </nav>

        <div class="p-3 border-top border-light border-opacity-10 small text-center" style="color:rgba(203,213,225,.7);font-size:.72rem;">
            IESTP «Utcubamba» · Bagua Grande<br>Sistema de Mantenimiento TAP 2026
        </div>
    </aside>

    <div class="app-main">
        <header class="app-topbar">
            <div class="d-flex align-items-center gap-2">
                <button class="btn btn-sm btn-outline-secondary d-lg-none" type="button" onclick="document.getElementById('appSidebar').classList.toggle('open')" aria-label="Menú">
                    <i class="fa-solid fa-bars"></i>
                </button>
                <div>
                    <div class="fw-bold" style="font-size:.98rem;line-height:1.15;"><?= h($titulo) ?></div>
                    <div class="text-muted" style="font-size:.75rem;">IESTP «Utcubamba» · Gestión Patrimonial y Soporte Técnico</div>
                </div>
            </div>
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <button type="button" class="btn btn-sm btn-outline-secondary" id="btnThemeToggle" onclick="toggleThemeMode()">
                    <i class="fa-solid fa-moon me-1"></i><span>Modo Oscuro</span>
                </button>
                <a href="<?= h(baseUrl('admin/buscar_equipo.php')) ?>" class="btn btn-sm btn-outline-secondary d-none d-md-inline-flex align-items-center gap-1">
                    <i class="fa-solid fa-magnifying-glass"></i><span>Buscar Código</span>
                </a>
                <a href="<?= h(baseUrl('logout.php')) ?>" class="btn btn-sm btn-outline-danger d-inline-flex align-items-center gap-1">
                    <i class="fa-solid fa-right-from-bracket"></i><span>Cerrar Sesión</span>
                </a>
            </div>
        </header>

        <main class="app-content">
<?php
}

function renderFooter(): void {
    ?>
        </main>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    function updateThemeBtnLabel() {
        var btn = document.getElementById('btnThemeToggle');
        if (!btn) return;
        var dark = document.body.classList.contains('dark-mode');
        btn.innerHTML = dark
            ? '<i class="fa-solid fa-sun me-1"></i><span>Modo Claro</span>'
            : '<i class="fa-solid fa-moon me-1"></i><span>Modo Oscuro</span>';
    }
    function toggleThemeMode() {
        document.body.classList.toggle('dark-mode');
        localStorage.setItem('iestpu_theme', document.body.classList.contains('dark-mode') ? 'dark' : 'light');
        updateThemeBtnLabel();
    }
    updateThemeBtnLabel();
</script>
</body>
</html>
<?php
}
