<h3 class="mb-3"><?= $title ?></h3>

<form method="POST" class="card p-4 col-md-6 mx-auto">

<div class="mb-3">
    <label class="form-label">Razón Social</label>
    <input type="text"
           name="razon_social"
           class="form-control"
           required
           value="<?= $proveedor['razon_social'] ?? '' ?>">
</div>

<div class="mb-3">
    <label class="form-label">CUIT</label>
    <input type="text"
           name="cuit"
           class="form-control"
           value="<?= $proveedor['cuit'] ?? '' ?>">
</div>

<div class="mb-3">
    <label class="form-label">Email</label>
    <input type="email"
           name="email"
           class="form-control"
           value="<?= $proveedor['email'] ?? '' ?>">
</div>

<div class="mb-3">
    <label class="form-label">Teléfono</label>
    <input type="text"
           name="telefono"
           class="form-control"
           value="<?= $proveedor['telefono'] ?? '' ?>">
</div>

<button class="btn btn-success w-100">
    Guardar
</button>

</form>