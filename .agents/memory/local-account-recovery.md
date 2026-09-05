---
name: Local account recovery
description: Password recovery behavior for the local Hospital de Malanje installation.
---

Password recovery is intentionally local: a user submits the account email and the application displays a one-time reset link in the same session. Tokens expire after 60 minutes and are consumed on use.

**Why:** This installation does not rely on external email delivery or domain verification; the user explicitly chose an on-premise/local recovery flow.

**How to apply:** Keep the reset token hashed in PostgreSQL, never log or persist the raw token, keep the generic response for unknown emails where possible, and preserve the authenticated change-password form in the patient profile.