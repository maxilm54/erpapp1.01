<h3 class="mb-3"><?= $title ?></h3>

<form method="POST" class="card p-4 col-md-6 mx-auto">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Csrf::generate()) ?>">
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

    <div class="mb-3">
        <label class="form-label">Contacto</label>
        <input type="text"
            name="contacto"
            class="form-control"
            value="<?= $proveedor['contacto'] ?? '' ?>">
    </div>
    <div class="mb-3">
        <label class="form-label">Rubro</label>
        <select name="rubro" id="" class="select form-control">
            <option value="Mani">Mani</option>
            <option value="Envases">Envases</option>
            <option value="Etiquetas">Etiquetas</option>
            <option value="Maquinas">Maquinas</option>
            <option value="Suplementos">Suplementos</option>
            <option value="Limpieza">Limpieza</option>
        </select>
    </div>

    <div class="mb-3">
        <label class="form-label">Localidad</label>
        <input type="text"
            name="localidad"
            class="form-control"
            value="<?= $proveedor['localidad'] ?? '' ?>">
    </div>

    <div class="mb-3">
        <label class="form-label">Direccion</label>
        <input type="text"
            name="direccion"
            class="form-control"
            value="<?= $proveedor['direccion'] ?? '' ?>">
    </div>

    <button class="btn btn-success w-100">
        Guardar
    </button>

</form>