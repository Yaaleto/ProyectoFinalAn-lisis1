-- BOVITRACK V1 · Usuarios de prueba (solo en la base `bovitrack`; `bovitrack_test` queda vacía).
-- Una organización, una finca y dos cuentas, sin animales ni registros operativos.
-- Contraseña de ambas cuentas: BoviTrackDemo2026!  (solo desarrollo; hash bcrypt).
-- Los id son AUTO_INCREMENT; con la base vacía, la organización y la finca quedan con id 1.

SET NAMES utf8mb4;

INSERT INTO organizacion_ganadera (codigo, nombre, activo)
VALUES ('BOVITRACK-DEMO', 'BOVITRACK Demo', 1);

INSERT INTO finca (id_organizacion, codigo, nombre, activo)
VALUES (1, 'DEMO-01', 'Finca Demo', 1);

INSERT INTO usuario (id_organizacion, nombre, correo, contrasena_hash, rol, finca_activa_id, activo)
VALUES (1, 'Administrador Demo', 'admin.demo@bovitrack.local',
        '$2y$12$PLBz.D3Eb7qbQKjTns/iqeG09TJfCdzYRvP/GTo8JPeTTQgy3Or5u', 'ADMINISTRADOR', NULL, 1);

INSERT INTO usuario (id_organizacion, nombre, correo, contrasena_hash, rol, finca_activa_id, activo)
VALUES (1, 'Encargado Demo', 'encargado.demo@bovitrack.local',
        '$2y$12$PLBz.D3Eb7qbQKjTns/iqeG09TJfCdzYRvP/GTo8JPeTTQgy3Or5u', 'ENCARGADO', 1, 1);
