<?php $np = $np ?? []; ?>
<h1>Anular Nota de Pedido</h1>

<?php if (!empty($np['presupuesto_id'])): ?>
    <div class="alert alert-info">
        Al anular esta NP, el presupuesto #<?= (int)$np['presupuesto_id'] ?> queda libre
        y después podrá anularse.
    </div>
<?php endif; ?>

<?php if (($np['remitido'] ?? '') === 'RemitidoCompleto'): ?>
    <div class="alert alert-warning">
        Esta NP está remitida por completo y <strong>no se puede anular</strong>.
        Para revertir la entrega, generá una devolución.
    </div>
    <a href="<?= BASE_URL ?>/devoluciones/create" class="btn btn-danger">Ir a Devoluciones</a>
    <a href="<?= BASE_URL ?>/notaspedido/show/<?= (int)$id ?>" class="btn btn-secondary">Volver</a>
<?php else: ?>
    <form method="post">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Csrf::generate()) ?>">
        <div class="mb-3">
            <label>Motivo de anulación</label>
            <textarea name="motivo" class="form-control" required></textarea>
        </div>

        <button type="button" class="btn btn-danger" onclick="confirmarAnulacionFinal()">
            Confirmar Anulación
        </button>
        <a href="<?= BASE_URL ?>/notaspedido/show/<?= (int)$id ?>" class="btn btn-secondary">Cancelar</a>
    </form>

    <script>
    function confirmarAnulacionFinal() {
        const motivo = document.querySelector('textarea[name="motivo"]').value.trim();

        if (!motivo) {
            Swal.fire({
                icon: 'error',
                title: 'Motivo requerido',
                text: 'Debe indicar el motivo de la anulación'
            });
            return;
        }

        Swal.fire({
            title: '¿Confirmar anulación?',
            text: 'Esta acción no se puede deshacer',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Sí, anular',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                document.querySelector('form').submit();
            }
        });
    }
    </script>
<?php endif; ?>
