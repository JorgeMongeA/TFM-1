CREATE TABLE roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(50) NOT NULL UNIQUE
);

CREATE TABLE usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    rol_id INT NOT NULL,
    FOREIGN KEY (rol_id) REFERENCES roles(id)
);

INSERT INTO roles (nombre) VALUES
('almacen'),
('edelvives');

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

CREATE TABLE inventario (
    id INT PRIMARY KEY,
    editorial VARCHAR(255) NOT NULL,
    colegio VARCHAR(255) NOT NULL,
    codigo_centro VARCHAR(50) NOT NULL,
    ubicacion VARCHAR(255) NOT NULL,
    fecha_entrada DATE NOT NULL,
    fecha_salida DATE NULL,
    bultos INT NULL,
    destino VARCHAR(50) NULL,
    `orden` VARCHAR(100) NULL,
    indicador_completa VARCHAR(100) NULL,
    estado ENUM('activo', 'historico') NOT NULL DEFAULT 'activo',
    fecha_confirmacion_salida DATETIME NULL,
    usuario_confirmacion_id INT NULL,
    usuario_confirmacion VARCHAR(100) NULL,
    numero_albaran VARCHAR(50) NULL,
    sync_pendiente_historico TINYINT(1) NOT NULL DEFAULT 0,
    fecha_sync_historico DATETIME NULL,
    creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_inventario_estado (estado),
    INDEX idx_inventario_codigo_centro (codigo_centro),
    INDEX idx_inventario_numero_albaran (numero_albaran),
    INDEX idx_inventario_confirmacion (fecha_confirmacion_salida),
    INDEX idx_inventario_sync_historico (estado, sync_pendiente_historico)
);

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

CREATE TABLE pedidos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    codigo_pedido VARCHAR(50) NOT NULL UNIQUE,
    usuario_creacion_id INT NULL,
    usuario_creacion VARCHAR(100) NOT NULL,
    estado ENUM('pendiente', 'en_preparacion', 'preparado', 'completado') NOT NULL DEFAULT 'pendiente',
    observaciones TEXT NULL,
    total_lineas INT NOT NULL DEFAULT 0,
    total_bultos INT NOT NULL DEFAULT 0,
    usuario_gestion_id INT NULL,
    usuario_gestion VARCHAR(100) NULL,
    fecha_creacion DATETIME NOT NULL,
    fecha_ultima_gestion DATETIME NULL,
    creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_pedidos_estado (estado),
    INDEX idx_pedidos_usuario_creacion (usuario_creacion_id),
    INDEX idx_pedidos_fecha_creacion (fecha_creacion)
);

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
