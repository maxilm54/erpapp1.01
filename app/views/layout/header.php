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
    <?php
    $logoPath = empresaLogoPath();
    if ($logoPath && file_exists($logoPath)):
    ?>
        <img src="<?= empresaUploadUrl('img_config') ?>/<?= htmlspecialchars(config('empresa')['logo'] ?? '') ?>"
             alt="Logo" style="height: 30px; margin-right: 5px; vertical-align: middle;">
    <?php else: ?>
        <i class="bi bi-building"></i>
    <?php endif; ?>
    <?= htmlspecialchars(Auth::getTenantName()) ?>
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
<li><hr class="dropdown-divider"></li>
<li><a class="dropdown-item" href="<?= BASE_URL ?>/unidadmedida">Unidades de Medida</a></li>
<li><a class="dropdown-item" href="<?= BASE_URL ?>/categoriamaterial">Categorías MP</a></li>
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
<li><a class="dropdown-item" href="<?= BASE_URL ?>/remitossalida/create-manual"><i class="bi bi-plus-lg"></i> Remito Manual</a></li>
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
<!-- COBROS -->
<li class="nav-item dropdown">
<a class="nav-link dropdown-toggle" data-bs-toggle="dropdown" href="#">
<i class="bi bi-cash-stack"></i> Cobros
</a>
<ul class="dropdown-menu">
<li><a class="dropdown-item" href="<?= BASE_URL ?>/cobros"><i class="bi bi-list-ul"></i> Cobros</a></li>
<li><a class="dropdown-item" href="<?= BASE_URL ?>/cobros/create"><i class="bi bi-plus-lg"></i> Nuevo Cobro</a></li>
<li><hr class="dropdown-divider"></li>
<li><a class="dropdown-item" href="<?= BASE_URL ?>/cobros/ventas-no-cobradas"><i class="bi bi-exclamation-triangle"></i> Ventas No Cobradas</a></li>
</ul>
</li>
<!-- CONTABILIDAD -->
<li class="nav-item dropdown">
<a class="nav-link dropdown-toggle" data-bs-toggle="dropdown" href="#">
<i class="bi bi-wallet2"></i> Contabilidad
</a>
<ul class="dropdown-menu">
<li><a class="dropdown-item" href="<?= BASE_URL ?>/gastos/dashboard"><i class="bi bi-graph-up"></i> Dashboard Gastos</a></li>
<li><a class="dropdown-item" href="<?= BASE_URL ?>/gastos"><i class="bi bi-list-ul"></i> Gastos</a></li>
<li><a class="dropdown-item" href="<?= BASE_URL ?>/gastos/create"><i class="bi bi-plus-lg"></i> Nuevo Gasto</a></li>
<li><hr class="dropdown-divider"></li>
<li><a class="dropdown-item" href="<?= BASE_URL ?>/contabilidad/asientos"><i class="bi bi-journal-text"></i> Libro Diario</a></li>
<li><a class="dropdown-item" href="<?= BASE_URL ?>/contabilidad/asientos/create"><i class="bi bi-plus-circle"></i> Nuevo Asiento</a></li>
<li><a class="dropdown-item" href="<?= BASE_URL ?>/contabilidad/plan-cuentas"><i class="bi bi-diagram-3"></i> Plan de Cuentas</a></li>
<li><hr class="dropdown-divider"></li>
<li><a class="dropdown-item" href="<?= BASE_URL ?>/contabilidad/cajas"><i class="bi bi-bank"></i> Cajas y Bancos</a></li>
<li><a class="dropdown-item" href="<?= BASE_URL ?>/creditos/dashboard"><i class="bi bi-cash-stack"></i> Dashboard Creditos</a></li>
<li><a class="dropdown-item" href="<?= BASE_URL ?>/creditos"><i class="bi bi-bank"></i> Creditos Bancarios</a></li>
<li><a class="dropdown-item" href="<?= BASE_URL ?>/contabilidad/conciliacion"><i class="bi bi-check2-all"></i> Conciliacion</a></li>
<li><hr class="dropdown-divider"></li>
<li><a class="dropdown-item" href="<?= BASE_URL ?>/contabilidad/balance"><i class="bi bi-balance-scale"></i> Balance General</a></li>
<li><a class="dropdown-item" href="<?= BASE_URL ?>/contabilidad/resultados"><i class="bi bi-graph-up-arrow"></i> Estado de Resultados</a></li>
<li><hr class="dropdown-divider"></li>
<li><a class="dropdown-item" href="<?= BASE_URL ?>/contabilidad/impuestos"><i class="bi bi-receipt"></i> Impuestos (IVA)</a></li>
</ul>
</li>
<!-- SDCOMP (solo ADMIN y GERENTE_FINANCIERO) -->
<?php if (Auth::check() && Auth::canSeeSdcomp()): ?>
<li class="nav-item dropdown">
<a class="nav-link dropdown-toggle" data-bs-toggle="dropdown" href="#">
<i class="bi bi-file-earmark-text"></i> Comprobantes
</a>
<ul class="dropdown-menu">
<li><a class="dropdown-item" href="<?= BASE_URL ?>/sdcomp/dashboard"><i class="bi bi-speedometer2"></i> Dashboard</a></li>
<li><a class="dropdown-item" href="<?= BASE_URL ?>/sdcomp"><i class="bi bi-list-ul"></i> Comprobantes</a></li>
<li><a class="dropdown-item" href="<?= BASE_URL ?>/sdcomp/create"><i class="bi bi-plus-lg"></i> Nuevo Comprobante</a></li>
</ul>
</li>
<?php endif; ?>
<?php endif; ?>

<!-- EMPRESA (solo tenant admins: gestion de su empresa) -->
<?php if (Auth::check() && Auth::hasTenant() && !Auth::isSuperAdmin() && Auth::isEmpresaAdmin()): ?>
<li class="nav-item dropdown">
<a class="nav-link dropdown-toggle" data-bs-toggle="dropdown" href="#">
Empresa
</a>
<ul class="dropdown-menu">
<li><a class="dropdown-item" href="<?= BASE_URL ?>/empresa"><i class="bi bi-info-circle"></i> Mi Empresa</a></li>
<li><a class="dropdown-item" href="<?= BASE_URL ?>/empresa/edit"><i class="bi bi-pencil"></i> Editar Empresa</a></li>
<li><hr class="dropdown-divider"></li>
<li><a class="dropdown-item" href="<?= BASE_URL ?>/empresa/users"><i class="bi bi-people"></i> Usuarios de mi Empresa</a></li>
<li><a class="dropdown-item" href="<?= BASE_URL ?>/empresa/create-user"><i class="bi bi-person-plus"></i> Nuevo Usuario</a></li>
<li><hr class="dropdown-divider"></li>
<li><a class="dropdown-item" href="<?= BASE_URL ?>/numeradores"><i class="bi bi-list-ol"></i> Numeradores</a></li>
<li><hr class="dropdown-divider"></li>
<li><a class="dropdown-item" href="<?= BASE_URL ?>/email/config"><i class="bi bi-envelope-gear"></i> Config Email (SMTP)</a></li>
<li><a class="dropdown-item" href="<?= BASE_URL ?>/email/templates"><i class="bi bi-envelope-paper"></i> Templates Email</a></li>
<li><a class="dropdown-item" href="<?= BASE_URL ?>/email/historial"><i class="bi bi-clock-history"></i> Historial Envíos</a></li>
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
<?php if (Auth::isSuperAdmin()): ?>
<li><a class="dropdown-item" href="<?= BASE_URL ?>/admin"><i class="bi bi-shield-lock"></i> Panel Admin</a></li>
<?php endif; ?>
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