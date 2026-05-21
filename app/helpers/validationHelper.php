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

function sanitizeInput($input): string {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

function validateEmail(string $email): bool {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function validateRequired(array $fields, array $data): array {
    $errors = [];
    foreach ($fields as $field) {
        if (empty($data[$field])) {
            $errors[] = "El campo {$field} es requerido.";
        }
    }
    return $errors;
}

function validateNumeric($value, string $fieldName): ?string {
    if (!is_numeric($value) || $value < 0) {
        return "El campo {$fieldName} debe ser un número positivo.";
    }
    return null;
}

function validateStringLength(string $value, int $min, int $max, string $fieldName): ?string {
    $length = strlen($value);
    if ($length < $min || $length > $max) {
        return "El campo {$fieldName} debe tener entre {$min} y {$max} caracteres.";
    }
    return null;
}