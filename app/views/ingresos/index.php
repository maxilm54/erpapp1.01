<h1>Ingresos de Mercadería</h1>

<table class="table table-striped">
    <thead>
        <tr>
            <th>Fecha</th>
            <th>Proveedor</th>
            <th>Remito</th>
            <th>Orden</th>
            <th></th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($ingresos as $i): ?>
        <tr>
            <td><?= date('d/m/Y', strtotime($i['fecha'])) ?></td>
            <td><?= $i['proveedor'] ?></td>
            <td><?= $i['remito'] ?></td>
            <td>#<?= $i['orden_compra_id'] ?></td>
            <td>
                <a href="<?= BASE_URL ?>/ingresosmercaderia/show/<?= $i['id'] ?>"
                   class="btn btn-sm btn-secondary">Ver
                </a>
            </td>
        </tr>
        <?php endforeach ?>
    </tbody>
</table>