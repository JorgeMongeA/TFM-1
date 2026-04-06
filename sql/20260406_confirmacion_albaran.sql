ALTER TABLE inventario
    ADD COLUMN estado ENUM('activo', 'historico') NOT NULL DEFAULT 'activo' AFTER indicador_completa,
    ADD COLUMN fecha_confirmacion_salida DATETIME NULL AFTER estado,
    ADD COLUMN usuario_confirmacion_id INT NULL AFTER fecha_confirmacion_salida,
    ADD COLUMN usuario_confirmacion VARCHAR(100) NULL AFTER usuario_confirmacion_id,
    ADD COLUMN numero_albaran VARCHAR(50) NULL AFTER usuario_confirmacion,
    ADD COLUMN sync_pendiente_historico TINYINT(1) NOT NULL DEFAULT 0 AFTER numero_albaran,
    ADD COLUMN fecha_sync_historico DATETIME NULL AFTER sync_pendiente_historico;

ALTER TABLE inventario
    ADD INDEX idx_inventario_estado (estado),
    ADD INDEX idx_inventario_numero_albaran (numero_albaran),
    ADD INDEX idx_inventario_confirmacion (fecha_confirmacion_salida),
    ADD INDEX idx_inventario_sync_historico (estado, sync_pendiente_historico);

CREATE TABLE albaranes_salida (
    id INT AUTO_INCREMENT PRIMARY KEY,
    numero_albaran VARCHAR(50) NOT NULL UNIQUE,
    fecha_confirmacion DATETIME NOT NULL,
    usuario_confirmacion_id INT NULL,
    usuario_confirmacion VARCHAR(100) NULL,
    empresa_recogida VARCHAR(150) NOT NULL DEFAULT 'MAXIMO SERVICIOS LOGISTICOS S.L.U.',
    total_lineas INT NOT NULL,
    total_bultos INT NOT NULL,
    creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_albaranes_salida_fecha_confirmacion (fecha_confirmacion)
);

CREATE TABLE albaranes_salida_lineas (
    albaran_id INT NOT NULL,
    inventario_id INT NOT NULL,
    PRIMARY KEY (albaran_id, inventario_id),
    UNIQUE KEY uq_albaranes_salida_lineas_inventario (inventario_id),
    CONSTRAINT fk_albaranes_salida_lineas_albaran
        FOREIGN KEY (albaran_id) REFERENCES albaranes_salida(id),
    CONSTRAINT fk_albaranes_salida_lineas_inventario
        FOREIGN KEY (inventario_id) REFERENCES inventario(id)
);
