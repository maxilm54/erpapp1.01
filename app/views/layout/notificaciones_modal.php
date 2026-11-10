<?php
/**
 * Modal de notificaciones + badge de la campana del header.
 * Se incluye desde layout/footer.php en sesiones con tenant.
 * - Actualiza el badge con la cantidad de no leidas (perfil/notificaciones-json).
 * - Al iniciar sesion (flag ver_notificaciones seteado en el login y consumido
 *   por el endpoint JSON) muestra el modal con las notificaciones sin leer.
 * - Permite marcar leida cada notificacion o todas juntas.
 */
?>
<div class="modal fade" id="modalNotificaciones" tabindex="-1" aria-labelledby="modalNotificacionesTitulo" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalNotificacionesTitulo">
                    <i class="bi bi-bell"></i> Notificaciones
                    <span class="badge bg-danger ms-1" id="notif-modal-cantidad">0</span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body" id="notif-modal-lista">
                <p class="text-muted mb-0">Cargando notificaciones...</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-sm btn-outline-primary" id="notif-modal-leer-todas">
                    <i class="bi bi-check2-all"></i> Marcar todas como leidas
                </button>
                <a href="<?= BASE_URL ?>/perfil#notificaciones" class="btn btn-sm btn-secondary">
                    <i class="bi bi-person-lines-fill"></i> Ver todas en Mi Perfil
                </a>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    const NOTIF_BASE = '<?= BASE_URL ?>';
    const NOTIF_CSRF = '<?= htmlspecialchars(Csrf::generate()) ?>';

    const TIPOS_NOTIF = {
        CHEQUE_VENCIMIENTO:  {label: 'Cheque',         icon: 'bi-wallet2',           color: 'warning'},
        PRESUPUESTO_ENTREGA: {label: 'Presupuesto',    icon: 'bi-file-earmark-text', color: 'info'},
        NP_ENTREGA:          {label: 'Nota de Pedido', icon: 'bi-cart',              color: 'primary'},
        OP_ENTREGA:          {label: 'Producción',     icon: 'bi-gear',              color: 'secondary'},
        NOVEDAD:             {label: 'Novedad',        icon: 'bi-megaphone',         color: 'success'}
    };

    function notifEscape(t) {
        // \u0026 = & (escrito con escape para que no lo interprete el template)
        return String(t ?? '')
            .replace(/&/g, '\u0026amp;')
            .replace(/</g, '\u0026lt;')
            .replace(/>/g, '\u0026gt;')
            .replace(/"/g, '\u0026quot;');
    }

    function notifFechaLabel(n) {
        const base = n.fecha_referencia || String(n.created_at || '').slice(0, 10);
        const hoy = new Date();
        const f = new Date(base + 'T00:00:00');
        const dias = Math.round((f - new Date(hoy.getFullYear(), hoy.getMonth(), hoy.getDate())) / 86400000);
        if (dias < 0) return 'Vencida hace ' + Math.abs(dias) + ' día(s)';
        if (dias === 0) return 'Vence HOY';
        if (dias === 1) return 'Mañana';
        return 'En ' + dias + ' día(s)';
    }

    function notifItemHTML(n) {
        const meta = TIPOS_NOTIF[n.tipo] || {label: n.tipo, icon: 'bi-bell', color: 'secondary'};
        const titulo = n.url
            ? '<a href="' + NOTIF_BASE + notifEscape(n.url) + '" class="fw-semibold text-decoration-none">' + notifEscape(n.titulo) + '</a>'
            : '<span class="fw-semibold">' + notifEscape(n.titulo) + '</span>';

        return '<div class="d-flex align-items-start border rounded p-2 mb-2" style="background:#fff8e1;">'
            + '<div class="me-2"><span class="badge bg-' + meta.color + '"><i class="bi ' + meta.icon + '"></i> ' + notifEscape(meta.label) + '</span></div>'
            + '<div class="flex-grow-1">'
            + '<div>' + titulo + '</div>'
            + (n.mensaje ? '<div class="small text-muted">' + notifEscape(n.mensaje) + '</div>' : '')
            + '<div class="small"><i class="bi bi-clock-history"></i> ' + notifFechaLabel(n) + '</div>'
            + '</div>'
            + '<div><button type="button" class="btn btn-sm btn-outline-primary btn-notif-leer" data-id="' + n.id + '">'
            + '<i class="bi bi-check2"></i> Marcar leida</button></div>'
            + '</div>';
    }

    function notifActualizarBadge(cantidad) {
        const badge = document.getElementById('notif-badge');
        if (!badge) return;
        if (cantidad > 0) {
            badge.textContent = cantidad > 99 ? '99+' : cantidad;
            badge.classList.remove('d-none');
        } else {
            badge.classList.add('d-none');
        }
    }

    function notifMarcar(id) {
        const body = new URLSearchParams();
        body.append('csrf_token', NOTIF_CSRF);
        return fetch(NOTIF_BASE + '/perfil/notificacion-leer/' + id, {
            method: 'POST',
            headers: {'X-Requested-With': 'fetch'},
            body: body
        }).then(r => r.json());
    }

    function notifMarcarTodas() {
        const body = new URLSearchParams();
        body.append('csrf_token', NOTIF_CSRF);
        return fetch(NOTIF_BASE + '/perfil/notificaciones-leer-todas', {
            method: 'POST',
            headers: {'X-Requested-With': 'fetch'},
            body: body
        }).then(r => r.json());
    }

    // Delegacion: botones "Marcar leida" dentro del modal
    document.getElementById('notif-modal-lista').addEventListener('click', function (e) {
        const btn = e.target.closest('.btn-notif-leer');
        if (!btn) return;
        btn.disabled = true;
        notifMarcar(btn.dataset.id).then(function (data) {
            if (data && data.ok) {
                const fila = btn.closest('.d-flex');
                if (fila) fila.remove();
                notifActualizarBadge(data.sin_leer || 0);
                document.getElementById('notif-modal-cantidad').textContent = data.sin_leer || 0;
                if (!(data.sin_leer > 0)) {
                    document.getElementById('notif-modal-lista').innerHTML =
                        '<p class="text-muted mb-0">No tenes notificaciones sin leer.</p>';
                }
            } else {
                alert((data && data.error) || 'No se pudo marcar como leida.');
                btn.disabled = false;
            }
        }).catch(function () {
            alert('No se pudo marcar como leida.');
            btn.disabled = false;
        });
    });

    document.getElementById('notif-modal-leer-todas').addEventListener('click', function () {
        const btn = this;
        btn.disabled = true;
        notifMarcarTodas().then(function (data) {
            if (data && data.ok) {
                document.getElementById('notif-modal-lista').innerHTML =
                    '<p class="text-muted mb-0">No tenes notificaciones sin leer.</p>';
                notifActualizarBadge(0);
                document.getElementById('notif-modal-cantidad').textContent = '0';
            } else {
                alert((data && data.error) || 'No se pudieron marcar las notificaciones.');
            }
            btn.disabled = false;
        }).catch(function () {
            alert('No se pudieron marcar las notificaciones.');
            btn.disabled = false;
        });
    });

    // Carga inicial: badge + modal al iniciar sesion
    fetch(NOTIF_BASE + '/perfil/notificaciones-json', {headers: {'X-Requested-With': 'fetch'}})
        .then(function (r) {
            if (!r.ok) throw new Error('HTTP ' + r.status);
            const ct = r.headers.get('content-type') || '';
            if (!ct.includes('application/json')) throw new Error('Respuesta no JSON');
            return r.json();
        })
        .then(function (data) {
            notifActualizarBadge(data.sin_leer || 0);
            document.getElementById('notif-modal-cantidad').textContent = data.sin_leer || 0;

            const lista = document.getElementById('notif-modal-lista');
            if (data.items && data.items.length > 0) {
                lista.innerHTML = data.items.map(notifItemHTML).join('');
            } else {
                lista.innerHTML = '<p class="text-muted mb-0">No tenes notificaciones sin leer.</p>';
            }

            // El modal aparece solo una vez por inicio de sesion y solo si hay sin leer
            if (data.mostrar_modal && data.sin_leer > 0 && typeof bootstrap !== 'undefined') {
                const modal = new bootstrap.Modal(document.getElementById('modalNotificaciones'));
                modal.show();
            }
        })
        .catch(function () {
            // Sin sesion o endpoint no disponible: el modal/badge quedan ocultos
        });
})();
</script>
