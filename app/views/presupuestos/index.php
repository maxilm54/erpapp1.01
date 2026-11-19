<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3>Presupuestos</h3>
        <a href="<?= BASE_URL ?>/presupuestos/create" class="btn btn-primary">
            + Nuevo Presupuesto
        </a>
    </div>

    <div class="table-scroll mt-3 table-responsive">
        <table class="table table-bordered table-hover align-middle">
            <thead class="table-light">
                <tr>
                    <th>#</th>
                    <th>Cliente</th>
                    <th>Fecha</th>
                    <th>Entrega</th>
                    <th>Vencimiento</th>
                    <th>Estado</th>
                    <th class="text-center">Acciones</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($presupuestos as $p): ?>
                <tr>
                    <td><?= $p['id'] ?></td>
                    <td><?= htmlspecialchars($p['razon_social']) ?></td>
                    <td><?= date('d/m/Y', strtotime($p['created_at'])) ?></td>
                    <td><?= !empty($p['fecha_entrega']) ? date('d/m/Y', strtotime($p['fecha_entrega'])) : '&mdash;' ?></td>
                    <td>
                        <?php if (!empty($p['vencimiento'])): ?>
                            <?= date('d/m/Y', strtotime($p['vencimiento'])) ?>
                            <?php if ($p['estado'] === 'BORRADOR' && $p['vencimiento'] < date('Y-m-d')): ?>
                                <span class="badge bg-danger">Vencido</span>
                            <?php elseif (!empty($p['aprobado_at'])
                                && date('Y-m-d', strtotime($p['aprobado_at'])) > $p['vencimiento']): ?>
                                <span class="badge bg-danger">Aprobado vencido</span>
                            <?php endif; ?>
                        <?php else: ?>
                            &mdash;
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php
                        $badgeEstado = $p['estado'] === 'APROBADO'
                            ? 'success'
                            : ($p['estado'] === 'ANULADO' ? 'danger' : 'secondary');
                        ?>
                        <span class="badge bg-<?= $badgeEstado ?>">
                            <?= $p['estado'] ?>
                        </span>
                    </td>
                    <td class="text-center">
                        <a href="<?= BASE_URL ?>/presupuestos/show/<?= $p['id'] ?>" class="btn btn-sm btn-info">
                            Ver
                        </a>

                        <?php if ($p['estado'] === 'BORRADOR'): ?>
                            <a href="<?= BASE_URL ?>/presupuestos/edit/<?= $p['id'] ?>" class="btn btn-sm btn-warning">
                                Editar
                            </a>
                        <?php endif; ?>

                        <?php if ($p['estado'] !== 'ANULADO' && empty($p['nota_pedido_id'])): ?>
                            <form method="POST"
                                  action="<?= BASE_URL ?>/presupuestos/anular/<?= $p['id'] ?>"
                                  class="d-inline"
                                  onsubmit="return confirm('¿Anular el presupuesto #<?= (int)$p['id'] ?>? Queda el registro y no se puede revertir.');">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Csrf::generate()) ?>">
                                <button class="btn btn-sm btn-danger">Anular</button>
                            </form>
                        <?php endif; ?>

                        <?php if (!empty($p['nota_pedido_id'])): ?>
                            <a href="<?= BASE_URL ?>/notaspedido/show/<?= (int)$p['nota_pedido_id'] ?>"
                               class="btn btn-sm btn-outline-secondary">
                                Ver NP #<?= (int)$p['nota_pedido_id'] ?>
                            </a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>