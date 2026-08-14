<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title><?= $title ?? 'Panel Admin' ?> - Admin</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" type="image/png" href="<?= BASE_URL ?>/triba-logo.png?v=<?= time() ?>">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link href="<?= BASE_URL ?>/assets/css/app.css?v=<?= time() ?>" rel="stylesheet">
    <style>
        .admin-sidebar {
            min-height: 100vh;
            background: #1a1f36;
            width: 250px;
            position: fixed;
            top: 0;
            left: 0;
            z-index: 100;
            padding-top: 0;
        }
        .admin-sidebar .sidebar-brand {
            padding: 20px;
            background: #0f1225;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        .admin-sidebar .sidebar-brand h5 {
            color: #fff;
            margin: 0;
            font-weight: 600;
        }
        .admin-sidebar .sidebar-brand small {
            color: rgba(255,255,255,0.5);
            font-size: 11px;
        }
        .admin-sidebar .nav-link {
            color: rgba(255,255,255,0.7);
            padding: 12px 20px;
            border-left: 3px solid transparent;
            transition: all 0.2s;
        }
        .admin-sidebar .nav-link:hover {
            color: #fff;
            background: rgba(255,255,255,0.05);
        }
        .admin-sidebar .nav-link.active {
            color: #fff;
            background: rgba(255,255,255,0.1);
            border-left-color: #4f8cff;
        }
        .admin-sidebar .nav-link i {
            width: 20px;
            margin-right: 10px;
        }
        .admin-sidebar .section-title {
            color: rgba(255,255,255,0.4);
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 1px;
            padding: 20px 20px 5px;
        }
        .admin-content {
            margin-left: 250px;
            background: #f5f7fa;
            min-height: 100vh;
        }
        .admin-topbar {
            background: #fff;
            padding: 15px 30px;
            border-bottom: 1px solid #e8ecf1;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .admin-topbar .badge-admin {
            background: #dc3545;
            color: #fff;
            font-size: 10px;
            padding: 4px 8px;
            border-radius: 4px;
            font-weight: 600;
        }
        .admin-body {
            padding: 30px;
        }
    </style>
</head>
<body>
<div class="d-flex">
    <!-- Sidebar -->
    <nav class="admin-sidebar">
        <div class="sidebar-brand">
            <h6><i class="bi bi-shield-lock"></i> Panel Admin</h6>
            <small>Sistema de Gestion</small>
        </div>
        <div class="mt-3">
            <div class="section-title">Gestion</div>
            <a href="<?= BASE_URL ?>/admin" class="nav-link <?= ($_GET['url'] ?? '') === 'admin' || ($_GET['url'] ?? '') === '' ? 'active' : '' ?>">
                <i class="bi bi-speedometer2"></i> Dashboard
            </a>
            <a href="<?= BASE_URL ?>/admin/tenants" class="nav-link <?= str_starts_with($_GET['url'] ?? '', 'admin/tenants') ? 'active' : '' ?>">
                <i class="bi bi-building"></i> Empresas
            </a>
            <a href="<?= BASE_URL ?>/admin/all-users" class="nav-link <?= str_starts_with($_GET['url'] ?? '', 'admin/all-users') ? 'active' : '' ?>">
                <i class="bi bi-people"></i> Usuarios
            </a>

            <div class="section-title">Sistema</div>
            <a href="<?= BASE_URL ?>/admin/migrations" class="nav-link <?= str_starts_with($_GET['url'] ?? '', 'admin/migrations') ? 'active' : '' ?>">
                <i class="bi bi-database"></i> Migraciones
            </a>
        </div>

        <div class="mt-auto p-3" style="position: absolute; bottom: 0; width: 100%; border-top: 1px solid rgba(255,255,255,0.1);">
            <a href="<?= BASE_URL ?>/home" class="nav-link text-warning">
                <i class="bi bi-box-arrow-left"></i> Volver al Sistema
            </a>
            <a href="<?= BASE_URL ?>/auth/logout" class="nav-link text-danger">
                <i class="bi bi-power"></i> Cerrar Sesion
            </a>
        </div>
    </nav>

    <!-- Content -->
    <div class="admin-content flex-grow-1">
        <div class="admin-topbar">
            <div>
                <span class="badge-admin">SUPER ADMIN</span>
                <span class="ms-2 text-muted"><?= htmlspecialchars($title ?? 'Panel Admin') ?></span>
            </div>
            <div>
                <span class="text-muted me-3">
                    <i class="bi bi-person-circle"></i> <?= htmlspecialchars(Auth::getCurrentUser()['nombre'] ?? 'Admin') ?>
                </span>
            </div>
        </div>
        <div class="admin-body">
