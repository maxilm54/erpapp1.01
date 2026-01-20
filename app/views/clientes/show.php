<form class="card p-4 col-md-6 mx-auto shadow">
       <h4 class="mb-3"><?= $title ?></h4>

       <input class="form-control mb-2" value="<?= $cliente['razon_social'] ?? '' ?>" readonly>
       <input class="form-control mb-2" value="<?= $cliente['cuit'] ?? '' ?>" readonly>
       <input class="form-control mb-2" value="<?= $cliente['email'] ?? '' ?>" readonly>
       <input class="form-control mb-2" value="<?= $cliente['telefono'] ?? '' ?>" readonly>
       <input class="form-control mb-3" value="<?= $cliente['localidad'] ?? '' ?>" readonly>
       <input class="form-control mb-3" value="<?= $cliente['direccion'] ?? '' ?>" readonly>
       <input class="form-control mb-3" value="<?= $cliente['contacto'] ?? '' ?>" readonly>
       <input class="form-control mb-3" type="text" value="Es Distribuidor: <?= $cliente['es_Distribuidor'] ?? '' ?>" readonly>
       <textarea  class="form-control mb-3" readonly><?= $cliente['observaciones_gral'] ?? '-' ?></textarea>
       <textarea  class="form-control mb-3" readonly><?= $cliente['obs_financieras'] ?? '-' ?></textarea>
    <div class="col d-flex justify-content-end">
        <a class="btn btn-secondary me-2" href="<?= BASE_URL ?>/clientes">Volver</a>
    </div>
</form>