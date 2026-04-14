ALTER TABLE usuarios
    ADD COLUMN rechazado TINYINT(1) NOT NULL DEFAULT 0 AFTER aprobado;

CREATE TABLE notificaciones (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    usuario_destino VARCHAR(100) NOT NULL,
    tipo VARCHAR(50) NOT NULL,
    mensaje TEXT NOT NULL,
    leida TINYINT(1) NOT NULL DEFAULT 0,
    fecha DATETIME NOT NULL,
    INDEX idx_notificaciones_destino_fecha (usuario_destino, fecha),
    INDEX idx_notificaciones_leida (usuario_destino, leida)
);
