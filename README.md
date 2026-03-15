# TFM - Aplicación Web de Gestión de Inventario

## Autor

Jorge Monge Antolín
Trabajo Final de Máster
Universitat Oberta de Catalunya (UOC)
[jorgemonge@uoc.edu](mailto:jorgemonge@uoc.edu)

## Descripción del proyecto

Este proyecto corresponde al desarrollo del Trabajo Final de Máster (TFM).
Consiste en el diseño e implementación de una aplicación web para la gestión de inventario y operaciones logísticas.

El sistema permitirá mejorar la trazabilidad de materiales, optimizar la gestión de entradas y salidas de inventario y reducir errores en los procesos manuales actualmente existentes.

## Tecnologías utilizadas

- PHP 8+
- MySQL / MariaDB
- HTML5
- CSS3
- Bootstrap
- Arquitectura MVC
- Git y GitHub para control de versiones

## Estructura del proyecto

public
Punto de entrada de la aplicación web. Contiene el archivo index.php y los recursos públicos como CSS, JavaScript e imágenes.

app
Contiene la lógica de la aplicación organizada en arquitectura MVC.

Controllers → Controladores de la aplicación
Models → Modelos de acceso a datos
Views → Vistas del sistema
Middleware → Control de acceso y roles
Core → Componentes base como router o conexión a base de datos

config
Archivos de configuración de la aplicación.

storage
Archivos generados en tiempo de ejecución, como logs.

sql
Scripts SQL para la creación de la base de datos y tablas del sistema.

## Configuración en entorno local

1. Clonar el repositorio

2. Crear un archivo config.php a partir de config/config.example.php

3. Importar el archivo sql/schema.sql en una base de datos MySQL

4. Iniciar el servidor de desarrollo con PHP

php -S localhost:8000 -t public

5. Acceder desde el navegador a:

http://localhost:8000

## Funcionalidades previstas (MVP)

- Sistema de autenticación de usuarios
- Gestión de roles (administrador, operaciones, cliente)
- Registro y consulta de inventario
- Gestión de entradas y movimientos de inventario
- Trazabilidad de operaciones

## Despliegue

- Acceso web: https://www.maximosl.com/CON/public/login.php
- \*Credenciales temporales para test y pruebas.\*
  user: almacen
  pass: 1234

El repositorio GitHub se utilizará como control de versiones y respaldo del proyecto.

La aplicación se desplegará en un servidor de hosting convencional mediante FTP, con base de datos MySQL alojada en el mismo servidor.

## Estado del proyecto

Proyecto en fase de desarrollo como parte del Trabajo Final de Máster.
