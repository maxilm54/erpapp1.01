function confirmarAccion({
    title = '¿Está seguro?',
    text = 'Esta acción no se puede deshacer',
    confirmText = 'Sí, continuar',
    cancelText = 'Cancelar',
    icon = 'warning',
    onConfirm
}) {
    Swal.fire({
        title,
        text,
        icon,
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#6c757d',
        confirmButtonText: confirmText,
        cancelButtonText: cancelText
    }).then(result => {
        if (result.isConfirmed && typeof onConfirm === 'function') {
            onConfirm();
        }
    });
}