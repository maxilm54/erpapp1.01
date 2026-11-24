<?php
/**
 * Resumen de una receta libre para mostrarla en el presupuesto o la nota de
 * pedido. Variables esperadas:
 *   $resumen      array devuelto por Receta::resumenCostos()
 *   $titulo       texto opcional del encabezado
 *   $volverUrl    URL opcional para volver al documento
 *   $precioLinea  precio unitario de la linea (opcional, para ver margen)
 */
if (empty($resumen) || empty($resumen['receta'])) {
    return;
}
$recetaResumen = $resumen['receta'];
$titulo = $titulo ?? 'Receta del ítem libre';
$precioLinea = isset($precioLinea) ? (float)$precioLinea : null;
$costoTotal = (float)$resumen['costo_total'];
$ganancia = $precioLinea !== null ? $precioLinea - $costoTotal : null;
?>
<div class="card border-info mb-3">
    <div class="card-header bg-info-subtle d-flex justify-content-between align-items-center">
        <div>
            <strong><i class="bi bi-book"></i> <?= htmlspecialchars($titulo) ?>:</strong>
            <?= htmlspecialchars($recetaResumen['nombre']) ?>
            <span class="badge bg-info text-dark ms-1">Ítem libre</span>
        </div>
        <div>
            <a href="<?= BASE_URL ?>/recetas/show/<?= (int)$recetaResumen['id'] ?>"
               class="btn btn-sm btn-outline-primary">
                Ver receta
            </a>
            <?php if (!empty($volverUrl)): ?>
                <a href="<?= htmlspecialchars($volverUrl) ?>" class="btn btn-sm btn-outline-secondary">Volver</a>
            <?php endif; ?>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Insumo</th>
                        <th class="text-end">Cantidad</th>
                        <th>Unidad</th>
                        <th class="text-end">Precio estimado</th>
                        <th class="text-end">Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($resumen['items'])): ?>
                        <tr><td colspan="5" class="text-center text-muted">La receta no tiene insumos cargados.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($resumen['items'] as $item): ?>
                        <tr>
                            <td>
                                <span class="badge bg-<?= $item['tipo'] === 'PRODUCTO' ? 'info text-dark' : 'secondary' ?>">
                                    <?= $item['tipo'] === 'PRODUCTO' ? 'Producto' : 'MP' ?>
                                </span>
                                <?= htmlspecialchars($item['nombre']) ?>
                            </td>
                            <td class="text-end"><?= number_format((float)$item['cantidad'], 3, ',', '.') ?></td>
                            <td><?= htmlspecialchars($item['unidad_medida'] ?? '-') ?></td>
                            <td class="text-end">
                                <?= $item['precio_unit'] !== null ? '$ ' . number_format((float)$item['precio_unit'], 2, ',', '.') : '<span class="text-muted">Sin precio</span>' ?>
                            </td>
                            <td class="text-end">
                                <?= $item['subtotal'] !== null ? '<strong>$ ' . number_format((float)$item['subtotal'], 2, ',', '.') . '</strong>' : '<span class="text-muted">-</span>' ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot class="table-light">
                    <tr>
                        <td colspan="4" class="text-end">Costo insumos:</td>
                        <td class="text-end">$ <?= number_format((float)$resumen['costo_insumos'], 2, ',', '.') ?></td>
                    </tr>
                    <?php if ((float)($resumen['costo_productos'] ?? 0) > 0): ?>
                    <tr>
                        <td colspan="4" class="text-end">Costo productos componentes:</td>
                        <td class="text-end">$ <?= number_format((float)$resumen['costo_productos'], 2, ',', '.') ?></td>
                    </tr>
                    <?php endif; ?>
                    <tr>
                        <td colspan="4" class="text-end">Costo fijo de procesos:</td>
                        <td class="text-end">$ <?= number_format((float)$resumen['costo_procesos'], 2, ',', '.') ?></td>
                    </tr>
                    <tr>
                        <td colspan="4" class="text-end"><strong>Costo total de fabricación por unidad:</strong></td>
                        <td class="text-end"><strong class="fs-6">$ <?= number_format($costoTotal, 2, ',', '.') ?></strong></td>
                    </tr>
                    <?php if ($precioLinea !== null): ?>
                        <tr>
                            <td colspan="4" class="text-end">Precio unitario de la línea:</td>
                            <td class="text-end">$ <?= number_format($precioLinea, 2, ',', '.') ?></td>
                        </tr>
                        <tr>
                            <td colspan="4" class="text-end">Diferencia (precio - costo):</td>
                            <td class="text-end">
                                <strong class="<?= $ganancia >= 0 ? 'text-success' : 'text-danger' ?>">
                                    $ <?= number_format($ganancia, 2, ',', '.') ?>
                                </strong>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tfoot>
            </table>
        </div>
        <?php if (!empty($resumen['sin_precio'])): ?>
            <div class="px-3 py-2 text-muted small border-top">
                Algunos insumos no tienen precio de compra registrado: el costo total puede estar incompleto.
            </div>
        <?php endif; ?>
    </div>
</div>
