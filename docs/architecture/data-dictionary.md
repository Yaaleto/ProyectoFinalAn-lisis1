# Diccionario de datos del MVP

Este diccionario complementa el diagrama entidad-relación de
[data-model.md](data-model.md). Describe el esquema que Doctrine mapea y las
migraciones aplican en MySQL 8.

## Convenciones comunes

Todas las tablas tienen una llave primaria `id INT` autoincremental. Las tablas
de negocio también comparten auditoría: `creado_por INT NULL` y
`actualizado_por INT NULL` referencian `usuario` con `ON DELETE SET NULL`; las
fechas `fecha_creacion DATETIME` y `fecha_actualizacion DATETIME` son
obligatorias.

| Tabla | Campos propios | Reglas principales |
|---|---|---|
| `organizacion_ganadera` | `nombre VARCHAR(150) NOT NULL`, `codigo VARCHAR(50) NOT NULL` | `codigo` único global. |
| `usuario` | `organizacion_id INT NOT NULL`, `finca_activa_id INT NULL`, `nombre VARCHAR(150) NOT NULL`, `email VARCHAR(180) NOT NULL`, `contrasena_hash VARCHAR(255) NOT NULL`, `rol VARCHAR(20) NOT NULL`, `activo TINYINT NOT NULL` | `email` único; organización `RESTRICT`; finca activa `SET NULL`. |
| `finca` | `organizacion_id INT NOT NULL`, `nombre VARCHAR(150) NOT NULL`, `codigo VARCHAR(50) NOT NULL` | `(organizacion_id, codigo)` único; organización `RESTRICT`. |
| `raza` | `organizacion_id INT NOT NULL`, `nombre VARCHAR(100) NOT NULL`, `activa TINYINT NOT NULL` | `(organizacion_id, nombre)` único; organización `RESTRICT`. |
| `vacuna` | `organizacion_id INT NOT NULL`, `nombre VARCHAR(120) NOT NULL`, `descripcion LONGTEXT NULL`, `activa TINYINT NOT NULL` | `(organizacion_id, nombre)` único; organización `RESTRICT`. |
| `animal` | `organizacion_id INT NOT NULL`, `finca_id INT NOT NULL`, `raza_id INT NULL`, `padre_id INT NULL`, `madre_id INT NULL`, `codigo_interno VARCHAR(36) NOT NULL`, `arete VARCHAR(50) NULL`, `nombre VARCHAR(120) NULL`, `sexo VARCHAR(10) NOT NULL`, `fecha_nacimiento DATE NULL`, `peso_nacimiento_kg DECIMAL(8,2) NULL`, `origen VARCHAR(20) NOT NULL`, `procedencia VARCHAR(255) NULL`, `productora_leche TINYINT NOT NULL`, `estado VARCHAR(15) NOT NULL`, `fecha_salida DATE NULL` | `codigo_interno` único; `(organizacion_id, arete)` único cuando hay arete; todas las relaciones operativas son `RESTRICT`; validaciones de sexo, origen, estado, peso y salida. |
| `pesaje` | `animal_id INT NOT NULL`, `fecha DATE NOT NULL`, `peso_kg DECIMAL(8,2) NOT NULL` | `(animal_id, fecha)` único; `peso_kg > 0`; animal `RESTRICT`. |
| `vacunacion` | `animal_id INT NOT NULL`, `vacuna_id INT NOT NULL`, `fecha DATE NOT NULL`, `dosis VARCHAR(50) NOT NULL`, `observaciones LONGTEXT NULL` | índices por animal/fecha; animal y vacuna `RESTRICT`. |
| `monta_reproductiva` | `hembra_id INT NOT NULL`, `macho_id INT NULL`, `fecha DATE NOT NULL`, `observacion LONGTEXT NULL` | índice por hembra/fecha; hembra y macho distintos; relaciones `RESTRICT`. |
| `produccion_leche` | `animal_id INT NOT NULL`, `fecha_hora DATETIME NOT NULL`, `cantidad_litros DECIMAL(8,2) NOT NULL` | índice por animal/fecha-hora; `cantidad_litros > 0`; animal `RESTRICT`. |

Las reglas que comparan organizaciones, sexo de progenitores, catálogo activo y
fechas operativas se aplican en las entidades de dominio y se prueban junto con
las restricciones físicas de MySQL.
