ALTER TABLE usuarios
    ADD COLUMN email VARCHAR(150) NULL AFTER username,
    ADD COLUMN activo TINYINT(1) NOT NULL DEFAULT 1 AFTER rol_id,
    ADD COLUMN aprobado TINYINT(1) NOT NULL DEFAULT 1 AFTER activo,
    ADD COLUMN aprobado_por_id INT NULL AFTER aprobado,
    ADD COLUMN fecha_aprobacion DATETIME NULL AFTER aprobado_por_id,
    ADD COLUMN creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER fecha_aprobacion,
    ADD COLUMN actualizado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER creado_en,
    ADD UNIQUE INDEX uq_usuarios_email (email),
    ADD INDEX idx_usuarios_estado (aprobado, activo),
    ADD INDEX idx_usuarios_aprobado_por (aprobado_por_id);
