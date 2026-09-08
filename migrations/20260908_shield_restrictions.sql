CREATE TABLE IF NOT EXISTS shield_restrictions (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  scope ENUM('global','local') NOT NULL,
  manager_id INT DEFAULT NULL,
  web_shield_id INT DEFAULT NULL,
  rule_type ENUM('whitelist_domain','blacklist_email','blacklist_city','blacklist_state','blacklist_zipcode') NOT NULL,
  rule_value VARCHAR(255) NOT NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (manager_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (web_shield_id) REFERENCES web_shields(id) ON DELETE CASCADE,
  INDEX idx_restrictions_global (scope, manager_id, rule_type, active),
  INDEX idx_restrictions_local (web_shield_id, rule_type, active),
  UNIQUE KEY unique_restriction_rule (scope, manager_id, web_shield_id, rule_type, rule_value)
);
