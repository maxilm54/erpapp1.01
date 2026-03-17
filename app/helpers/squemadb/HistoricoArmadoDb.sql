CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    email_verificado TINYINT(1) DEFAULT 0,
    token_verificacion VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE clientes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    razon_social VARCHAR(150) NOT NULL,
    cuit VARCHAR(20) NOT NULL UNIQUE,
    email VARCHAR(150),
    telefono VARCHAR(50),
    direccion VARCHAR(255),
    activo TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE productos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(150) NOT NULL,
    sku VARCHAR(100) NOT NULL UNIQUE,
    descripcion TEXT,
    precio_venta DECIMAL(10,2) NOT NULL,
    imagen VARCHAR(255),
    activo TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE producto_codigos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    producto_id INT NOT NULL,
    codigo VARCHAR(100) NOT NULL UNIQUE,
    tipo VARCHAR(50) NOT NULL,
    FOREIGN KEY (producto_id) REFERENCES productos(id)
        ON DELETE CASCADE
);


CREATE TABLE materias_primas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(150) NOT NULL,
    sku VARCHAR(100) NOT NULL UNIQUE,
    unidad_medida VARCHAR(20) NOT NULL,
    stock_actual DECIMAL(10,3) DEFAULT 0,
    activo TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE recetas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    producto_id INT NOT NULL UNIQUE,
    FOREIGN KEY (producto_id) REFERENCES productos(id)
        ON DELETE CASCADE
);

CREATE TABLE receta_detalle (
    id INT AUTO_INCREMENT PRIMARY KEY,
    receta_id INT NOT NULL,
    materia_prima_id INT NOT NULL,
    cantidad DECIMAL(10,3) NOT NULL,
    FOREIGN KEY (receta_id) REFERENCES recetas(id)
        ON DELETE CASCADE,
    FOREIGN KEY (materia_prima_id) REFERENCES materias_primas(id)
);


CREATE TABLE producciones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    producto_id INT NOT NULL,
    cantidad DECIMAL(10,3) NOT NULL,
    usuario_id INT NOT NULL,
    fecha TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (producto_id) REFERENCES productos(id),
    FOREIGN KEY (usuario_id) REFERENCES users(id)
);


CREATE TABLE movimientos_stock (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tipo ENUM('INGRESO','EGRESO') NOT NULL,
    referencia VARCHAR(100),
    materia_prima_id INT,
    producto_id INT,
    cantidad DECIMAL(10,3) NOT NULL,
    fecha TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

ALTER TABLE productos ADD tipo ENUM('BASE','PRESENTACION') NOT NULL;
ALTER TABLE productos ADD producto_base_id INT NULL;

CREATE TABLE conversiones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    producto_base_id INT NOT NULL,
    producto_presentacion_id INT NOT NULL,
    factor DECIMAL(10,4) NOT NULL
);


ALTER TABLE producciones ADD tipo ENUM('PRODUCCION','FRACCIONADO');

CREATE TABLE proveedores (
    id INT AUTO_INCREMENT PRIMARY KEY,
    razon_social VARCHAR(150) NOT NULL,
    cuit VARCHAR(20) UNIQUE,
    email VARCHAR(150),
    telefono VARCHAR(50),
    activo TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

/* editamos las tablas de compras por mal inicio
CREATE TABLE compras (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proveedor_id INT NOT NULL,
    fecha TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    usuario_id INT NOT NULL,
    estado ENUM('CARGADA','CONFIRMADA') DEFAULT 'CONFIRMADA',
    FOREIGN KEY (proveedor_id) REFERENCES proveedores(id),
    FOREIGN KEY (usuario_id) REFERENCES users(id)
);

CREATE TABLE compras_detalle (
    id INT AUTO_INCREMENT PRIMARY KEY,
    compra_id INT NOT NULL,
    materia_prima_id INT NOT NULL,
    cantidad DECIMAL(10,3) NOT NULL,
    FOREIGN KEY (compra_id) REFERENCES compras(id) ON DELETE CASCADE,
    FOREIGN KEY (materia_prima_id) REFERENCES materias_primas(id)
); */

CREATE TABLE ordenes_compra (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proveedor_id INT NOT NULL,
    usuario_id INT NOT NULL,
    estado ENUM('PENDIENTE','APROBADA','CERRADA') DEFAULT 'PENDIENTE',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (proveedor_id) REFERENCES proveedores(id),
    FOREIGN KEY (usuario_id) REFERENCES users(id)
);

CREATE TABLE ordenes_compra_detalle (
    id INT AUTO_INCREMENT PRIMARY KEY,
    orden_compra_id INT NOT NULL,
    materia_prima_id INT NOT NULL,
    cantidad DECIMAL(10,3) NOT NULL,
    FOREIGN KEY (orden_compra_id) REFERENCES ordenes_compra(id),
    FOREIGN KEY (materia_prima_id) REFERENCES materias_primas(id)
);

CREATE TABLE ingresos_mercaderia (
    id INT AUTO_INCREMENT PRIMARY KEY,
    orden_compra_id INT NOT NULL,
    proveedor_id INT NOT NULL,
    remito VARCHAR(20) NOT NULL,
    usuario_id INT NOT NULL,
    fecha TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (orden_compra_id) REFERENCES ordenes_compra(id),
    FOREIGN KEY (proveedor_id) REFERENCES proveedores(id),
    FOREIGN KEY (usuario_id) REFERENCES users(id),
    UNIQUE (proveedor_id, remito)
);

CREATE TABLE ingresos_mercaderia_detalle (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ingreso_id INT NOT NULL,
    materia_prima_id INT NOT NULL,
    cantidad DECIMAL(10,3) NOT NULL,
    FOREIGN KEY (ingreso_id) REFERENCES ingresos_mercaderia(id),
    FOREIGN KEY (materia_prima_id) REFERENCES materias_primas(id)
);


CREATE TABLE stock_materias_primas (
    materia_prima_id INT PRIMARY KEY,
    cantidad DECIMAL(12,3) NOT NULL DEFAULT 0,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (materia_prima_id) REFERENCES materias_primas(id)
);

CREATE TABLE presupuestos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cliente_id INT NOT NULL,
    estado ENUM('BORRADOR','APROBADO','CANCELADO') DEFAULT 'BORRADOR',
    usuario_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (cliente_id) REFERENCES clientes(id),
    FOREIGN KEY (usuario_id) REFERENCES users(id)
);


