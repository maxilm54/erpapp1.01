<!-- Encabezado con título y badge -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1>Registro de Avances para Orden de Producción #<?= $orden['id'] ?></h1>
    <span class="badge bg-warning text-dark"><?= $orden['estado'] ?></span>
</div>

<?php
// Calcular totales: solo los avances confirmados cuentan como producidos.
$totalProducido = 0;
$totalPendiente = 0;
foreach ($orden_det as $r) {
    if (!empty($r['confirma_produccion'])) {
        $totalProducido += (float)$r['cantidad_producida'];
    } else {
        $totalPendiente += (float)$r['cantidad_producida'];
    }
}
$cantidadPedida = (float)$orden['cantidad'];
$faltanteParaCargar = max(0, $cantidadPedida - $totalProducido - $totalPendiente);
$produccionCompleta = $totalProducido >= $cantidadPedida - 0.0005;
?>

<!-- Bloque de dos columnas: info izquierda | botón/leyenda derecha -->
<div class="row">
    <div class="col-md-8">
        <p>Creado por: <?= htmlspecialchars($orden['nombre_user']) ?></p>
        <p>Fecha creada: <?= ($orden['created_at']) ?> - Fecha entrega: <?= ($orden['fecha_entrega']) ?></p>
        <p>
            <strong>Producto:</strong> <?= htmlspecialchars($orden['producto']) ?>
            <strong class="badge bg-warning text-dark fs-6 ml-2">
                Cantidad a Producir: <?= number_format($orden['cantidad'], 2) ?>
            </strong>
        </p>
        <p><strong>Estado:</strong> <?= $orden['estado'] ?></p>
        <p><strong>Cantidad Producida (confirmada): </strong><?= number_format($totalProducido, 2, ',', '.') ?></p>
        <?php if ($totalPendiente > 0): ?>
            <p><strong>Pendiente de confirmar: </strong><?= number_format($totalPendiente, 2, ',', '.') ?></p>
        <?php endif; ?>

        <?php if ($orden['observaciones']): ?>
        <p><strong>Observaciones:</strong><br>
            <?= nl2br(htmlspecialchars($orden['observaciones'])) ?>
        </p>
        <?php endif ?>
    </div>

    <!-- Columna derecha: botón o leyenda -->
    <div class="col-md-4 d-flex align-items-center justify-content-center">
        <?php if ($produccionCompleta): ?>
            <div class="alert alert-success text-center w-100">
                ✅ <strong>Producción Completa</strong><br>
                <small>Se alcanzó la cantidad pedida.</small>
            </div>
        <?php elseif ($faltanteParaCargar > 0.0005): ?>
            <button type="button" class="btn btn-primary btn-sm btn-lg w-50" data-bs-toggle="modal" data-bs-target="#modalProduccion">
                &#10010; Iniciar Producción
            </button>
        <?php else: ?>
            <div class="alert alert-warning text-center w-100">
                ⚠ <strong>Hay avances pendientes</strong><br>
                <small>Confirmá los avances cargados antes de agregar otro.</small>
            </div>
        <?php endif ?>
    </div>
</div>
<!-- Modal Iniciar Producción -->
<div class="modal fade" id="modalProduccion" tabindex="-1" aria-labelledby="modalProduccionLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalProduccionLabel">
                    Agregar Producción — Orden #<?= $orden['id'] ?> | <?= htmlspecialchars($orden['producto']) ?>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Csrf::generate()) ?>">
                <div class="modal-body">
                    <input type="hidden" name="orden_id" value="<?= $orden['id'] ?>">
                    <input type="hidden" name="receta_id" value="<?= $orden['receta_id'] ?>">
                    <input type="hidden" name="csrf" value="<?= $csrf ?>">
                    <input type="hidden" name="producto_id" value="<?= $orden['producto_id'] ?>">
                    <input type="hidden" name="cantidad_faltante" value="<?= number_format($faltanteParaCargar, 3, '.', '') ?>">

                    <div class="mb-3">
                        <label class="form-label" for="cantidad_producida">Cantidad a cargar <span class="text-muted">(máx: <?= number_format($faltanteParaCargar, 3, ',', '.') ?>)</span></label>
                        <input id="cantidad_producida" name="cantidad_producida" type="number" step="0.001" min="0.001" max="<?= number_format($faltanteParaCargar, 3, '.', '') ?>" class="form-control" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="obaservaciones">Observaciones</label>
                        <input id="obaservaciones" name="observaciones" type="text" class="form-control">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-danger">Producir</button>
                </div>
            </form>
        </div>
    </div>
</div>

<hr>

<!-- Tabla de avances -->
<div class="table-scroll mt-3">
    <table class="table table-bordered">
        <thead>
            <tr>
                <th>Producto</th>
                <th>Fecha inicio</th>
                <th>Cantidad</th>
                <th>Operario</th>
                <th>Fecha Finalizado</th>
                <th>Obs</th>
                <th>Confirmar</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($orden_det as $r): ?>
            <tr>
                <td><?= htmlspecialchars($r['producto']) ?></td>
                <td><?= $r['registred_at'] ?></td>
                <td><?= number_format($r['cantidad_producida'], 2, ',', '.') ?></td>
                <td><?= htmlspecialchars($r['nombre_user']) ?></td>
                <td>
                    <?php
                    if($r['confirma_produccion']==null){?>
                        <span class="badge bg-warning text-dark">En Produccion</span>
                    <?php
                        }elseif($r['confirma_produccion']!=null){
                            echo '<span class="badge bg-success text-light">'.$r['confirma_produccion'].'</span>';
                        }
                    ?>
                </td>
                <td><?= htmlspecialchars($r['observaciones']) ?></td>
                <td>
                    <?php
                        if($r['confirma_produccion']==null){?>
                            <a href="<?= BASE_URL ?>/ordenproduccion/addavance/<?= $r['id_tbl_ordendetalle']?>" class="btn btn-sm btn-primary"><i class="bi bi-plus-lg"></i></a>
                    <?php
                        }elseif($r['confirma_produccion']!=null){
                            echo '<span class="badge btn-sm bg-success text-light"><i class="bi bi-check-lg"></i></span>';
                        }
                    ?>
                </td>
            </tr>
            <?php endforeach ?>
            <tr>
                <td colspan="6" class="text-end"><strong>Total Producido (confirmado):</strong></td>
                <td><strong><?= number_format($totalProducido, 2, ',', '.') ?></strong></td>
            </tr>
            <tr>
                <td colspan="6" class="text-end"><strong>Pendiente de confirmar:</strong></td>
                <td><strong><?= number_format($totalPendiente, 2, ',', '.') ?></strong></td>
            </tr>
            <tr>
                <td colspan="6" class="text-end"><strong>Disponible para cargar:</strong></td>
                <td><strong><?= number_format($faltanteParaCargar, 2, ',', '.') ?></strong></td>
            </tr>
        </tbody>
    </table>
</div>

<a href="<?= BASE_URL ?>/ordenproduccion" class="btn btn-secondary">Volver</a>