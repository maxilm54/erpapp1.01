<h3><?= $title ?></h3>

<?php $action = $deposito ? BASE_URL . "/depositos/edit/{$deposito['id']}" : BASE_URL . '/depositos/create'; ?>
<form method="POST" action="<?= $action ?>" class="card p-4 col-md-6 mx-auto">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Csrf::generate()) ?>">

    <div class="mb-3">
        <label class="form-label">Nombre *</label>
        <input type="text" name="nombre" class="form-control" required
               value="<?= htmlspecialchars($deposito['nombre'] ?? '') ?>">
    </div>

    <div class="mb-3">
        <label class="form-label">Descripción</label>
        <textarea name="descripcion" class="form-control" rows="3"><?= htmlspecialchars($deposito['descripcion'] ?? '') ?></textarea>
    </div>

    <button class="btn btn-success w-100">Guardar</button>
    <a href="<?= BASE_URL ?>/depositos" class="btn btn-secondary w-100 mt-2">Cancelar</a>
</form>