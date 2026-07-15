<div class="d-flex justify-content-between align-items-center mb-3">
    <h3><i class="bi bi-diagram-3"></i> <?= htmlspecialchars($title) ?></h3>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalCuenta" onclick="abrirModalCrear()">
        <i class="bi bi-plus-lg"></i> Nueva Cuenta
    </button>
</div>

<?php foreach ($arbol as $cuenta): ?>
<?php
$colorClass = match($cuenta['tipo']) {
    'ACTIVO'   => 'bg-primary',
    'PASIVO'   => 'bg-danger',
    'PATRIMONIO'=> 'bg-dark',
    'INGRESO'  => 'bg-success',
    'EGRESO'   => 'bg-warning text-dark',
    default    => 'bg-secondary',
};
?>
<div class="card mb-3">
    <div class="card-header <?= $colorClass ?> text-white d-flex justify-content-between align-items-center">
        <div>
            <strong><?= htmlspecialchars($cuenta['codigo']) ?> - <?= htmlspecialchars($cuenta['nombre']) ?></strong>
            <span class="badge bg-light text-dark ms-2"><?= $cuenta['tipo'] ?></span>
        </div>
        <button class="btn btn-sm btn-light" data-bs-toggle="modal" data-bs-target="#modalCuenta"
                onclick="abrirModalEditar(<?= htmlspecialchars(json_encode($cuenta)) ?>)">
            <i class="bi bi-pencil"></i>
        </button>
    </div>
    <?php if (!empty($cuenta['children'])): ?>
    <div class="card-body p-0">
        <table class="table table-sm mb-0">
            <thead class="table-light">
                <tr>
                    <th>Código</th>
                    <th>Nombre</th>
                    <th>Movimientos</th>
                    <th>Estado</th>
                    <th class="text-end">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($cuenta['children'] as $hijo): ?>
                <tr class="<?= !$hijo['activa'] ? 'table-secondary' : '' ?>">
                    <td><code><?= htmlspecialchars($hijo['codigo']) ?></code></td>
                    <td><?= htmlspecialchars($hijo['nombre']) ?></td>
                    <td>
                        <?= $hijo['acepta_movimiento'] ? '<span class="badge bg-success">Sí</span>' : '<span class="badge bg-secondary">No</span>' ?>
                    </td>
                    <td>
                        <?= $hijo['activa'] ? '<span class="badge bg-success">Activa</span>' : '<span class="badge bg-danger">Inactiva</span>' ?>
                    </td>
                    <td class="text-end">
                        <button class="btn btn-sm btn-outline-warning" data-bs-toggle="modal" data-bs-target="#modalCuenta"
                                onclick="abrirModalEditar(<?= htmlspecialchars(json_encode($hijo)) ?>)">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <a href="<?= BASE_URL ?>/contabilidad/cuenta-toggle/<?= $hijo['id'] ?>"
                           class="btn btn-sm <?= $hijo['activa'] ? 'btn-outline-danger' : 'btn-outline-success' ?>"
                           onclick="return confirm('¿<?= $hijo['activa'] ? 'Desactivar' : 'Activar' ?> esta cuenta?')">
                            <i class="bi <?= $hijo['activa'] ? 'bi-eye-slash' : 'bi-eye' ?>"></i>
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
    <div class="card-body d-flex justify-content-between align-items-center">
        <small class="text-muted">Sin subcuentas</small>
        <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalCuenta"
                onclick="abrirModalCrear(<?= $cuenta['id'] ?>, '<?= $cuenta['codigo'] ?>')">
            <i class="bi bi-plus"></i> Agregar subcuenta
        </button>
    </div>
    <?php endif; ?>
</div>
<?php endforeach; ?>

<!-- Modal para crear/editar cuenta -->
<div class="modal fade" id="modalCuenta" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="<?= BASE_URL ?>/contabilidad/cuenta-save" id="formCuenta">
                <input type="hidden" name="csrf_token" id="csrfCuenta" value="">
                <input type="hidden" name="id" id="cuentaId" value="">
                <input type="hidden" name="padre_id" id="cuentaPadreId" value="">

                <div class="modal-header">
                    <h5 class="modal-title" id="modalCuentaTitle">Nueva Cuenta</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Código *</label>
                        <input type="text" name="codigo" id="cuentaCodigo" class="form-control" required
                               maxlength="20" placeholder="Ej: 1101">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Nombre *</label>
                        <input type="text" name="nombre" id="cuentaNombre" class="form-control" required
                               maxlength="150" placeholder="Nombre de la cuenta">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Tipo *</label>
                        <select name="tipo" id="cuentaTipo" class="form-select" required>
                            <option value="ACTIVO">ACTIVO</option>
                            <option value="PASIVO">PASIVO</option>
                            <option value="PATRIMONIO">PATRIMONIO</option>
                            <option value="INGRESO">INGRESO</option>
                            <option value="EGRESO">EGRESO</option>
                        </select>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Nivel</label>
                            <input type="number" name="nivel" id="cuentaNivel" class="form-control" min="1" max="5" value="1">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Acepta movimientos</label>
                            <select name="acepta_movimiento" id="cuentaAcepta" class="form-select">
                                <option value="1">Sí</option>
                                <option value="0">No (es categoría)</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Guardar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function abrirModalCrear(padreId, padreCodigo) {
    document.getElementById('modalCuentaTitle').textContent = 'Nueva Cuenta';
    document.getElementById('formCuenta').reset();
    document.getElementById('cuentaId').value = '';
    document.getElementById('cuentaPadreId').value = padreId || '';
    document.getElementById('csrfCuenta').value = '<?= Csrf::generate() ?>';
    if (padreId) {
        document.getElementById('cuentaCodigo').placeholder = 'Ej: ' + padreCodigo + '01';
    }
}

function abrirModalEditar(cuenta) {
    document.getElementById('modalCuentaTitle').textContent = 'Editar Cuenta';
    document.getElementById('formCuenta').reset();
    document.getElementById('cuentaId').value = cuenta.id;
    document.getElementById('cuentaPadreId').value = cuenta.padre_id || '';
    document.getElementById('cuentaCodigo').value = cuenta.codigo;
    document.getElementById('cuentaNombre').value = cuenta.nombre;
    document.getElementById('cuentaTipo').value = cuenta.tipo;
    document.getElementById('cuentaNivel').value = cuenta.nivel;
    document.getElementById('cuentaAcepta').value = cuenta.acepta_movimiento;
    document.getElementById('csrfCuenta').value = '<?= Csrf::generate() ?>';
}
</script>
