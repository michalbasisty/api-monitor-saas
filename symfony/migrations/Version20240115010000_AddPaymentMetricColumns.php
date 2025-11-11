<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20240115010000_AddPaymentMetricColumns extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add failure_reason and failure_message columns to payment_metrics table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE ecommerce_payment_metrics ADD COLUMN failure_reason VARCHAR(100) DEFAULT NULL');
        $this->addSql('ALTER TABLE ecommerce_payment_metrics ADD COLUMN failure_message TEXT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE ecommerce_payment_metrics DROP COLUMN failure_message');
        $this->addSql('ALTER TABLE ecommerce_payment_metrics DROP COLUMN failure_reason');
    }
}
