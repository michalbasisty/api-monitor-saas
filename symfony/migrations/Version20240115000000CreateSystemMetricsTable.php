<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20240115000000CreateSystemMetricsTable extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create system_metrics table for storing collected metrics';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('
            CREATE TABLE IF NOT EXISTS system_metrics (
                id BIGSERIAL PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                type VARCHAR(50) NOT NULL,
                value DECIMAL(20, 4) NOT NULL,
                timestamp TIMESTAMP NOT NULL,
                tags JSONB DEFAULT \'{}\'::jsonb,
                description TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ');

        // Create indexes for better query performance
        $this->addSql('
            CREATE INDEX IF NOT EXISTS idx_system_metrics_name_timestamp 
            ON system_metrics (name, timestamp DESC)
        ');

        $this->addSql('
            CREATE INDEX IF NOT EXISTS idx_system_metrics_timestamp 
            ON system_metrics (timestamp DESC)
        ');

        $this->addSql('
            CREATE INDEX IF NOT EXISTS idx_system_metrics_name 
            ON system_metrics (name)
        ');

        $this->addSql('
            CREATE INDEX IF NOT EXISTS idx_system_metrics_type 
            ON system_metrics (type)
        ');

        // Enable partitioning by month (optional, for very high volume)
        // This improves query performance and maintenance for large metric tables
        $this->addSql('
            CREATE INDEX IF NOT EXISTS idx_system_metrics_tags 
            ON system_metrics USING GIN (tags)
        ');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS system_metrics');
    }
}
