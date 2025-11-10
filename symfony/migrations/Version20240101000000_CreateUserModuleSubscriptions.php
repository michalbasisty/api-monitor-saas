<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20240101000000_CreateUserModuleSubscriptions extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create user module subscriptions table for module enablement tracking';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE user_module_subscriptions (
            id INT AUTO_INCREMENT NOT NULL,
            user_id CHAR(36) NOT NULL COMMENT "(DC2Type:uuid)",
            module_name VARCHAR(50) NOT NULL,
            tier VARCHAR(20) NOT NULL,
            enabled TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT NULL,
            PRIMARY KEY(id),
            UNIQUE KEY unique_user_module (user_id, module_name),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE user_module_subscriptions');
    }
}
