<div class="d-flex justify-content-between align-items-center mb-3">
    <h3><i class="bi bi-receipt"></i> <?= htmlspecialchars($title) ?></h3>
    <button class="btn btn-primary" onclick="abrirModal()">
        <i class="bi bi-plus-lg"></i> Nuevo Impuesto
    </button>
</div>

<!-- Tabla de impuestos -->
<div class="table-responsive">
<table class="table table-striped table-hover">
    <thead class="table-dark">
        <tr>
            <th>#</th>
            <th>Nombre</th>
            <th>Código</th>
            <th>Porcentaje</th>
            <th>Estado</th>
            <th>Acciones</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($impuestos as $imp): ?>
        <tr>
            <td><?= $imp['id'] ?></td>
            <td><?= htmlspecialchars($imp['nombre']) ?></td>
            <td><code><?= htmlspecialchars($imp['codigo']) ?></code></td>
            <td class="fw-bold"><?= number_format($imp['porcentaje'], 1) ?>%</td>
            <td>
                <span class="badge <?= $imp['activo'] ? 'bg-success' : 'bg-secondary' ?>">
                    <?= $imp['activo'] ? 'Activo' : 'Inactivo' ?>
                </span>
            </td>
            <td>
                <button class="btn btn-sm btn-outline-primary" onclick='editar(<?= json_encode($imp) ?>)'>
                    <i class="bi bi-pencil"></i>
                </button>
                <a href="<?= BASE_URL ?>/contabilidad/impuesto-toggle/<?= $imp['id'] ?>"
                   class="btn btn-sm btn-outline-<?= $imp['activo'] ? 'warning' : 'success' ?>"
                   onclick="return confirm('¿Cambiar estado?')">
                    <i class="bi bi-<?= $imp['activo'] ? 'pause' : 'play' ?>"></i>
                </a>
            </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($impuestos)): ?>
        <tr>
            <td colspan="6" class="text-center text-muted py-4">
                No hay impuestos configurados.
            </td>
        </tr>
        <?php endif; ?>
    </tbody>
</table>
</div>

<!-- Info -->
<div class="card bg-light mt-3">
    <div class="card-body py-2">
        <small class="text-muted">
            <i class="bi bi-info-circle"></i>
            Los impuestos se aplican al registrar gastos. El monto total se desglosa automáticamente en base imponible e IVA.
            Los porcentajes comunes en Argentina: 21%, 10.5%, 27%, 2.5%, 5%.
        </small>
    </div>
</div>

<!-- Modal ABM -->
<div class="modal fade" id="modalImpuesto" tabindex="-1">
<div class="modal-dialog">
<div class="modal-content">
    <form method="POST" action="<?= BASE_URL ?>/contabilidad/impuesto-save" id="formImpuesto">
        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
        <input type="hidden" id="imp_id" name="id" value="">

        <div class="modal-header">
            <h5 class="modal-title" id="modalTitle">Nuevo Impuesto</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
            <div class="mb-3">
                <label for="imp_nombre" class="form-label">Nombre *</label>
                <input type="text" id="imp_nombre" name="nombre" class="form-control" required
                       placeholder="Ej: IVA 21%, Exento, etc.">
            </div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="imp_codigo" class="form-label">Código *</label>
                    <input type="text" id="imp_codigo" name="codigo" class="form-control" required
                           placeholder="Ej: IVA21" style="text-transform: uppercase;">
                </div>
                <div class="col-md-6 mb-3">
                    <label for="imp_porcentaje" class="form-label">Porcentaje (%) *</label>
                    <input type="number" id="imp_porcentaje" name="porcentaje" class="form-control"
                           step="0.1" min="0" max="100" required value="0">
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
            <button type="submit" class="btn btn-primary">
                <i class="bi bi-check-lg"></i> Guardar
            </button>
        </div>
    </form>
</div>
</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
function abrirModal() {
    document.getElementById('imp_id').value = '';
    document.getElementById('imp_nombre').value = '';
    document.getElementById('imp_codigo').value = '';
    document.getElementById('imp_porcentaje').value = '0';
    document.getElementById('modalTitle').textContent = 'Nuevo Impuesto';
    new bootstrap.Modal(document.getElementById('modalImpuesto')).show();
}

function editar(imp) {
    document.getElementById('imp_id').value = imp.id;
    document.getElementById('imp_nombre').value = imp.nombre;
    document.getElementById('imp_codigo').value = imp.codigo;
    document.getElementById('imp_porcentaje').value = imp.porcentaje;
    document.getElementById('modalTitle').textContent = 'Editar Impuesto #' + imp.id;
    new bootstrap.Modal(document.getElementById('modalImpuesto')).show();
}
</script>
