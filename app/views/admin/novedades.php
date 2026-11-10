<div class="d-flex justify-content-between align-items-center mb-3">
    <h3><i class="bi bi-megaphone"></i> <?= htmlspecialchars($title) ?></h3>
    <span class="text-muted small">
        Las novedades se ven en el centro de notificaciones de los usuarios hasta su fecha de vigencia.
    </span>
</div>

<!-- Formulario de alta / edicion -->
<div class="card mb-4">
    <div class="card-header <?= !empty($editar) ? 'bg-warning text-dark' : 'bg-dark text-white' ?>">
        <h5 class="mb-0">
            <?php if (!empty($editar)): ?>
                <i class="bi bi-pencil"></i> Editar novedad #<?= (int)$editar['id'] ?>
            <?php else: ?>
                <i class="bi bi-plus-lg"></i> Nueva novedad
            <?php endif; ?>
        </h5>
    </div>
    <div class="card-body">
        <form method="POST"
              action="<?= !empty($editar)
                  ? BASE_URL . '/admin/novedades-update/' . (int)$editar['id']
                  : BASE_URL . '/admin/novedades-create' ?>">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Csrf::generate()) ?>">

            <div class="row g-3">
                <div class="col-md-5">
                    <label class="form-label">Titulo <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" name="titulo" required maxlength="150"
                           value="<?= htmlspecialchars($editar['titulo'] ?? '') ?>"
                           placeholder="Ej: Nueva funcionalidad de fechas de entrega">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Empresa</label>
                    <select class="form-select" name="tenant_id">
                        <option value="">Todas las empresas</option>
                        <?php foreach ($tenants as $t): ?>
                            <option value="<?= (int)$t['id'] ?>"
                                <?= isset($editar['tenant_id']) && (int)$editar['tenant_id'] === (int)$t['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($t['nombre']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-text">Si elegis una, solo esa empresa la ve.</div>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Visible hasta <span class="text-danger">*</span></label>
                    <input type="date" class="form-control" name="vigencia_hasta" required
                           value="<?= htmlspecialchars($editar['vigencia_hasta'] ?? date('Y-m-d', strtotime('+30 days'))) ?>">
                    <div class="form-text">Ultimo dia en que se muestra.</div>
                </div>
                <div class="col-md-2 d-flex align-items-end gap-2">
                    <button class="btn btn-primary flex-grow-1">
                        <?= !empty($editar) ? 'Guardar cambios' : 'Publicar' ?>
                    </button>
                    <?php if (!empty($editar)): ?>
                        <a href="<?= BASE_URL ?>/admin/novedades" class="btn btn-secondary">Cancelar</a>
                    <?php endif; ?>
                </div>
            </div>

            <div class="mt-3">
                <label class="form-label">Mensaje</label>
                <textarea class="form-control" name="mensaje" rows="3"
                          placeholder="Detalle de la novedad o aviso..."><?= htmlspecialchars($editar['mensaje'] ?? '') ?></textarea>
            </div>
        </form>
    </div>
</div>

<!-- Listado -->
<div class="card">
    <div class="card-header bg-secondary text-white">
        <h5 class="mb-0"><i class="bi bi-list-ul"></i> Novedades publicadas (<?= count($novedades) ?>)</h5>
    </div>
    <div class="card-body">
        <?php if (empty($novedades)): ?>
            <p class="text-muted mb-0">Todavia no hay novedades publicadas.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Titulo</th>
                            <th>Mensaje</th>
                            <th>Empresa</th>
                            <th>Visible hasta</th>
                            <th>Estado</th>
                            <th>Creada</th>
                            <th class="text-end">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($novedades as $n): ?>
                        <?php
                            $vigente = ($n['vigencia_hasta'] === null || $n['vigencia_hasta'] >= date('Y-m-d')) && !empty($n['activo']);
                        ?>
                        <tr class="<?= !$vigente ? 'table-light text-muted' : '' ?>">
                            <td><?= (int)$n['id'] ?></td>
                            <td class="fw-semibold"><?= htmlspecialchars($n['titulo']) ?></td>
                            <td style="max-width:320px;"><?= nl2br(htmlspecialchars(mb_substr((string)($n['mensaje'] ?? ''), 0, 160))) ?></td>
                            <td><?= $n['tenant_nombre'] ? htmlspecialchars($n['tenant_nombre']) : 'Todas' ?></td>
                            <td><?= !empty($n['vigencia_hasta']) ? date('d/m/Y', strtotime($n['vigencia_hasta'])) : '-' ?></td>
                            <td>
                                <?php if (empty($n['activo'])): ?>
                                    <span class="badge bg-secondary">Desactivada</span>
                                <?php elseif ($n['vigencia_hasta'] !== null && $n['vigencia_hasta'] < date('Y-m-d')): ?>
                                    <span class="badge bg-warning text-dark">Vencida</span>
                                <?php else: ?>
                                    <span class="badge bg-success">Vigente</span>
                                <?php endif; ?>
                            </td>
                            <td><?= date('d/m/Y H:i', strtotime($n['created_at'])) ?></td>
                            <td class="text-end">
                                <?php if (!empty($n['activo'])): ?>
                                    <a href="<?= BASE_URL ?>/admin/novedades-edit/<?= (int)$n['id'] ?>"
                                       class="btn btn-sm btn-warning" title="Editar">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <form method="POST" action="<?= BASE_URL ?>/admin/novedades-delete/<?= (int)$n['id'] ?>"
                                          class="d-inline"
                                          onsubmit="return confirm('¿Desactivar esta novedad? Dejara de mostrarse en las empresas.')">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Csrf::generate()) ?>">
                                        <button class="btn btn-sm btn-danger" title="Desactivar">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <span class="small">-</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
