CREATE TABLE pedidos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    codigo_pedido VARCHAR(50) NOT NULL UNIQUE,
    usuario_creacion_id INT NULL,
    usuario_creacion VARCHAR(100) NOT NULL,
    estado ENUM('pendiente', 'en_preparacion', 'preparado', 'completado') NOT NULL DEFAULT 'pendiente',
    observaciones TEXT NULL,
    total_lineas INT NOT NULL DEFAULT 0,
    total_bultos INT NOT NULL DEFAULT 0,
    usuario_gestion_id INT NULL,
    usuario_gestion VARCHAR(100) NULL,
    fecha_creacion DATETIME NOT NULL,
    fecha_ultima_gestion DATETIME NULL,
    creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_pedidos_estado (estado),
    INDEX idx_pedidos_usuario_creacion (usuario_creacion_id),
    INDEX idx_pedidos_fecha_creacion (fecha_creacion)
);

CREATE TABLE pedido_lineas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pedido_id INT NOT NULL,
    inventario_id INT NOT NULL,
    editorial VARCHAR(255) NOT NULL,
    colegio VARCHAR(255) NOT NULL,
    codigo_centro VARCHAR(50) NOT NULL,
    ubicacion VARCHAR(255) NOT NULL,
    fecha_entrada DATE NULL,
    bultos INT NOT NULL DEFAULT 0,
    destino VARCHAR(50) NULL,
    `orden` VARCHAR(100) NULL,
    indicador_completa VARCHAR(100) NULL,
    creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_pedido_lineas_pedido (pedido_id),
    INDEX idx_pedido_lineas_inventario (inventario_id),
    CONSTRAINT fk_pedido_lineas_pedido
        FOREIGN KEY (pedido_id) REFERENCES pedidos(id)
        ON DELETE CASCADE
);
