-- BOVITRACK V1 · Inicialización de MySQL.
-- La imagen mysql:8.4 ejecuta este archivo una sola vez, cuando el volumen está vacío.
-- Crea las dos bases con el mismo esquema final (01-esquema.sql). Solo `bovitrack`
-- recibe los usuarios de prueba (02-usuarios-demo.sql); `bovitrack_test` queda vacía
-- para que las pruebas puedan crear y borrar datos sin afectar a la base principal.
-- Los archivos 01 y 02 se montan en /docker/mysql-sql (fuera de docker-entrypoint-initdb.d)
-- para que la imagen no los ejecute por su cuenta.

SET NAMES utf8mb4;

CREATE DATABASE IF NOT EXISTS bovitrack      CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE IF NOT EXISTS bovitrack_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

GRANT ALL PRIVILEGES ON bovitrack.*      TO 'bovitrack'@'%';
GRANT ALL PRIVILEGES ON bovitrack_test.* TO 'bovitrack'@'%';
FLUSH PRIVILEGES;

USE bovitrack;
SOURCE /docker/mysql-sql/01-esquema.sql
SOURCE /docker/mysql-sql/02-usuarios-demo.sql

USE bovitrack_test;
SOURCE /docker/mysql-sql/01-esquema.sql
