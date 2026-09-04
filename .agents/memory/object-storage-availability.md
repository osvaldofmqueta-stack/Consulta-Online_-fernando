---
name: App Storage availability
description: Environment limitation affecting private clinical document uploads.
---

Private clinical files must use Replit App Storage rather than PostgreSQL. Provisioning can be blocked by the workspace credit budget; when that happens, keep document bytes out of the database and surface the dependency instead of adding local or base64 storage.

**Why:** The storage provisioning service returned a permission-denied response specifically because the workspace had exhausted its credits.

**How to apply:** Before implementing document upload flows, verify App Storage provisioning succeeds and only then add presigned uploads, protected object serving, and metadata persistence.