<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20240115020000_AddCheckoutMetricColumns extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add conversion_rate, abandonment_rate, and session_abandoned columns to checkout_metrics table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE ecommerce_checkout_metrics ADD COLUMN conversion_rate DECIMAL(5, 2) DEFAULT NULL');
        $this->addSql('ALTER TABLE ecommerce_checkout_metrics ADD COLUMN abandonment_rate DECIMAL(5, 2) DEFAULT NULL');
        $this->addSql('ALTER TABLE ecommerce_checkout_metrics ADD COLUMN session_abandoned BOOLEAN NOT NULL DEFAULT FALSE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE ecommerce_checkout_metrics DROP COLUMN session_abandoned');
        $this->addSql('ALTER TABLE ecommerce_checkout_metrics DROP COLUMN abandonment_rate');
        $this->addSql('ALTER TABLE ecommerce_checkout_metrics DROP COLUMN conversion_rate');
    }
}
