CREATE TABLE centros (
    codigo_centro VARCHAR(50) PRIMARY KEY,
    nombre_centro VARCHAR(255) NULL,
    ciudad VARCHAR(150) NULL,
    codigo_congregacion VARCHAR(50) NULL,
    congregacion VARCHAR(255) NULL,
    entrada VARCHAR(20) NULL,
    almacen VARCHAR(50) NULL,
    destino VARCHAR(50) NULL,
    tipo VARCHAR(100) NULL,
    codigo_grupo VARCHAR(50) NULL,
    actualizado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
