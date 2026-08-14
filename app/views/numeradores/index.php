<div class="d-flex justify-content-between mb-3">
    <h3><i class="bi bi-list-ol"></i> Numeradores</h3>
    <a href="<?= BASE_URL ?>/numeradores/create" class="btn btn-success">
        <i class="bi bi-plus-lg"></i> Nuevo Numerador
    </a>
</div>

<div class="table-responsive">
<table class="table table-hover">
    <thead class="table-dark">
        <tr>
            <th>#</th>
            <th>Tipo</th>
            <th>Prefijo</th>
            <th>Ultimo Numero</th>
            <th>Incremento</th>
            <th>Proximo Numero</th>
            <th>Acciones</th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($numeradores)): ?>
        <tr><td colspan="7" class="text-center text-muted">No hay numeradores configurados.</td></tr>
        <?php else: ?>
        <?php foreach ($numeradores as $n): ?>
        <tr>
            <td><?= $n['id'] ?></td>
            <td>
                <strong><code><?= htmlspecialchars($n['tipo']) ?></code></strong>
            </td>
            <td>
                <?php if (!empty($n['prefijo'])): ?>
                    <span class="badge bg-secondary"><?= htmlspecialchars($n['prefijo']) ?></span>
                <?php else: ?>
                    <span class="text-muted">-</span>
                <?php endif; ?>
            </td>
            <td><?= (int)$n['ultimo_numero'] ?></td>
            <td>+ <?= (int)$n['incremento'] ?></td>
            <td>
                <span class="text-primary fw-bold">
                    <?php
                    $proximo = (int)$n['ultimo_numero'] + (int)$n['incremento'];
                    $prefijo = $n['prefijo'] ?? '';
                    echo htmlspecialchars($prefijo) . str_pad($proximo, max(6, strlen((string)$proximo)), '0', STR_PAD_LEFT);
                    ?>
                </span>
            </td>
            <td>
                <a href="<?= BASE_URL ?>/numeradores/edit/<?= $n['id'] ?>" class="btn btn-sm btn-outline-warning">
                    <i class="bi bi-pencil"></i>
                </a>
                <form method="POST" action="<?= BASE_URL ?>/numeradores/delete/<?= $n['id'] ?>" class="d-inline"
                      onsubmit="return confirm('Seguro que quieres eliminar este numerador?')">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
                    <button type="submit" class="btn btn-sm btn-outline-danger">
                        <i class="bi bi-trash"></i>
                    </button>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
</table>
</div>

<div class="card mt-4">
    <div class="card-header bg-info text-white">
        <h6 class="mb-0"><i class="bi bi-info-circle"></i> Tipos de Numeradores</h6>
    </div>
    <div class="card-body">
        <small class="text-muted">
            <strong>REMITO</strong> &mdash; Remitos de salida (ventas)<br>
            <strong>FACTURA</strong> &mdash; Facturas de venta<br>
            <strong>REMITO_COMPRA</strong> &mdash; Remitos de compra (proveedores)<br>
            <strong>SDCOMP_AJUSTE</strong> &mdash; Ajustes sin declarar<br>
            <strong>NOTA_PEDIDO</strong> &mdash; Notas de pedido<br>
            <strong>PRESUPUESTO</strong> &mdash; Presupuestos<br>
            <br>
            Los tipos en <strong>negrita</strong> no se pueden eliminar porque estan en uso por modulos del sistema.
            Puedes modificar su ultimo numero, prefijo e incremento.
        </small>
    </div>
</div>
