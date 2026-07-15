<div class="d-flex justify-content-between align-items-center mb-3">
    <h3><i class="bi bi-journal-text"></i> <?= htmlspecialchars($title) ?></h3>
    <a href="<?= BASE_URL ?>/contabilidad/asiento-create" class="btn btn-primary">
        <i class="bi bi-plus-lg"></i> Nuevo Asiento
    </a>
</div>

<!-- Filtros -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" action="<?= BASE_URL ?>/contabilidad/asientos" class="row g-2 align-items-end">
            <div class="col-md-2">
                <label class="form-label form-label-sm">Desde</label>
                <input type="date" name="fecha_desde" class="form-control form-control-sm"
                       value="<?= htmlspecialchars($filters['fecha_desde'] ?? '') ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label form-label-sm">Hasta</label>
                <input type="date" name="fecha_hasta" class="form-control form-control-sm"
                       value="<?= htmlspecialchars($filters['fecha_hasta'] ?? '') ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label form-label-sm">Tipo</label>
                <select name="tipo" class="form-select form-select-sm">
                    <option value="">Todos</option>
                    <?php foreach (['OPERACION','APERTURA','CIERRE','AJUSTE'] as $t): ?>
                        <option value="<?= $t ?>" <?= ($filters['tipo'] ?? '') === $t ? 'selected' : '' ?>><?= $t ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label form-label-sm">Buscar</label>
                <input type="text" name="buscar" class="form-control form-control-sm"
                       placeholder="Descripción..." value="<?= htmlspecialchars($filters['buscar'] ?? '') ?>">
            </div>
            <div class="col-md-3 d-flex gap-1">
                <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-search"></i> Filtrar</button>
                <a href="<?= BASE_URL ?>/contabilidad/asientos" class="btn btn-sm btn-outline-secondary">Limpiar</a>
            </div>
        </form>
    </div>
</div>

<!-- Tabla de asientos -->
<div class="table-responsive">
<table class="table table-striped table-hover">
    <thead class="table-dark">
        <tr>
            <th>#</th>
            <th>Fecha</th>
            <th>Descripción</th>
            <th>Tipo</th>
            <th class="text-end">Debe</th>
            <th class="text-end">Haber</th>
            <th>Registrado por</th>
            <th></th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($asientos as $a):
            $totalDebe = 0;
            $totalHaber = 0;
        ?>
        <tr>
            <td><strong>#<?= $a['numero'] ?></strong></td>
            <td><?= date('d/m/Y', strtotime($a['fecha'])) ?></td>
            <td><?= htmlspecialchars(mb_substr($a['descripcion'], 0, 60)) ?></td>
            <td><span class="badge bg-secondary"><?= $a['tipo'] ?></span></td>
            <td class="text-end">$ <?= number_format($a['total_debe'] ?? 0, 2, ',', '.') ?></td>
            <td class="text-end">$ <?= number_format($a['total_haber'] ?? 0, 2, ',', '.') ?></td>
            <td><?= htmlspecialchars($a['usuario_nombre']) ?></td>
            <td>
                <a href="<?= BASE_URL ?>/contabilidad/asientos/show/<?= $a['id'] ?>" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-eye"></i>
                </a>
            </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($asientos)): ?>
        <tr>
            <td colspan="8" class="text-center text-muted py-4">
                No se encontraron asientos contables.
            </td>
        </tr>
        <?php endif; ?>
    </tbody>
</table>
</div>
