-- Defect #37: scope idempotency keys and rate-limit buckets by profile.
--
-- Both unique indexes previously keyed only on the logical key, so two profiles
-- sharing a principal could collide on insert (cross-profile 500/corruption) and
-- rate-limit lookups could read another profile's bucket. The index names are
-- unchanged, so this is safe to apply to a fresh install created by the xPDO
-- resolver as well (drop + recreate the same definition).
ALTER TABLE `{PREFIX}aibridge_idempotency`
  DROP INDEX `idempotency_unique`,
  ADD UNIQUE KEY `idempotency_unique` (`profile_id`,`idempotency_key`,`principal_id`,`operation`);

ALTER TABLE `{PREFIX}aibridge_rate_limits`
  DROP INDEX `bucket_unique`,
  ADD UNIQUE KEY `bucket_unique` (`profile_id`,`bucket_key`,`window_start`);