CREATE TABLE presupuestos_detalle (
    id INT AUTO_INCREMENT PRIMARY KEY,
    presupuesto_id INT NOT NULL,
    producto_id INT NOT NULL,
    cantidad DECIMAL(10,3) NOT NULL,
    precio DECIMAL(10,2) NOT NULL,

    FOREIGN KEY (presupuesto_id) REFERENCES presupuestos(id) ON DELETE CASCADE,
    FOREIGN KEY (producto_id) REFERENCES productos(id)
);

CREATE TABLE notas_pedido (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cliente_id INT NOT NULL,
    presupuesto_id INT NULL,
    usuario_id INT NOT NULL,

    estado ENUM('BORRADOR','APROBADA','ANULADA') DEFAULT 'BORRADOR',

    observaciones TEXT NULL,
    motivo_anulacion TEXT NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL,

    FOREIGN KEY (cliente_id) REFERENCES clientes(id),
    FOREIGN KEY (presupuesto_id) REFERENCES presupuestos(id),
    FOREIGN KEY (usuario_id) REFERENCES users(id)
);


CREATE TABLE notas_pedido_detalle (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nota_pedido_id INT NOT NULL,
    producto_id INT NOT NULL,
    cantidad DECIMAL(10,3) NOT NULL,
    precio DECIMAL(10,2) NOT NULL,

    FOREIGN KEY (nota_pedido_id) REFERENCES notas_pedido(id),
    FOREIGN KEY (producto_id) REFERENCES productos(id)
);

/*
Se agrega estas opciones al campo estado 'LIBRE','ASIGNADO','ANULADO'
ALTER TABLE presupuestos
ADD COLUMN estado ENUM('LIBRE','ASIGNADO','ANULADO') NOT NULL DEFAULT 'LIBRE';
ALTER TABLE notas_pedido
ADD COLUMN estado ENUM('ACTIVA','ANULADA') NOT NULL DEFAULT 'ACTIVA',
*/
ALTER TABLE presupuestos
ADD COLUMN pre_asign ENUM('LIBRE','ASIGNADO','ANULADO') NOT NULL DEFAULT 'LIBRE';
ALTER TABLE notas_pedido
ADD COLUMN anulado_at DATETIME NULL;


CREATE TABLE movimientos_stock (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tipo ENUM('ENTRADA','SALIDA','AJUSTE') NOT NULL,
    origen VARCHAR(50) NOT NULL, 
    referencia_id INT NULL,
    producto_id INT NULL,
    materia_prima_id INT NULL,
    cantidad DECIMAL(12,3) NOT NULL,
    observaciones TEXT NULL,
    usuario_id INT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);


ALTER TABLE productos
ADD stock DECIMAL(12,3) NOT NULL DEFAULT 0;

CREATE TABLE remitos_salida (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nota_pedido_id INT NOT NULL,
    usuario_id INT NOT NULL,
    estado ENUM('CONFIRMADO') DEFAULT 'CONFIRMADO',
    observaciones TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (nota_pedido_id) REFERENCES notas_pedido(id)
);

