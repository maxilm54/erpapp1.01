<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3>Presupuesto #<?= $presupuesto['id'] ?></h3>

        <div>
            <?php if ($presupuesto['estado'] === 'BORRADOR'): ?>
                <a href="<?= BASE_URL ?>/presupuestos/edit/<?= $presupuesto['id'] ?>"
                   class="btn btn-warning">Editar</a>

                <form method="POST"
                      action="<?= BASE_URL ?>/presupuestos/aprobar/<?= $presupuesto['id'] ?>"
                      class="d-inline">
                    <button class="btn btn-success">Aprobar</button>
                </form>
            <?php endif; ?>

            <a href="<?= BASE_URL ?>/presupuestos" class="btn btn-secondary">Volver</a>
            <a href="<?= BASE_URL ?>/presupuestos/volvernp/<?= $presupuesto['id'] ?>" class="btn btn-secondary">NP</a>
        </div>
    </div>

    <div class="mb-3">
        <strong>Cliente:</strong> <?= htmlspecialchars($presupuesto['razon_social']) ?><br>
        <strong>Fecha:</strong> <?= date('d/m/Y', strtotime($presupuesto['created_at'])) ?><br>
        <strong>Estado:</strong>
        <span class="badge bg-<?= $presupuesto['estado'] === 'APROBADO' ? 'success' : 'secondary' ?>">
            <?= $presupuesto['estado'] ?>
        </span>
    </div>

    <div class="table-responsive">
        <table class="table table-bordered align-middle">
            <thead class="table-light">
                <tr>
                    <th>Producto</th>
                    <th>Cantidad</th>
                    <th>Precio</th>
                    <th>Subtotal</th>
                </tr>
            </thead>
            <tbody>
            <?php $total = 0; ?>
            <?php foreach ($presupuesto['detalle'] as $d): 
                $sub = $d['cantidad'] * $d['precio'];
                $total += $sub;
            ?>
                <tr>
                    <td><?= htmlspecialchars($d['nombre']) ?></td>
                    <td><?= number_format($d['cantidad'], 3) ?></td>
                    <td>$<?= number_format($d['precio'], 2) ?></td>
                    <td>$<?= number_format($sub, 2) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <th colspan="3" class="text-end">Total</th>
                    <th>$<?= number_format($total, 2) ?></th>
                </tr>
            </tfoot>
        </table>
    </div>
</div>