START TRANSACTION;

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

UPDATE usuarios u
JOIN roles r_actual ON r_actual.id = u.rol_id
JOIN roles r_destino ON r_destino.nombre = 'almacen'
SET u.rol_id = r_destino.id
WHERE r_actual.nombre IN ('admin', 'operaciones');

UPDATE usuarios u
JOIN roles r_actual ON r_actual.id = u.rol_id
JOIN roles r_destino ON r_destino.nombre = 'edelvives'
SET u.rol_id = r_destino.id
WHERE r_actual.nombre = 'cliente';

DELETE r
FROM roles r
LEFT JOIN usuarios u ON u.rol_id = r.id
WHERE r.nombre IN ('admin', 'operaciones', 'cliente')
  AND u.id IS NULL;

COMMIT;
