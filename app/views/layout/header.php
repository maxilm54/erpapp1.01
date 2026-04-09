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

<a class="navbar-brand" href="<?= BASE_URL ?>">TRIBA APP</a>

<button class="navbar-toggler" type="button"
 data-bs-toggle="collapse" data-bs-target="#menu">
 <span class="navbar-toggler-icon"></span>
</button>

<div class="collapse navbar-collapse" id="menu">
<ul class="navbar-nav me-auto">

<!-- ABM -->
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
    <!-- <li><a class="dropdown-item" href="<?= BASE_URL ?>/stock">Stock</a></li> -->
    <li><a class="dropdown-item" href="<?= BASE_URL ?>/ajustesstock">Movimientos</a></li>
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
CtaCte
</a>
<ul class="dropdown-menu">
<li><a class="dropdown-item" href="<?= BASE_URL ?>/ctacte">Movimientos</a></li>
</ul>
</li>

</ul>

<ul class="navbar-nav">
<li class="nav-item">
<a class="nav-link" href="<?= BASE_URL ?>/auth/logout">Salir</a>
</li>
</ul>

</div>
</div>
</nav>
<div class="container-fluid">
    <div class="page-container">