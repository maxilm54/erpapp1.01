<div class="d-flex justify-content-between mb-3">
    <h3><i class="bi bi-person-circle"></i> Mi Perfil</h3>
    <a href="<?= BASE_URL ?>" class="btn btn-secondary">
        <i class="bi bi-arrow-left"></i> Volver
    </a>
</div>

<div class="row">
    <div class="col-md-8">
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="bi bi-person"></i> Mis datos</h5>
            </div>
            <div class="card-body">
                <form method="POST" action="<?= BASE_URL ?>/perfil">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Csrf::generate()) ?>">

                    <div class="mb-3">
                        <label for="nombre" class="form-label">Nombre</label>
                        <input type="text" class="form-control" id="nombre" name="nombre" required
                               maxlength="100" value="<?= htmlspecialchars($user['nombre'] ?? '') ?>">
                    </div>

                    <div class="mb-3">
                        <label for="email" class="form-label">Email</label>
                        <input type="email" class="form-control" id="email" name="email" required
                               maxlength="150" value="<?= htmlspecialchars($user['email'] ?? '') ?>">
                        <small class="text-muted">Es el usuario con el que inicias sesion.</small>
                    </div>

                    <hr>

                    <div class="mb-3">
                        <label for="password" class="form-label">Nueva contrasena</label>
                        <input type="password" class="form-control" id="password" name="password"
                               minlength="6" autocomplete="new-password"
                               placeholder="Dejalo vacio para no cambiarla">
                    </div>

                    <div class="mb-3">
                        <label for="password_confirm" class="form-label">Repetir contrasena</label>
                        <input type="password" class="form-control" id="password_confirm" name="password_confirm"
                               minlength="6" autocomplete="new-password"
                               placeholder="Dejalo vacio para no cambiarla">
                    </div>

                    <div class="d-flex justify-content-end">
                        <button class="btn btn-success"><i class="bi bi-save"></i> Guardar cambios</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card">
            <div class="card-header bg-secondary text-white">
                <h5 class="mb-0"><i class="bi bi-info-circle"></i> Informacion</h5>
            </div>
            <div class="card-body">
                <p class="mb-1"><strong>Rol:</strong>
                    <?= htmlspecialchars(Role::getRoleName($user['rol'] ?? '')) ?>
                </p>
                <p class="mb-1"><strong>Estado:</strong>
                    <?= !empty($user['activo']) ? 'Activo' : 'Inactivo' ?>
                </p>
                <p class="mb-0"><strong>Alta:</strong>
                    <?= htmlspecialchars(substr((string)($user['created_at'] ?? ''), 0, 10)) ?>
                </p>
                <hr>
                <small class="text-muted">
                    El rol y el estado los administra un administrador de la empresa.
                </small>
            </div>
        </div>
    </div>
</div>

