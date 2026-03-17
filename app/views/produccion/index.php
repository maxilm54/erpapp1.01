<h1>Órdenes de Producción</h1>

<a href="<?= BASE_URL ?>/ordenproduccion/create" class="btn btn-success mb-3">
    Nueva Orden
</a>
<div class="table-scroll mt-3">
    <table class="table table-bordered">
        <thead>
            <tr>
                <th>#</th>
                <th>Producto</th>
                <th>Cantidad</th>
                <th>Estado</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($ordenes as $o): ?>
            <tr>
                <td><?= $o['id'] ?></td>
                <td><?= htmlspecialchars($o['producto']) ?></td>
                <td><?= number_format($o['cantidad'], 2) ?></td>
                <td><?php
                    if($o['estado']==='FINALIZADA') { ?>
                        <span class="badge bg-success"><?= $o['estado'] ?></span>
                    <?php
                    }elseif($o['estado']==='CANCELADA'){?>
                        <span class="badge bg-danger"><?= $o['estado'] ?></span>
                    <?php }else{ ?>
                        <span class="badge bg-secondary"><?= $o['estado'] ?></span>
                    <?php } ?>
                </td>
                <td>
                    <a href="<?= BASE_URL ?>/ordenproduccion/show/<?= $o['id'] ?>"
                    class="btn btn-primary btn-sm">
                    Ver
                    </a>
                    <?php
                        if($o['estado']==='EN_PRODUCCION'):?>
                        <a href="<?= BASE_URL ?>/ordenproduccion/avance/<?= $o['id'] ?>" class="btn btn-primary btn-sm">
                            Avances
                        </a>
                    <?php endif ?>
                    <?php
                        if($o['estado']==='EN_PRODUCCION'):?>
                            <button class="btn btn-danger btn-sm"
                                onclick="confirmarAnulacionNP(<?= $o['id'] ?>)">
                                Anular
                            </button>
                    <?php endif ?>
                </td>
            </tr>
            <?php endforeach ?>
        </tbody>
    </table>
</div>
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