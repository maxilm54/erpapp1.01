<?php require_once BASE_PATH . '/app/core/Role.php'; ?>
<div class="d-flex justify-content-between mb-3">
    <h4><?= htmlspecialchars($title) ?></h4>
    <a href="<?= BASE_URL ?>/admin/all-users" class="btn btn-secondary">
        <i class="bi bi-arrow-left"></i> Volver
    </a>
</div>

<form method="POST" class="card p-4 col-md-8 mx-auto shadow">
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
        <label class="form-label">Contrasena <?= $user ? '(dejar vacio para no cambiar)' : '' ?></label>
        <input class="form-control" name="password" type="password"
               <?= $user ? '' : 'required' ?>
               minlength="6"
               placeholder="Minimo 6 caracteres">
    </div>

    <div class="mb-3">
        <label class="form-label">Rol Global</label>
        <select class="form-select" name="rol" required>
            <?php foreach (Role::getAllRoles() as $key => $label): ?>
            <option value="<?= $key ?>" <?= (isset($user['rol']) && strtoupper($user['rol']) === $key) ? 'selected' : '' ?>>
                <?= $label ?>
            </option>
            <?php endforeach; ?>
        </select>
        <div class="form-text">
            <strong>Super Administrador</strong>: controla el panel admin global (tenants, usuarios, migraciones).<br>
            <strong>Administrador</strong>: gestiona empresa y usuarios dentro del tenant.<br>
            <strong>Operario</strong>: accede a los menus y opera el sistema.<br>
            <strong>Gerente Financiero</strong>: acceso a comprobantes y finanzas.<br>
            <strong>Visor</strong>: solo consulta.
        </div>
    </div>

    <div class="mb-3">
        <label class="form-label">Asignar a Empresa(s)</label>
        <?php
        $userTenantIds = $userTenants ?? [];
        ?>
        <?php if (empty($tenants)): ?>
            <p class="text-muted">No hay empresas creadas aun.</p>
        <?php else: ?>
        <div class="border rounded p-3">
            <?php foreach ($tenants as $t): ?>
            <div class="form-check">
                <input class="form-check-input" type="checkbox" name="tenants[]"
                       value="<?= $t['id'] ?>" id="tenant_<?= $t['id'] ?>"
                       <?= in_array($t['id'], $userTenantIds) ? 'checked' : '' ?>>
                <label class="form-check-label" for="tenant_<?= $t['id'] ?>">
                    <?= htmlspecialchars($t['nombre']) ?>
                    <small class="text-muted">(<?= htmlspecialchars($t['dbname']) ?>)</small>
                </label>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <div class="form-text">Selecciona a que empresas tendra acceso este usuario.</div>
    </div>

    <div class="col d-flex justify-content-end">
        <a class="btn btn-secondary me-2" href="<?= BASE_URL ?>/admin/all-users">Cancelar</a>
        <button class="btn btn-success"><?= $user ? 'Actualizar' : 'Crear' ?> Usuario</button>
    </div>
</form>
