<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20240100999999_CreateUserTable extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create users table for user management';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE IF NOT EXISTS "user" (
            id UUID NOT NULL PRIMARY KEY,
            email VARCHAR(180) NOT NULL UNIQUE,
            "password" VARCHAR(255) NOT NULL,
            roles JSONB NOT NULL DEFAULT \'[]\',
            company_id UUID DEFAULT NULL,
            subscription_tier VARCHAR(20) NOT NULL DEFAULT \'free\',
            is_verified BOOLEAN NOT NULL DEFAULT FALSE,
            verification_token VARCHAR(255) DEFAULT NULL,
            verification_token_expires_at TIMESTAMP DEFAULT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT NULL,
            last_login_at TIMESTAMP DEFAULT NULL
        )');

        $this->addSql('CREATE UNIQUE INDEX idx_user_email ON "user"(email)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS "user"');
    }
}
