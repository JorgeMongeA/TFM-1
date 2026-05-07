CREATE TABLE pedido_eventos (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    pedido_id INT NOT NULL,
    tipo_evento VARCHAR(50) NOT NULL,
    estado_anterior VARCHAR(50) NULL,
    estado_nuevo VARCHAR(50) NULL,
    usuario_id INT NULL,
    usuario VARCHAR(100) NULL,
    descripcion VARCHAR(255) NOT NULL,
    metadata_json TEXT NULL,
    fecha_evento DATETIME NOT NULL,
    creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_pedido_eventos_pedido_fecha (pedido_id, fecha_evento),
    INDEX idx_pedido_eventos_tipo (tipo_evento),
    CONSTRAINT fk_pedido_eventos_pedido
        FOREIGN KEY (pedido_id) REFERENCES pedidos(id)
        ON DELETE CASCADE
);
