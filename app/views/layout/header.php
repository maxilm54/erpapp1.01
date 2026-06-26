<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title><?= $title ?? APP_NAME ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" type="image/png" href="<?= BASE_URL ?>/triba-logo.png?v=<?= time() ?>">

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <!-- Sweet alert -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <!-- css global -->
    <link href="<?= BASE_URL ?>/assets/css/app.css?v=<?= time() ?>" rel="stylesheet">
    <!-- charts js -->
     <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>

<body>
<nav class="navbar navbar-expand-lg navbar-dark" style="background: #14194a;">
<div class="container-fluid">


<?php if (Auth::hasTenant()): ?>
<span class="navbar-text text-light me-3 d-none d-md-inline-block">
    <i class="bi bi-building"></i> <?= htmlspecialchars(Auth::getTenantName()) ?>
</span>
<?php endif; ?>

<button class="navbar-toggler" type="button"
 data-bs-toggle="collapse" data-bs-target="#menu">
 <span class="navbar-toggler-icon"></span>
</button>

<div class="collapse navbar-collapse" id="menu">
<ul class="navbar-nav me-auto">

<!-- ABM -->
<?php if (Auth::check()): ?>
<li class="nav-item dropdown">
<a class="nav-link dropdown-toggle" data-bs-toggle="dropdown" href="#">
ABM
</a>
<ul class="dropdown-menu">
<li><a class="dropdown-item" href="<?= BASE_URL ?>/clientes">Clientes</a></li>
<li><a class="dropdown-item" href="<?= BASE_URL ?>/proveedores">Proveedores</a></li>
<li><a class="dropdown-item" href="<?= BASE_URL ?>/productos">Productos</a></li>
<li><a class="dropdown-item" href="<?= BASE_URL ?>/materiasprimas">Materias primas</a></li>
</ul>
</li>
<?php endif; ?>

<!-- Más secciones con verificación de login -->
<?php if (Auth::check()): ?>
<!-- VENTAS -->
<li class="nav-item dropdown">
<a class="nav-link dropdown-toggle" data-bs-toggle="dropdown" href="#">
Ventas
</a>
<ul class="dropdown-menu">
<li><a class="dropdown-item" href="<?= BASE_URL ?>/presupuestos/create">Nuevo PR</a></li>
<li><a class="dropdown-item" href="<?= BASE_URL ?>/presupuestos">Presupuestos</a></li>
<li><a class="dropdown-item" href="<?= BASE_URL ?>/notaspedido/create">Nueva NP</a></li>
<li><a class="dropdown-item" href="<?= BASE_URL ?>/notaspedido">Notas de Pedidos</a></li>
<li><a class="dropdown-item" href="<?= BASE_URL ?>/remitossalida">Remitos de Salida</a></li>
</ul>
</li>
<!-- COMPRAS -->
<li class="nav-item dropdown">
<a class="nav-link dropdown-toggle" data-bs-toggle="dropdown" href="#">
Compras
</a>
<ul class="dropdown-menu">
<li><a class="dropdown-item" href="<?= BASE_URL ?>/ordenescompra/create">Nueva OC</a></li>
<li><a class="dropdown-item" href="<?= BASE_URL ?>/ordenescompra">Ordenes</a></li>
<li><a class="dropdown-item" href="<?= BASE_URL ?>/ingresosmercaderia">Ingresos</a></li>
<li><a class="dropdown-item" href="<?= BASE_URL ?>/ingresosmercaderia/showhist">Historial Ingresos</a></li>
</ul>
</li>
<!-- STOCK -->
<li class="nav-item dropdown">
<a class="nav-link dropdown-toggle" data-bs-toggle="dropdown" href="#">
Stock
</a>
<ul class="dropdown-menu">
    <li><a class="dropdown-item" href="<?= BASE_URL ?>/stock/productos">Stock Productos</a></li>
    <li><a class="dropdown-item" href="<?= BASE_URL ?>/stock/materiasprimas">Stock Materias Primas</a></li>
    <li><a class="dropdown-item" href="<?= BASE_URL ?>/ajustesstock">Movimientos</a></li>
    <li><a class="dropdown-item" href="<?= BASE_URL ?>/ajustesstock/producto">Aj Producto</a></li>
    <li><a class="dropdown-item" href="<?= BASE_URL ?>/ajustesstock/materiaprima">Aj Materia Prima</a></li>
