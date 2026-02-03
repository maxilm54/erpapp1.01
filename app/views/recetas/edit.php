<h1>Editar Receta - <?= $receta['id'] ?></h1>
 <div class="container">
<form method="post" class="gap-2">
    <input type="hidden" name="receta_id" class="form-control" value="<?= $receta['id'] ?>">
    <input type="hidden" name="producto_id" class="form-control" value="<?= $receta['producto_id'] ?>">
    <label>Producto Final - <?= $receta['producto'] ?></label>
    <label>Nombre:</label>
    <input type="text" class="form-control mb-3" required name="nombre" value="<?= $receta['nombre'] ?>">
    <hr>

    <h5>Insumos</h5>
   
    <?php
        foreach($receta['detalle'] as $det){
            echo '<div class="row">
                    <div class="col-md-3">'.$det['nombre'].'</div>
                    <div class="col-md-3"><input step="0.001" class="form-control w-50" type="number" name="items['.$det['materia_prima_id'].']" value="'.$det['cantidad'].'"></div>
                </div>';
        }
    ?>
    <label>Indique un procedimiento de fabricacion u obsercion, en caso de ser necesario:</label>
    <textarea name="procedimiento" class="form-control" rows="4"><?= $receta['procedimiento'] ?? '' ?></textarea>
    <br>
    <button class="btn btn-success">Guardar Receta</button>
    <a href="<?= BASE_URL ?>/recetas" class="btn btn-secondary">Cancelar</a>
</form>
  </div>