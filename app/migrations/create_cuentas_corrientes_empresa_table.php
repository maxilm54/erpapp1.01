<?php
// Migration: Create cuentas_corrientes_empresa table
require_once BASE_PATH . '/app/core/Database.php';

$db = Database::getInstance();

try {
    $sql = "CREATE TABLE IF NOT EXISTS cuentas_corrientes_empresa (
        id INT AUTO_INCREMENT PRIMARY KEY,
        tipo ENUM('ingreso','gasto') NOT NULL,
        descripcion TEXT NOT NULL,
        monto DECIMAL(10,2) NOT NULL,
        fecha DATETIME NOT NULL,
        categoria_id INT NULL,
        referencia_id INT NULL,
        referencia_tipo VARCHAR(50) NULL,
        usuario_id INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (usuario_id) REFERENCES users(id),
        FOREIGN KEY (categoria_id) REFERENCES categorias_gastos_ingresos(id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    
    $stmt = $db->prepare($sql);
    $stmt->execute();
    echo "Migration completed successfully: cuentas_corrientes_empresa table created.\n";
} catch (PDOException $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
}