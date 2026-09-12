CREATE TABLE IF NOT EXISTS `{PREFIX}aibridge_tokens` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(190) NOT NULL,
  `token_hash` CHAR(64) NOT NULL,
  `status` VARCHAR(32) NOT NULL DEFAULT 'active',
  `expires_at` DATETIME NULL,
  `last_used_at` DATETIME NULL,
  `created_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `token_hash` (`token_hash`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `{PREFIX}aibridge_audit` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `event` VARCHAR(190) NOT NULL,
  `actor_type` VARCHAR(32) NOT NULL,
  `actor_id` VARCHAR(190) NULL,
  `operation` VARCHAR(64) NULL,
  `resource_id` INT NULL,
  `request_id` VARCHAR(190) NULL,
  `context_json` LONGTEXT NULL,
  `created_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  KEY `request_id` (`request_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `{PREFIX}aibridge_jobs` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `type` VARCHAR(190) NOT NULL,
  `status` VARCHAR(32) NOT NULL DEFAULT 'queued',
  `payload_json` LONGTEXT NULL,
  `result_json` LONGTEXT NULL,
  `error_json` LONGTEXT NULL,
  `created_at` DATETIME NOT NULL,
  `started_at` DATETIME NULL,
  `finished_at` DATETIME NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `{PREFIX}aibridge_schemas` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `type` VARCHAR(64) NOT NULL,
  `version` VARCHAR(32) NOT NULL,
  `schema_json` LONGTEXT NOT NULL,
  `created_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `{PREFIX}aibridge_fingerprints` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `algorithm` VARCHAR(64) NOT NULL,
  `fingerprint` CHAR(64) NOT NULL,
  `snapshot_json` LONGTEXT NOT NULL,
  `created_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `{PREFIX}aibridge_policies` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(190) NOT NULL,
  `rules_json` LONGTEXT NOT NULL,
  `status` VARCHAR(32) NOT NULL DEFAULT 'active',
  `created_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `{PREFIX}aibridge_snapshots` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `resource_id` INT NULL,
  `operation` VARCHAR(64) NOT NULL,
  `data_json` LONGTEXT NOT NULL,
  `created_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
