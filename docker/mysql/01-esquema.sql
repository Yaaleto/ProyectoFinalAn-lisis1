-- BOVITRACK V1 · Esquema físico completo (MySQL 8.4)
-- Fuente: documentacion/diccionario/5. Modelo relacional.docx (26/09/2026) y DERCAS v1.2.
-- Se ejecuta tal cual sobre las bases `bovitrack` y `bovitrack_test` (véase 00-inicializar.sh).
-- Sin datos: solo estructura, claves e índices. Motor InnoDB, utf8mb4 / utf8mb4_unicode_ci.
-- Todas las claves foráneas usan ON DELETE RESTRICT y ON UPDATE RESTRICT (RN-06).

SET NAMES utf8mb4;

-- ---------------------------------------------------------------------------
-- D9 · organizacion_ganadera (tenant; no depende de ninguna tabla)
-- ---------------------------------------------------------------------------
CREATE TABLE organizacion_ganadera (
    id_organizacion INT UNSIGNED NOT NULL AUTO_INCREMENT,
    codigo          VARCHAR(50)  NOT NULL,
    nombre          VARCHAR(150) NOT NULL,
    telefono        VARCHAR(30)  NULL,
    direccion       VARCHAR(255) NULL,
    activo          TINYINT(1)   NOT NULL DEFAULT 1,
    PRIMARY KEY (id_organizacion),
    UNIQUE KEY uq_organizacion_codigo (codigo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- D2 · finca
-- ---------------------------------------------------------------------------
CREATE TABLE finca (
    id_finca        INT UNSIGNED NOT NULL AUTO_INCREMENT,
    id_organizacion INT UNSIGNED NOT NULL,
    codigo          VARCHAR(50)  NOT NULL,
    nombre          VARCHAR(150) NOT NULL,
    ubicacion       VARCHAR(200) NULL,
    activo          TINYINT(1)   NOT NULL DEFAULT 1,
    PRIMARY KEY (id_finca),
    UNIQUE KEY uq_finca_organizacion_codigo (id_organizacion, codigo),            -- RN-04
    UNIQUE KEY uq_finca_id_organizacion (id_finca, id_organizacion),              -- soporte de FK compuestas
    CONSTRAINT fk_finca_organizacion FOREIGN KEY (id_organizacion)
        REFERENCES organizacion_ganadera (id_organizacion) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- D1 · usuario
-- La FK compuesta (finca_activa_id, id_organizacion) exige que la finca asignada
-- sea de la misma organización; con finca_activa_id NULL (Administrador) no se evalúa.
-- ---------------------------------------------------------------------------
CREATE TABLE usuario (
    id_usuario      INT UNSIGNED NOT NULL AUTO_INCREMENT,
    id_organizacion INT UNSIGNED NOT NULL,
    nombre          VARCHAR(150) NOT NULL,
    correo          VARCHAR(180) NOT NULL,
    contrasena_hash VARCHAR(255) NOT NULL,
    rol             VARCHAR(15)  NOT NULL,
    finca_activa_id INT UNSIGNED NULL,
    activo          TINYINT(1)   NOT NULL DEFAULT 1,
    PRIMARY KEY (id_usuario),
    UNIQUE KEY uq_usuario_correo (correo),
    KEY idx_usuario_organizacion (id_organizacion),
    KEY idx_usuario_finca_organizacion (finca_activa_id, id_organizacion),
    CONSTRAINT fk_usuario_organizacion FOREIGN KEY (id_organizacion)
        REFERENCES organizacion_ganadera (id_organizacion) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_usuario_finca FOREIGN KEY (finca_activa_id, id_organizacion)
        REFERENCES finca (id_finca, id_organizacion) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT ck_usuario_rol CHECK (rol IN ('ADMINISTRADOR', 'ENCARGADO')),
    CONSTRAINT ck_usuario_finca_por_rol CHECK (                                    -- RN-05
        (rol = 'ADMINISTRADOR' AND finca_activa_id IS NULL)
        OR (rol = 'ENCARGADO' AND finca_activa_id IS NOT NULL))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- D3 · raza y vacuna (catálogos por organización)
-- ---------------------------------------------------------------------------
CREATE TABLE raza (
    id_raza         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    id_organizacion INT UNSIGNED NOT NULL,
    nombre          VARCHAR(100) NOT NULL,
    activa          TINYINT(1)   NOT NULL DEFAULT 1,
    PRIMARY KEY (id_raza),
    UNIQUE KEY uq_raza_organizacion_nombre (id_organizacion, nombre),             -- CA-03-A
    UNIQUE KEY uq_raza_id_organizacion (id_raza, id_organizacion),
    CONSTRAINT fk_raza_organizacion FOREIGN KEY (id_organizacion)
        REFERENCES organizacion_ganadera (id_organizacion) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE vacuna (
    id_vacuna       INT UNSIGNED NOT NULL AUTO_INCREMENT,
    id_organizacion INT UNSIGNED NOT NULL,
    nombre          VARCHAR(120) NOT NULL,
    descripcion     TEXT         NULL,
    activa          TINYINT(1)   NOT NULL DEFAULT 1,
    PRIMARY KEY (id_vacuna),
    UNIQUE KEY uq_vacuna_organizacion_nombre (id_organizacion, nombre),           -- CA-03-A
    UNIQUE KEY uq_vacuna_id_organizacion (id_vacuna, id_organizacion),
    CONSTRAINT fk_vacuna_organizacion FOREIGN KEY (id_organizacion)
        REFERENCES organizacion_ganadera (id_organizacion) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- D4 · animal (genealogía recursiva con id_madre e id_padre)
-- ---------------------------------------------------------------------------
CREATE TABLE animal (
    id_animal           INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    id_organizacion     INT UNSIGNED  NOT NULL,
    id_finca            INT UNSIGNED  NOT NULL,
    codigo_interno      VARCHAR(36)   NOT NULL,
    numero_arete        VARCHAR(50)   NOT NULL,
    nombre              VARCHAR(120)  NULL,
    sexo                VARCHAR(6)    NOT NULL,
    raza_id             INT UNSIGNED  NULL,
    fecha_nacimiento    DATE          NULL,
    fecha_ingreso       DATE          NOT NULL,                                  -- RN-19
    peso_nacimiento_kg  DECIMAL(8,2)  NULL,
    origen              VARCHAR(15)   NOT NULL,
    procedencia         VARCHAR(200)  NULL,
    id_madre            INT UNSIGNED  NULL,
    id_padre            INT UNSIGNED  NULL,
    es_productora       TINYINT(1)    NOT NULL DEFAULT 0,
    estado              VARCHAR(10)   NOT NULL DEFAULT 'ACTIVO',
    fecha_salida        DATE          NULL,
    creado_por          INT UNSIGNED  NOT NULL,
    fecha_creacion      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    modificado_por      INT UNSIGNED  NULL,
    fecha_actualizacion DATETIME      NULL,
    PRIMARY KEY (id_animal),
    UNIQUE KEY uq_animal_codigo_interno (codigo_interno),                         -- RN-03
    UNIQUE KEY uq_animal_organizacion_arete (id_organizacion, numero_arete),      -- RN-02
    UNIQUE KEY uq_animal_id_organizacion (id_animal, id_organizacion),
    KEY idx_animal_finca_organizacion (id_finca, id_organizacion),
    KEY idx_animal_raza_organizacion (raza_id, id_organizacion),
    KEY idx_animal_madre_organizacion (id_madre, id_organizacion),
    KEY idx_animal_padre_organizacion (id_padre, id_organizacion),
    KEY idx_animal_creado_por (creado_por),
    KEY idx_animal_modificado_por (modificado_por),
    CONSTRAINT fk_animal_organizacion FOREIGN KEY (id_organizacion)
        REFERENCES organizacion_ganadera (id_organizacion) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_animal_finca FOREIGN KEY (id_finca, id_organizacion)
        REFERENCES finca (id_finca, id_organizacion) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_animal_raza FOREIGN KEY (raza_id, id_organizacion)
        REFERENCES raza (id_raza, id_organizacion) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_animal_madre FOREIGN KEY (id_madre, id_organizacion)
        REFERENCES animal (id_animal, id_organizacion) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_animal_padre FOREIGN KEY (id_padre, id_organizacion)
        REFERENCES animal (id_animal, id_organizacion) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_animal_creado_por FOREIGN KEY (creado_por)
        REFERENCES usuario (id_usuario) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_animal_modificado_por FOREIGN KEY (modificado_por)
        REFERENCES usuario (id_usuario) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT ck_animal_sexo CHECK (sexo IN ('HEMBRA', 'MACHO')),
    CONSTRAINT ck_animal_origen CHECK (origen IN ('NACIDO_EN_FINCA', 'EXTERNO')),
    CONSTRAINT ck_animal_estado CHECK (estado IN ('ACTIVO', 'VENDIDO', 'FALLECIDO', 'EGRESADO')),
    CONSTRAINT ck_animal_productora_hembra CHECK (es_productora = 0 OR sexo = 'HEMBRA'),   -- RN-09
    CONSTRAINT ck_animal_salida_por_estado CHECK ((estado = 'ACTIVO') = (fecha_salida IS NULL)), -- CA-04-C
    CONSTRAINT ck_animal_peso_nacimiento CHECK (peso_nacimiento_kg IS NULL OR peso_nacimiento_kg > 0),
    CONSTRAINT ck_animal_nacimiento_ingreso CHECK (fecha_nacimiento IS NULL OR fecha_nacimiento <= fecha_ingreso), -- RN-19
    CONSTRAINT ck_animal_salida_ingreso CHECK (fecha_salida IS NULL OR fecha_salida >= fecha_ingreso)             -- RN-19
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- D5 · pesaje
-- ---------------------------------------------------------------------------
CREATE TABLE pesaje (
    id_pesaje           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    organizacion_id     INT UNSIGNED NOT NULL,
    id_animal           INT UNSIGNED NOT NULL,
    fecha_pesaje        DATE         NOT NULL,
    peso_kg             DECIMAL(8,2) NOT NULL,
    creado_por          INT UNSIGNED NOT NULL,
    fecha_creacion      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    modificado_por      INT UNSIGNED NULL,
    fecha_actualizacion DATETIME     NULL,
    PRIMARY KEY (id_pesaje),
    UNIQUE KEY uq_pesaje_animal_fecha (id_animal, fecha_pesaje),                  -- RN-07
    KEY idx_pesaje_animal_organizacion (id_animal, organizacion_id),
    KEY idx_pesaje_organizacion (organizacion_id),
    KEY idx_pesaje_creado_por (creado_por),
    KEY idx_pesaje_modificado_por (modificado_por),
    CONSTRAINT fk_pesaje_organizacion FOREIGN KEY (organizacion_id)
        REFERENCES organizacion_ganadera (id_organizacion) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_pesaje_animal FOREIGN KEY (id_animal, organizacion_id)
        REFERENCES animal (id_animal, id_organizacion) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_pesaje_creado_por FOREIGN KEY (creado_por)
        REFERENCES usuario (id_usuario) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_pesaje_modificado_por FOREIGN KEY (modificado_por)
        REFERENCES usuario (id_usuario) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT ck_pesaje_peso CHECK (peso_kg > 0)                                 -- RN-14
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- D6 · vacunacion
-- ---------------------------------------------------------------------------
CREATE TABLE vacunacion (
    id_vacunacion       INT UNSIGNED NOT NULL AUTO_INCREMENT,
    organizacion_id     INT UNSIGNED NOT NULL,
    id_animal           INT UNSIGNED NOT NULL,
    id_vacuna           INT UNSIGNED NOT NULL,
    fecha_vacunacion    DATE         NOT NULL,
    dosis               VARCHAR(50)  NOT NULL,
    observaciones       TEXT         NULL,
    creado_por          INT UNSIGNED NOT NULL,
    fecha_creacion      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    modificado_por      INT UNSIGNED NULL,
    fecha_actualizacion DATETIME     NULL,
    PRIMARY KEY (id_vacunacion),
    KEY idx_vacunacion_animal_fecha (id_animal, fecha_vacunacion),
    KEY idx_vacunacion_animal_organizacion (id_animal, organizacion_id),
    KEY idx_vacunacion_vacuna_organizacion (id_vacuna, organizacion_id),
    KEY idx_vacunacion_organizacion (organizacion_id),
    KEY idx_vacunacion_creado_por (creado_por),
    KEY idx_vacunacion_modificado_por (modificado_por),
    CONSTRAINT fk_vacunacion_organizacion FOREIGN KEY (organizacion_id)
        REFERENCES organizacion_ganadera (id_organizacion) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_vacunacion_animal FOREIGN KEY (id_animal, organizacion_id)
        REFERENCES animal (id_animal, id_organizacion) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_vacunacion_vacuna FOREIGN KEY (id_vacuna, organizacion_id)
        REFERENCES vacuna (id_vacuna, id_organizacion) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_vacunacion_creado_por FOREIGN KEY (creado_por)
        REFERENCES usuario (id_usuario) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_vacunacion_modificado_por FOREIGN KEY (modificado_por)
        REFERENCES usuario (id_usuario) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- D7 · monta_reproductiva (el macho es opcional, RN-10)
-- ---------------------------------------------------------------------------
CREATE TABLE monta_reproductiva (
    id_monta            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    organizacion_id     INT UNSIGNED NOT NULL,
    id_hembra           INT UNSIGNED NOT NULL,
    id_macho            INT UNSIGNED NULL,
    fecha_monta         DATE         NOT NULL,
    observacion         TEXT         NULL,
    creado_por          INT UNSIGNED NOT NULL,
    fecha_creacion      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    modificado_por      INT UNSIGNED NULL,
    fecha_actualizacion DATETIME     NULL,
    PRIMARY KEY (id_monta),
    KEY idx_monta_hembra_fecha (id_hembra, fecha_monta),
    KEY idx_monta_hembra_organizacion (id_hembra, organizacion_id),
    KEY idx_monta_macho_organizacion (id_macho, organizacion_id),
    KEY idx_monta_organizacion (organizacion_id),
    KEY idx_monta_creado_por (creado_por),
    KEY idx_monta_modificado_por (modificado_por),
    CONSTRAINT fk_monta_organizacion FOREIGN KEY (organizacion_id)
        REFERENCES organizacion_ganadera (id_organizacion) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_monta_hembra FOREIGN KEY (id_hembra, organizacion_id)
        REFERENCES animal (id_animal, id_organizacion) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_monta_macho FOREIGN KEY (id_macho, organizacion_id)             -- CA-08-C
        REFERENCES animal (id_animal, id_organizacion) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_monta_creado_por FOREIGN KEY (creado_por)
        REFERENCES usuario (id_usuario) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_monta_modificado_por FOREIGN KEY (modificado_por)
        REFERENCES usuario (id_usuario) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- D8 · produccion_leche (varios ordeños por animal y día, RN-08)
-- id_finca_ordeno conserva la finca histórica donde ocurrió el ordeño.
-- ---------------------------------------------------------------------------
CREATE TABLE produccion_leche (
    id_produccion       INT UNSIGNED NOT NULL AUTO_INCREMENT,
    organizacion_id     INT UNSIGNED NOT NULL,
    id_animal           INT UNSIGNED NOT NULL,
    id_finca_ordeno     INT UNSIGNED NOT NULL,
    fecha_hora          DATETIME     NOT NULL,
    cantidad_litros     DECIMAL(8,2) NOT NULL,
    creado_por          INT UNSIGNED NOT NULL,
    fecha_creacion      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    modificado_por      INT UNSIGNED NULL,
    fecha_actualizacion DATETIME     NULL,
    PRIMARY KEY (id_produccion),
    KEY idx_produccion_animal_fecha (id_animal, fecha_hora),
    KEY idx_produccion_animal_organizacion (id_animal, organizacion_id),
    KEY idx_produccion_finca_organizacion (id_finca_ordeno, organizacion_id),
    KEY idx_produccion_organizacion (organizacion_id),
    KEY idx_produccion_creado_por (creado_por),
    KEY idx_produccion_modificado_por (modificado_por),
    CONSTRAINT fk_produccion_organizacion FOREIGN KEY (organizacion_id)
        REFERENCES organizacion_ganadera (id_organizacion) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_produccion_animal FOREIGN KEY (id_animal, organizacion_id)
        REFERENCES animal (id_animal, id_organizacion) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_produccion_finca FOREIGN KEY (id_finca_ordeno, organizacion_id)
        REFERENCES finca (id_finca, id_organizacion) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_produccion_creado_por FOREIGN KEY (creado_por)
        REFERENCES usuario (id_usuario) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_produccion_modificado_por FOREIGN KEY (modificado_por)
        REFERENCES usuario (id_usuario) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT ck_produccion_cantidad CHECK (cantidad_litros > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