</ul>
</li>
<!-- PRODUCCIÓN -->
<li class="nav-item dropdown">
<a class="nav-link dropdown-toggle" data-bs-toggle="dropdown" href="#">
Producción
</a>
<ul class="dropdown-menu">
<li><a class="dropdown-item" href="<?= BASE_URL ?>/recetas">Recetas</a></li>
<li><a class="dropdown-item" href="<?= BASE_URL ?>/ordenproduccion">Producción</a></li>
</ul>
</li>

<!-- CtaCte -->
<li class="nav-item dropdown">
<a class="nav-link dropdown-toggle" data-bs-toggle="dropdown" href="#">
Movimientos
</a>
<ul class="dropdown-menu">
<li><a class="dropdown-item" href="<?= BASE_URL ?>/ctacte">Movimientos</a></li>
<li><a class="dropdown-item" href="<?= BASE_URL ?>/ctacte/cliente">Mov Cliente</a></li>
</ul>
</li>
<?php endif; ?>

<!-- EMPRESA (ADMIN + OPERARIO: ven su propia empresa) -->
<?php if (Auth::check() && Auth::hasTenant() && Auth::isEmpresaAdmin()): ?>
<li class="nav-item dropdown">
<a class="nav-link dropdown-toggle" data-bs-toggle="dropdown" href="#">
<i class="bi bi-building"></i> Empresa
</a>
<ul class="dropdown-menu">
<li><a class="dropdown-item" href="<?= BASE_URL ?>/admin/empresa"><i class="bi bi-info-circle"></i> Mi Empresa</a></li>
<li><a class="dropdown-item" href="<?= BASE_URL ?>/admin/empresa-users"><i class="bi bi-people"></i> Usuarios de mi Empresa</a></li>
<li><a class="dropdown-item" href="<?= BASE_URL ?>/admin/empresa-create-user"><i class="bi bi-person-plus"></i> Nuevo Usuario</a></li>
</ul>
</li>
<?php endif; ?>

<!-- ADMIN (solo superadmins: gestión global de tenants + usuarios) -->
<?php if (Auth::check() && Auth::isSuperAdmin()): ?>
<li class="nav-item dropdown">
<a class="nav-link dropdown-toggle" data-bs-toggle="dropdown" href="#">
<i class="bi bi-gear"></i> Admin
</a>
<ul class="dropdown-menu">
<li><a class="dropdown-item" href="<?= BASE_URL ?>/admin"><i class="bi bi-building"></i> Empresas (Tenants)</a></li>
<li><a class="dropdown-item" href="<?= BASE_URL ?>/admin/create"><i class="bi bi-plus-lg"></i> Nueva Empresa</a></li>
<li><hr class="dropdown-divider"></li>
<li><a class="dropdown-item" href="<?= BASE_URL ?>/users"><i class="bi bi-people"></i> Todos los Usuarios</a></li>
<li><a class="dropdown-item" href="<?= BASE_URL ?>/users/create"><i class="bi bi-person-plus"></i> Nuevo Usuario Global</a></li>
<li><hr class="dropdown-divider"></li>
<li><a class="dropdown-item" href="<?= BASE_URL ?>/admin/migrations"><i class="bi bi-database"></i> Migraciones</a></li>
<li><a class="dropdown-item" href="<?= BASE_URL ?>/admin/select-tenant"><i class="bi bi-arrow-left-right"></i> Cambiar Empresa</a></li>
</ul>
</li>
<?php endif; ?>

<ul class="navbar-nav">
<?php if (Auth::check()): ?>
<li class="nav-item dropdown">
<a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
<i class="bi bi-person-circle"></i>
<?php $u = Auth::getCurrentUser(); echo htmlspecialchars($u['nombre'] ?? 'Usuario'); ?>
</a>
<ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
<li><a class="dropdown-item" href="<?= BASE_URL ?>/perfil">Mi Perfil</a></li>
<li><hr class="dropdown-divider"></li>
<li><a class="dropdown-item" href="<?= BASE_URL ?>/auth/logout">Salir</a></li>
</ul>
</li>
<?php endif; ?>
</ul>

</div>
</div>
</nav>
<div class="container-fluid">
<div class="page-container">