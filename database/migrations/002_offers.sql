-- Affiliate Offers: S2S Dashboard (postback) + Affiliate Direct (link only)
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS offers (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(160) NOT NULL,
  network VARCHAR(120) NULL,
  delivery_mode ENUM('s2s','direct') NOT NULL DEFAULT 's2s',
  tracking_url VARCHAR(1000) NOT NULL,
  default_payout DECIMAL(10,4) NOT NULL DEFAULT 0,
  postback_key VARCHAR(64) NOT NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  notes VARCHAR(500) NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  INDEX (active)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS offer_clicks (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  click_id VARCHAR(40) NOT NULL UNIQUE,
  offer_id INT UNSIGNED NOT NULL,
  lead_id BIGINT UNSIGNED NULL,
  sub_id VARCHAR(120) NULL,
  ip_address VARCHAR(64) NULL,
  user_agent VARCHAR(500) NULL,
  converted TINYINT(1) NOT NULL DEFAULT 0,
  payout DECIMAL(10,4) NULL,
  conversion_txid VARCHAR(128) NULL,
  converted_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  INDEX idx_oc_offer (offer_id),
  INDEX idx_oc_lead (lead_id),
  INDEX idx_oc_created (created_at),
  CONSTRAINT fk_oc_offer FOREIGN KEY (offer_id) REFERENCES offers(id) ON DELETE CASCADE,
  CONSTRAINT fk_oc_lead  FOREIGN KEY (lead_id)  REFERENCES leads(id)  ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS offer_postbacks (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  offer_id INT UNSIGNED NULL,
  click_id VARCHAR(40) NULL,
  result VARCHAR(30) NOT NULL,
  query_string VARCHAR(2000) NULL,
  ip_address VARCHAR(64) NULL,
  created_at DATETIME NOT NULL,
  INDEX (offer_id), INDEX (click_id), INDEX (created_at)
) ENGINE=InnoDB
