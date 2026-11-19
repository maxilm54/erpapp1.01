<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="mb-0"><i class="bi bi-clipboard-check"></i> NP pendientes de producir</h1>
    <a href="<?= BASE_URL ?>/ordenproduccion" class="btn btn-secondary">
        <i class="bi bi-arrow-left"></i> Volver a Producción
    </a>
</div>

<p class="text-muted">
    Notas de Pedido aprobadas con el camino <strong>“Procesar mediante receta en producción”</strong>
    que todavía no tienen una Orden de Producción.
</p>

<?php if (empty($pendientes)): ?>
    <div class="alert alert-info">
        No hay Notas de Pedido pendientes de producir.
    </div>
<?php else: ?>
    <div class="table-scroll">
        <table class="table table-bordered align-middle">
            <thead>
                <tr>
                    <th>NP</th>
                    <th>Cliente</th>
                    <th>Productos</th>
                    <th>Producto a producir</th>
                    <th>Receta / Acción</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pendientes as $p): ?>
                    <?php $eval = $p['evaluacion']; ?>
                    <tr>
                        <td>
                            <a href="<?= BASE_URL ?>/notaspedido/show/<?= (int)$p['id'] ?>">
                                #<?= (int)$p['id'] ?>
                            </a>
                            <div class="small text-muted">
                                <?= date('d/m/Y', strtotime($p['created_at'])) ?>
                            </div>
                        </td>
                        <td><?= htmlspecialchars($p['razon_social']) ?></td>
                        <td><?= htmlspecialchars($p['productos'] ?? '—') ?></td>
                        <td>
                            <?php if (!empty($eval['producto_id'])): ?>
                                <strong><?= htmlspecialchars($eval['producto']) ?></strong>
                                <div class="small text-muted">
                                    Cantidad: <?= number_format((float)$eval['cantidad'], 3, ',', '.') ?>
                                </div>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (empty($eval['producto_id'])): ?>
                                <div class="alert alert-warning py-1 px-2 mb-0 small">
                                    <?= htmlspecialchars($eval['motivo'] ?: 'La NP no se puede procesar automáticamente desde una receta.') ?>
                                </div>
                            <?php else: ?>
                                <?php $recetasProducto = $p['recetas_producto']; ?>

                                <?php if (count($recetasProducto) === 1): ?>
                                    <div class="small mb-1">
                                        Receta: <strong><?= htmlspecialchars($recetasProducto[0]['nombre']) ?></strong>
                                    </div>
                                    <form method="POST"
                                          action="<?= BASE_URL ?>/ordenproduccion/crear-desde-np/<?= (int)$p['id'] ?>">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Csrf::generate()) ?>">
                                        <input type="hidden" name="receta_id" value="<?= (int)$recetasProducto[0]['id'] ?>">
                                        <button class="btn btn-success btn-sm">
                                            <i class="bi bi-play-fill"></i> Iniciar producción
                                        </button>
                                    </form>

                                <?php elseif (count($recetasProducto) > 1): ?>
                                    <form method="POST"
                                          action="<?= BASE_URL ?>/ordenproduccion/crear-desde-np/<?= (int)$p['id'] ?>">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Csrf::generate()) ?>">
                                        <select name="receta_id" class="form-select form-select-sm mb-1" required>
                                            <?php foreach ($recetasProducto as $r): ?>
                                                <option value="<?= (int)$r['id'] ?>"><?= htmlspecialchars($r['nombre']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button class="btn btn-success btn-sm">
                                            <i class="bi bi-play-fill"></i> Iniciar producción
                                        </button>
                                    </form>

                                <?php else: ?>
                                    <div class="small text-muted mb-1">
                                        El producto todavía no tiene una receta activa.
                                    </div>
                                    <?php if (!empty($recetasDisponibles)): ?>
                                        <form method="POST"
                                              action="<?= BASE_URL ?>/ordenproduccion/crear-desde-np/<?= (int)$p['id'] ?>"
                                              class="mb-1">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Csrf::generate()) ?>">
                                            <select name="receta_id" class="form-select form-select-sm mb-1" required>
                                                <option value="">Elegir receta existente...</option>
                                                <?php foreach ($recetasDisponibles as $r): ?>
                                                    <option value="<?= (int)$r['id'] ?>">
                                                        <?= htmlspecialchars($r['nombre']) ?> — <?= htmlspecialchars($r['producto']) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <button class="btn btn-success btn-sm">
                                                <i class="bi bi-play-fill"></i> Usar receta e iniciar producción
                                            </button>
                                        </form>
                                        <div class="small text-muted mb-1">
                                            Si elegís una receta de otro producto, se usa solo para esta OP.
                                        </div>
                                    <?php else: ?>
                                        <div class="small text-warning mb-1">
                                            No hay recetas activas en el sistema.
                                        </div>
                                    <?php endif; ?>
                                    <a href="<?= BASE_URL ?>/recetas/create?producto_id=<?= (int)$eval['producto_id'] ?>&amp;volver=np-pendientes"
                                       class="btn btn-outline-primary btn-sm">
                                        <i class="bi bi-plus-circle"></i> Crear receta para este producto
                                    </a>
                                <?php endif; ?>
                            <?php endif; ?>

                            <a href="<?= BASE_URL ?>/notaspedido/show/<?= (int)$p['id'] ?>"
                               class="btn btn-link btn-sm">Ver NP</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
