<?php require_once BASE_PATH . '/app/core/Role.php'; ?>
<div class="d-flex justify-content-between mb-3">
    <h4><?= htmlspecialchars($title) ?></h4>
    <a href="<?= BASE_URL ?>/admin/empresa-users" class="btn btn-secondary">
        <i class="bi bi-arrow-left"></i> Volver
    </a>
</div>

<form method="POST" class="card p-4 col-md-6 mx-auto shadow">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">

    <div class="alert alert-info mb-3">
        <i class="bi bi-building"></i> Este usuario será asignado automáticamente a: <strong><?= htmlspecialchars($tenant['nombre']) ?></strong>
    </div>

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
            <option value="USUARIO" selected>Operario</option>
            <option value="VISITOR">Visor (solo consulta)</option>
        </select>
        <div class="form-text">
            <strong>Operario</strong>: accede a los menús de la empresa y puede gestionar usuarios.<br>
            <strong>Visor</strong>: solo consulta, sin altas/bajas.
        </div>
    </div>

    <div class="col d-flex justify-content-end">
        <a class="btn btn-secondary me-2" href="<?= BASE_URL ?>/admin/empresa-users">Volver</a>
        <button class="btn btn-success">Crear Usuario</button>
    </div>
</form>
