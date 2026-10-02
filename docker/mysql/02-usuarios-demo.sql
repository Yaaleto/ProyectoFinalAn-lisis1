-- BOVITRACK V1 · Usuarios de prueba (solo en la base `bovitrack`; `bovitrack_test` queda vacía).
-- Contiene lo mínimo para iniciar sesión: una organización, una finca y dos cuentas.
-- Contraseña de ambas cuentas: BoviTrackDemo2026!  (solo desarrollo; hash bcrypt).
-- No se crean animales ni registros operativos.

SET NAMES utf8mb4;

INSERT INTO organizacion_ganadera (codigo, nombre, activo)
VALUES ('BOVITRACK-DEMO', 'BOVITRACK Demo', 1);

INSERT INTO finca (id_organizacion, codigo, nombre, activo)
SELECT id_organizacion, 'DEMO-01', 'Finca Demo', 1
FROM organizacion_ganadera WHERE codigo = 'BOVITRACK-DEMO';

INSERT INTO usuario (id_organizacion, nombre, correo, contrasena_hash, rol, finca_activa_id, activo)
SELECT o.id_organizacion, 'Administrador Demo', 'admin.demo@bovitrack.local',
       '$2y$12$PLBz.D3Eb7qbQKjTns/iqeG09TJfCdzYRvP/GTo8JPeTTQgy3Or5u', 'ADMINISTRADOR', NULL, 1
FROM organizacion_ganadera o WHERE o.codigo = 'BOVITRACK-DEMO';

INSERT INTO usuario (id_organizacion, nombre, correo, contrasena_hash, rol, finca_activa_id, activo)
SELECT o.id_organizacion, 'Encargado Demo', 'encargado.demo@bovitrack.local',
       '$2y$12$PLBz.D3Eb7qbQKjTns/iqeG09TJfCdzYRvP/GTo8JPeTTQgy3Or5u', 'ENCARGADO', f.id_finca, 1
FROM organizacion_ganadera o
JOIN finca f ON f.id_organizacion = o.id_organizacion AND f.codigo = 'DEMO-01'
WHERE o.codigo = 'BOVITRACK-DEMO';
