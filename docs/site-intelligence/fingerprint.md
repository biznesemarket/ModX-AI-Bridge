# Site Fingerprint

**Status:** Draft

The fingerprint is a SHA-256 digest of a normalized Site Contract.

Normalization removes the volatile `generated_at` field, recursively normalizes associative arrays, and preserves list ordering. This gives the Bridge a deterministic comparison primitive for later change detection.

The fingerprint is not a security signature and does not prove authenticity. It is a change-detection identifier.
