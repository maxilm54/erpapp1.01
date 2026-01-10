<h1>Notas de Pedido</h1>

<a href="<?= BASE_URL ?>/notaspedido/create" class="btn btn-success mb-3">Nueva NP</a>

<table class="table table-striped">
    <thead>
        <tr>
            <th>#</th>
            <th>Cliente</th>
            <th>Estado</th>
            <th>Fecha</th>
            <th>Ver/Remitar</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($notas as $n): ?>
        <tr>
            <td><?= $n['id'] ?></td>
            <td><?= $n['razon_social'] ?></td>
            <td><?= $n['estado'] ?></td>
            <td><?= date('d/m/Y', strtotime($n['created_at'])) ?></td>
            <td>
                <a href="<?= BASE_URL ?>/notaspedido/show/<?= $n['id'] ?>" class="btn btn-sm btn-primary">Ver</a>
                <?php if ($n['remitido'] !== 'RemitidoCompleto'): ?>
                <a href="<?= BASE_URL ?>/remitossalida/create/<?= $n['id'] ?>" class="btn btn-sm btn-primary">Crear Remito</a>
                <?php else: ?>
                <span class="text-success">Remitido Completo</span>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach ?>
    </tbody>
</table>
