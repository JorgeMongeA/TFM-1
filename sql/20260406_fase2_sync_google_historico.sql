ALTER TABLE albaranes_salida
    ADD COLUMN empresa_recogida VARCHAR(150) NOT NULL DEFAULT 'MAXIMO SERVICIOS LOGISTICOS S.L.U.' AFTER usuario_confirmacion;
