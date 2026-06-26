<div class="d-flex justify-content-between mb-3">
    <h3><i class="bi bi-database"></i> <?= htmlspecialchars($title) ?></h3>
    <form method="POST" action="<?= BASE_URL ?>/admin/migrations-run-all" style="display:inline"
          onclick="return confirm('¿Aplicar migraciones pendientes a TODOS los tenants?')">
        <button class="btn btn-warning">
            <i class="bi bi-play-circle"></i> Ejecutar Todas las Migraciones
        </button>
    </form>
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
