<a href="<?= BASE_URL ?>/proveedores/create"
   class="btn btn-primary mb-3">Nuevo proveedor</a>

<table class="table table-hover">
<thead class="table-dark">
<tr>
    <th>Razón Social</th>
    <th>CUIT</th>
    <th>Email</th>
    <th>Editar</th>
</tr>
</thead>
<tbody>
<?php foreach($items as $p): ?>
<tr>
    <td><?= $p['razon_social'] ?></td>
    <td><?= $p['cuit'] ?></td>
    <td><?= $p['email'] ?></td>
    <td>
        <a href="<?= BASE_URL ?>/proveedores/edit/<?= $p['id'] ?>"
        class="btn btn-sm btn-warning">
        Editar
        </a>
    </td>
</tr>
<?php endforeach ?>
</tbody>
</table>