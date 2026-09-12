# End-to-End Suite

E2E tests are executed only against the Docker MODX environment.

Required scenarios:

1. install Bridge;
2. authenticate a test token;
3. read Site Schema;
4. read Content Contract;
5. validate content;
6. create a resource;
7. preview it;
8. update it with the same idempotency key and verify no duplicate execution;
9. execute a queued mutation;
10. verify audit and snapshot records;
11. inject a failure and verify rollback;
12. verify publish approval enforcement;
13. verify profile isolation.

A missing runtime is a failed gate, not a pass.
