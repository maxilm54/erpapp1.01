<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3>Presupuesto #<?= $presupuesto['id'] ?></h3>

        <div>
            <?php if ($presupuesto['estado'] === 'BORRADOR'): ?>
                <a href="<?= BASE_URL ?>/presupuestos/edit/<?= $presupuesto['id'] ?>"
                   class="btn btn-warning">Editar</a>

                <form method="POST"
                      action="<?= BASE_URL ?>/presupuestos/aprobar/<?= $presupuesto['id'] ?>"
                      class="d-inline">
                    <button class="btn btn-success">Aprobar</button>
                </form>
            <?php endif; ?>

            <a href="<?= BASE_URL ?>/presupuestos" class="btn btn-secondary">Volver</a>
            <a href="<?= BASE_URL ?>/presupuestos/volvernp/<?= $presupuesto['id'] ?>" class="btn btn-secondary">NP</a>

            <a href="<?= BASE_URL ?>/presupuestos/pdf/<?= $presupuesto['id'] ?>" target="_blank"
               class="btn btn-outline-danger">
               <i class="bi bi-file-pdf"></i> Descargar PDF
            </a>
            <a href="<?= BASE_URL ?>/presupuestos/regenerar-pdf/<?= $presupuesto['id'] ?>"
               class="btn btn-outline-warning"
               onclick="return confirm('¿Regenerar el PDF del presupuesto? Se sobreescribirá el actual.')">
               <i class="bi bi-arrow-clockwise"></i> Regenerar PDF
            </a>
            <a href="<?= BASE_URL ?>/presupuestos/reenviar/<?= $presupuesto['id'] ?>"
               class="btn btn-outline-primary"
               onclick="return confirm('¿Enviar presupuesto por email al cliente?')">
               <i class="bi bi-envelope"></i> Enviar por Email
            </a>
        </div>
    </div>

    <div class="mb-3">
        <strong>Cliente:</strong> <?= htmlspecialchars($presupuesto['razon_social']) ?><br>
        <strong>Fecha:</strong> <?= date('d/m/Y', strtotime($presupuesto['created_at'])) ?><br>
        <strong>Estado:</strong>
        <span class="badge bg-<?= $presupuesto['estado'] === 'APROBADO' ? 'success' : 'secondary' ?>">
            <?= $presupuesto['estado'] ?>
        </span>
    </div>

    <?php $adjModulo = 'PRESUPUESTO'; $adjDocId = (int)$presupuesto['id'];
          require BASE_PATH . '/app/views/adjuntos/panel.php'; ?>

    <div class="table-scroll mt-3 table-responsive">
        <table class="table table-bordered align-middle">
            <thead class="table-light">
                <tr>
                    <th>Producto</th>
                    <th>Cantidad</th>
                    <th>Precio</th>
                    <th>Subtotal</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
            <?php $total = 0; ?>
            <?php foreach ($presupuesto['detalle'] as $d): 
                $sub = $d['cantidad'] * $d['precio'];
                $total += $sub;
            ?>
                <tr>
                    <td>
                        <?= htmlspecialchars($d['nombre']) ?>
                        <?php if (empty($d['producto_id'])): ?>
                            <span class="badge bg-info">Libre</span>
                        <?php endif; ?>
                    </td>
                    <td><?= number_format($d['cantidad'], 3) ?></td>
                    <td>$<?= number_format($d['precio'], 2) ?></td>
                    <td>$<?= number_format($sub, 2) ?></td>
                    <td>
                        <?php if (empty($d['producto_id'])): ?>
                            <form method="POST"
                                  action="<?= BASE_URL ?>/presupuestos/agregar-producto/<?= $presupuesto['id'] ?>/<?= $d['id'] ?>"
                                  class="d-inline"
                                  onsubmit="return confirm('¿Agregar este producto a la base de productos? Después podrá editarlo en Productos.');">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Csrf::generate()) ?>">
                                <button class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-plus-circle"></i> Agregar a Productos
                                </button>
                            </form>
                        <?php else: ?>
                            <a href="<?= BASE_URL ?>/productos/update/<?= $d['producto_id'] ?>"
                               class="btn btn-sm btn-outline-secondary">
                                <i class="bi bi-pencil"></i> Editar producto
                            </a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <th colspan="4" class="text-end">Total</th>
                    <th>$<?= number_format($total, 2) ?></th>
                </tr>
            </tfoot>
        </table>
    </div>
</div>