<h4 class="mb-3"><?= $title ?></h4>
<form method="POST" class="card p-4 col-md-6 mx-auto shadow">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">

    <div class="mb-3">
        <label class="form-label">Nombre de la Empresa</label>
        <input class="form-control" name="nombre"
               value="<?= htmlspecialchars($tenant['nombre'] ?? '') ?>" required
               placeholder="Ej: Alimentos Triba S.R.L.">
    </div>

    <?php if (!$tenant): ?>
    <div class="mb-3">
        <label class="form-label">Nombre de la Base de Datos</label>
        <input class="form-control" name="dbname"
               value="" required
               placeholder="Ej: tenant_triba"
               pattern="[a-zA-Z0-9_]+"
               title="Solo letras, números y guiones bajos">
        <div class="form-text text-danger">Atención, la BD en algunos casos debe ser creada previamente.</div>
    </div>
    <?php else: ?>
    <input type="hidden" name="dbname" value="<?= htmlspecialchars($tenant['dbname']) ?>">
    <div class="mb-3">
        <label class="form-label">Base de Datos</label>
        <input class="form-control" value="<?= htmlspecialchars($tenant['dbname']) ?>" disabled>
        <div class="form-text">No se puede modificar el nombre de la BD.</div>
    </div>
    <?php endif; ?>

    <div class="mb-3">
        <label class="form-label">Host</label>
        <input class="form-control" name="host"
               value="<?= htmlspecialchars($tenant['host'] ?? 'localhost') ?>"
               placeholder="localhost">
    </div>

    <?php if ($tenant): ?>
    <div class="mb-3">
        <label class="form-label">Estado</label>
        <select class="form-select" name="activo">
            <option value="1" <?= ($tenant['activo'] ?? 1) ? 'selected' : '' ?>>Activo</option>
            <option value="0" <?= empty($tenant['activo']) ? 'selected' : '' ?>>Inactivo</option>
        </select>
    </div>
    <?php endif; ?>

    <div class="col d-flex justify-content-end">
        <a class="btn btn-secondary me-2" href="<?= BASE_URL ?>/admin">Volver</a>
        <button class="btn btn-success">Guardar</button>
    </div>
</form>
