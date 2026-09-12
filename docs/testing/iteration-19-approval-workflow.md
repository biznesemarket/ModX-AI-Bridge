# Iteration 19 Test Plan

- valid/invalid change state transitions;
- approval bound to exact change ID;
- rejected changes cannot execute;
- unapproved changes cannot execute;
- approved changes create queue jobs;
- manager permission is required;
- payload redaction;
- publish remains protected by approval policy;
- execution continues through ResourceExecutionService;
- audit events exist for workflow transitions;
- multi-site profile ID is preserved.