CREATE TABLE remitos_salida_detalle (
    id INT AUTO_INCREMENT PRIMARY KEY,
    remito_id INT NOT NULL,
    producto_id INT NOT NULL,
    cantidad DECIMAL(12,3) NOT NULL,
    FOREIGN KEY (remito_id) REFERENCES remitos_salida(id),
    FOREIGN KEY (producto_id) REFERENCES productos(id)
);


CREATE TABLE cuentas_corriente_clientes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cliente_id INT NOT NULL,
    fecha DATE NOT NULL,
    tipo ENUM('DEBITO','CREDITO') NOT NULL,
    origen VARCHAR(50) NOT NULL,
    referencia_id INT NOT NULL,
    monto DECIMAL(14,2) NOT NULL,
    saldo DECIMAL(14,2) NOT NULL,
    observaciones TEXT NULL,
    usuario_id INT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);


CREATE TABLE numeradores (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tipo VARCHAR(30) UNIQUE,
    ultimo_numero INT NOT NULL
);
INSERT INTO numeradores (tipo, ultimo_numero) VALUES ('REMITO', 0);

ALTER TABLE remitos_salida
ADD numero INT NOT NULL UNIQUE;


ALTER TABLE remitos_salida
ADD pdf_path VARCHAR(255) NULL,
ADD pdf_hash CHAR(64) NULL,
ADD firmado TINYINT(1) DEFAULT 0;

ALTER TABLE remitos_salida
ADD firmado_por INT NULL,
ADD firmado_at DATETIME NULL;

CREATE TABLE mails_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tipo ENUM('REMITO','PAGO') NOT NULL,
    referencia_id INT NOT NULL,
    email_destino VARCHAR(255) NOT NULL,
    asunto VARCHAR(255),
    enviado_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    estado ENUM('ENVIADO','ERROR') NOT NULL,
    error TEXT NULL,
    usuario_id INT NULL
);

CREATE TABLE pagos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cliente_id INT NOT NULL,
    usuario_id INT NOT NULL,
    monto DECIMAL(12,2) NOT NULL,
    medio_pago VARCHAR(50),
    observaciones TEXT,
    pdf_path VARCHAR(255),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
/*
* HASTA AQUI SE DESAROLLA LA PRIMER VERSION DEL SOFT, DE AHORA EN ADELANTE ES TOMAR AGREGADOS DE LA BASE Y MANIPULAR LA CREACION DE TABLAS DIRECTO EN PRODUCCION, SIEMPRE TENIENDO RESPALDOS DE SEGUIRDAD
 */
 /*29-1-26 modulos de recetas y produccion */
 CREATE TABLE recetas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    producto_id INT NOT NULL,
    nombre VARCHAR(150) NOT NULL,
    activo TINYINT(1) DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
); 
/*RELACION ENTRE PRODUCTO ID Y TABLA PRODUCTO, ESTE ES EL PRODUCTO A PRODUCIR*/
 
 CREATE TABLE recetas_detalle (
    id INT AUTO_INCREMENT PRIMARY KEY,
    receta_id INT NOT NULL,
    materia_prima_id INT NOT NULL,
    cantidad DECIMAL(12,3) NOT NULL,
    unidad VARCHAR(20),
    UNIQUE (receta_id, materia_prima_id)
);
/*RElACION ENTRE MATERIA PRIMA ID Y TABLA MATERIA PRIMA, ES LA MATIERA QUE SE USA PARA PRODUCIR UN PRODUCTO*/ 

