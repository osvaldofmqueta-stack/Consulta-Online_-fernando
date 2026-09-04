---
name: API codegen compatibility
description: Compatibility constraint between the workspace OpenAPI generator and its pinned Zod version.
---

The current workspace generator emits `zod.int()` for OpenAPI integer fields, while the pinned Zod 3 package does not expose that API. Use numeric fields as `number` in the OpenAPI contract unless the generator configuration and Zod dependency are upgraded together.

**Why:** A contract regeneration otherwise succeeds but the chained library typecheck fails before the frontend or server can consume the generated code.

**How to apply:** When adding numeric IDs, counters, or query parameters to `lib/api-spec/openapi.yaml`, prefer `number` and keep integer validation at the persistence or route boundary if needed.