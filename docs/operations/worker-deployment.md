# Worker Deployment

Workers must run the same Bridge version as the API process. A worker must not execute jobs against a schema/application version it does not understand.

Recommended deployment order:

1. deploy application/package;
2. apply compatible migrations;
3. verify readiness;
4. stop old workers gracefully;
5. start workers with the new version;
6. monitor queue depth and stale jobs.

Graceful shutdown should allow a claimed job to finish or expire its lease rather than creating a second concurrent execution.
