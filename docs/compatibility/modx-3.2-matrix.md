# MODX 3.2.x Compatibility Matrix

| MODX | PHP | Database | Status | Required evidence |
|---|---|---|---|---|
| 3.2.x | 8.1+ | MySQL/MariaDB supported by MODX | Target | Full CI runtime |
| 3.2.0 | 8.1+ | MySQL/MariaDB | Verify | Integration + E2E |
| 3.2.1 | 8.1+ | MySQL/MariaDB | Verify | Integration + E2E |
| 3.2.2-pl | 8.1+ | MySQL/MariaDB | Primary CI target | Integration + E2E |

The matrix is intentionally conservative. A row marked `Verify` must not be represented as `Stable` without successful runtime evidence for that exact combination.
