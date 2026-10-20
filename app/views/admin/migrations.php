<div class="d-flex justify-content-between mb-3">
    <h3><i class="bi bi-database"></i> <?= htmlspecialchars($title) ?></h3>
    <form method="POST" action="<?= BASE_URL ?>/admin/migrations-run-all" style="display:inline"
          onclick="return confirm('¿Aplicar migraciones pendientes a TODOS los tenants?')">
        <button class="btn btn-warning">
            <i class="bi bi-play-circle"></i> Ejecutar Todas las Migraciones
        </button>
    </form>
</div>

<!-- Migraciones de la BD MASTER (schema de la instalacion, no de los tenants) -->
<div class="card mb-4">
    <div class="card-header bg-secondary text-white d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="bi bi-hdd-stack"></i> Migraciones de la BD Master</h5>
        <?php if (!empty($masterPending)): ?>
        <form method="POST" action="<?= BASE_URL ?>/admin/migrations-run-master" style="display:inline"
              onsubmit="return confirm('¿Aplicar las migraciones pendientes de la BD master?')">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Csrf::generate()) ?>">
            <button class="btn btn-sm btn-light text-dark">
                <i class="bi bi-play-circle"></i>
                Aplicar <?= count($masterPending) ?> pendiente(s)
            </button>
        </form>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <p class="small text-muted mb-2">
            El schema de la BD master (usuarios, empresas, tareas) se actualiza con archivos en
            <code>app/helpers/squemadb/master_migrations/</code>, distintos de las migraciones de los tenants.
        </p>
        <?php if (empty($masterMigrations)): ?>
            <p class="text-muted mb-0">No hay migraciones master disponibles.</p>
        <?php else: ?>
        <table class="table table-sm">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Archivo</th>
                    <th>Descripcion</th>
                    <th>Estado</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($masterMigrations as $m): ?>
                <tr>
                    <td><code><?= $m['number'] ?></code></td>
                    <td><code><?= htmlspecialchars($m['filename']) ?></code></td>
                    <td><?= htmlspecialchars(str_replace('_', ' ', $m['name'])) ?></td>
                    <td>
                        <?php if (in_array($m['number'], array_column($masterApplied, 'version'), true)): ?>
                            <span class="badge bg-success">Aplicada</span>
                        <?php else: ?>
                            <span class="badge bg-warning text-dark">Pendiente</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>

<!-- Migraciones disponibles -->
<div class="card mb-4">
    <div class="card-header bg-dark text-white">
        <h5 class="mb-0"><i class="bi bi-list-ol"></i> Migraciones Disponibles (<?= count($allMigrations) ?>)</h5>
    </div>
    <div class="card-body">
        <?php if (empty($allMigrations)): ?>
            <p class="text-muted">No hay archivos de migración en <code>app/helpers/migrations/</code></p>
        <?php else: ?>
        <table class="table table-sm">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Archivo</th>
                    <th>Descripción</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($allMigrations as $m): ?>
                <tr>
                    <td><code><?= $m['number'] ?></code></td>
                    <td><code><?= htmlspecialchars($m['filename']) ?></code></td>
                    <td><?= htmlspecialchars(str_replace('_', ' ', $m['name'])) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>

<!-- Estado por tenant -->
<div class="card">
    <div class="card-header bg-primary text-white">
        <h5 class="mb-0"><i class="bi bi-building"></i> Estado por Tenant (versión actual: <?= $latestVersion ?>)</h5>
    </div>
    <div class="card-body">
        <table class="table table-hover">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Empresa</th>
                    <th>Versión</th>
                    <th>Estado</th>
                    <th>Pendientes</th>
                    <th class="text-end">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($tenants as $t): ?>
                <tr>
                    <td><?= $t['id'] ?></td>
                    <td><?= htmlspecialchars($t['nombre']) ?></td>
                    <td><code><?= $t['schema_version'] ?></code></td>
                    <td>
                        <?php if (empty($t['pending'])): ?>
                            <span class="badge bg-success">Actualizado</span>
                        <?php else: ?>
                            <span class="badge bg-warning text-dark"><?= count($t['pending']) ?> pendiente(s)</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if (!empty($t['pending'])): ?>
                            <?php foreach ($t['pending'] as $p): ?>
                                <code class="me-1"><?= $p['number'] ?></code>
                            <?php endforeach; ?>
                        <?php else: ?>
                            -
                        <?php endif; ?>
                    </td>
                    <td class="text-end">
                        <?php if (!empty($t['pending'])): ?>
                        <a href="<?= BASE_URL ?>/admin/migrations-run/<?= $t['id'] ?>"
                           class="btn btn-sm btn-outline-success"
                           onclick="return confirm('¿Aplicar migraciones pendientes a <?= htmlspecialchars($t['nombre']) ?>?')">
                            <i class="bi bi-play"></i> Ejecutar
                        </a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
