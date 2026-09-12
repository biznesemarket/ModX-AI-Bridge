# TypeScript SDK

```ts
import { BridgeClient } from '@modx-ai-bridge/sdk';
const bridge = new BridgeClient({baseUrl: process.env.AIBRIDGE_URL!, token: process.env.AIBRIDGE_TOKEN!});
const schema = await bridge.siteSchema({limit: 100});
const result = await bridge.createResource({pagetitle: 'AI article', content: '<p>...</p>'});
```

The SDK uses `AbortController` for request timeouts and exposes machine-readable `ApiError` values.
