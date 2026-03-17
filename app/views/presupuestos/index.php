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
                    <td>
                        <span class="badge bg-<?= $p['estado'] === 'APROBADO' ? 'success' : 'secondary' ?>">
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
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>