# Database SQL

- `schema.sql` contiene el esquema consolidado para levantar la base de datos completa desde cero.
- `tables/` contiene un archivo SQL por tabla con la estructura consolidada actual.
- `legacy/` conserva migraciones y scripts históricos ya consolidados en el esquema principal.

## Orden recomendado

1. Ejecutar `database/schema.sql` en una base vacía.
2. Usar `database/tables/` solo para revisiones puntuales por tabla o mantenimiento controlado.
3. Consultar `database/legacy/` como histórico de cambios y migraciones anteriores.
