<div class="d-flex justify-content-between mb-3">
    <h3>Usuarios de: <?= htmlspecialchars($tenant['nombre']) ?></h3>
    <a href="<?= BASE_URL ?>/admin" class="btn btn-secondary">
        <i class="bi bi-arrow-left"></i> Volver
    </a>
</div>

<div class="row">
    <!-- Usuarios asignados -->
    <div class="col-md-6">
        <div class="card">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0">Usuarios Asignados</h5>
            </div>
            <div class="card-body">
                <?php if (empty($tenantUsers)): ?>
                    <p class="text-muted">No hay usuarios asignados a este tenant.</p>
                <?php else: ?>
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>Email</th>
                            <th>Rol</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tenantUsers as $u): ?>
                        <tr>
                            <td><?= htmlspecialchars($u['nombre']) ?></td>
                            <td><?= htmlspecialchars($u['email']) ?></td>
                            <td><span class="badge bg-info"><?= $u['rol'] ?></span></td>
                            <td>
                                <form method="POST" style="display:inline">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                                    <input type="hidden" name="action" value="remove">
                                    <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                    <button class="btn btn-sm btn-outline-danger"
                                            onclick="return confirm('¿Remover este usuario del tenant?')">
                                        <i class="bi bi-x-lg"></i>
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
    </div>

    <!-- Asignar usuario -->
    <div class="col-md-6">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">Asignar Usuario</h5>
            </div>
            <div class="card-body">
                <?php
                // Filtrar usuarios que NO están asignados aún
                $assignedIds = array_column($tenantUsers, 'id');
                $available = array_filter($allUsers, function($u) use ($assignedIds) {
                    return !in_array($u['id'], $assignedIds);
                });
                ?>
                <?php if (empty($available)): ?>
                    <p class="text-muted">Todos los usuarios ya están asignados a este tenant.</p>
                <?php else: ?>
                <form method="POST" class="d-flex gap-2">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                    <input type="hidden" name="action" value="assign">
                    <select name="user_id" class="form-select" required>
                        <option value="">Seleccionar usuario...</option>
                        <?php foreach ($available as $u): ?>
                        <option value="<?= $u['id'] ?>">
                            <?= htmlspecialchars($u['nombre']) ?> (<?= htmlspecialchars($u['email']) ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <button class="btn btn-primary">
                        <i class="bi bi-plus-lg"></i> Asignar
                    </button>
                </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
