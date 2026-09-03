<div class="card p-4 shadow col-md-6 mx-auto">
    <h4><?= $title ?></h4>
    <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Csrf::generate()) ?>">

        <label class="form-label">Nombre</label>
        <input class="form-control mb-3" name="nombre" required placeholder="Ej: kg, un, lt"
               value="<?= htmlspecialchars($registro['nombre'] ?? '') ?>">

        <label class="form-label">Detalle</label>
        <input class="form-control mb-3" name="detalle" placeholder="Ej: Kilogramos, Unidades, Litros"
               value="<?= htmlspecialchars($registro['detalle'] ?? '') ?>">

        <div class="d-flex justify-content-end">
            <a class="btn btn-secondary me-2" href="<?= BASE_URL ?>/unidadmedida">Volver</a>
            <button class="btn btn-success">Guardar</button>
        </div>
    </form>
</div>
