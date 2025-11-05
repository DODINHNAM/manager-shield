-- setup for php_shield_v2
CREATE DATABASE IF NOT EXISTS php_shield_v2 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE php_shield_v2;

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

-- seed payment types
INSERT INTO payment_types (code, name, description) VALUES
('paypal','PayPal','PayPal payment'),
('stripe','Stripe','Stripe payment'),
('momo','Momo','Momo e-wallet');

-- seed admin
INSERT INTO users (username, password, role)
VALUES ('admin', '$2y$10$ZrV6ydbkP3p1Qq7n3G0lXe5kFZ5h5w28v0J8sDqG/7Z6dS8YwYf9G', 'admin');
