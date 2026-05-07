CREATE TABLE actividad_sistema (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NULL,
    usuario VARCHAR(100) NULL,
    tipo_evento VARCHAR(50) NOT NULL,
    entidad VARCHAR(50) NOT NULL,
    entidad_id INT NULL,
    entidad_codigo VARCHAR(80) NULL,
    descripcion VARCHAR(255) NOT NULL,
    metadata_json TEXT NULL,
    fecha_evento DATETIME NOT NULL,
    creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_actividad_fecha (fecha_evento),
    INDEX idx_actividad_tipo (tipo_evento),
    INDEX idx_actividad_entidad (entidad, entidad_id),
    INDEX idx_actividad_usuario (usuario_id)
);
