<?php
$buscar = $buscar ?? '';
$csrf = $csrf ?? Csrf::generate();
$inactivos = $inactivos ?? false;
$cantInactivos = $cantInactivos ?? 0;
$urlFiltro = $inactivos ? '?estado=inactivos' : '';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="mb-0">
        Productos
        <?php if ($inactivos): ?>
            <span class="badge bg-secondary fs-6">INACTIVOS</span>
        <?php endif; ?>
    </h3>
    <div>
        <a href="<?= BASE_URL ?>/productos/create" class="btn btn-primary">
            <i class="bi bi-plus-lg"></i> Nuevo Producto
        </a>
        <a href="<?= BASE_URL ?>/productos/import" class="btn btn-success">
            <i class="bi bi-upload"></i> Importar
        </a>
        <?php if ($inactivos): ?>
            <a href="<?= BASE_URL ?>/productos" class="btn btn-outline-primary">
                <i class="bi bi-arrow-left"></i> Ver activos
            </a>
        <?php else: ?>
            <a href="<?= BASE_URL ?>/productos?estado=inactivos" class="btn btn-outline-secondary">
                <i class="bi bi-archive"></i> Inactivos
                <span class="badge bg-secondary"><?= (int)$cantInactivos ?></span>
            </a>
        <?php endif; ?>
    </div>
</div>

<!-- Buscador: nombre, SKU, descripción, precio y código de barra -->
<div class="card mb-3">
    <div class="card-body py-2">
        <form method="get" action="<?= BASE_URL ?>/productos" class="row g-2 align-items-end">
            <?php if ($inactivos): ?>
                <input type="hidden" name="estado" value="inactivos">
            <?php endif; ?>
            <div class="col">
                <label class="form-label mb-0 small">Buscar</label>
                <input type="text" name="q" class="form-control form-control-sm"
                       placeholder="Nombre, SKU, descripción, precio o código de barra..."
                       value="<?= htmlspecialchars($buscar) ?>" autofocus>
            </div>
            <div class="col-auto">
                <button class="btn btn-primary btn-sm"><i class="bi bi-search"></i> Buscar</button>
                <?php if ($buscar !== ''): ?>
                    <a href="<?= BASE_URL ?>/productos<?= $urlFiltro ?>" class="btn btn-secondary btn-sm">Limpiar</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<?php if ($buscar !== ''): ?>
    <p class="text-muted small">
        <?= count($productos) ?> resultado(s) para "<?= htmlspecialchars($buscar) ?>".
    </p>
<?php endif; ?>

