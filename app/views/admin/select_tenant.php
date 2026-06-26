<div class="d-flex justify-content-center align-items-center" style="min-height: 60vh;">
    <div class="card shadow" style="width: 100%; max-width: 500px;">
        <div class="card-body p-5">
            <h4 class="text-center mb-4">
                <i class="bi bi-shield-lock"></i><br>
                SuperAdmin: Seleccionar Empresa
            </h4>
            <p class="text-muted text-center mb-4">
                Elegí la empresa sobre la cual querés trabajar:
            </p>

            <div class="list-group mb-4">
                <?php foreach ($tenants as $t): ?>
                <a href="<?= BASE_URL ?>/auth/switch-tenant/<?= $t['id'] ?>"
                   class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                    <div>
                        <strong><?= htmlspecialchars($t['nombre']) ?></strong>
                        <br>
                        <small class="text-muted">BD: <?= htmlspecialchars($t['dbname']) ?></small>
                    </div>
                    <i class="bi bi-chevron-right text-muted"></i>
                </a>
                <?php endforeach; ?>
            </div>

            <div class="text-center mt-3">
                <a href="<?= BASE_URL ?>/auth/logout" class="text-muted">
                    <i class="bi bi-arrow-left"></i> Cambiar de cuenta
                </a>
            </div>
        </div>
    </div>
</div>
