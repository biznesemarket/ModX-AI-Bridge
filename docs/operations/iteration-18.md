# Iteration 18 — Resource Explorer & Site Operations UI

Status: Review

Iteration 18 adds the Manager-side Resource Explorer without creating a privileged browser CRUD path. Read operations are handled by `ResourceExplorerService`; mutations are submitted as jobs through `ResourceOperationService` and execute through the established `ResourceExecutionService`.
