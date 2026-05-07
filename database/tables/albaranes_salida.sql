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
