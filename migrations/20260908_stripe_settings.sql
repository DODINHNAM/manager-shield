ALTER TABLE stripe_configs
    ADD COLUMN environment ENUM('test','live') NOT NULL DEFAULT 'test',
    ADD COLUMN payment_method_all_enable BOOLEAN NOT NULL DEFAULT FALSE,
    ADD COLUMN enable_max_order_value BOOLEAN NOT NULL DEFAULT FALSE,
    ADD COLUMN max_order_value DECIMAL(18,2) NOT NULL DEFAULT 100,
    ADD COLUMN enable_random_order_no BOOLEAN NOT NULL DEFAULT FALSE,
    ADD COLUMN random_order_no_length INT NOT NULL DEFAULT 16,
    ADD COLUMN test_publishable_key VARCHAR(255) NULL,
    ADD COLUMN test_secret_key VARCHAR(255) NULL,
    ADD COLUMN live_publishable_key VARCHAR(255) NULL,
    ADD COLUMN live_secret_key VARCHAR(255) NULL;

UPDATE stripe_configs SET
    environment = CASE WHEN api_key LIKE 'sk_live_%' THEN 'live' ELSE 'test' END,
    test_secret_key = CASE WHEN api_key LIKE 'sk_test_%' THEN api_key ELSE NULL END,
    test_publishable_key = CASE WHEN publishable_key LIKE 'pk_test_%' THEN publishable_key ELSE NULL END,
    live_secret_key = CASE WHEN api_key LIKE 'sk_live_%' THEN api_key ELSE NULL END,
    live_publishable_key = CASE WHEN publishable_key LIKE 'pk_live_%' THEN publishable_key ELSE NULL END;
