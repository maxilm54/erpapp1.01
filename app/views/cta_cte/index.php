<h1>Cuenta Corriente de Clientes</h1>

<a href="<?= BASE_URL ?>/ctacte/pago" class="btn btn-success mb-3">
    Registrar Pago
</a>

<table class="table table-bordered table-striped table-sm">
    <thead class="table-dark">
        <tr>
            <th>Fecha</th>
            <th>Cliente</th>
            <th>Tipo</th>
            <th>Origen</th>
            <th>Referencia</th>
            <th>Débito</th>
            <th>Crédito</th>
            <th>Saldo</th>
            <th></th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($movimientos as $m): ?>
        <tr>
            <td><?= date('d/m/Y', strtotime($m['fecha'])) ?></td>
            <td><?= htmlspecialchars($m['razon_social']) ?></td>
            <td>
                <span class="badge bg-<?= $m['tipo']=='DEBITO'?'danger':'success' ?>">
                    <?= $m['tipo'] ?>
                </span>
            </td>
            <td><?= $m['origen'] ?></td>
            <td>#<?= $m['referencia_id'] ?></td>
            <td><?= $m['tipo']=='DEBITO' ? number_format($m['monto'],2) : '' ?></td>
            <td><?= $m['tipo']=='CREDITO' ? number_format($m['monto'],2) : '' ?></td>
            <td><strong><?= number_format($m['saldo'],2) ?></strong></td>
            <td>
                <a href="<?= BASE_URL ?>/ctacte/show/<?= $m['id'] ?>"
                   class="btn btn-outline-primary btn-sm">Ver</a>
            </td>
        </tr>
        <?php endforeach ?>
    </tbody>
</table>