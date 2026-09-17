-- Direct Post buyers (post-only APIs with redirect URL, e.g. Round Sky / LeadHorizon)
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS direct_post_buyers (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(160) NOT NULL,
  provider VARCHAR(40) NOT NULL DEFAULT 'roundsky',
  active TINYINT(1) NOT NULL DEFAULT 0,
  test_mode TINYINT(1) NOT NULL DEFAULT 1,
  test_url VARCHAR(500) NOT NULL,
  live_url VARCHAR(500) NOT NULL,
  credentials_enc TEXT NULL,
  sub_id VARCHAR(120) NOT NULL,
  domain VARCHAR(190) NOT NULL,
  time_allowed INT NOT NULL DEFAULT 20,
  total_budget_s INT NOT NULL DEFAULT 45,
  price_tiers_json JSON NOT NULL,
  filters_json JSON NOT NULL,
  priority INT NOT NULL DEFAULT 100,
  daily_cap INT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  INDEX (active)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS direct_post_attempts (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  lead_id BIGINT UNSIGNED NULL,
  buyer_id INT UNSIGNED NOT NULL,
  is_test TINYINT(1) NOT NULL DEFAULT 0,
  minimum_price DECIMAL(10,4) NULL,
  decision VARCHAR(20) NULL,
  buyer_lead_id VARCHAR(64) NULL,
  price DECIMAL(10,4) NULL,
  message VARCHAR(500) NULL,
  redirect_url VARCHAR(1000) NULL,
  response_time_ms INT NOT NULL DEFAULT 0,
  request_masked MEDIUMTEXT NULL,
  response_body MEDIUMTEXT NULL,
  error_message VARCHAR(500) NULL,
  created_at DATETIME NOT NULL,
  INDEX idx_dpa_lead (lead_id),
  INDEX idx_dpa_buyer (buyer_id),
  INDEX idx_dpa_created (created_at),
  CONSTRAINT fk_dpa_buyer FOREIGN KEY (buyer_id) REFERENCES direct_post_buyers(id) ON DELETE CASCADE,
  CONSTRAINT fk_dpa_lead  FOREIGN KEY (lead_id)  REFERENCES leads(id) ON DELETE SET NULL
) ENGINE=InnoDB
