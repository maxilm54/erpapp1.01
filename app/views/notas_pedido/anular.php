<h1>Anular Nota de Pedido</h1>

<form method="post">
    <div class="mb-3">
        <label>Motivo de anulación</label>
        <textarea name="motivo" class="form-control" required></textarea>
    </div>

    <button type="button" class="btn btn-danger" onclick="confirmarAnulacionFinal()">
        Confirmar Anulación
    </button>
    <a href="<?= BASE_URL ?>/notaspedido/show/<?= $id ?>" class="btn btn-secondary">Cancelar</a>
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