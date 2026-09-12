CREATE TABLE IF NOT EXISTS `{PREFIX}aibridge_profiles` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(190) NOT NULL,
  `site_key` varchar(190) NOT NULL,
  `status` varchar(32) NOT NULL DEFAULT 'active',
  `environment` varchar(32) NOT NULL DEFAULT 'production',
  `base_url` varchar(500) DEFAULT NULL,
  `metadata_json` longtext,
  `created_at` datetime NOT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `site_key` (`site_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `{PREFIX}aibridge_tokens` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `profile_id` int NOT NULL,
  `name` varchar(190) NOT NULL,
  `token_hash` char(64) NOT NULL,
  `status` varchar(32) NOT NULL DEFAULT 'active',
  `expires_at` datetime DEFAULT NULL,
  `last_used_at` datetime DEFAULT NULL,
  `scopes_json` text NOT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `token_hash` (`token_hash`),
  KEY `profile_id` (`profile_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `{PREFIX}aibridge_audit` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `profile_id` int NOT NULL,
  `event` varchar(190) NOT NULL,
  `actor_type` varchar(32) NOT NULL,
  `actor_id` varchar(190) DEFAULT NULL,
  `operation` varchar(64) DEFAULT NULL,
  `resource_id` int DEFAULT NULL,
  `request_id` varchar(190) DEFAULT NULL,
  `context_json` text,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `profile_id_request_id` (`profile_id`,`request_id`),
  KEY `request_id` (`request_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `{PREFIX}aibridge_jobs` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `profile_id` int NOT NULL,
  `type` varchar(190) NOT NULL,
  `status` varchar(32) NOT NULL DEFAULT 'queued',
  `payload_json` longtext,
  `result_json` longtext,
  `error_json` longtext,
  `attempts` int NOT NULL DEFAULT 0,
  `max_attempts` int NOT NULL DEFAULT 3,
  `timeout_seconds` int NOT NULL DEFAULT 300,
  `available_at` datetime NOT NULL,
  `locked_at` datetime DEFAULT NULL,
  `locked_by` varchar(190) DEFAULT NULL,
  `progress` int NOT NULL DEFAULT 0,
  `progress_json` text,
  `idempotency_key` varchar(190) DEFAULT NULL,
  `principal_id` varchar(190) DEFAULT NULL,
  `request_id` varchar(190) DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `started_at` datetime DEFAULT NULL,
  `finished_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `profile_id_status_available` (`profile_id`,`status`,`available_at`),
  KEY `status_available` (`status`,`available_at`),
  KEY `locked_at` (`status`,`locked_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `{PREFIX}aibridge_schemas` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `profile_id` int NOT NULL,
  `type` varchar(64) NOT NULL,
  `version` varchar(32) NOT NULL,
  `schema_json` longtext NOT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `{PREFIX}aibridge_fingerprints` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `profile_id` int NOT NULL,
  `algorithm` varchar(64) NOT NULL,
  `fingerprint` char(64) NOT NULL,
  `snapshot_json` longtext NOT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `{PREFIX}aibridge_policies` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `profile_id` int NOT NULL,
  `name` varchar(190) NOT NULL,
  `rules_json` longtext NOT NULL,
  `status` varchar(32) NOT NULL DEFAULT 'active',
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `{PREFIX}aibridge_snapshots` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `profile_id` int NOT NULL,
  `resource_id` int DEFAULT NULL,
  `operation` varchar(64) NOT NULL,
  `data_json` longtext NOT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `{PREFIX}aibridge_idempotency` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `profile_id` int NOT NULL,
  `idempotency_key` varchar(190) NOT NULL,
  `principal_id` varchar(190) NOT NULL,
  `operation` varchar(64) NOT NULL,
  `request_hash` char(64) NOT NULL,
  `status` varchar(32) NOT NULL DEFAULT 'in_progress',
  `response_json` longtext,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idempotency_unique` (`idempotency_key`,`principal_id`,`operation`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `{PREFIX}aibridge_rate_limits` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `profile_id` int NOT NULL,
  `bucket_key` varchar(190) NOT NULL,
  `window_start` int NOT NULL,
  `requests` int NOT NULL DEFAULT 0,
  `expires_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `bucket_unique` (`bucket_key`,`window_start`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `{PREFIX}aibridge_change_requests` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `profile_id` int NOT NULL,
  `operation` varchar(64) NOT NULL,
  `resource_id` int DEFAULT NULL,
  `status` varchar(32) NOT NULL DEFAULT 'draft',
  `input_json` longtext NOT NULL,
  `before_json` longtext,
  `after_json` longtext,
  `diff_json` longtext,
  `qa_json` longtext,
  `approval_id` int DEFAULT NULL,
  `job_id` int DEFAULT NULL,
  `requested_by` varchar(190) DEFAULT NULL,
  `request_id` varchar(190) DEFAULT NULL,
  `rejection_reason` text,
  `created_at` datetime NOT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `profile_status` (`profile_id`,`status`),
  KEY `approval_id` (`approval_id`),
  KEY `job_id` (`job_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `{PREFIX}aibridge_approvals` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `change_id` int NOT NULL,
  `status` varchar(32) NOT NULL DEFAULT 'pending',
  `requested_by` varchar(190) DEFAULT NULL,
  `requested_at` datetime NOT NULL,
  `decided_by` varchar(190) DEFAULT NULL,
  `decided_at` datetime DEFAULT NULL,
  `comment` text,
  PRIMARY KEY (`id`),
  KEY `change_id` (`change_id`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
