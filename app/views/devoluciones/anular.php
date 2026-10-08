<h3><i class="bi bi-x-circle"></i> Anular Devolución #DEV-<?= str_pad($devolucion['numero'], 6, '0', STR_PAD_LEFT) ?></h3>

<div class="alert alert-warning">
    <strong>¿Está seguro de anular esta devolución?</strong><br>
    Esta acción revertirá el impacto en stock, cuenta corriente y caja/banco.
</div>

<form method="POST">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Csrf::generate()) ?>">

    <div class="mb-3">
        <label class="form-label">Motivo de anulación <span class="text-danger">*</span></label>
        <textarea name="motivo" class="form-control" rows="3" required
                  placeholder="Indique el motivo de la anulación..."></textarea>
    </div>

    <button type="button" class="btn btn-danger" onclick="confirmarAnulacionFinal()">
        <i class="bi bi-x-circle"></i> Confirmar Anulación
    </button>
    <a href="<?= BASE_URL ?>/devoluciones/show/<?= $devolucion['id'] ?>" class="btn btn-secondary">Cancelar</a>
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
        text: 'Se revertirán todos los impactos (stock, ctacte, caja)',
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
