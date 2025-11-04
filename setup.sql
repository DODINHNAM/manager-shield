-- SQL: create database and tables for php_shield
CREATE DATABASE php_shield DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE php_shield;

CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(50) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  role ENUM('admin','manager') NOT NULL DEFAULT 'manager',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE web_shields (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  domain VARCHAR(255) DEFAULT NULL,
  manager_id INT DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (manager_id) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE payment_configs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  web_shield_id INT NOT NULL,
  type ENUM('paypal','stripe') NOT NULL DEFAULT 'paypal',
  environment ENUM('sandbox','live') NOT NULL DEFAULT 'sandbox',
  client_id VARCHAR(255) DEFAULT NULL,
  secret_id VARCHAR(255) DEFAULT NULL,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (web_shield_id) REFERENCES web_shields(id) ON DELETE CASCADE
);

-- Seed admin (password: Admin@123)
INSERT INTO users (username, password, role)
VALUES ('admin', '$2y$10$ZrV6ydbkP3p1Qq7n3G0lXe5kFZ5h5w28v0J8sDqG/7Z6dS8YwYf9G', 'admin');
