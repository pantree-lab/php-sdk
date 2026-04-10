# Migration Guide — @pantree/js

## v0.x → v1.1

### DSN replaces separate credentials

**Before (v0.x):**
```js
import { sendPantreeEvent } from "@pantree/js";

await sendPantreeEvent({
  endpoint:     "https://your-pantree.com/api/ingest",
  projectKey:   "pk_xxx",
  ingestSecret: "sk_xxx",
  event: { message: "...", level: "error" },
});
```

**After (v1.x):**
```js
import Pantree from "@pantree/js";

Pantree.init({ dsn: "https://pk_xxx:sk_xxx@your-pantree.com/api/ingest" });
Pantree.captureException(err);
// or
Pantree.captureMessage("...", { level: "error" });
```

The old `sendPantreeEvent` export is still available and will not be removed, so existing code continues to work without changes.

### Health reporting (new in v1.1)

No migration needed — health reporting is **opt-in**. Add `healthReporting: true` to `init()` when you are ready.
