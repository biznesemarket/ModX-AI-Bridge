# Content QA

Status: **Draft**

Content QA validates proposed content before execution.

## Error classes

Current blocking checks include:

- missing required fields;
- field maximum length violations;
- forbidden `<script>` tags;
- forbidden `<iframe>` tags;
- H1 count violations;
- invalid JSON-LD syntax.

## Warnings

Current non-blocking checks include:

- SEO title shorter or longer than the recommended range;
- meta description shorter or longer than the recommended range;
- missing JSON-LD;
- empty links.

## Output

```json
{
  "valid": true,
  "errors": [],
  "warnings": [],
  "metrics": {
    "error_count": 0,
    "warning_count": 0
  }
}
```

The QA service does not sanitize arbitrary HTML and does not establish that a URL is safe. Security policy and sanitization belong to later execution/security layers.
