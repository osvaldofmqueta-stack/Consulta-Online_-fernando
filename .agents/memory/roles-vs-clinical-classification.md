---
name: Roles vs clinical classification
description: The distinction between account permissions and patient profile classification.
---

Account roles are security permissions and must be assigned by an administrator. Public registration creates patient accounts only; it must never allow users to choose staff roles.

Patient clinical classification is separate profile data managed by reception or administration. It helps organise care and reporting but must not grant or remove application permissions.

**Why:** Mixing clinical categories with access roles would allow a patient profile attribute to affect security and could expose hospital data.

**How to apply:** Keep account role checks centralised in `Account::roleCan`; add future clinical categories to the patient record and staff workflows without using them as authorization rules.