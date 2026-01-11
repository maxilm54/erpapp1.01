<?php
$error   = $_SESSION['error']   ?? null;
$success = $_SESSION['success'] ?? null;
$warning = $_SESSION['warning'] ?? null;

/*
 | Log para depuración
 */
if ($error) {
    error_log('[ERROR] ' . $error);
}
if ($success) {
    error_log('[SUCCESS] ' . $success);
}
if ($warning) {
    error_log('[WARNING] ' . $warning);
}

/*
 | Limpiar sesión inmediatamente
 */
unset($_SESSION['error'], $_SESSION['success'], $_SESSION['warning']);
?>

<?php if ($error): ?>
<script>
Swal.fire({
    icon: 'error',
    title: 'Error',
    text: <?= json_encode($error) ?>,
    confirmButtonColor: '#d33'
});
</script>
<?php endif; ?>

<?php if ($success): ?>
<script>
Swal.fire({
    icon: 'success',
    title: 'Operación exitosa',
    text: <?= json_encode($success) ?>,
    timer: 2500,
    showConfirmButton: false
});
</script>
<?php endif; ?>

<?php if ($warning): ?>
<script>
Swal.fire({
    icon: 'warning',
    title: 'Atención',
    text: <?= json_encode($warning) ?>,
    confirmButtonColor: '#f0ad4e'
});
</script>
<?php endif; ?>