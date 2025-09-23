<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250923141512 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE Food_Category (FoodId INT NOT NULL, CategoryId INT NOT NULL, INDEX IDX_64091720345E7DEA (FoodId), INDEX IDX_64091720D36A08A1 (CategoryId), PRIMARY KEY(FoodId, CategoryId)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE Menu_Category (MenuId INT NOT NULL, CategoryId INT NOT NULL, INDEX IDX_601575F4B1713BAA (MenuId), INDEX IDX_601575F4D36A08A1 (CategoryId), PRIMARY KEY(MenuId, CategoryId)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE Food_Category ADD CONSTRAINT FK_64091720345E7DEA FOREIGN KEY (FoodId) REFERENCES food (id)');
        $this->addSql('ALTER TABLE Food_Category ADD CONSTRAINT FK_64091720D36A08A1 FOREIGN KEY (CategoryId) REFERENCES category (id)');
        $this->addSql('ALTER TABLE Menu_Category ADD CONSTRAINT FK_601575F4B1713BAA FOREIGN KEY (MenuId) REFERENCES menu (id)');
        $this->addSql('ALTER TABLE Menu_Category ADD CONSTRAINT FK_601575F4D36A08A1 FOREIGN KEY (CategoryId) REFERENCES category (id)');
        $this->addSql('ALTER TABLE category_food DROP FOREIGN KEY FK_5FA353B0BA8E87C4');
        $this->addSql('ALTER TABLE category_food DROP FOREIGN KEY FK_5FA353B012469DE2');
        $this->addSql('ALTER TABLE category_menu DROP FOREIGN KEY FK_F69E40D412469DE2');
        $this->addSql('ALTER TABLE category_menu DROP FOREIGN KEY FK_F69E40D4CCD7E912');
        $this->addSql('DROP TABLE category_food');
        $this->addSql('DROP TABLE category_menu');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE category_food (category_id INT NOT NULL, food_id INT NOT NULL, INDEX IDX_5FA353B0BA8E87C4 (food_id), INDEX IDX_5FA353B012469DE2 (category_id), PRIMARY KEY(category_id, food_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE category_menu (category_id INT NOT NULL, menu_id INT NOT NULL, INDEX IDX_F69E40D412469DE2 (category_id), INDEX IDX_F69E40D4CCD7E912 (menu_id), PRIMARY KEY(category_id, menu_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE category_food ADD CONSTRAINT FK_5FA353B0BA8E87C4 FOREIGN KEY (food_id) REFERENCES food (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE category_food ADD CONSTRAINT FK_5FA353B012469DE2 FOREIGN KEY (category_id) REFERENCES category (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE category_menu ADD CONSTRAINT FK_F69E40D412469DE2 FOREIGN KEY (category_id) REFERENCES category (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE category_menu ADD CONSTRAINT FK_F69E40D4CCD7E912 FOREIGN KEY (menu_id) REFERENCES menu (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE Food_Category DROP FOREIGN KEY FK_64091720345E7DEA');
        $this->addSql('ALTER TABLE Food_Category DROP FOREIGN KEY FK_64091720D36A08A1');
        $this->addSql('ALTER TABLE Menu_Category DROP FOREIGN KEY FK_601575F4B1713BAA');
        $this->addSql('ALTER TABLE Menu_Category DROP FOREIGN KEY FK_601575F4D36A08A1');
        $this->addSql('DROP TABLE Food_Category');
        $this->addSql('DROP TABLE Menu_Category');
    }
}
