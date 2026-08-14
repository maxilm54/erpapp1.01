<?php
/**
 * Seed para crear el usuario superadmin.
 * Script standalone - no depende del framework.
 * 
 * Uso: php app/helpers/seed_superadmin.php
 */

// Configuracion de BD - ajustar si es necesario
$dbHost = 'localhost';
$dbName = 'app_master';
$dbUser = 'root';
$dbPass = '';

echo "=== Creando usuario SuperAdmin ===\n\n";

try {
    $pdo = new PDO("mysql:host={$dbHost};charset=utf8mb4", $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);

    // Verificar que existe la BD
    $stmt = $pdo->query("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = '{$dbName}'");
    if (!$stmt->fetch()) {
        echo "ERROR: La BD '{$dbName}' no existe.\n";
        echo "Ejecuta primero: app/helpers/squemadb/create_master.sql\n";
        exit(1);
    }

    $pdo->exec("USE `{$dbName}`");

$email = 'soporte@dmtech.com.ar';
$nombre = 'Soporte DmTech';
$password = 'Tucuman#1588';
$rol = 'SUPERADMIN';

    // Verificar si ya existe
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $existing = $stmt->fetch();

    if ($existing) {
        echo "El usuario {$email} ya existe (ID: {$existing['id']}).\n";
        echo "Si deseas resetear la contrasena, ejecuta este SQL:\n\n";
        $newHash = password_hash($password, PASSWORD_DEFAULT);
        echo "  UPDATE users SET password_hash = '{$newHash}' WHERE email = '{$email}';\n";
        exit(0);
    }

    // Crear usuario
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);

    $stmt = $pdo->prepare("
        INSERT INTO users (nombre, email, password_hash, email_verificado, activo, rol)
        VALUES (?, ?, ?, 1, 1, ?)
    ");
    $stmt->execute([$nombre, $email, $passwordHash, $rol]);
    $userId = $pdo->lastInsertId();

    echo "Usuario creado exitosamente:\n";
    echo "  ID:     {$userId}\n";
    echo "  Nombre: {$nombre}\n";
    echo "  Email:  {$email}\n";
    echo "  Rol:    {$rol}\n";
    echo "  Pass:   {$password}\n";
    echo "\n";
    echo "Ahora puedes iniciar sesion en el panel de administracion.\n";

} catch (PDOException $e) {
    echo "Error de conexion: " . $e->getMessage() . "\n";
    echo "Verifica que MySQL este corriendo y las credenciales sean correctas.\n";
    exit(1);
}
