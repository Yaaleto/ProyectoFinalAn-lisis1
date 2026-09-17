<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260917050000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create the complete BOVITRACK MVP schema for catalogs, animals and operational history.';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(!$this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform, 'This migration is only safe on MySQL.');

        $this->addSql('CREATE TABLE raza (id INT AUTO_INCREMENT NOT NULL, organizacion_id INT NOT NULL, nombre VARCHAR(100) NOT NULL, activa TINYINT DEFAULT 1 NOT NULL, creado_por INT DEFAULT NULL, fecha_creacion DATETIME NOT NULL, actualizado_por INT DEFAULT NULL, fecha_actualizacion DATETIME NOT NULL, UNIQUE INDEX uniq_raza_organizacion_nombre (organizacion_id, nombre), INDEX IDX_4602D6AE90B1019E (organizacion_id), INDEX IDX_4602D6AEA13368D4 (creado_por), INDEX IDX_4602D6AEB3E83A14 (actualizado_por), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE vacuna (id INT AUTO_INCREMENT NOT NULL, organizacion_id INT NOT NULL, nombre VARCHAR(120) NOT NULL, descripcion LONGTEXT DEFAULT NULL, activa TINYINT DEFAULT 1 NOT NULL, creado_por INT DEFAULT NULL, fecha_creacion DATETIME NOT NULL, actualizado_por INT DEFAULT NULL, fecha_actualizacion DATETIME NOT NULL, UNIQUE INDEX uniq_vacuna_organizacion_nombre (organizacion_id, nombre), INDEX IDX_7289F43390B1019E (organizacion_id), INDEX IDX_7289F433A13368D4 (creado_por), INDEX IDX_7289F433B3E83A14 (actualizado_por), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE animal (id INT AUTO_INCREMENT NOT NULL, organizacion_id INT NOT NULL, finca_id INT NOT NULL, raza_id INT DEFAULT NULL, padre_id INT DEFAULT NULL, madre_id INT DEFAULT NULL, codigo_interno VARCHAR(36) NOT NULL, arete VARCHAR(50) DEFAULT NULL, nombre VARCHAR(120) DEFAULT NULL, sexo VARCHAR(10) NOT NULL, fecha_nacimiento DATE DEFAULT NULL, peso_nacimiento_kg NUMERIC(8, 2) DEFAULT NULL, origen VARCHAR(20) NOT NULL, procedencia VARCHAR(255) DEFAULT NULL, productora_leche TINYINT DEFAULT 0 NOT NULL, estado VARCHAR(15) DEFAULT \'ACTIVO\' NOT NULL, fecha_salida DATE DEFAULT NULL, creado_por INT DEFAULT NULL, fecha_creacion DATETIME NOT NULL, actualizado_por INT DEFAULT NULL, fecha_actualizacion DATETIME NOT NULL, UNIQUE INDEX UNIQ_6AAB231F69D2337E (codigo_interno), UNIQUE INDEX uniq_animal_organizacion_arete (organizacion_id, arete), INDEX idx_animal_finca_estado (finca_id, estado), INDEX IDX_6AAB231F90B1019E (organizacion_id), INDEX IDX_6AAB231F9B7E9090 (finca_id), INDEX IDX_6AAB231F8CCBB6A9 (raza_id), INDEX IDX_6AAB231F613CEC58 (padre_id), INDEX IDX_6AAB231F8682C8A2 (madre_id), INDEX IDX_6AAB231FA13368D4 (creado_por), INDEX IDX_6AAB231FB3E83A14 (actualizado_por), PRIMARY KEY (id), CONSTRAINT CHK_ANIMAL_SEXO CHECK (sexo IN (\'HEMBRA\', \'MACHO\')), CONSTRAINT CHK_ANIMAL_ORIGEN CHECK (origen IN (\'NACIDO_EN_FINCA\', \'EXTERNO\')), CONSTRAINT CHK_ANIMAL_ESTADO CHECK (estado IN (\'ACTIVO\', \'VENDIDO\', \'FALLECIDO\', \'EGRESADO\')), CONSTRAINT CHK_ANIMAL_PESO_NACIMIENTO CHECK (peso_nacimiento_kg IS NULL OR peso_nacimiento_kg > 0), CONSTRAINT CHK_ANIMAL_PRODUCTORA CHECK (productora_leche = 0 OR sexo = \'HEMBRA\'), CONSTRAINT CHK_ANIMAL_SALIDA CHECK (fecha_salida IS NULL OR fecha_nacimiento IS NULL OR fecha_salida >= fecha_nacimiento)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE pesaje (id INT AUTO_INCREMENT NOT NULL, animal_id INT NOT NULL, fecha DATE NOT NULL, peso_kg NUMERIC(8, 2) NOT NULL, creado_por INT DEFAULT NULL, fecha_creacion DATETIME NOT NULL, actualizado_por INT DEFAULT NULL, fecha_actualizacion DATETIME NOT NULL, UNIQUE INDEX uniq_pesaje_animal_fecha (animal_id, fecha), INDEX IDX_7967ECC0A13368D4 (creado_por), INDEX IDX_7967ECC0B3E83A14 (actualizado_por), PRIMARY KEY (id), CONSTRAINT CHK_PESAJE_POSITIVO CHECK (peso_kg > 0)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE vacunacion (id INT AUTO_INCREMENT NOT NULL, animal_id INT NOT NULL, vacuna_id INT NOT NULL, fecha DATE NOT NULL, dosis VARCHAR(50) NOT NULL, observaciones LONGTEXT DEFAULT NULL, creado_por INT DEFAULT NULL, fecha_creacion DATETIME NOT NULL, actualizado_por INT DEFAULT NULL, fecha_actualizacion DATETIME NOT NULL, INDEX idx_vacunacion_animal_fecha (animal_id, fecha), INDEX IDX_3A7F3388E962C16 (animal_id), INDEX IDX_3A7F338A4D881B6 (vacuna_id), INDEX IDX_3A7F338A13368D4 (creado_por), INDEX IDX_3A7F338B3E83A14 (actualizado_por), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE monta_reproductiva (id INT AUTO_INCREMENT NOT NULL, hembra_id INT NOT NULL, macho_id INT DEFAULT NULL, fecha DATE NOT NULL, observacion LONGTEXT DEFAULT NULL, creado_por INT DEFAULT NULL, fecha_creacion DATETIME NOT NULL, actualizado_por INT DEFAULT NULL, fecha_actualizacion DATETIME NOT NULL, INDEX idx_monta_hembra_fecha (hembra_id, fecha), INDEX IDX_BEFDACDBAF6C2552 (hembra_id), INDEX IDX_BEFDACDBDE6A975D (macho_id), INDEX IDX_BEFDACDBA13368D4 (creado_por), INDEX IDX_BEFDACDBB3E83A14 (actualizado_por), PRIMARY KEY (id), CONSTRAINT CHK_MONTA_ANIMALES_DISTINTOS CHECK (macho_id IS NULL OR macho_id <> hembra_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE produccion_leche (id INT AUTO_INCREMENT NOT NULL, animal_id INT NOT NULL, fecha_hora DATETIME NOT NULL, cantidad_litros NUMERIC(8, 2) NOT NULL, creado_por INT DEFAULT NULL, fecha_creacion DATETIME NOT NULL, actualizado_por INT DEFAULT NULL, fecha_actualizacion DATETIME NOT NULL, INDEX idx_leche_animal_fecha_hora (animal_id, fecha_hora), INDEX IDX_930635C68E962C16 (animal_id), INDEX IDX_930635C6A13368D4 (creado_por), INDEX IDX_930635C6B3E83A14 (actualizado_por), PRIMARY KEY (id), CONSTRAINT CHK_LECHE_POSITIVA CHECK (cantidad_litros > 0)) DEFAULT CHARACTER SET utf8mb4');

        $this->addSql('ALTER TABLE raza ADD CONSTRAINT FK_RAZA_ORGANIZACION FOREIGN KEY (organizacion_id) REFERENCES organizacion_ganadera (id) ON DELETE RESTRICT, ADD CONSTRAINT FK_RAZA_CREADO_POR FOREIGN KEY (creado_por) REFERENCES usuario (id) ON DELETE SET NULL, ADD CONSTRAINT FK_RAZA_ACTUALIZADO_POR FOREIGN KEY (actualizado_por) REFERENCES usuario (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE vacuna ADD CONSTRAINT FK_VACUNA_ORGANIZACION FOREIGN KEY (organizacion_id) REFERENCES organizacion_ganadera (id) ON DELETE RESTRICT, ADD CONSTRAINT FK_VACUNA_CREADO_POR FOREIGN KEY (creado_por) REFERENCES usuario (id) ON DELETE SET NULL, ADD CONSTRAINT FK_VACUNA_ACTUALIZADO_POR FOREIGN KEY (actualizado_por) REFERENCES usuario (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE animal ADD CONSTRAINT FK_ANIMAL_ORGANIZACION FOREIGN KEY (organizacion_id) REFERENCES organizacion_ganadera (id) ON DELETE RESTRICT, ADD CONSTRAINT FK_ANIMAL_FINCA FOREIGN KEY (finca_id) REFERENCES finca (id) ON DELETE RESTRICT, ADD CONSTRAINT FK_ANIMAL_RAZA FOREIGN KEY (raza_id) REFERENCES raza (id) ON DELETE RESTRICT, ADD CONSTRAINT FK_ANIMAL_PADRE FOREIGN KEY (padre_id) REFERENCES animal (id) ON DELETE RESTRICT, ADD CONSTRAINT FK_ANIMAL_MADRE FOREIGN KEY (madre_id) REFERENCES animal (id) ON DELETE RESTRICT, ADD CONSTRAINT FK_ANIMAL_CREADO_POR FOREIGN KEY (creado_por) REFERENCES usuario (id) ON DELETE SET NULL, ADD CONSTRAINT FK_ANIMAL_ACTUALIZADO_POR FOREIGN KEY (actualizado_por) REFERENCES usuario (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE pesaje ADD CONSTRAINT FK_PESAJE_ANIMAL FOREIGN KEY (animal_id) REFERENCES animal (id) ON DELETE RESTRICT, ADD CONSTRAINT FK_PESAJE_CREADO_POR FOREIGN KEY (creado_por) REFERENCES usuario (id) ON DELETE SET NULL, ADD CONSTRAINT FK_PESAJE_ACTUALIZADO_POR FOREIGN KEY (actualizado_por) REFERENCES usuario (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE vacunacion ADD CONSTRAINT FK_VACUNACION_ANIMAL FOREIGN KEY (animal_id) REFERENCES animal (id) ON DELETE RESTRICT, ADD CONSTRAINT FK_VACUNACION_VACUNA FOREIGN KEY (vacuna_id) REFERENCES vacuna (id) ON DELETE RESTRICT, ADD CONSTRAINT FK_VACUNACION_CREADO_POR FOREIGN KEY (creado_por) REFERENCES usuario (id) ON DELETE SET NULL, ADD CONSTRAINT FK_VACUNACION_ACTUALIZADO_POR FOREIGN KEY (actualizado_por) REFERENCES usuario (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE monta_reproductiva ADD CONSTRAINT FK_MONTA_HEMBRA FOREIGN KEY (hembra_id) REFERENCES animal (id) ON DELETE RESTRICT, ADD CONSTRAINT FK_MONTA_MACHO FOREIGN KEY (macho_id) REFERENCES animal (id) ON DELETE RESTRICT, ADD CONSTRAINT FK_MONTA_CREADO_POR FOREIGN KEY (creado_por) REFERENCES usuario (id) ON DELETE SET NULL, ADD CONSTRAINT FK_MONTA_ACTUALIZADO_POR FOREIGN KEY (actualizado_por) REFERENCES usuario (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE produccion_leche ADD CONSTRAINT FK_LECHE_ANIMAL FOREIGN KEY (animal_id) REFERENCES animal (id) ON DELETE RESTRICT, ADD CONSTRAINT FK_LECHE_CREADO_POR FOREIGN KEY (creado_por) REFERENCES usuario (id) ON DELETE SET NULL, ADD CONSTRAINT FK_LECHE_ACTUALIZADO_POR FOREIGN KEY (actualizado_por) REFERENCES usuario (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(!$this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform, 'This migration is only safe on MySQL.');
        $this->addSql('DROP TABLE produccion_leche');
        $this->addSql('DROP TABLE monta_reproductiva');
        $this->addSql('DROP TABLE vacunacion');
        $this->addSql('DROP TABLE pesaje');
        $this->addSql('DROP TABLE animal');
        $this->addSql('DROP TABLE vacuna');
        $this->addSql('DROP TABLE raza');
    }
}
