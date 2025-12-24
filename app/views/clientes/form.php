<form method="POST" class="card p-4 col-md-6 mx-auto shadow">
    <h4 class="mb-3"><?= $title ?></h4>

    <input class="form-control mb-2" name="razon_social"
           value="<?= $cliente['razon_social'] ?? '' ?>" required
           placeholder="Razón Social">

    <input class="form-control mb-2" name="cuit"
           value="<?= $cliente['cuit'] ?? '' ?>" required
           placeholder="CUIT">

    <input class="form-control mb-2" name="email"
           value="<?= $cliente['email'] ?? '' ?>" placeholder="Email">

    <input class="form-control mb-2" name="telefono"
           value="<?= $cliente['telefono'] ?? '' ?>" placeholder="Teléfono">

    <input class="form-control mb-3" name="direccion"
           value="<?= $cliente['direccion'] ?? '' ?>" placeholder="Dirección">

    <button class="btn btn-success w-100">Guardar</button>
</form>