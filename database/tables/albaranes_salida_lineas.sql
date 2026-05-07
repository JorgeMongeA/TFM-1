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
