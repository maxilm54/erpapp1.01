<h2>Historico Ingreso de Mercadería</h2>

<table class="table table-bordered">
    <thead>
        <tr>
            <th>Fecha</th>
            <th>Remito</th>
            <th>Usuario</th>
            <th>Materia Prima</th>
            <th>Cantidad</th>
        </tr>
    </thead>
    <tbody>
        <?php 
        foreach ($historico as $d): ?>
        <tr>
            <td><?= $d['fecha'] ?></td>
            <td><?= $d['remito'] ?></td>
            <td><?= $d['usuario'] ?></td>
            <td><?= $d['materia_prima'] ?></td>
            <td><?= $d['cantidad'] ?></td>
        </tr>
        <?php endforeach;?>
    </tbody>
</table>
<div class="mt-4 d-flex gap-2">
    <a href="<?= BASE_URL ?>/ingresosmercaderia"
       class="btn btn-secondary">
       Volver
    </a>    
</div>