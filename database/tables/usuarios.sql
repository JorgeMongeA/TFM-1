CREATE TABLE usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL UNIQUE,
    email VARCHAR(150) NULL,
    password VARCHAR(255) NOT NULL,
    rol_id INT NOT NULL,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    aprobado TINYINT(1) NOT NULL DEFAULT 1,
    rechazado TINYINT(1) NOT NULL DEFAULT 0,
    aprobado_por_id INT NULL,
    fecha_aprobacion DATETIME NULL,
    creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (rol_id) REFERENCES roles(id),
    UNIQUE KEY uq_usuarios_email (email),
    INDEX idx_usuarios_estado (aprobado, activo),
    INDEX idx_usuarios_aprobado_por (aprobado_por_id)
);

INSERT INTO usuarios (username, password, rol_id)
VALUES (
'admin',
'$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9Y9K/5Cz0aC9m/7aS7kF9e',
1
);
