<form method="POST" class="card p-4 col-md-6 mx-auto">
    <h4><?= $title ?></h4>

    <input name="nombre" class="form-control mb-2" required placeholder="Nombre">
    <input name="sku" class="form-control mb-2" required placeholder="SKU">
    <input name="unidad_medida" class="form-control mb-3" required placeholder="kg, g, lt">

    <button class="btn btn-success w-100">Guardar</button>
</form>