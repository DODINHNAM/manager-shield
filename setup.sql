-- setup for manager_lazypaygate_com
CREATE DATABASE IF NOT EXISTS manager_lazypaygate_com DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE manager_lazypaygate_com;

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE,
    password VARCHAR(255),
    role ENUM('admin','manager') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE web_shields (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    domain VARCHAR(255),
    manager_id INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (manager_id) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE payment_types (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(50) UNIQUE,
    name VARCHAR(100) NOT NULL,
    description TEXT
);

CREATE TABLE web_shield_payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    web_shield_id INT NOT NULL,
    payment_type_id INT NOT NULL,
    active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (web_shield_id) REFERENCES web_shields(id) ON DELETE CASCADE,
    FOREIGN KEY (payment_type_id) REFERENCES payment_types(id) ON DELETE CASCADE,
    UNIQUE (web_shield_id, payment_type_id)
);

CREATE TABLE paypal_configs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    web_shield_payment_id INT NOT NULL,
    environment ENUM('sandbox','live') DEFAULT 'sandbox',
    client_id VARCHAR(255),
    secret_id VARCHAR(255),
    FOREIGN KEY (web_shield_payment_id) REFERENCES web_shield_payments(id) ON DELETE CASCADE
);

CREATE TABLE stripe_configs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    web_shield_payment_id INT NOT NULL,
    api_key VARCHAR(255),
    publishable_key VARCHAR(255),
    FOREIGN KEY (web_shield_payment_id) REFERENCES web_shield_payments(id) ON DELETE CASCADE
);

CREATE TABLE momo_configs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    web_shield_payment_id INT NOT NULL,
    partner_code VARCHAR(100),
    access_key VARCHAR(100),
    secret_key VARCHAR(255),
    environment ENUM('sandbox','production') DEFAULT 'sandbox',
    FOREIGN KEY (web_shield_payment_id) REFERENCES web_shield_payments(id) ON DELETE CASCADE
);

CREATE TABLE manager_whitelist_domains (
  id INT AUTO_INCREMENT PRIMARY KEY,
  web_shield_id INT NOT NULL,
  domain VARCHAR(255) NOT NULL,
  active TINYINT(1) DEFAULT 1,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (web_shield_id) REFERENCES web_shields(id) ON DELETE CASCADE,
  UNIQUE KEY unique_shield_domain (web_shield_id, domain)
);

CREATE TABLE payment_transactions (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  transaction_key CHAR(64) NOT NULL UNIQUE,
  web_shield_id INT DEFAULT NULL,
  shield_name VARCHAR(100) DEFAULT NULL,
  shield_domain VARCHAR(255) DEFAULT NULL,
  manager_id INT DEFAULT NULL,
  manager_name VARCHAR(50) DEFAULT NULL,
  merchant_domain VARCHAR(255) NOT NULL,
  wc_order_id VARCHAR(100) DEFAULT NULL,
  wc_order_number VARCHAR(100) DEFAULT NULL,
  payment_provider VARCHAR(40) NOT NULL,
  provider_order_id VARCHAR(100) DEFAULT NULL,
  provider_transaction_id VARCHAR(100) DEFAULT NULL,
  payment_action VARCHAR(40) NOT NULL,
  status VARCHAR(40) NOT NULL,
  amount DECIMAL(18, 2) DEFAULT NULL,
  currency CHAR(3) DEFAULT NULL,
  provider_fee DECIMAL(18, 2) DEFAULT NULL,
  payout DECIMAL(18, 2) DEFAULT NULL,
  error_message TEXT DEFAULT NULL,
  first_occurred_at DATETIME DEFAULT NULL,
  last_occurred_at DATETIME DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_transactions_shield_date (web_shield_id, last_occurred_at),
  INDEX idx_transactions_manager_date (manager_id, last_occurred_at),
  INDEX idx_transactions_provider (payment_provider, provider_transaction_id),
  INDEX idx_transactions_status (status),
  INDEX idx_transactions_merchant (merchant_domain)
);

CREATE TABLE payment_events (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  event_key CHAR(64) NOT NULL UNIQUE,
  transaction_key CHAR(64) NOT NULL,
  web_shield_id INT DEFAULT NULL,
  shield_domain VARCHAR(255) DEFAULT NULL,
  manager_id INT DEFAULT NULL,
  manager_name VARCHAR(50) DEFAULT NULL,
  merchant_domain VARCHAR(255) NOT NULL,
  payment_provider VARCHAR(40) NOT NULL,
  payment_action VARCHAR(40) NOT NULL,
  status VARCHAR(40) NOT NULL,
  payload JSON DEFAULT NULL,
  occurred_at DATETIME NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_events_transaction (transaction_key),
  INDEX idx_events_manager_date (manager_id, occurred_at),
  INDEX idx_events_provider (payment_provider, payment_action)
);


-- seed payment types
INSERT INTO payment_types (code, name, description) VALUES
('paypal','PayPal','PayPal payment'),
('stripe','Stripe','Stripe payment'),
('momo','Momo','Momo e-wallet');

-- seed admin/Admin@123456
INSERT INTO users (username, password, role)
VALUES ('admin', '$2y$10$5GZ7BXZ4t8MuDtFG3KWcHOXjB4SczrkOsAnk5mCAZQ7jqWyd4XWEW', 'admin');
