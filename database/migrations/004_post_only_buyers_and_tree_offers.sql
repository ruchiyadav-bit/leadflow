ALTER TABLE buyers ADD COLUMN integration_type VARCHAR(20) NOT NULL DEFAULT 'ping_post';
ALTER TABLE buyers ADD COLUMN post_config_json JSON NULL;
ALTER TABLE buyers ADD COLUMN credentials_enc TEXT NULL;
ALTER TABLE ping_trees ADD COLUMN offer_ids_json JSON NULL;
DROP TABLE IF EXISTS direct_post_attempts;
DROP TABLE IF EXISTS direct_post_buyers;
