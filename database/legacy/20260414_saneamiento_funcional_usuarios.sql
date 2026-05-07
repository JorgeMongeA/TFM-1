INSERT INTO roles (nombre)
SELECT 'almacen'
WHERE NOT EXISTS (
    SELECT 1
    FROM roles
    WHERE nombre = 'almacen'
);

INSERT INTO roles (nombre)
SELECT 'edelvives'
WHERE NOT EXISTS (
    SELECT 1
    FROM roles
    WHERE nombre = 'edelvives'
);

UPDATE usuarios
SET email = 'almacen@maximosl.com'
WHERE username = 'almacen';
