-- LeadFlow initial schema (MySQL 8)
SET NAMES utf8mb4;
SET foreign_key_checks = 0;

CREATE TABLE IF NOT EXISTS users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(255) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  name VARCHAR(255) NOT NULL,
  role ENUM('super_admin','admin','manager','analyst') NOT NULL DEFAULT 'analyst',
  active TINYINT(1) NOT NULL DEFAULT 1,
  last_login_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS sources (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  slug VARCHAR(120) NOT NULL UNIQUE,
  api_key VARCHAR(80) NOT NULL UNIQUE,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS campaigns (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  source_id INT UNSIGNED NULL,
  name VARCHAR(160) NOT NULL,
  campaign_key VARCHAR(120) NOT NULL UNIQUE,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL,
  INDEX (source_id),
  CONSTRAINT fk_campaigns_source FOREIGN KEY (source_id) REFERENCES sources(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS leads (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  lead_id VARCHAR(32) NOT NULL UNIQUE,
  uuid CHAR(36) NOT NULL UNIQUE,
  status ENUM('NEW','VALIDATING','VALID','DUPLICATE','PINGING','BIDS_RECEIVED','WINNER_SELECTED','POSTING','SOLD','REJECTED','FAILED') NOT NULL DEFAULT 'NEW',
  first_name VARCHAR(80) NOT NULL,
  last_name  VARCHAR(80) NOT NULL,
  email VARCHAR(255) NOT NULL,
  phone VARCHAR(20) NOT NULL,
  address VARCHAR(255) NULL,
  city VARCHAR(120) NULL,
  state CHAR(2) NOT NULL,
  zip VARCHAR(10) NOT NULL,
  date_of_birth DATE NULL,
  employment_status VARCHAR(40) NULL,
  monthly_income DECIMAL(12,2) NULL,
  pay_frequency VARCHAR(20) NULL,
  loan_amount DECIMAL(12,2) NULL,
  source_id INT UNSIGNED NULL,
  campaign_id INT UNSIGNED NULL,
  sub_id VARCHAR(120) NULL,
  fb_campaign_id VARCHAR(64) NULL,
  fb_adset_id VARCHAR(64) NULL,
  fb_ad_id VARCHAR(64) NULL,
  utm_source   VARCHAR(120) NULL,
  utm_medium   VARCHAR(120) NULL,
  utm_campaign VARCHAR(160) NULL,
  utm_content  VARCHAR(160) NULL,
  winning_buyer_id INT UNSIGNED NULL,
  revenue DECIMAL(10,4) NOT NULL DEFAULT 0,
  buyer_transaction_id VARCHAR(128) NULL,
  sold_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  INDEX idx_leads_created (created_at),
  INDEX idx_leads_status (status),
  INDEX idx_leads_email (email),
  INDEX idx_leads_phone (phone),
  INDEX idx_leads_state (state),
  INDEX idx_leads_source (source_id),
  INDEX idx_leads_campaign (campaign_id),
  INDEX idx_leads_winner (winning_buyer_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS lead_custom_fields (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  lead_id BIGINT UNSIGNED NOT NULL,
  field_key VARCHAR(80) NOT NULL,
  field_value TEXT NULL,
  created_at DATETIME NOT NULL,
  INDEX (lead_id),
  INDEX (field_key),
  CONSTRAINT fk_lcf_lead FOREIGN KEY (lead_id) REFERENCES leads(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS lead_consents (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  lead_id BIGINT UNSIGNED NOT NULL,
  trustedform_cert VARCHAR(500) NULL,
  jornaya_lead_id  VARCHAR(120) NULL,
  consent_version  VARCHAR(20)  NOT NULL,
  disclosure_version VARCHAR(20) NOT NULL,
  privacy_version  VARCHAR(20)  NOT NULL,
  terms_version    VARCHAR(20)  NOT NULL,
  landing_url      VARCHAR(500) NULL,
  ip_address       VARCHAR(64)  NULL,
  user_agent       VARCHAR(500) NULL,
  consent_timestamp DATETIME NOT NULL,
  created_at DATETIME NOT NULL,
  INDEX (lead_id),
  CONSTRAINT fk_consent_lead FOREIGN KEY (lead_id) REFERENCES leads(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS lead_status_history (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  lead_id BIGINT UNSIGNED NOT NULL,
  from_status VARCHAR(30) NULL,
  to_status   VARCHAR(30) NOT NULL,
  note VARCHAR(500) NULL,
  created_at DATETIME NOT NULL,
  INDEX (lead_id),
  CONSTRAINT fk_lsh_lead FOREIGN KEY (lead_id) REFERENCES leads(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS buyers (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(160) NOT NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  ping_url VARCHAR(500) NOT NULL,
  post_url VARCHAR(500) NOT NULL,
  ping_method VARCHAR(10) NOT NULL DEFAULT 'POST',
  post_method VARCHAR(10) NOT NULL DEFAULT 'POST',
  request_format VARCHAR(10) NOT NULL DEFAULT 'json',
  timeout_ms INT NOT NULL DEFAULT 3000,
  priority INT NOT NULL DEFAULT 100,
  weight   INT NOT NULL DEFAULT 1,
  daily_cap   INT NULL,
  hourly_cap  INT NULL,
  monthly_cap INT NULL,
  total_cap   INT NULL,
  credentials_json JSON NULL,
  headers_json     JSON NULL,
  field_map_json   JSON NULL,
  transformations_json JSON NULL,
  response_rules_json  JSON NULL,
  schedule_json    JSON NULL,
  rules_json       JSON NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS ping_trees (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(160) NOT NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  routing_mode ENUM('highest_bid','priority','weighted','round_robin','waterfall') NOT NULL DEFAULT 'highest_bid',
  min_bid DECIMAL(10,4) NOT NULL DEFAULT 0,
  allow_fallback TINYINT(1) NOT NULL DEFAULT 1,
  max_wait_ms INT NOT NULL DEFAULT 5000,
  max_buyers  INT NOT NULL DEFAULT 25,
  source_id   INT UNSIGNED NULL,
  campaign_id INT UNSIGNED NULL,
  buyer_ids_json    JSON NOT NULL,
  fallback_ids_json JSON NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  INDEX (source_id), INDEX (campaign_id), INDEX (active)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS ping_transactions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  lead_id BIGINT UNSIGNED NOT NULL,
  buyer_id INT UNSIGNED NOT NULL,
  status VARCHAR(20) NOT NULL,
  accepted TINYINT(1) NULL,
  bid DECIMAL(10,4) NULL,
  transaction_id VARCHAR(128) NULL,
  response_time_ms INT NOT NULL DEFAULT 0,
  error_message VARCHAR(512) NULL,
  created_at DATETIME NOT NULL,
  INDEX idx_pt_lead (lead_id),
  INDEX idx_pt_buyer (buyer_id),
  INDEX idx_pt_created (created_at),
  CONSTRAINT fk_pt_lead  FOREIGN KEY (lead_id)  REFERENCES leads(id) ON DELETE CASCADE,
  CONSTRAINT fk_pt_buyer FOREIGN KEY (buyer_id) REFERENCES buyers(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS ping_responses (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  ping_transaction_id BIGINT UNSIGNED NOT NULL,
  request_body MEDIUMTEXT NULL,
  response_body MEDIUMTEXT NULL,
  created_at DATETIME NOT NULL,
  INDEX (ping_transaction_id),
  CONSTRAINT fk_pr_pt FOREIGN KEY (ping_transaction_id) REFERENCES ping_transactions(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS post_transactions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  lead_id BIGINT UNSIGNED NOT NULL,
  buyer_id INT UNSIGNED NOT NULL,
  success TINYINT(1) NOT NULL DEFAULT 0,
  transaction_id VARCHAR(128) NULL,
  payout DECIMAL(10,4) NULL,
  response_time_ms INT NOT NULL DEFAULT 0,
  request_body MEDIUMTEXT NULL,
  response_body MEDIUMTEXT NULL,
  error_message VARCHAR(512) NULL,
  idempotency_key CHAR(64) NOT NULL,
  created_at DATETIME NOT NULL,
  UNIQUE KEY uk_post_idem (buyer_id, idempotency_key),
  INDEX idx_po_lead (lead_id),
  INDEX idx_po_created (created_at),
  CONSTRAINT fk_po_lead  FOREIGN KEY (lead_id)  REFERENCES leads(id) ON DELETE CASCADE,
  CONSTRAINT fk_po_buyer FOREIGN KEY (buyer_id) REFERENCES buyers(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS revenue_records (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  lead_id BIGINT UNSIGNED NOT NULL,
  buyer_id INT UNSIGNED NOT NULL,
  post_transaction_id BIGINT UNSIGNED NOT NULL,
  revenue DECIMAL(10,4) NOT NULL,
  created_at DATETIME NOT NULL,
  INDEX (lead_id), INDEX (buyer_id), INDEX (created_at),
  CONSTRAINT fk_rev_lead  FOREIGN KEY (lead_id)  REFERENCES leads(id) ON DELETE CASCADE,
  CONSTRAINT fk_rev_buyer FOREIGN KEY (buyer_id) REFERENCES buyers(id) ON DELETE CASCADE,
  CONSTRAINT fk_rev_post  FOREIGN KEY (post_transaction_id) REFERENCES post_transactions(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS webhooks (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  url  VARCHAR(500) NOT NULL,
  events_json JSON NOT NULL,
  secret VARCHAR(120) NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS webhook_events (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  webhook_id INT UNSIGNED NOT NULL,
  event VARCHAR(60) NOT NULL,
  payload JSON NOT NULL,
  success TINYINT(1) NOT NULL DEFAULT 0,
  attempts INT NOT NULL DEFAULT 0,
  last_error VARCHAR(500) NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  INDEX (webhook_id), INDEX (event),
  CONSTRAINT fk_we_wh FOREIGN KEY (webhook_id) REFERENCES webhooks(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS audit_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NULL,
  action VARCHAR(60) NOT NULL,
  entity_type VARCHAR(60) NULL,
  entity_id BIGINT UNSIGNED NULL,
  meta_json JSON NULL,
  ip_address VARCHAR(64) NULL,
  created_at DATETIME NOT NULL,
  INDEX (user_id), INDEX (action), INDEX (entity_type, entity_id), INDEX (created_at)
) ENGINE=InnoDB;

SET foreign_key_checks = 1;
