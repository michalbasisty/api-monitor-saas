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
            id SERIAL PRIMARY KEY,
            user_id UUID NOT NULL,
            module_name VARCHAR(50) NOT NULL,
            tier VARCHAR(20) NOT NULL,
            enabled BOOLEAN NOT NULL DEFAULT FALSE,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT NULL,
            UNIQUE(user_id, module_name),
            CONSTRAINT fk_user_module_subscriptions_user_id FOREIGN KEY (user_id) REFERENCES "user"(id) ON DELETE CASCADE
        )');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE user_module_subscriptions');
    }
}
