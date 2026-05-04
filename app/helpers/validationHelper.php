<?php
function validarId($id, string $redirectUrl): void {
    error_log('Validando ID: ' . $id . ' con redirectUrl: ' . $redirectUrl);
    if (!filter_var($id, FILTER_VALIDATE_INT) || (int)$id <= 0) {
        $_SESSION['error'] = 'Hubo un error al validar el id.';
        error_log('Error de validación: ID no es un entero válido o es menor o igual a 0. ID recibido: ' . $id);
        header('Location: ' . $redirectUrl);
        exit;
    }
}