<div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="mb-0"><i class="bi bi-check2-square"></i> <?= htmlspecialchars($title) ?></h3>
    <?php if (!empty($masterPendientes)): ?>
    <span class="badge bg-warning text-dark">
        <i class="bi bi-exclamation-triangle"></i>
        <?= (int)$masterPendientes ?> migracion(es) master pendiente(s)
    </span>
    <?php endif; ?>
</div>

<?php if (!empty($error)): ?>
<div class="alert alert-warning">
    <i class="bi bi-exclamation-triangle"></i> <?= htmlspecialchars($error) ?>
    <a href="<?= BASE_URL ?>/admin/migrations" class="alert-link">Ir a Migraciones</a>
</div>
<?php endif; ?>

<!-- Resumen -->
<div class="row mb-3">
    <div class="col-md-4">
        <div class="card border-warning">
            <div class="card-body py-2 text-center">
                <div class="small text-muted">Pendientes</div>
                <div class="fs-4 fw-bold text-warning"><?= (int)$pendientes ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-success">
            <div class="card-body py-2 text-center">
                <div class="small text-muted">Completadas (filtro actual)</div>
                <div class="fs-4 fw-bold text-success">
                    <?= count(array_filter($tareas, fn($t) => $t['estado'] === 'HECHA')) ?>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-primary">
            <div class="card-body py-2 text-center">
                <div class="small text-muted">Total (filtro actual)</div>
                <div class="fs-4 fw-bold text-primary"><?= count($tareas) ?></div>
            </div>
        </div>
    </div>
</div>

<!-- Alta de tarea -->
<div class="card mb-3">
    <div class="card-header bg-primary text-white">
        <h5 class="mb-0"><i class="bi bi-plus-circle"></i> Nueva Tarea</h5>
    </div>
    <div class="card-body">
        <form method="POST" action="<?= BASE_URL ?>/admin/tareas-create" class="row g-2 align-items-end">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Csrf::generate()) ?>">

            <div class="col-md-6">
                <label class="form-label mb-0 small">Descripcion *</label>
                <input type="text" class="form-control form-control-sm" name="texto" required maxlength="500"
                       placeholder="Ej: Correr la migracion 021 en el tenant Pistachos">
            </div>
            <div class="col-md-3">
                <label class="form-label mb-0 small">Empresa (opcional)</label>
                <select name="tenant_id" class="form-select form-select-sm">
                    <option value="">General (sin empresa)</option>
                    <?php foreach ($tenants as $t): ?>
                    <option value="<?= (int)$t['id'] ?>"><?= htmlspecialchars($t['nombre']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <button class="btn btn-success btn-sm w-100"><i class="bi bi-plus-lg"></i> Crear Tarea</button>
            </div>
        </form>
    </div>
</div>

<!-- Filtros -->
<div class="card mb-3">
    <div class="card-body py-2">
        <form method="get" action="<?= BASE_URL ?>/admin/tareas" class="row g-2 align-items-end">
            <div class="col-auto">
                <label class="form-label mb-0 small">Estado</label>
                <select name="estado" class="form-select form-select-sm">
                    <option value="">Todos</option>
                    <option value="PENDIENTE" <?= ($filtros['estado'] ?? '') === 'PENDIENTE' ? 'selected' : '' ?>>Pendiente</option>
                    <option value="HECHA" <?= ($filtros['estado'] ?? '') === 'HECHA' ? 'selected' : '' ?>>Hecha</option>
                </select>
            </div>
            <div class="col-auto">
                <label class="form-label mb-0 small">Empresa</label>
                <select name="tenant_id" class="form-select form-select-sm">
                    <option value="">Todas</option>
                    <?php foreach ($tenants as $t): ?>
                    <option value="<?= (int)$t['id'] ?>" <?= (string)($filtros['tenant_id'] ?? '') === (string)$t['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($t['nombre']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-auto">
                <label class="form-label mb-0 small">Buscar</label>
                <input type="text" name="q" class="form-control form-control-sm" placeholder="Texto de la tarea..."
                       value="<?= htmlspecialchars($filtros['q'] ?? '') ?>">
            </div>
            <div class="col-auto">
                <button class="btn btn-primary btn-sm"><i class="bi bi-search"></i> Filtrar</button>
                <a href="<?= BASE_URL ?>/admin/tareas" class="btn btn-secondary btn-sm">Limpiar</a>
            </div>
        </form>
    </div>
</div>

<!-- Listado -->
<div class="card">
    <div class="card-header bg-dark text-white">
        <h5 class="mb-0"><i class="bi bi-list-check"></i> Listado</h5>
    </div>
    <div class="card-body">
        <?php if (empty($tareas)): ?>
            <p class="text-muted">No hay tareas para este filtro.</p>
        <?php else: ?>
        <table class="table table-hover">
            <thead>
                <tr>
                    <th>Tarea</th>
                    <th>Empresa</th>
                    <th>Estado</th>
                    <th>Creada por</th>
                    <th>Fecha</th>
                    <th>Completada</th>
                    <th class="text-end">Acciones</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($tareas as $t): ?>
                <tr class="<?= $t['estado'] === 'HECHA' ? 'table-light' : '' ?>">
                    <td><?= htmlspecialchars($t['texto']) ?></td>
                    <td><?= htmlspecialchars($t['tenant_nombre'] ?? 'General') ?></td>
                    <td>
                        <?php if ($t['estado'] === 'HECHA'): ?>
                            <span class="badge bg-success"><i class="bi bi-check-lg"></i> Hecha</span>
                        <?php else: ?>
                            <span class="badge bg-warning text-dark"><i class="bi bi-hourglass-split"></i> Pendiente</span>
                        <?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars($t['usuario_nombre'] ?? '-') ?></td>
                    <td><?= htmlspecialchars(substr((string)$t['created_at'], 0, 16)) ?></td>
                    <td>
                        <?php if ($t['estado'] === 'HECHA'): ?>
                            <?= htmlspecialchars(substr((string)$t['completada_at'], 0, 16)) ?>
                            <small class="text-muted">por <?= htmlspecialchars($t['completada_por_nombre'] ?? '-') ?></small>
                        <?php else: ?>
                            -
                        <?php endif; ?>
                    </td>
                    <td class="text-end">
                        <?php if ($t['estado'] === 'PENDIENTE'): ?>
                            <form method="POST" action="<?= BASE_URL ?>/admin/tareas-confirmar/<?= (int)$t['id'] ?>" style="display:inline">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Csrf::generate()) ?>">
                                <button class="btn btn-sm btn-success" title="Confirmar tarea">
                                    <i class="bi bi-check-lg"></i>
                                </button>
                            </form>
                        <?php else: ?>
                            <form method="POST" action="<?= BASE_URL ?>/admin/tareas-reabrir/<?= (int)$t['id'] ?>" style="display:inline">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Csrf::generate()) ?>">
                                <button class="btn btn-sm btn-outline-warning" title="Reabrir tarea">
                                    <i class="bi bi-arrow-counterclockwise"></i>
                                </button>
                            </form>
                        <?php endif; ?>
                        <form method="POST" action="<?= BASE_URL ?>/admin/tareas-delete/<?= (int)$t['id'] ?>" style="display:inline"
                              onsubmit="return confirm('¿Eliminar esta tarea?')">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Csrf::generate()) ?>">
                            <button class="btn btn-sm btn-outline-danger" title="Eliminar tarea">
                                <i class="bi bi-trash"></i>
                            </button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>
