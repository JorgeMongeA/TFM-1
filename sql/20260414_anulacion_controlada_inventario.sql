ALTER TABLE inventario
    MODIFY COLUMN estado ENUM('activo', 'historico', 'anulado') NOT NULL DEFAULT 'activo',
    ADD COLUMN usuario_anulacion_id INT NULL AFTER usuario_confirmacion,
    ADD COLUMN usuario_anulacion VARCHAR(100) NULL AFTER usuario_anulacion_id,
    ADD COLUMN fecha_anulacion DATETIME NULL AFTER usuario_anulacion,
    ADD COLUMN motivo_anulacion VARCHAR(255) NULL AFTER fecha_anulacion,
    ADD INDEX idx_inventario_fecha_anulacion (fecha_anulacion);
