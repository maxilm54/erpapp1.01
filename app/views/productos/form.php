<div class="card p-4 shadow col-md-8 mx-auto">
    <h4><?= $title ?></h4>
    <form method="POST" enctype="multipart/form-data">    
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Csrf::generate()) ?>">
        <input class="form-control mb-2" name="nombre" placeholder="Nombre" required>
        <input class="form-control mb-2" name="sku" placeholder="SKU" required>
        <textarea class="form-control mb-2" name="descripcion" placeholder="Descripción"></textarea>
        <input class="form-control mb-2" name="precio_venta" type="number" step="0.01" required placeholder="Precio de Venta ($)">

        <select class="form-control mb-2" name="unidad_medida" required>
            <option value="">Seleccionar Unidad de Medida</option>
            <?php foreach ($umedida as $um): ?>
                <option value="<?= htmlspecialchars($um['id_medida']) ?>"><?= htmlspecialchars($um['nombre']) . " - " . htmlspecialchars($um['detalle']) ?></option>
            <?php endforeach; ?>
        </select>

        <label for="imagen">Imagen:</label>
        <input class="form-control mb-3" type="file" name="imagen">

        <h5>Códigos de Barra</h5>

        <div id="codigos">
            <div class="row mb-2">
                <div class="col">
                    <input class="form-control" name="codigos[]" placeholder="Código">
                </div>
                <div class="col">
                    <input class="form-control" name="tipos[]" placeholder="Tipo (EAN, Interno)">
                </div>
            </div>
        </div>

        <button type="button" class="btn btn-secondary w-50 mb-3" onclick="agregarCodigo()">Agregar código</button>

        <button class="btn btn-success w-50 mb-3">Guardar</button>
    </form>
<a href="<?= BASE_URL ?>/productos" class="btn btn-primary mb-3 w-50">Volver</a>
</div>
<script>
function agregarCodigo() {
    document.getElementById('codigos').insertAdjacentHTML(
        'beforeend',
        `<div class="row mb-2">
            <div class="col">
                <input class="form-control" name="codigos[]" placeholder="Código">
            </div>
            <div class="col">
                <input class="form-control" name="tipos[]" placeholder="Tipo">
            </div>
        </div>`
    );
}
</script>