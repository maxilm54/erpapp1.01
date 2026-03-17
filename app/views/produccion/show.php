<?php
// Calcular total producido
$totalProducido = 0;
foreach ($orden_det as $r) {
    $totalProducido += $r['cantidad_producida'];
}
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1>Orden de Producción #<?= $orden['id'] ?></h1>
    <span class="badge bg-warning text-dark"><?= $orden['estado'] ?></span>
</div>
<p>Creado por: <?= htmlspecialchars($orden['nombre_user']) ?></p>
<p>Fecha creada: <?= htmlspecialchars($orden['created_at']) ?> - Fecha entrega: <?= htmlspecialchars($orden['fecha_entrega']) ?></p>
<p><strong>Producto:</strong> <?= htmlspecialchars($orden['producto']) ?> <strong class="badge bg-warning text-dark fs-6 ml-2"> Cantidad a Producir: <?= number_format($orden['cantidad'], 2) ?></strong></p>
<p><strong>Estado:</strong> <?= $orden['estado'] ?></p>
<p><strong>Cantidad Producida: </strong> <?= $totalProducido ?></p>

<?php if ($orden['observaciones']): ?>
<p><strong>Observaciones:</strong><br>
<?= nl2br(htmlspecialchars($orden['observaciones'])) ?>
</p>
<?php endif ?>

<hr>

<h5>Materia Prima Reservada</h5>
<div class="table-scroll mt-3">
    <table class="table table-bordered">
        <thead>
            <tr>
                <th>Materia Prima</th>
                <th>Cantidad</th>
                <th>Un Medida</th>
                <th>Precio U</th>
                <th>SubTotal</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $total=0;
            foreach ($reservas as $r):
                $total += $r['cantidad'] * ($r['precio_unitario'] ?? 0);
            ?>
            <tr>
                <td><?= htmlspecialchars($r['nombre']) ?></td>
                <td><?= number_format($r['cantidad'], 2,',','.') ?></td>
                <td><?= htmlspecialchars($r['unidad_medida']) ?></td>
                <td><?= number_format($r['precio_unitario'] ?? 0, 2) ?></td>
                <td><?= number_format($r['cantidad'] * ($r['precio_unitario'] ?? 0), 2,'.',',') ?></td>
            </tr>
            <?php endforeach ?>
            <tr>
                <td colspan="3" class="text-end"><strong>Total:</strong></td>
                <td><strong><?= number_format($total, 2) ?></strong></td>
            </tr>
        </tbody>
    </table>
</div>
<a href="<?= BASE_URL ?>/ordenproduccion" class="btn btn-secondary">Volver</a>
<?php if ($orden['estado'] === 'PENDIENTE'): ?>
<a href="<?= BASE_URL ?>/ordenproduccion/producir/<?= $orden['id'] ?>" class="btn btn-success">Producir</a>
<?php endif ?>
<?php if ($orden['estado'] !== 'CANCELADA' && $orden['estado'] !== 'FINALIZADA' && $orden['estado'] == 'EN_PRODUCCION'): ?>
    <button class="btn btn-danger"
        onclick="confirmarAnulacionNP(<?= $orden['id'] ?>)">
        Anular
    </button>
<?php endif ?>
<script>
function confirmarAnulacionNP(id) {
    Swal.fire({
        title: '¿Dese cancelar el faltante de produccion?',
        text: 'Se devolvera a stock disponible la materia prima no utilizada.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Sí, cancelar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = '<?= BASE_URL ?>/ordenproduccion/cancelarproduccion/' + id;
        }
    });
}
</script>