<h4><i class="bi bi-envelope-gear"></i> Configuración SMTP</h4>
<hr>

<?php if (!empty($_SESSION['success'])): ?>
<div class="alert alert-success"><?= $_SESSION['success']; unset($_SESSION['success']); ?></div>
<?php endif; ?>
<?php if (!empty($_SESSION['error'])): ?>
<div class="alert alert-danger"><?= $_SESSION['error']; unset($_SESSION['error']); ?></div>
<?php endif; ?>

<div class="row">
    <div class="col-md-8">
        <div class="card mb-4">
            <div class="card-header"><strong>Datos del Servidor SMTP</strong></div>
            <div class="card-body">
                <form method="post" action="<?= BASE_URL ?>/email/save-config">
                    <div class="row mb-3">
                        <div class="col-md-8">
                            <label class="form-label">Host SMTP *</label>
                            <input type="text" name="smtp_host" class="form-control" 
                                   value="<?= htmlspecialchars($config['smtp_host'] ?? SMTP_HOST ?? '') ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Puerto *</label>
                            <input type="number" name="smtp_port" class="form-control" 
                                   value="<?= $config['smtp_port'] ?? SMTP_PORT ?? 465 ?>" required>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Seguridad</label>
                            <select name="smtp_secure" class="form-select">
                                <option value="ssl" <?= ($config['smtp_secure'] ?? SMTP_SECURE ?? 'ssl') === 'ssl' ? 'selected' : '' ?>>SSL</option>
                                <option value="tls" <?= ($config['smtp_secure'] ?? '') === 'tls' ? 'selected' : '' ?>>TLS</option>
                                <option value="none" <?= ($config['smtp_secure'] ?? '') === 'none' ? 'selected' : '' ?>>Ninguna</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">BCC (copia oculta)</label>
                            <input type="email" name="bcc_email" class="form-control" 
                                   value="<?= htmlspecialchars($config['bcc_email'] ?? '') ?>" 
                                   placeholder="email@copia.com">
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Usuario SMTP *</label>
                            <input type="text" name="smtp_user" class="form-control" 
                                   value="<?= htmlspecialchars($config['smtp_user'] ?? SMTP_USER ?? '') ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Contraseña SMTP *</label>
                            <input type="password" name="smtp_pass" class="form-control" 
                                   value="<?= htmlspecialchars($config['smtp_pass'] ?? '') ?>"
                                   <?= $config ? '' : 'placeholder="Dejar vacío para mantener la actual"' ?>>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Email del remitente *</label>
                            <input type="email" name="smtp_from_email" class="form-control" 
                                   value="<?= htmlspecialchars($config['smtp_from_email'] ?? SMTP_FROM ?? '') ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Nombre del remitente</label>
                            <input type="text" name="smtp_from_name" class="form-control" 
                                   value="<?= htmlspecialchars($config['smtp_from_name'] ?? SMTP_FROM_NAME ?? '') ?>">
                        </div>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-success">
                            <i class="bi bi-check-lg"></i> Guardar
                        </button>
                        <button type="button" class="btn btn-outline-primary" id="btn-test-smtp">
                            <i class="bi bi-lightning"></i> Probar Conexión
                        </button>
                        <a href="<?= BASE_URL ?>/home" class="btn btn-secondary">Cancelar</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card mb-4">
            <div class="card-header"><strong>Estado Actual</strong></div>
            <div class="card-body">
                <?php if ($config): ?>
                    <p><span class="badge bg-success">Configurado en BD</span></p>
                    <p><strong>Host:</strong> <?= htmlspecialchars($config['smtp_host']) ?></p>
                    <p><strong>Puerto:</strong> <?= $config['smtp_port'] ?></p>
                    <p><strong>Seguridad:</strong> <?= strtoupper($config['smtp_secure']) ?></p>
                    <p><strong>Usuario:</strong> <?= htmlspecialchars($config['smtp_user']) ?></p>
                    <p><strong>Remitente:</strong> <?= htmlspecialchars($config['smtp_from_email']) ?></p>
                    <p><strong>Nombre:</strong> <?= htmlspecialchars($config['smtp_from_name']) ?></p>
                    <?php if (!empty($config['bcc_email'])): ?>
                        <p><strong>BCC:</strong> <?= htmlspecialchars($config['bcc_email']) ?></p>
                    <?php endif; ?>
                <?php else: ?>
                    <p><span class="badge bg-warning text-dark">Usando configuración del .env</span></p>
                    <p class="text-muted small">No hay configuración SMTP personalizada para esta empresa. Se están usando las credenciales globales del sistema.</p>
                    <p><strong>Host:</strong> <?= htmlspecialchars(SMTP_HOST ?? '') ?></p>
                    <p><strong>Remitente:</strong> <?= htmlspecialchars(SMTP_FROM ?? '') ?></p>
                <?php endif; ?>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><strong>Ayuda</strong></div>
            <div class="card-body">
                <p class="small text-muted">
                    Configure las credenciales SMTP de su empresa para enviar emails desde el sistema.
                </p>
                <p class="small text-muted">
                    <strong>Gmail:</strong> Host: <code>smtp.gmail.com</code>, Puerto: <code>587</code>, TLS. Use una <em>Contraseña de Aplicación</em> en vez de su contraseña normal.
                </p>
                <p class="small text-muted">
                    <strong>Outlook:</strong> Host: <code>smtp.office365.com</code>, Puerto: <code>587</code>, TLS.
                </p>
                <p class="small text-muted">
                    Deje la contraseña vacía al editar si no desea cambiarla.
                </p>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('btn-test-smtp').addEventListener('click', function() {
    const form = document.querySelector('form');
    const formData = new FormData(form);
    
    this.disabled = true;
    this.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Probando...';
    
    fetch('<?= BASE_URL ?>/email/test-smtp', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            Swal.fire('Éxito', data.message, 'success');
        } else {
            Swal.fire('Error', data.message, 'error');
        }
    })
    .catch(() => {
        Swal.fire('Error', 'No se pudo probar la conexión', 'error');
    })
    .finally(() => {
        this.disabled = false;
        this.innerHTML = '<i class="bi bi-lightning"></i> Probar Conexión';
    });
});
</script>
