---
name: Clinical delivery storage
description: Durable storage and delivery constraints for private clinical records in the Hospital de Malanje app.
---

Private clinical documents currently use PostgreSQL bytea records with role-checked download routes because App Storage cannot be provisioned in this workspace.

**Why:** The patient portal must provide a real upload/download flow before App Storage is available; a database-backed record keeps the file attached to the clinical record instead of presenting a placeholder.

**How to apply:** Keep document access behind the patient ownership check or an authorized staff role, audit downloads, validate MIME type and file size before insertion, and migrate storage only through the supported publish/database workflow if App Storage becomes available.