<div class="card p-4 shadow col-md-6 mx-auto">
    <h4><?= $title ?></h4>
    <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Csrf::generate()) ?>">

        <label class="form-label">Nombre de la Categoría</label>
        <input class="form-control mb-3" name="categoria_nombre" required placeholder="Ej: Alimenticia"
               value="<?= htmlspecialchars($registro['categoria_nombre'] ?? '') ?>">

        <div class="d-flex justify-content-end">
            <a class="btn btn-secondary me-2" href="<?= BASE_URL ?>/categoriamaterial">Volver</a>
            <button class="btn btn-success">Guardar</button>
        </div>
    </form>
</div>
