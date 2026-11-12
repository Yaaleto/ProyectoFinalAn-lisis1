-- BOVITRACK · Segunda organización para demostrar el aislamiento entre organizaciones.
-- Una organización, una finca y un administrador. Contraseña: BoviTrackDemo2026!  (solo desarrollo).
-- Se ejecuta una sola vez sobre la base con los datos demo; la organización y la finca quedan con id 2.

SET NAMES utf8mb4;

INSERT INTO organizacion_ganadera (codigo, nombre, activo)
VALUES ('GANADERA-NORTE', 'Ganadera del Norte', 1);

INSERT INTO finca (id_organizacion, codigo, nombre, activo)
VALUES (2, 'NORTE-01', 'Finca El Norte', 1);

INSERT INTO usuario (id_organizacion, nombre, correo, contrasena_hash, rol, finca_activa_id, activo)
VALUES (2, 'Administrador Norte', 'admin.norte@bovitrack.local',
        '$2y$12$PLBz.D3Eb7qbQKjTns/iqeG09TJfCdzYRvP/GTo8JPeTTQgy3Or5u', 'ADMINISTRADOR', NULL, 1);
