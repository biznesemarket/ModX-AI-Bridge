# Queue Operations

Recommended operational endpoints are:

- `POST /api/ai/v2/jobs` — enqueue an approved asynchronous operation.
- `GET /api/ai/v2/jobs/{id}` — retrieve state, progress and sanitized result/error.
- `POST /api/ai/v2/jobs/{id}/cancel` — request cancellation.

These HTTP routes are intentionally documented as an application contract first; transport wiring should reuse the existing authentication, authorization, policy and audit middleware.
