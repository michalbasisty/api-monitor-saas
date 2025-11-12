<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20240116000000_AddStripeColumnsToUser extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add Stripe columns to user table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "user" ADD COLUMN stripe_customer_id VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE "user" ADD COLUMN reset_token VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE "user" ADD COLUMN reset_token_expires_at TIMESTAMP DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "user" DROP COLUMN IF EXISTS stripe_customer_id');
        $this->addSql('ALTER TABLE "user" DROP COLUMN IF EXISTS reset_token');
        $this->addSql('ALTER TABLE "user" DROP COLUMN IF EXISTS reset_token_expires_at');
    }
}
