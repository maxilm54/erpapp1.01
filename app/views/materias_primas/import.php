<?php if ($preview === null): ?>

<div class="card p-4 shadow col-md-8 mx-auto">
    <h4><i class="bi bi-upload"></i> <?= $title ?></h4>
    <p class="text-muted">Subí un archivo .xlsx con las materias primas a importar.</p>

    <div class="alert alert-info">
        <strong>Formato esperado del archivo (columnas):</strong>
        <code>nombre</code>, <code>sku</code>, <code>unidad_medida</code> (opcional), <code>categoria</code> (opcional), <code>barcode</code> (opcional).
        <br>
        <small>La primera fila debe ser el encabezado. La <strong>unidad_medida</strong> se matchea por nombre (ej: "kg", "un"). Si no coincide, usa "Unidad" por defecto. La <strong>categoria</strong> se matchea por nombre (ej: "alimenticia"). Si no coincide, queda vacía.</small>
    </div>

    <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
        <div class="mb-3">
            <label for="archivo" class="form-label">Archivo Excel (.xlsx)</label>
            <input class="form-control" type="file" name="archivo" id="archivo" accept=".xlsx" required>
        </div>
        <button type="submit" class="btn btn-primary">
            <i class="bi bi-upload"></i> Subir y Previsualizar
        </button>
        <a href="<?= BASE_URL ?>/materiasprimas" class="btn btn-secondary">Volver</a>
    </form>
</div>

<?php else: ?>

<div class="card p-4 shadow">
    <h4><i class="bi bi-table"></i> Vista Previa - Importar Materias Primas</h4>
    <?php
        $validos = array_filter($preview, fn($p) => empty($p['_error']));
        $conError = array_filter($preview, fn($p) => !empty($p['_error']));
        $totalArch = count($preview);
    ?>
    <div class="row mb-3">
        <div class="col-md-3">
            <span class="badge bg-secondary fs-6">Total filas: <?= $totalArch ?></span>
        </div>
        <div class="col-md-3">
            <span class="badge bg-success fs-6">Válidos: <?= count($validos) ?></span>
        </div>
        <div class="col-md-3">
            <span class="badge bg-danger fs-6">Con error: <?= count($conError) ?></span>
        </div>
    </div>

    <?php if (!empty($conError)): ?>
    <div class="alert alert-warning">
        <i class="bi bi-exclamation-triangle"></i>
        Hay <?= count($conError) ?> filas con errores. Revisá los datos antes de confirmar.
        Las filas con errores serán omitidas durante la importación.
    </div>
    <?php endif; ?>

    <div class="table-responsive" style="max-height: 500px; overflow-y: auto;">
        <table class="table table-striped table-bordered table-sm">
            <thead class="table-dark sticky-top">
                <tr>
                    <th>#</th>
                    <th>Nombre</th>
                    <th>SKU</th>
                    <th>Unidad Medida</th>
                    <th>Categoría</th>
                    <th>Barcode</th>
                    <th>Estado</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($preview as $p): ?>
                <tr class="<?= !empty($p['_error']) ? 'table-danger' : '' ?>">
                    <td><?= $p['_row'] ?></td>
                    <td><?= htmlspecialchars($p['nombre']) ?></td>
                    <td><?= htmlspecialchars($p['sku']) ?></td>
                    <td><?= htmlspecialchars($p['unidad_medida_text'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($p['categoria_text'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($p['barcode'] ?? '-') ?></td>
                    <td>
                        <?php if (empty($p['_error'])): ?>
                            <span class="badge bg-success">OK</span>
                        <?php else: ?>
                            <span class="text-danger small">
                                <i class="bi bi-x-circle"></i> <?= htmlspecialchars($p['_error']) ?>
                            </span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <form method="POST" class="mt-3">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
        <?php if (!empty($validos)): ?>
        <button type="submit" name="confirmar" value="1" class="btn btn-success">
            <i class="bi bi-check-lg"></i> Confirmar Importación (<?= count($validos) ?> materias primas)
        </button>
        <?php else: ?>
        <button type="submit" class="btn btn-success" disabled>
            <i class="bi bi-check-lg"></i> No hay materias primas válidas para importar
        </button>
        <?php endif; ?>
        <a href="<?= BASE_URL ?>/materiasprimas/import" class="btn btn-warning">
            <i class="bi bi-arrow-left"></i> Subir otro archivo
        </a>
        <a href="<?= BASE_URL ?>/materiasprimas" class="btn btn-secondary">Cancelar</a>
    </form>
</div>

<?php endif; ?>
