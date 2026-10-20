<div class="d-flex justify-content-between mb-3">
    <h3><i class="bi bi-person-circle"></i> Mi Perfil</h3>
    <a href="<?= BASE_URL ?>" class="btn btn-secondary">
        <i class="bi bi-arrow-left"></i> Volver
    </a>
</div>

<div class="row">
    <div class="col-md-8">
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="bi bi-person"></i> Mis datos</h5>
            </div>
            <div class="card-body">
                <form method="POST" action="<?= BASE_URL ?>/perfil">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Csrf::generate()) ?>">

                    <div class="mb-3">
                        <label for="nombre" class="form-label">Nombre</label>
                        <input type="text" class="form-control" id="nombre" name="nombre" required
                               maxlength="100" value="<?= htmlspecialchars($user['nombre'] ?? '') ?>">
                    </div>

                    <div class="mb-3">
                        <label for="email" class="form-label">Email</label>
                        <input type="email" class="form-control" id="email" name="email" required
                               maxlength="150" value="<?= htmlspecialchars($user['email'] ?? '') ?>">
                        <small class="text-muted">Es el usuario con el que inicias sesion.</small>
                    </div>

                    <hr>

                    <div class="mb-3">
                        <label for="password" class="form-label">Nueva contrasena</label>
                        <input type="password" class="form-control" id="password" name="password"
                               minlength="6" autocomplete="new-password"
                               placeholder="Dejalo vacio para no cambiarla">
                    </div>

                    <div class="mb-3">
                        <label for="password_confirm" class="form-label">Repetir contrasena</label>
                        <input type="password" class="form-control" id="password_confirm" name="password_confirm"
                               minlength="6" autocomplete="new-password"
                               placeholder="Dejalo vacio para no cambiarla">
                    </div>

                    <div class="d-flex justify-content-end">
                        <button class="btn btn-success"><i class="bi bi-save"></i> Guardar cambios</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card">
            <div class="card-header bg-secondary text-white">
                <h5 class="mb-0"><i class="bi bi-info-circle"></i> Informacion</h5>
            </div>
            <div class="card-body">
                <p class="mb-1"><strong>Rol:</strong>
                    <?= htmlspecialchars(Role::getRoleName($user['rol'] ?? '')) ?>
                </p>
                <p class="mb-1"><strong>Estado:</strong>
                    <?= !empty($user['activo']) ? 'Activo' : 'Inactivo' ?>
                </p>
                <p class="mb-0"><strong>Alta:</strong>
                    <?= htmlspecialchars(substr((string)($user['created_at'] ?? ''), 0, 10)) ?>
                </p>
                <hr>
                <small class="text-muted">
                    El rol y el estado los administra un administrador de la empresa.
                </small>
            </div>
        </div>
    </div>
</div>