<div class="table-responsive table-scroll mt-3">
    <table class="table table-striped">
        <thead class="table-dark">
            <tr>
                <th>Imagen</th>
                <th>Nombre</th>
                <th>SKU</th>
                <th>Precio</th>
                <th class="text-end">Stock</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($productos)): ?>
                <tr><td colspan="6" class="text-center text-muted">
                    <?= $inactivos ? 'No hay productos inactivos' : 'No hay productos activos' ?><?= $buscar !== '' ? ' que coincidan con la búsqueda' : '' ?>.
                </td></tr>
            <?php endif; ?>
            <?php foreach ($productos as $p): ?>
            <?php
                $stock = (float)($p['stock_total'] ?? 0);
                $imgPath = $p['imagen'] ?? null;
                $imgUrl = $imgPath ? empresaUploadUrl('productos') . '/' . htmlspecialchars($imgPath) : empresaUploadUrl('productos') . '/sin-imagen.jpg';
            ?>
            <tr>
                <td>
                    <img src="<?= $imgUrl ?>" width="50" height="50" style="object-fit: cover;" alt="img">
                </td>
                <td><?= htmlspecialchars($p['nombre']) ?></td>
                <td><?= htmlspecialchars($p['sku']) ?></td>
                <td>$<?= number_format((float)$p['precio_venta'], 2, ',', '.') ?></td>
                <td class="text-end"><?= number_format($stock, 3, ',', '.') ?></td>
                <td class="text-nowrap">
                    <a href="<?= BASE_URL ?>/productos/show/<?= $p['id'] ?>" class="btn btn-sm btn-info" title="Ver"><i class="bi bi-eye"></i></a>
                    <a href="<?= BASE_URL ?>/productos/update/<?= $p['id'] ?>" class="btn btn-sm btn-warning" title="Editar"><i class="bi bi-pencil-square"></i></a>
                    <?php if ($inactivos): ?>
                        <form method="post" action="<?= BASE_URL ?>/productos/reactivar/<?= (int)$p['id'] ?>"
                              class="d-inline js-form-reactivar"
                              data-nombre="<?= htmlspecialchars($p['nombre']) ?>">
                            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                            <button type="submit" class="btn btn-sm btn-success" title="Reactivar">
                                <i class="bi bi-arrow-repeat"></i> Reactivar
                            </button>
                        </form>
                    <?php else: ?>
                        <a href="<?= BASE_URL ?>/productos/updatebarcode/<?= $p['id'] ?>" class="btn btn-sm btn-secondary" title="Códigos de barras"><i class="bi bi-upc-scan"></i></a>
                        <a href="<?= BASE_URL ?>/productos/stockdata/<?= $p['id'] ?>" class="btn btn-sm btn-info" title="Parámetros de stock"><i class="bi bi-boxes"></i></a>
                        <a href="<?= BASE_URL ?>/productos/preciocompra/<?= $p['id'] ?>" class="btn btn-sm btn-success" title="Costos y Precios"><i class="bi bi-tag"></i></a>
                        <form method="post" action="<?= BASE_URL ?>/productos/delete/<?= (int)$p['id'] ?>"
                              class="d-inline js-form-inactivar"
                              data-nombre="<?= htmlspecialchars($p['nombre']) ?>"
                              data-stock="<?= $stock ?>">
                            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                            <input type="hidden" name="forzar_stock" value="0">
                            <button type="submit" class="btn btn-sm btn-danger" title="Inactivar"><i class="bi bi-trash"></i></button>
                        </form>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<script>
document.querySelectorAll('.js-form-inactivar').forEach(function (form) {
    form.addEventListener('submit', function (e) {
        e.preventDefault();

        var stock = parseFloat(form.dataset.stock || '0') || 0;
        var nombre = form.dataset.nombre || '';
        var stockTxt = stock.toLocaleString('es-AR', { minimumFractionDigits: 3, maximumFractionDigits: 3 });

        var titulo = stock > 0 ? 'El producto tiene stock' : '¿Inactivar producto?';
        var texto = stock > 0
            ? 'El producto "' + nombre + '" tiene ' + stockTxt + ' en stock. Si lo inactiva igual, se registrará un ajuste de inventario a 0 y dejará de estar disponible para nuevos presupuestos, pedidos, remitos y compras. ¿Desea inactivarlo de todos modos?'
            : 'El producto "' + nombre + '" dejará de aparecer en el listado y no podrá usarse en nuevos presupuestos, pedidos, remitos o compras.';

        Swal.fire({
            icon: stock > 0 ? 'warning' : 'question',
            title: titulo,
            text: texto,
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#6c757d',
            confirmButtonText: stock > 0 ? 'Sí, inactivar y ajustar stock' : 'Sí, inactivar',
            cancelButtonText: 'Cancelar'
        }).then(function (result) {
            if (!result.isConfirmed) return;
            form.querySelector('[name="forzar_stock"]').value = stock > 0 ? '1' : '0';
            form.submit();
        });
    });
});

document.querySelectorAll('.js-form-reactivar').forEach(function (form) {
    form.addEventListener('submit', function (e) {
        e.preventDefault();
        Swal.fire({
            icon: 'question',
            title: '¿Reactivar producto?',
            text: 'El producto "' + (form.dataset.nombre || '') + '" volverá a estar disponible para nuevos presupuestos, pedidos, remitos y compras. El stock queda como está (no se repone).',
            showCancelButton: true,
            confirmButtonColor: '#198754',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Sí, reactivar',
            cancelButtonText: 'Cancelar'
        }).then(function (result) {
            if (result.isConfirmed) form.submit();
        });
    });
});
</script>
