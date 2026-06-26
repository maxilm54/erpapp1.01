<div class="d-flex justify-content-center align-items-center" style="min-height: 60vh;">
    <div class="card shadow" style="width: 100%; max-width: 500px;">
        <div class="card-body p-5">
            <h4 class="text-center mb-4">
                <i class="bi bi-building"></i><br>
                Seleccionar Empresa
            </h4>
            <p class="text-muted text-center mb-4">
                Tenés acceso a múltiples empresas. Elegí con cuál querés trabajar:
            </p>

            <?php if (isset($_SESSION['user_nombre'])): ?>
            <p class="text-center"><strong>Bienvenido, <?= htmlspecialchars($_SESSION['user_nombre']) ?></strong></p>
            <?php endif; ?>

            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">

                <div class="list-group mb-4">
                    <?php foreach ($tenants as $t): ?>
                    <label class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                        <div>
                            <input type="radio" name="tenant_id" value="<?= $t['id'] ?>" required
                                   class="form-check-input me-2">
                            <strong><?= htmlspecialchars($t['nombre']) ?></strong>
                            <br>
                            <small class="text-muted">BD: <?= htmlspecialchars($t['dbname']) ?></small>
                        </div>
                        <i class="bi bi-chevron-right text-muted"></i>
                    </label>
                    <?php endforeach; ?>
                </div>

                <button type="submit" class="btn btn-primary w-100">
                    <i class="bi bi-box-arrow-in-right"></i> Ingresar
                </button>
            </form>

            <div class="text-center mt-3">
                <a href="<?= BASE_URL ?>/auth/logout" class="text-muted">
                    <i class="bi bi-arrow-left"></i> Cambiar de cuenta
                </a>
            </div>
        </div>
    </div>
</div>
