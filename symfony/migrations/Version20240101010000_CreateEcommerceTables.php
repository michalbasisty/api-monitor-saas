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
            id UUID NOT NULL PRIMARY KEY,
            user_id UUID NOT NULL,
            store_name VARCHAR(255) NOT NULL,
            store_url VARCHAR(255) NOT NULL UNIQUE,
            platform VARCHAR(50) NOT NULL,
            currency VARCHAR(3) NOT NULL DEFAULT \'USD\',
            timezone VARCHAR(50) DEFAULT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT NULL,
            CONSTRAINT fk_ecommerce_stores_user_id FOREIGN KEY (user_id) REFERENCES "user"(id) ON DELETE CASCADE
        )');
        $this->addSql('CREATE INDEX idx_ecommerce_stores_user_id ON ecommerce_stores(user_id)');

        // Checkout steps table
        $this->addSql('CREATE TABLE ecommerce_checkout_steps (
            id UUID NOT NULL PRIMARY KEY,
            store_id UUID NOT NULL,
            step_number INT NOT NULL,
            step_name VARCHAR(100) NOT NULL,
            endpoint_url VARCHAR(255) NOT NULL,
            expected_load_time_ms INT NOT NULL DEFAULT 1000,
            alert_threshold_ms INT NOT NULL DEFAULT 2000,
            enabled BOOLEAN NOT NULL DEFAULT TRUE,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT NULL,
            CONSTRAINT fk_ecommerce_checkout_steps_store_id FOREIGN KEY (store_id) REFERENCES ecommerce_stores(id) ON DELETE CASCADE
        )');
        $this->addSql('CREATE INDEX idx_ecommerce_checkout_steps_store_id ON ecommerce_checkout_steps(store_id)');
        $this->addSql('CREATE INDEX idx_ecommerce_checkout_steps_store_number ON ecommerce_checkout_steps(store_id, step_number)');

        // Checkout metrics table
        $this->addSql('CREATE TABLE ecommerce_checkout_metrics (
            id UUID NOT NULL PRIMARY KEY,
            store_id UUID NOT NULL,
            step_id UUID NOT NULL,
            "timestamp" TIMESTAMP NOT NULL,
            load_time_ms INT NOT NULL,
            api_response_time_ms INT DEFAULT NULL,
            error_occurred BOOLEAN NOT NULL DEFAULT FALSE,
            error_message TEXT DEFAULT NULL,
            http_status_code INT DEFAULT NULL,
            session_id VARCHAR(255) DEFAULT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_ecommerce_checkout_metrics_store_id FOREIGN KEY (store_id) REFERENCES ecommerce_stores(id) ON DELETE CASCADE,
            CONSTRAINT fk_ecommerce_checkout_metrics_step_id FOREIGN KEY (step_id) REFERENCES ecommerce_checkout_steps(id) ON DELETE CASCADE
        )');
        $this->addSql('CREATE INDEX idx_ecommerce_checkout_metrics_store_timestamp ON ecommerce_checkout_metrics(store_id, "timestamp")');
        $this->addSql('CREATE INDEX idx_ecommerce_checkout_metrics_step_timestamp ON ecommerce_checkout_metrics(step_id, "timestamp")');
        $this->addSql('CREATE INDEX idx_ecommerce_checkout_metrics_session_id ON ecommerce_checkout_metrics(session_id)');

        // Payment gateways table
        $this->addSql('CREATE TABLE ecommerce_payment_gateways (
            id UUID NOT NULL PRIMARY KEY,
            store_id UUID NOT NULL,
            gateway_name VARCHAR(100) NOT NULL,
            api_key_encrypted VARCHAR(500) NOT NULL,
            webhook_url VARCHAR(255) DEFAULT NULL,
            webhook_secret_encrypted VARCHAR(500) DEFAULT NULL,
            is_primary BOOLEAN NOT NULL DEFAULT FALSE,
            enabled BOOLEAN NOT NULL DEFAULT TRUE,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT NULL,
            CONSTRAINT fk_ecommerce_payment_gateways_store_id FOREIGN KEY (store_id) REFERENCES ecommerce_stores(id) ON DELETE CASCADE,
            UNIQUE(store_id, gateway_name)
        )');
        $this->addSql('CREATE INDEX idx_ecommerce_payment_gateways_store_id ON ecommerce_payment_gateways(store_id)');

        // Payment metrics table
        $this->addSql('CREATE TABLE ecommerce_payment_metrics (
            id UUID NOT NULL PRIMARY KEY,
            store_id UUID NOT NULL,
            gateway_id UUID NOT NULL,
            transaction_id VARCHAR(255) NOT NULL,
            amount DECIMAL(10, 2) NOT NULL,
            currency VARCHAR(3) NOT NULL,
            status VARCHAR(50) NOT NULL,
            authorization_time_ms INT DEFAULT NULL,
            settlement_time_hours INT DEFAULT NULL,
            webhook_received BOOLEAN NOT NULL DEFAULT FALSE,
            webhook_timestamp TIMESTAMP DEFAULT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_ecommerce_payment_metrics_store_id FOREIGN KEY (store_id) REFERENCES ecommerce_stores(id) ON DELETE CASCADE,
            CONSTRAINT fk_ecommerce_payment_metrics_gateway_id FOREIGN KEY (gateway_id) REFERENCES ecommerce_payment_gateways(id) ON DELETE CASCADE
        )');
        $this->addSql('CREATE INDEX idx_ecommerce_payment_metrics_store_created ON ecommerce_payment_metrics(store_id, created_at)');
        $this->addSql('CREATE INDEX idx_ecommerce_payment_metrics_transaction_id ON ecommerce_payment_metrics(transaction_id)');
        $this->addSql('CREATE INDEX idx_ecommerce_payment_metrics_status ON ecommerce_payment_metrics(status)');

        // Sales metrics table
        $this->addSql('CREATE TABLE ecommerce_sales_metrics (
            id UUID NOT NULL PRIMARY KEY,
            store_id UUID NOT NULL,
            "timestamp" TIMESTAMP NOT NULL,
            revenue_per_minute DECIMAL(10, 2) DEFAULT NULL,
            orders_per_minute INT DEFAULT NULL,
            checkout_success_rate DECIMAL(5, 2) DEFAULT NULL,
            avg_order_value DECIMAL(10, 2) DEFAULT NULL,
            estimated_lost_revenue DECIMAL(10, 2) DEFAULT NULL,
            status VARCHAR(50) NOT NULL DEFAULT \'normal\',
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_ecommerce_sales_metrics_store_id FOREIGN KEY (store_id) REFERENCES ecommerce_stores(id) ON DELETE CASCADE
        )');
        $this->addSql('CREATE INDEX idx_ecommerce_sales_metrics_store_timestamp ON ecommerce_sales_metrics(store_id, "timestamp")');
        $this->addSql('CREATE INDEX idx_ecommerce_sales_metrics_status ON ecommerce_sales_metrics(store_id, status)');

        // Checkout abandonment table
        $this->addSql('CREATE TABLE ecommerce_abandonment (
            id UUID NOT NULL PRIMARY KEY,
            store_id UUID NOT NULL,
            session_id VARCHAR(255) NOT NULL,
            started_at TIMESTAMP NOT NULL,
            abandoned_at_step_id UUID DEFAULT NULL,
            last_seen TIMESTAMP NOT NULL,
            reason VARCHAR(255) DEFAULT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_ecommerce_abandonment_store_id FOREIGN KEY (store_id) REFERENCES ecommerce_stores(id) ON DELETE CASCADE,
            CONSTRAINT fk_ecommerce_abandonment_step_id FOREIGN KEY (abandoned_at_step_id) REFERENCES ecommerce_checkout_steps(id) ON DELETE SET NULL
        )');
        $this->addSql('CREATE INDEX idx_ecommerce_abandonment_store_created ON ecommerce_abandonment(store_id, created_at)');
        $this->addSql('CREATE INDEX idx_ecommerce_abandonment_session_id ON ecommerce_abandonment(session_id)');

        // Traffic spikes detection
        $this->addSql('CREATE TABLE ecommerce_traffic_spikes (
            id UUID NOT NULL PRIMARY KEY,
            store_id UUID NOT NULL,
            spike_detected_at TIMESTAMP NOT NULL,
            baseline_rpm INT NOT NULL,
            peak_rpm INT NOT NULL,
            duration_minutes INT NOT NULL,
            event_type VARCHAR(100) DEFAULT NULL,
            performance_impact VARCHAR(50) NOT NULL DEFAULT \'normal\',
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_ecommerce_traffic_spikes_store_id FOREIGN KEY (store_id) REFERENCES ecommerce_stores(id) ON DELETE CASCADE
        )');
        $this->addSql('CREATE INDEX idx_ecommerce_traffic_spikes_store_detected ON ecommerce_traffic_spikes(store_id, spike_detected_at)');

        // E-commerce specific alerts
        $this->addSql('CREATE TABLE ecommerce_alerts (
            id UUID NOT NULL PRIMARY KEY,
            store_id UUID NOT NULL,
            alert_type VARCHAR(100) NOT NULL,
            severity VARCHAR(20) NOT NULL,
            triggered_at TIMESTAMP NOT NULL,
            metric_value DECIMAL(10, 2) DEFAULT NULL,
            threshold_value DECIMAL(10, 2) DEFAULT NULL,
            description TEXT DEFAULT NULL,
            resolved_at TIMESTAMP DEFAULT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_ecommerce_alerts_store_id FOREIGN KEY (store_id) REFERENCES ecommerce_stores(id) ON DELETE CASCADE
        )');
        $this->addSql('CREATE INDEX idx_ecommerce_alerts_store_created ON ecommerce_alerts(store_id, created_at)');
        $this->addSql('CREATE INDEX idx_ecommerce_alerts_severity ON ecommerce_alerts(store_id, severity)');
        $this->addSql('CREATE INDEX idx_ecommerce_alerts_resolved ON ecommerce_alerts(resolved_at)');
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