CREATE TABLE reservas_materia_prima (
    id INT AUTO_INCREMENT PRIMARY KEY,
    orden_produccion_id INT NOT NULL,
    materia_prima_id INT NOT NULL,
    cantidad DECIMAL(12,3) NOT NULL,
    estado ENUM('RESERVADO','CONSUMIDO','LIBERADO') DEFAULT 'RESERVADO',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
/*se le agrega la relacion con materia prima y orden de prod con sus repsectivas tablas*/
/*se reqalizan cambios a la tabla de produccion
*CREATE TABLE ordenes_produccion ( //cambio de nombre prncipalmente
*    id INT AUTO_INCREMENT PRIMARY KEY,
*    producto_id INT NOT NULL, //relacionado al prducto
*    receta_id INT NOT NULL, //relacionado a la receta
*    cantidad DECIMAL(12,3) NOT NULL,
*    estado ENUM('BORRADOR','RESERVADA','EN_PRODUCCION','FINALIZADA','CANCELADA') DEFAULT 'BORRADOR',
*    observaciones TEXT,
*    usuario_id INT NOT NULL, //relacionado al usuario
*    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
*/
/*
3/2/26 edtitar tabla materia prima para agregar precio actual:
*/
ALTER TABLE `materias_primas`
ADD COLUMN `precio_actual` DECIMAL(10,3) NOT NULL AFTER `created_at`;
/*5/2/2026 EDITAR COLUMNAS EN TABLA ORDENES PRODUCCION*/
ALTER TABLE `ordenes_produccion`
CHANGE COLUMN `estado` `estado` ENUM('PENDIENTE','EN_PRODUCCION','FINALIZADA','CANCELADA') 
NOT NULL DEFAULT 'PENDIENTE' COLLATE 'utf8mb4_unicode_ci' AFTER `fecha_entrega`;
/*
cambio en base proveedores, creacion del proveedor particular id 0
cambio en base orden compra, default id proveedor 0 para cuando se hacen oc automaticas
*/
/*14/2/26 cambios para control de niveles de stock*/
/*tabla prductos*/
ALTER TABLE productos
ADD stock_minimo DECIMAL(10,2) DEFAULT 0,
ADD stock_maximo DECIMAL(10,2) DEFAULT 0,
ADD stock_critico DECIMAL(10,2) DEFAULT 0;

/*tabla materias primas*/
ALTER TABLE materias_primas
ADD stock_minimo DECIMAL(10,2) DEFAULT 0,
ADD stock_maximo DECIMAL(10,2) DEFAULT 0,
ADD stock_critico DECIMAL(10,2) DEFAULT 0;

/*1-3-26 creacio de tabla para registro del hisotrial de stock de reserva de mp*/
CREATE TABLE `tbl_historico_reserva_mp` (
	`id` INT(11) NOT NULL,
	`orden_produccion_id` INT(11) NOT NULL,
	`materia_prima_id` INT(11) NOT NULL,
	`cantidad` DECIMAL(12,3) NOT NULL,
	`estado` ENUM('RESERVADO','CONSUMIDO','LIBERADO') NULL DEFAULT 'RESERVADO' COLLATE 'utf8mb4_unicode_ci',
	`created_at` DATETIME NULL DEFAULT current_timestamp(),
	PRIMARY KEY (`id`) USING BTREE,
	INDEX `orden_vs_tblordenid_FK12` (`orden_produccion_id`) USING BTREE,
	INDEX `materiprima_vs_tblmateria_FK22` (`materia_prima_id`) USING BTREE,
	CONSTRAINT `materiprima_vs_tblmateria_FK22` FOREIGN KEY (`materia_prima_id`) REFERENCES `materias_primas` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT,
	CONSTRAINT `orden_vs_tblordenid_FK12` FOREIGN KEY (`orden_produccion_id`) REFERENCES `ordenes_produccion` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT
)
COLLATE='utf8mb4_unicode_ci'
ENGINE=InnoDB
AUTO_INCREMENT=0
;
/*trigger para pasar de materia prima reservada a historica*/
DELIMITER $$

CREATE TRIGGER hitorico_reserva_mp_trig
AFTER DELETE ON reservas_materia_prima
FOR EACH ROW
BEGIN
    INSERT INTO tbl_historico_reserva_mp (
        id,
        orden_produccion_id,
        materia_prima_id,
        cantidad,
        estado,
        created_at
    )
    VALUES (
        OLD.id,
        OLD.orden_produccion_id,
        OLD.materia_prima_id,
        OLD.cantidad,
        OLD.estado,
        OLD.created_at
    );
END$$

DELIMITER ;

/*4/3/2026 agregado de columna fecha de finalizacion e ordenes_produccion*/
ALTER TABLE `ordenes_produccion`
	ADD COLUMN `finalizada_at` DATETIME NULL AFTER `receta_id`;
 /*cambiamos el estado por default para que si producimos podemos crear un trigger de foirma automatica after update*/	
ALTER TABLE `tbl_historico_reserva_mp`
	CHANGE COLUMN `estado` `estado` ENUM('RESERVADO','CONSUMIDO','LIBERADO') NULL DEFAULT 'CONSUMIDO' COLLATE 'utf8mb4_unicode_ci' AFTER `cantidad`;


/*17-3-26 cambios para ñps moduloso de produccion y reserva de MP*/
ALTER TABLE `ordenes_compra_detalle`
	ADD COLUMN `referencia_oc` ENUM('STOCKLEVEL','STOCK_OP') NULL DEFAULT 'STOCKLEVEL';
ALTER TABLE `ordenes_compra_detalle`
	ADD COLUMN `referencia_id` INT NULL AFTER `referencia_oc`;
RENAME TABLE `stock_materias_primas` TO `stock_mp_transito`;
ALTER TABLE `orden_produccion_detalle`
	ADD COLUMN `confirma_produccion` DATETIME NULL AFTER `observaciones`;
ALTER TABLE `orden_produccion_detalle`
	ADD COLUMN `id_tbl_ordendetalle` INT NOT NULL AUTO_INCREMENT FIRST,
	ADD PRIMARY KEY (`id_tbl_ordendetalle`);




 

