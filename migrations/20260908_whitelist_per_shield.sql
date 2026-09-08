-- Run once on an existing installation.
-- Existing manager-level entries are copied to every shield owned by that manager.
ALTER TABLE manager_whitelist_domains
    ADD COLUMN web_shield_id INT NULL AFTER id;

UPDATE manager_whitelist_domains mwd
JOIN web_shields ws ON ws.manager_id = mwd.manager_id
SET mwd.web_shield_id = ws.id;

ALTER TABLE manager_whitelist_domains
    MODIFY web_shield_id INT NOT NULL,
    ADD CONSTRAINT fk_whitelist_web_shield
        FOREIGN KEY (web_shield_id) REFERENCES web_shields(id) ON DELETE CASCADE,
    ADD UNIQUE KEY unique_shield_domain (web_shield_id, domain),
    DROP FOREIGN KEY manager_whitelist_domains_ibfk_1,
    DROP COLUMN manager_id;
