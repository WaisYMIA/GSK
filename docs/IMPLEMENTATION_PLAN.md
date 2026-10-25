# Implementation plan and gates

1. **Bootstrap (1–2 days):** create Laravel app, install Sanctum, set PHP extensions, CI lint/test workflow, local environment and disposable database.
2. **Core schema (2–4 days):** run migrations; fix schema issues on the selected MySQL/MariaDB version; implement model relationships, factories, policies and development-only seed data.
3. **Policy operationalisation (parallel):** principles POL-01..POL-06 are approved. Assign accountable owners and define the still-unspecified numeric retention schedule, severity/response targets, escalation rota, employer verification checklist, and payment provider capabilities before the affected modules are production-enabled.
4. **Identity/RBAC (3–6 days):** registration, verification, password reset, MFA for staff/admin, explicit permission grants, audit trail, lockout and rate limits.
5. **Jobs and applications (4–7 days):** verified employer publishing, candidate profile, search/filter, application lifecycle, secure application messaging and attachment access.
6. **Welfare and safeguarding (4–8 days):** anonymous/identified intake, secure follow-up, triage, assignment, event log, escalation service, confidential file handling and access tests.
7. **Donations (4–8 days):** provider selection, one-time M-Pesa/card, verified webhooks, receipts/refunds; recurring mandates only after provider capability confirmation.
8. **Consent/data sharing (3–6 days):** versioned notices, consent grant/withdrawal, lawful-basis review, disclosure approval/logging and retention tasks.
9. **Content/admin/public UI (5–10 days):** accessible responsive pages, CMS/blog editor, media positioning, staff management, analytics and moderation.
10. **Hardening/UAT/deploy (5–8 days):** threat modelling, dependency scan, OWASP-oriented tests, load test, backup restore, staging UAT, cPanel-VPS deployment rehearsal and rollback.

Effort is an indicative planning range for a small experienced team; stakeholder/legal review and provider onboarding may extend calendar time. Production readiness requires all critical acceptance tests passing, not merely completion of the scaffold.
