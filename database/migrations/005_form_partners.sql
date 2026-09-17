-- Form Partners: permission-based browser form submission to partner sites.
-- Partners are managed from the dashboard; a separate Node/Playwright worker (worker/) processes the queue.
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS form_partners (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(160) NOT NULL,
  form_url VARCHAR(1000) NOT NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  sort_order INT NOT NULL DEFAULT 100,
  steps_json JSON NOT NULL,
  success_json JSON NULL,
  timeout_ms INT NOT NULL DEFAULT 45000,
  max_retries INT NOT NULL DEFAULT 2,
  notes VARCHAR(500) NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  INDEX (active), INDEX (sort_order)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS form_submissions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  lead_id BIGINT UNSIGNED NOT NULL,
  partner_id INT UNSIGNED NOT NULL,
  status ENUM('queued','running','submitted','failed') NOT NULL DEFAULT 'queued',
  attempts INT NOT NULL DEFAULT 0,
  next_attempt_at DATETIME NOT NULL,
  locked_at DATETIME NULL,
  final_url VARCHAR(1000) NULL,
  error_message VARCHAR(1000) NULL,
  duration_ms INT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  UNIQUE KEY uk_fs_lead_partner (lead_id, partner_id),
  INDEX idx_fs_queue (status, next_attempt_at),
  INDEX idx_fs_created (created_at),
  CONSTRAINT fk_fs_lead FOREIGN KEY (lead_id) REFERENCES leads(id) ON DELETE CASCADE,
  CONSTRAINT fk_fs_partner FOREIGN KEY (partner_id) REFERENCES form_partners(id) ON DELETE CASCADE
) ENGINE=InnoDB;
