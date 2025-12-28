<form method="POST" class="card p-4 col-md-6 mx-auto">
    <h4>Producción</h4>

    <input name="producto_id" class="form-control mb-2"
           placeholder="ID Producto" required>

    <input name="cantidad" type="number" step="0.001"
           class="form-control mb-3" required>

    <button class="btn btn-danger w-100">
        Producir
    </button>
</form>