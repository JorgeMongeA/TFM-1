CREATE TABLE roles (
id INT AUTO_INCREMENT PRIMARY KEY,
nombre VARCHAR(50) UNIQUE
);

CREATE TABLE usuarios (
id INT AUTO_INCREMENT PRIMARY KEY,
username VARCHAR(100) UNIQUE,
password VARCHAR(255),
rol_id INT,
FOREIGN KEY (rol_id) REFERENCES roles(id)
);

INSERT INTO roles (nombre) VALUES
('admin'),
('operaciones'),
('cliente');

INSERT INTO usuarios (username, password, rol_id)
VALUES (
'admin',
'$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9Y9K/5Cz0aC9m/7aS7kF9e',
1
);

CREATE TABLE centros (
codigo_centro VARCHAR(50) PRIMARY KEY,
nombre_centro VARCHAR(255) NULL,
ciudad VARCHAR(150) NULL,
tipo VARCHAR(100) NULL,
codigo_grupo VARCHAR(50) NULL,
actualizado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
