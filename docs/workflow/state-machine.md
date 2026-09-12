# Change State Machine

```text
draft
  │ submit
  ▼
pending_approval ──reject──> rejected
  │ approve
  ▼
approved ──dispatch──> executing ──success──> completed
                              │
                              └──failure──> failed
```

Only the defined transitions are valid.
