<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20240115030000_AddCheckoutStepColumns extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add conversion_rate and abandonment_rate columns to checkout_steps table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE ecommerce_checkout_steps ADD COLUMN conversion_rate DECIMAL(5, 2) DEFAULT 100.00');
        $this->addSql('ALTER TABLE ecommerce_checkout_steps ADD COLUMN abandonment_rate DECIMAL(5, 2) DEFAULT 0.00');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE ecommerce_checkout_steps DROP COLUMN abandonment_rate');
        $this->addSql('ALTER TABLE ecommerce_checkout_steps DROP COLUMN conversion_rate');
    }
}
