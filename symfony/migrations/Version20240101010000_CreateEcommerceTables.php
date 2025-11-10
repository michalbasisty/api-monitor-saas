<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20240101010000_CreateEcommerceTables extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create e-commerce monitoring tables: stores, checkout, payments, sales metrics';
    }

    public function up(Schema $schema): void
    {
        // Stores table
        $this->addSql('CREATE TABLE ecommerce_stores (
            id CHAR(36) NOT NULL COMMENT "(DC2Type:uuid)",
            user_id CHAR(36) NOT NULL COMMENT "(DC2Type:uuid)",
            store_name VARCHAR(255) NOT NULL,
            store_url VARCHAR(255) NOT NULL UNIQUE,
            platform VARCHAR(50) NOT NULL,
            currency VARCHAR(3) NOT NULL DEFAULT "USD",
            timezone VARCHAR(50) DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT NULL,
            PRIMARY KEY(id),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_user_id (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

        // Checkout steps table
        $this->addSql('CREATE TABLE ecommerce_checkout_steps (
            id CHAR(36) NOT NULL COMMENT "(DC2Type:uuid)",
            store_id CHAR(36) NOT NULL COMMENT "(DC2Type:uuid)",
            step_number INT NOT NULL,
            step_name VARCHAR(100) NOT NULL,
            endpoint_url VARCHAR(255) NOT NULL,
            expected_load_time_ms INT NOT NULL DEFAULT 1000,
            alert_threshold_ms INT NOT NULL DEFAULT 2000,
            enabled TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT NULL,
            PRIMARY KEY(id),
            FOREIGN KEY (store_id) REFERENCES ecommerce_stores(id) ON DELETE CASCADE,
            INDEX idx_store_id (store_id),
            INDEX idx_store_number (store_id, step_number)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

        // Checkout metrics table
        $this->addSql('CREATE TABLE ecommerce_checkout_metrics (
            id CHAR(36) NOT NULL COMMENT "(DC2Type:uuid)",
            store_id CHAR(36) NOT NULL COMMENT "(DC2Type:uuid)",
            step_id CHAR(36) NOT NULL COMMENT "(DC2Type:uuid)",
            timestamp DATETIME NOT NULL,
            load_time_ms INT NOT NULL,
            api_response_time_ms INT DEFAULT NULL,
            error_occurred TINYINT(1) NOT NULL DEFAULT 0,
            error_message TEXT DEFAULT NULL,
            http_status_code INT DEFAULT NULL,
            session_id VARCHAR(255) DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY(id),
            FOREIGN KEY (store_id) REFERENCES ecommerce_stores(id) ON DELETE CASCADE,
            FOREIGN KEY (step_id) REFERENCES ecommerce_checkout_steps(id) ON DELETE CASCADE,
            INDEX idx_store_timestamp (store_id, timestamp),
            INDEX idx_step_timestamp (step_id, timestamp),
            INDEX idx_session_id (session_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

        // Payment gateways table
        $this->addSql('CREATE TABLE ecommerce_payment_gateways (
            id CHAR(36) NOT NULL COMMENT "(DC2Type:uuid)",
            store_id CHAR(36) NOT NULL COMMENT "(DC2Type:uuid)",
            gateway_name VARCHAR(100) NOT NULL,
            api_key_encrypted VARCHAR(500) NOT NULL,
            webhook_url VARCHAR(255) DEFAULT NULL,
            webhook_secret_encrypted VARCHAR(500) DEFAULT NULL,
            is_primary TINYINT(1) NOT NULL DEFAULT 0,
            enabled TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT NULL,
            PRIMARY KEY(id),
            FOREIGN KEY (store_id) REFERENCES ecommerce_stores(id) ON DELETE CASCADE,
            INDEX idx_store_id (store_id),
            UNIQUE KEY unique_store_gateway (store_id, gateway_name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

        // Payment metrics table
        $this->addSql('CREATE TABLE ecommerce_payment_metrics (
            id CHAR(36) NOT NULL COMMENT "(DC2Type:uuid)",
            store_id CHAR(36) NOT NULL COMMENT "(DC2Type:uuid)",
            gateway_id CHAR(36) NOT NULL COMMENT "(DC2Type:uuid)",
            transaction_id VARCHAR(255) NOT NULL,
            amount DECIMAL(10, 2) NOT NULL,
            currency VARCHAR(3) NOT NULL,
            status VARCHAR(50) NOT NULL,
            authorization_time_ms INT DEFAULT NULL,
            settlement_time_hours INT DEFAULT NULL,
            webhook_received TINYINT(1) NOT NULL DEFAULT 0,
            webhook_timestamp DATETIME DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY(id),
            FOREIGN KEY (store_id) REFERENCES ecommerce_stores(id) ON DELETE CASCADE,
            FOREIGN KEY (gateway_id) REFERENCES ecommerce_payment_gateways(id) ON DELETE CASCADE,
            INDEX idx_store_timestamp (store_id, created_at),
            INDEX idx_transaction_id (transaction_id),
            INDEX idx_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

        // Sales metrics table
        $this->addSql('CREATE TABLE ecommerce_sales_metrics (
            id CHAR(36) NOT NULL COMMENT "(DC2Type:uuid)",
            store_id CHAR(36) NOT NULL COMMENT "(DC2Type:uuid)",
            timestamp DATETIME NOT NULL,
            revenue_per_minute DECIMAL(10, 2) DEFAULT NULL,
            orders_per_minute INT DEFAULT NULL,
            checkout_success_rate DECIMAL(5, 2) DEFAULT NULL,
            avg_order_value DECIMAL(10, 2) DEFAULT NULL,
            estimated_lost_revenue DECIMAL(10, 2) DEFAULT NULL,
            status VARCHAR(50) NOT NULL DEFAULT "normal",
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY(id),
            FOREIGN KEY (store_id) REFERENCES ecommerce_stores(id) ON DELETE CASCADE,
            INDEX idx_store_timestamp (store_id, timestamp),
            INDEX idx_status (store_id, status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

        // Checkout abandonment table
        $this->addSql('CREATE TABLE ecommerce_abandonment (
            id CHAR(36) NOT NULL COMMENT "(DC2Type:uuid)",
            store_id CHAR(36) NOT NULL COMMENT "(DC2Type:uuid)",
            session_id VARCHAR(255) NOT NULL,
            started_at DATETIME NOT NULL,
            abandoned_at_step_id CHAR(36) COMMENT "(DC2Type:uuid)",
            last_seen DATETIME NOT NULL,
            reason VARCHAR(255) DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY(id),
            FOREIGN KEY (store_id) REFERENCES ecommerce_stores(id) ON DELETE CASCADE,
            FOREIGN KEY (abandoned_at_step_id) REFERENCES ecommerce_checkout_steps(id) ON DELETE SET NULL,
            INDEX idx_store_abandoned (store_id, created_at),
            INDEX idx_session_id (session_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

        // Traffic spikes detection
        $this->addSql('CREATE TABLE ecommerce_traffic_spikes (
            id CHAR(36) NOT NULL COMMENT "(DC2Type:uuid)",
            store_id CHAR(36) NOT NULL COMMENT "(DC2Type:uuid)",
            spike_detected_at DATETIME NOT NULL,
            baseline_rpm INT NOT NULL,
            peak_rpm INT NOT NULL,
            duration_minutes INT NOT NULL,
            event_type VARCHAR(100) DEFAULT NULL,
            performance_impact VARCHAR(50) NOT NULL DEFAULT "normal",
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY(id),
            FOREIGN KEY (store_id) REFERENCES ecommerce_stores(id) ON DELETE CASCADE,
            INDEX idx_store_detected (store_id, spike_detected_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

        // E-commerce specific alerts
        $this->addSql('CREATE TABLE ecommerce_alerts (
            id CHAR(36) NOT NULL COMMENT "(DC2Type:uuid)",
            store_id CHAR(36) NOT NULL COMMENT "(DC2Type:uuid)",
            alert_type VARCHAR(100) NOT NULL,
            severity VARCHAR(20) NOT NULL,
            triggered_at DATETIME NOT NULL,
            metric_value DECIMAL(10, 2) DEFAULT NULL,
            threshold_value DECIMAL(10, 2) DEFAULT NULL,
            description TEXT DEFAULT NULL,
            resolved_at DATETIME DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY(id),
            FOREIGN KEY (store_id) REFERENCES ecommerce_stores(id) ON DELETE CASCADE,
            INDEX idx_store_created (store_id, created_at),
            INDEX idx_severity (store_id, severity),
            INDEX idx_resolved (resolved_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE ecommerce_alerts');
        $this->addSql('DROP TABLE ecommerce_traffic_spikes');
        $this->addSql('DROP TABLE ecommerce_abandonment');
        $this->addSql('DROP TABLE ecommerce_sales_metrics');
        $this->addSql('DROP TABLE ecommerce_payment_metrics');
        $this->addSql('DROP TABLE ecommerce_payment_gateways');
        $this->addSql('DROP TABLE ecommerce_checkout_metrics');
        $this->addSql('DROP TABLE ecommerce_checkout_steps');
        $this->addSql('DROP TABLE ecommerce_stores');
    }
}
