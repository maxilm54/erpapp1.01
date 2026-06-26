<?php require_once BASE_PATH . '/app/core/Role.php'; ?>
<h4 class="mb-3"><?= $title ?></h4>
<form method="POST" class="card p-4 col-md-6 mx-auto shadow">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">

    <div class="mb-3">
        <label class="form-label">Nombre</label>
        <input class="form-control" name="nombre"
               value="<?= htmlspecialchars($user['nombre'] ?? '') ?>" required
               placeholder="Nombre completo">
    </div>

    <div class="mb-3">
        <label class="form-label">Email</label>
        <input class="form-control" name="email" type="email"
               value="<?= htmlspecialchars($user['email'] ?? '') ?>" required
               placeholder="usuario@ejemplo.com">
    </div>

    <div class="mb-3">
        <label class="form-label">Contraseña <?= $user ? '(dejar vacío para no cambiar)' : '' ?></label>
        <input class="form-control" name="password" type="password"
               <?= $user ? '' : 'required' ?>
               minlength="6"
               placeholder="Mínimo 6 caracteres">
    </div>

    <div class="mb-3">
        <label class="form-label">Rol</label>
        <select class="form-select" name="rol" required>
            <?php foreach (Role::getAllRoles() as $key => $label): ?>
            <option value="<?= $key ?>"
                <?= (strtoupper($user['rol'] ?? '') === strtoupper($key)) ? 'selected' : '' ?>>
                <?= htmlspecialchars($label) ?>
            </option>
            <?php endforeach; ?>
        </select>
        <div class="form-text">
            <strong>Admin</strong>: ve todos los menús y gestiona usuarios.<br>
            <strong>Operario</strong>: menú restringido, no gestiona usuarios.<br>
            <strong>Visor</strong>: solo consulta, sin altas/bajas.
        </div>
    </div>

    <div class="mb-3">
        <label class="form-label">Espacios (Tenants)</label>
        <div class="border rounded p-3" style="max-height: 200px; overflow-y: auto;">
            <?php if (empty($tenants)): ?>
                <p class="text-muted mb-0">No hay empresas (tenants) creadas.</p>
            <?php else: ?>
                <?php foreach ($tenants as $t): ?>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox"
                           name="tenants[]" value="<?= $t['id'] ?>"
                           id="tenant_<?= $t['id'] ?>"
                           <?= in_array($t['id'], $selectedTenants) ? 'checked' : '' ?>>
                    <label class="form-check-label" for="tenant_<?= $t['id'] ?>">
                        <?= htmlspecialchars($t['nombre']) ?>
                        <small class="text-muted">(<?= htmlspecialchars($t['dbname']) ?>)</small>
                    </label>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <div class="form-text">Seleccioná a qué empresas tiene acceso este usuario.</div>
    </div>

    <div class="col d-flex justify-content-end">
        <a class="btn btn-secondary me-2" href="<?= BASE_URL ?>/users">Volver</a>
        <button class="btn btn-success">Guardar</button>
    </div>
</form>
