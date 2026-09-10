<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260910063849 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE finca (id INT AUTO_INCREMENT NOT NULL, nombre VARCHAR(150) NOT NULL, codigo VARCHAR(50) NOT NULL, fecha_creacion DATETIME NOT NULL, fecha_actualizacion DATETIME NOT NULL, organizacion_id INT NOT NULL, creado_por INT DEFAULT NULL, actualizado_por INT DEFAULT NULL, INDEX IDX_A05242F990B1019E (organizacion_id), INDEX IDX_A05242F9A13368D4 (creado_por), INDEX IDX_A05242F9B3E83A14 (actualizado_por), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE organizacion_ganadera (id INT AUTO_INCREMENT NOT NULL, nombre VARCHAR(150) NOT NULL, codigo VARCHAR(50) NOT NULL, fecha_creacion DATETIME NOT NULL, fecha_actualizacion DATETIME NOT NULL, creado_por INT DEFAULT NULL, actualizado_por INT DEFAULT NULL, UNIQUE INDEX UNIQ_FFE9404820332D99 (codigo), INDEX IDX_FFE94048A13368D4 (creado_por), INDEX IDX_FFE94048B3E83A14 (actualizado_por), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE usuario (id INT AUTO_INCREMENT NOT NULL, nombre VARCHAR(150) NOT NULL, email VARCHAR(180) NOT NULL, contrasena_hash VARCHAR(255) NOT NULL, rol VARCHAR(20) NOT NULL, fecha_creacion DATETIME NOT NULL, fecha_actualizacion DATETIME NOT NULL, organizacion_id INT NOT NULL, finca_activa_id INT DEFAULT NULL, creado_por INT DEFAULT NULL, actualizado_por INT DEFAULT NULL, UNIQUE INDEX UNIQ_2265B05DE7927C74 (email), INDEX IDX_2265B05D90B1019E (organizacion_id), INDEX IDX_2265B05DA9A81F8A (finca_activa_id), INDEX IDX_2265B05DA13368D4 (creado_por), INDEX IDX_2265B05DB3E83A14 (actualizado_por), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE finca ADD CONSTRAINT FK_A05242F990B1019E FOREIGN KEY (organizacion_id) REFERENCES organizacion_ganadera (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE finca ADD CONSTRAINT FK_A05242F9A13368D4 FOREIGN KEY (creado_por) REFERENCES usuario (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE finca ADD CONSTRAINT FK_A05242F9B3E83A14 FOREIGN KEY (actualizado_por) REFERENCES usuario (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE organizacion_ganadera ADD CONSTRAINT FK_FFE94048A13368D4 FOREIGN KEY (creado_por) REFERENCES usuario (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE organizacion_ganadera ADD CONSTRAINT FK_FFE94048B3E83A14 FOREIGN KEY (actualizado_por) REFERENCES usuario (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE usuario ADD CONSTRAINT FK_2265B05D90B1019E FOREIGN KEY (organizacion_id) REFERENCES organizacion_ganadera (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE usuario ADD CONSTRAINT FK_2265B05DA9A81F8A FOREIGN KEY (finca_activa_id) REFERENCES finca (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE usuario ADD CONSTRAINT FK_2265B05DA13368D4 FOREIGN KEY (creado_por) REFERENCES usuario (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE usuario ADD CONSTRAINT FK_2265B05DB3E83A14 FOREIGN KEY (actualizado_por) REFERENCES usuario (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE finca DROP FOREIGN KEY FK_A05242F990B1019E');
        $this->addSql('ALTER TABLE finca DROP FOREIGN KEY FK_A05242F9A13368D4');
        $this->addSql('ALTER TABLE finca DROP FOREIGN KEY FK_A05242F9B3E83A14');
        $this->addSql('ALTER TABLE organizacion_ganadera DROP FOREIGN KEY FK_FFE94048A13368D4');
        $this->addSql('ALTER TABLE organizacion_ganadera DROP FOREIGN KEY FK_FFE94048B3E83A14');
        $this->addSql('ALTER TABLE usuario DROP FOREIGN KEY FK_2265B05D90B1019E');
        $this->addSql('ALTER TABLE usuario DROP FOREIGN KEY FK_2265B05DA9A81F8A');
        $this->addSql('ALTER TABLE usuario DROP FOREIGN KEY FK_2265B05DA13368D4');
        $this->addSql('ALTER TABLE usuario DROP FOREIGN KEY FK_2265B05DB3E83A14');
        $this->addSql('DROP TABLE finca');
        $this->addSql('DROP TABLE organizacion_ganadera');
        $this->addSql('DROP TABLE usuario');
    }
}
