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
