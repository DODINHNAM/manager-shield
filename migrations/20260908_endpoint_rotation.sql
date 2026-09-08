CREATE TABLE IF NOT EXISTS endpoint_rotation_configs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  token_hash CHAR(64) NOT NULL UNIQUE,
  token_preview VARCHAR(16) NOT NULL,
  payment_provider VARCHAR(40) NOT NULL DEFAULT 'paypal',
  rotation_method ENUM('by_time','by_amount') NOT NULL DEFAULT 'by_time',
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_by INT DEFAULT NULL,
  current_member_id INT DEFAULT NULL,
  current_rotation_at DATETIME DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS endpoint_rotation_members (
  id INT AUTO_INCREMENT PRIMARY KEY,
  config_id INT NOT NULL,
  web_shield_id INT NOT NULL,
  position INT NOT NULL DEFAULT 0,
  rotation_value DECIMAL(18,2) NOT NULL DEFAULT 0,
  paid_amount DECIMAL(18,2) NOT NULL DEFAULT 0,
  paid_date DATE DEFAULT NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (config_id) REFERENCES endpoint_rotation_configs(id) ON DELETE CASCADE,
  FOREIGN KEY (web_shield_id) REFERENCES web_shields(id) ON DELETE CASCADE,
  UNIQUE KEY unique_endpoint_rotation_shield (config_id, web_shield_id),
  INDEX idx_endpoint_rotation_order (config_id, position, active)
);

ALTER TABLE endpoint_rotation_configs
  ADD COLUMN IF NOT EXISTS payment_provider VARCHAR(40) NOT NULL DEFAULT 'paypal' AFTER token_preview;
