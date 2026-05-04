<form method="POST" enctype="multipart/form-data"
      class="card p-4 shadow col-md-8 mx-auto">

    <h4><?= $title ?></h4>
    <input type="hidden" name="csrf_token" value="<?= Csrf::generate() ?>">
    <label class="form-control mb-2"><?= $materia['nombre'] ?></label>
    <label class="form-control mb-2"><?= $materia['sku'] ?></label>
    <label class="form-control mb-2"><?= $materia['nombre_categoria'] ?></label>
    <label class="form-control mb-2"><?= $materia['nombre_unidad'] ?></label>
    <h5>Agregar Nuevo Código de Barra</h5>
    <div id="codigos">
        <div class="row mb-2">
            <input type="hidden" name="id_prod" value="<?= $materia['id'] ?>">
            <div class="col">
                <input class="form-control" name="codigo" placeholder="Código" required>
            </div>
            <div class="col">
                <input type="text" class="form-control" name="tipo" placeholder="Tipo (EAN, Interno)" required>
            </div>
        </div>
    </div>
    <div class="row">
    <div class="col d-flex justify-content-end">
        <a class="btn btn-secondary me-2" href="<?= BASE_URL ?>/materiasprimas">Volver</a>
        <button class="btn btn-success">Guardar</button>
    </div>
    
    
</div>
</form>