<!-- ================= NOTIFICACIONES (centro por usuario) ================= -->
<div class="card mt-4" id="notificaciones" data-csrf="<?= htmlspecialchars(Csrf::generate()) ?>">
    <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="bi bi-bell"></i> Notificaciones
            <?php if (!empty($notificacionesSinLeer)): ?>
                <span class="badge bg-danger ms-1" id="notif-cantidad-perfil"><?= (int)$notificacionesSinLeer ?> sin leer</span>
            <?php endif; ?>
        </h5>
        <button type="button" class="btn btn-sm btn-light text-dark" id="btn-notif-leer-todas"
                <?= empty($notificaciones) ? 'disabled' : '' ?>>
            <i class="bi bi-check2-all"></i> Marcar todas como leidas
        </button>
    </div>
    <div class="card-body">
        <?php if (empty($notificaciones)): ?>
            <p class="text-muted mb-0"><i class="bi bi-emoji-smile"></i> No tenes notificaciones.</p>
        <?php else: ?>
            <?php
            // Agrupar dia por dia por la fecha de referencia (vencida, hoy, mañana, d/m/Y)
            $gruposNotif = [];
            $hoyStr = date('Y-m-d');
            $mananaStr = date('Y-m-d', strtotime('+1 day'));
            foreach ($notificaciones as $n) {
                $base = $n['fecha_referencia'] ?: substr((string)$n['created_at'], 0, 10);
                if ($base < $hoyStr) {
                    $grupo = 'Vencidas / atrasadas';
                } elseif ($base === $hoyStr) {
                    $grupo = 'Hoy';
                } elseif ($base === $mananaStr) {
                    $grupo = 'Mañana';
                } else {
                    $grupo = date('d/m/Y', strtotime($base));
                }
                $gruposNotif[$grupo][] = $n;
            }

            $tiposNotif = [
                'CHEQUE_VENCIMIENTO'  => ['label' => 'Cheque',          'icon' => 'bi-wallet2',           'color' => 'warning'],
                'PRESUPUESTO_ENTREGA' => ['label' => 'Presupuesto',     'icon' => 'bi-file-earmark-text', 'color' => 'info'],
                'NP_ENTREGA'          => ['label' => 'Nota de Pedido',  'icon' => 'bi-cart',              'color' => 'primary'],
                'OP_ENTREGA'          => ['label' => 'Producción',      'icon' => 'bi-gear',              'color' => 'secondary'],
                'NOVEDAD'             => ['label' => 'Novedad',         'icon' => 'bi-megaphone',         'color' => 'success'],
            ];
            ?>
            <?php foreach ($gruposNotif as $grupo => $items): ?>
                <h6 class="mt-3 mb-2 text-muted border-bottom pb-1">
                    <i class="bi bi-calendar3"></i> <?= htmlspecialchars($grupo) ?>
                    <span class="badge bg-light text-dark border ms-1"><?= count($items) ?></span>
                </h6>
                <?php foreach ($items as $n): ?>
                    <?php
                        $meta = $tiposNotif[$n['tipo']] ?? ['label' => $n['tipo'], 'icon' => 'bi-bell', 'color' => 'secondary'];
                        $sinLeer = $n['leido_id'] === null;
                    ?>
                    <div class="d-flex align-items-start border rounded p-2 mb-2 notif-fila"
                         id="notif-<?= (int)$n['id'] ?>"
                         style="<?= $sinLeer ? 'background:#fff8e1;' : 'opacity:.65;' ?>">
                        <div class="me-2">
                            <span class="badge bg-<?= $meta['color'] ?>"><i class="bi <?= $meta['icon'] ?>"></i> <?= htmlspecialchars($meta['label']) ?></span>
                        </div>
                        <div class="flex-grow-1">
                            <div>
                                <?php if (!empty($n['url'])): ?>
                                    <a href="<?= BASE_URL . htmlspecialchars($n['url']) ?>" class="fw-semibold text-decoration-none">
                                        <?= htmlspecialchars($n['titulo']) ?>
                                    </a>
                                <?php else: ?>
                                    <span class="fw-semibold"><?= htmlspecialchars($n['titulo']) ?></span>
                                <?php endif; ?>
                                <?php if ($sinLeer): ?>
                                    <span class="badge bg-danger ms-1">Nueva</span>
                                <?php endif; ?>
                            </div>
                            <?php if (!empty($n['mensaje'])): ?>
                                <div class="small text-muted"><?= htmlspecialchars($n['mensaje']) ?></div>
                            <?php endif; ?>
                            <div class="small text-muted">
                                <i class="bi bi-clock-history"></i>
                                <?= date('d/m/Y H:i', strtotime($n['created_at'])) ?>
                                <?php if (!$sinLeer): ?>
                                    · Leida el <?= date('d/m/Y H:i', strtotime($n['leido_at'])) ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div>
                            <?php if ($sinLeer): ?>
                                <button type="button" class="btn btn-sm btn-outline-primary btn-notif-leer" data-id="<?= (int)$n['id'] ?>">
                                    <i class="bi bi-check2"></i> Marcar leida
                                </button>
                            <?php else: ?>
                                <span class="badge bg-secondary"><i class="bi bi-check2-all"></i> Leida</span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<script>
// Centro de notificaciones del perfil: marcar leida / marcar todas (AJAX + CSRF)
(function () {
    const card = document.getElementById('notificaciones');
    if (!card) return;
    const csrf = card.dataset.csrf;

    function notifPost(url) {
        const body = new URLSearchParams();
        body.append('csrf_token', csrf);
        return fetch(url, {
            method: 'POST',
            headers: {'X-Requested-With': 'fetch'},
            body: body
        }).then(r => r.json());
    }

    document.querySelectorAll('.btn-notif-leer').forEach(function (btn) {
        btn.addEventListener('click', function () {
            btn.disabled = true;
            notifPost('<?= BASE_URL ?>/perfil/notificacion-leer/' + btn.dataset.id)
                .then(function (data) {
                    if (data && data.ok) {
                        location.reload();
                    } else {
                        alert((data && data.error) || 'No se pudo marcar como leida.');
                        btn.disabled = false;
                    }
                })
                .catch(function () {
                    alert('No se pudo marcar como leida.');
                    btn.disabled = false;
                });
        });
    });

    const btnTodas = document.getElementById('btn-notif-leer-todas');
    if (btnTodas) {
        btnTodas.addEventListener('click', function () {
            btnTodas.disabled = true;
            notifPost('<?= BASE_URL ?>/perfil/notificaciones-leer-todas')
                .then(function (data) {
                    if (data && data.ok) {
                        location.reload();
                    } else {
                        alert((data && data.error) || 'No se pudieron marcar las notificaciones.');
                        btnTodas.disabled = false;
                    }
                })
                .catch(function () {
                    alert('No se pudieron marcar las notificaciones.');
                    btnTodas.disabled = false;
                });
        });
    }
})();
</script>
