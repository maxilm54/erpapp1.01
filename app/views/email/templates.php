<h4><i class="bi bi-envelope-paper"></i> Templates de Email</h4>
<hr>

<?php if (!empty($_SESSION['success'])): ?>
<div class="alert alert-success"><?= $_SESSION['success']; unset($_SESSION['success']); ?></div>
<?php endif; ?>
<?php if (!empty($_SESSION['error'])): ?>
<div class="alert alert-danger"><?= $_SESSION['error']; unset($_SESSION['error']); ?></div>
<?php endif; ?>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <strong>Plantillas por Tipo</strong>
        <a href="<?= BASE_URL ?>/email/template-form" class="btn btn-sm btn-success">
            <i class="bi bi-plus-lg"></i> Nuevo Template
        </a>
    </div>
    <div class="card-body p-0">
        <table class="table table-sm table-striped mb-0">
            <thead class="table-dark">
                <tr>
                    <th>Tipo</th>
                    <th>Asunto</th>
                    <th class="text-center">Estado</th>
                    <th class="text-center">Default</th>
                    <th class="text-center">Fecha</th>
                    <th class="text-end">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($templates as $t): ?>
                <tr>
                    <td>
                        <?php
                        $badgeClass = match($t['tipo']) {
                            'REMITO' => 'bg-primary',
                            'PAGO' => 'bg-success',
                            'PRESUPUESTO' => 'bg-info',
                            'NOTA_PEDIDO' => 'bg-warning text-dark',
                            'FACTURA' => 'bg-danger',
                            'ORDEN_COMPRA' => 'bg-secondary',
                            default => 'bg-dark'
                        };
                        ?>
                        <span class="badge <?= $badgeClass ?>"><?= $tipos[$t['tipo']] ?? $t['tipo'] ?></span>
                    </td>
                    <td><?= htmlspecialchars($t['asunto']) ?></td>
                    <td class="text-center">
                        <?php if ($t['activo']): ?>
                            <span class="badge bg-success">Activo</span>
                        <?php else: ?>
                            <span class="badge bg-secondary">Inactivo</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-center">
                        <?php if ($t['es_default']): ?>
                            <span class="badge bg-info">Default</span>
                        <?php else: ?>
                            <span class="badge bg-warning text-dark">Personalizado</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-center small"><?= date('d/m/Y', strtotime($t['updated_at'] ?? $t['created_at'])) ?></td>
                    <td class="text-end">
                        <a href="<?= BASE_URL ?>/email/template-form?id=<?= $t['id'] ?>" class="btn btn-sm btn-outline-primary" title="Editar">
                            <i class="bi bi-pencil"></i>
                        </a>
                        <?php if ($t['es_default']): ?>
                        <form method="post" action="<?= BASE_URL ?>/email/clone-template" style="display:inline">
                            <input type="hidden" name="template_id" value="<?= $t['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-outline-success" title="Clonar para personalizar">
                                <i class="bi bi-clipboard-plus"></i>
                            </button>
                        </form>
                        <?php else: ?>
                        <form method="post" action="<?= BASE_URL ?>/email/reset-template" style="display:inline"
                              onsubmit="return confirm('¿Restaurar este template a la versión por defecto?')">
                            <input type="hidden" name="tipo" value="<?= $t['tipo'] ?>">
                            <button type="submit" class="btn btn-sm btn-outline-warning" title="Restaurar default">
                                <i class="bi bi-arrow-counterclockwise"></i>
                            </button>
                        </form>
                        <form method="post" action="<?= BASE_URL ?>/email/delete-template" style="display:inline"
                              onsubmit="return confirm('¿Eliminar este template personalizado?')">
                            <input type="hidden" name="template_id" value="<?= $t['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Eliminar">
                                <i class="bi bi-trash"></i>
                            </button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($templates)): ?>
                <tr><td colspan="6" class="text-center text-muted py-4">No hay templates configurados</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
