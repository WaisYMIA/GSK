# Global Skills Kenya (GSK) — AI Developer Master Specification

**Purpose:** This document is the authoritative hand-off for an AI coding agent or engineering team to turn the current scaffold into a secure, tested, deployable GSK website and system. The existing archive is a starting scaffold, not a complete application. The agent must inspect the repository, run it, fix existing defects, and implement all requirements below; it must not merely produce plans or claim tests passed without execution evidence.

## 1. Product outcome

Build a responsive, accessible website and operational platform for Global Skills Kenya (GSK), an NGO/platform supporting youth skills training, employment and international placement. Public visitors can learn about GSK, browse programmes and vacancies, donate, read news, and submit welfare concerns. Candidates can maintain profiles, consent to programme-specific sharing, apply to opportunities and communicate securely. Verified employers can manage organisations and vacancies and review applications. Authorised staff can administer programmes, content, users, employer verification, safeguarding/welfare cases, donations, consents, reports and staff permissions.

## 2. Engineering rules for the AI developer

1. Work in the actual repository. Start by reading `README.md`, all `docs/*.md`, migrations, models, policies, controllers, routes, tests, Composer manifest and workflow.
2. Establish a baseline. Record PHP, Composer, database, framework versions and exact commands/results. Run formatter, static analysis if configured, migrations, seeders and tests. Fix baseline failures before feature work.
3. Use supported, mutually compatible stable versions. Do not silently upgrade major dependencies. Record chosen versions in `composer.lock` and documentation.
4. Implement in small vertical slices. Every feature includes migration/schema, model relations/casts, request validation, service/domain logic, policy/authorization, UI/API, audit/notifications as applicable, tests, and user/admin documentation.
5. Security is default-deny. Enforce authentication, authorization and tenant/object ownership server-side on every read/write/download/export/action. Hiding a button is not authorization.
6. Do not log secrets, passwords, session/token values, anonymous follow-up credentials, payment secrets, full sensitive welfare narratives, or private contact details.
7. Never store raw card data. Verify payment provider webhooks cryptographically, use idempotency and reconcile transaction states. Do not fake a successful payment in production paths.
8. No production seed accounts, sample passwords, debug mode, development credentials, or public access to private files. Fail closed when configuration is missing.
9. Do not call a feature complete if tests are incomplete, skipped without reason, or not run. No `markTestIncomplete` for a release-critical requirement.
10. Do not overwrite or drop any non-disposable database. `migrate:fresh` is permitted only against explicitly disposable test databases.
11. Preserve backward compatibility where practical; use explicit, reversible migrations and document destructive data migrations.
12. Keep policy-driven values in configuration/database settings with validation, owner, effective date and audit history—not scattered magic numbers.
13. For external integrations requiring credentials/contract approval, implement a sandbox adapter, contract tests and setup instructions, and clearly gate production enablement until credentials and provider evidence exist.
14. If a requirement is ambiguous, implement the safest reasonable default, record the assumption in `docs/OPEN_DECISIONS.md`, and ask the product owner only where a decision blocks safe implementation. Do not stall all work over non-blocking decisions.

## 3. Required technology and deployment target

- Laravel 12 or another currently supported Laravel version explicitly approved in a version decision; PHP 8.3+ compatible with selected Laravel release.
- MySQL 8 or a specifically tested MariaDB version; CI must test the actual target engine/version.
- Blade + Livewire or a clearly justified frontend approach. Prefer Laravel-native server-rendered pages for maintainability on a cPanel VPS unless project owners explicitly select a separate SPA.
- Laravel Sanctum only where token-based API authentication is needed; web sessions should use secure Laravel session auth.
- Queue-backed mail/notifications, scheduled tasks, private storage for sensitive uploads, virus/malware scanning integration or a documented quarantine workflow.
- GitHub Actions CI; Pint; PHPUnit/Pest; static analysis (PHPStan/Larastan) and dependency audit.
- Production host: cPanel VPS, HTTPS, web document root pointed only at Laravel `public/`, least-privilege database account, private storage outside web root, backups and tested restore.

## 4. Roles and access model

Implement permissions as explicit records or a proven role/permission package, with policy enforcement in all controllers/actions/jobs. Suggested roles are starting presets, not unlimited powers:

- Public visitor: view published public content and vacancies; submit public forms and donations.
- Candidate: manage own profile and documents; view own applications/messages; grant/withdraw programme-specific consent; view own sharing history where policy permits.
- Employer owner/admin: manage only their verified organisation, organisation members and vacancies; view only applications submitted to their vacancies; message applicants through authorised threads.
- Content editor: draft/edit content and blog; publish only if explicitly granted.
- Programme officer: manage assigned programmes and related candidate workflows within scope.
- Welfare officer: access only assigned welfare cases or cases covered by explicit emergency escalation policy; update case workflow and secure notes.
- Finance officer: view donation and payment reconciliation data needed for finance; no welfare narrative access by default.
- Verification officer: review employer evidence and record outcomes; no unnecessary candidate/welfare access.
- Administrator: user and configuration administration, still audited; sensitive welfare/payment access must be separately granted where feasible.
- Super administrator: break-glass system access, MFA required, tightly audited and reviewed.

Permission requirements: deny by default; least privilege; separate permissions for view/list/create/update/delete/publish/export/assign/close/refund/verify/share; organisation and assigned-case scoping; MFA for all privileged staff; rate limits and lockouts; immutable audit events for sensitive access and changes; no user can elevate their own role; permission changes require authorised actor and audit record.

## 5. Functional scope — implement all modules

### A. Public website and CMS
- Responsive home page, About, mission/impact, programmes, how it works, candidate/employer pages, contact, donation page, safeguarding/report concern page, privacy notice, terms, accessibility statement and FAQs.
- News/blog: draft, review, publish/unpublish, schedule if supported, categories/tags, SEO metadata, featured image, author, revision history and safe rich-text sanitisation.
- Media library with MIME/size validation, image transformations, metadata stripping where appropriate, alt text and permissions.
- Admin CMS for pages, menus, reusable sections, SEO, announcements and contact details. Never allow unsanitised arbitrary scripts/HTML.
- Accessibility: WCAG 2.2 AA target; keyboard navigation, visible focus, semantic headings, labels, contrast, screen-reader status, reduced motion, error summaries and accessible consent controls.

### B. Identity, account and profile
- Candidate/employer/staff invitation or registration flows as configured; email verification; password reset; secure session management; optional MFA for candidates and mandatory MFA for privileged staff.
- Profile fields with data minimisation, profile completion, skills, education, experience, languages, location, availability, CV/document upload and visibility settings.
- Private file delivery via short-lived authorised download route; never public permanent URLs for CVs, IDs or welfare attachments.
- Account deactivation/deletion request workflow, export/access request workflow, notification preferences and session/token revocation.

### C. Programmes, opportunities and candidate placement
- Programme CRUD and publication, eligibility, requirements, dates, capacity, location, contact owner, status and application windows.
- Vacancy CRUD with draft/review/publish/close/archive lifecycle; search/filter/sort/pagination; employer verification and approval required before publication.
- Candidate application lifecycle: draft/submitted, screening, shortlisted, interview, offer, placed, rejected, withdrawn; timestamps, actor, reason codes and notifications. Validate transition graph server-side.
- Candidate can apply only when eligible/window open and cannot create duplicate active application unless configured.
- Employer can access only applications for its vacancies and only allowed candidate fields. Candidate profile sharing with a recipient must follow applicable consent/legal basis and programme rules.
- Secure messaging scoped to application/relationship, attachment access checks, report/block/moderation path, notification preferences and audit metadata. Do not expose other applicants.
- Employer verification queue: evidence checklist, representative verification, status pending/approved/rejected/suspended, reviewer notes, expiry/reverification, audit and vacancy publishing gate.
- Candidate/employer ratings or reviews only under explicit eligibility rules; prevent self-review, duplicate/fraudulent review and unauthorised disclosure; moderation, right of reply and appeal process.

### D. Welfare and safeguarding
- Anonymous and identified reporting; clear privacy/safety explanation; do not require account for anonymous report.
- Anonymous follow-up: cryptographically secure random credential shown once; store only a one-way hash; never log or email it; rate-limit attempts; use constant-time comparison; provide a secure follow-up portal for messages/status where permitted. Explain lost-credential limitations. Do not use predictable case IDs as credentials.
- Store contact details and highly sensitive case data encrypted where feasible, with strict role/assignment access and separation from general CRM/search/indexing.
- Case categories, severity, status, safeguarding triage, assignment, secure notes, events/audit timeline, attachments, referrals, resolution and restricted closure. Do not let ordinary admins or employers access case narratives by default.
- Severity/response targets configurable by approved policy; queue/scheduler notifications to primary and backup responders; escalation if acknowledgement deadline missed; holiday/on-call coverage and failure alerting; record acknowledgement/response timestamps. Never advertise 24/7 availability unless operational rota supports it.
- Safe confirmation page that does not reveal sensitive content; abuse/spam protections that do not block legitimate anonymous reports; attachment quarantine and scanning.
- Emergency copy must state that GSK reporting is not an emergency service and give locally approved emergency options. Product owner must supply/approve current country-specific contact text before production.

### E. Consent, data sharing and privacy
- Versioned privacy notices and programme notices with effective dates and content hashes/version identifiers.
- Record consent grants and withdrawals with subject, purpose, programme, notice version, timestamp, method, actor/source and relevant recipient scope. Consent is not presumed from account creation.
- A withdrawal prevents future optional processing/sharing where applicable; retain minimal evidence of the consent event if legally required. Do not imply withdrawal can reverse disclosures already lawfully completed.
- International sharing workflow: named recipient/organisation, country, purpose, data categories, programme, lawful basis, notice/consent where applicable, transfer safeguard/legal review, approving officer, date and expiry/review date. Block disclosure unless all required approvals and safeguards are present.
- Implement subject access/export, correction, objection/withdrawal and deletion/anonymisation request workflow, with identity verification proportional to risk and exceptions/legal holds.
- Retention engine by record category, retention start event, period, legal hold, purge/anonymise action, audit record and backup expiry. Numeric periods must be supplied/approved by GSK's accountable legal/privacy owner before production; do not invent statutory periods.

### F. Donations and finance
- Donation form with one-time donation and recurring option shown only for supported payment rails. Amount, currency, donor details optional where legally/provider allowed, anonymous public attribution preference, receipt preference and clear terms.
- Provider adapter boundary for M-Pesa and card processor; use provider-hosted checkout/tokenisation. No raw PAN/CVV stored or logged.
- Payment lifecycle: initiated, pending, succeeded, failed, cancelled, refunded, disputed; immutable provider reference, amount/currency, fees if available, timestamps, receipt number and reconciliation state.
- Webhooks: signature verification, replay defence, idempotency, event persistence, safe retries, reconciliation job and alerts for inconsistent states. Browser redirect alone never confirms payment.
- Receipts, finance report/export with permission, refund workflow and audit. Do not claim tax deductibility unless legally confirmed.
- Recurring mandates: production-enable only after the selected provider confirms the specific M-Pesa/card rail, mandate setup, cancellation, retry and webhook capabilities. Implement cancellation and status reconciliation; never simulate recurring collection.

### G. Communications and notifications
- Queue-backed email notifications for verification, password reset (framework-secure), application state changes, secure-message alerts, welfare escalation, donation receipt and admin events.
- SMS/WhatsApp only if provider is approved and user opted in; avoid sensitive case details in notification content.
- Templates are editable with safe placeholders and preview; retry/backoff, dead-letter/failure visibility, opt-outs and audit of delivery metadata.
- Notifications must not include anonymous report credentials or full welfare narratives.

### H. Admin operations, reports and audit
- Dashboard counts and actionable queues with permission-filtered data.
- Staff management, role/permission editor, MFA enforcement, account suspension and access reviews.
- Programme/job/application management, employer verification, CMS, welfare assignment, donation reconciliation, consent/disclosure approvals and retention job status.
- Reports for programme performance, placements, applications, employer activity and donations; CSV export with field-level permission, export audit and privacy safeguards. No bulk export of sensitive welfare cases by default.
- Audit log records actor, action, subject type/id, timestamp, outcome, request/correlation id and minimal before/after metadata; avoid sensitive values. Append-only storage or equivalent tamper-resistance for high-risk actions.

## 6. Required data model and integrity

Review and normalise the existing migrations; do not assume the current schema is complete. Include, at minimum, suitable entities for users, roles/permissions, organisations/memberships, employer verification evidence/reviews, candidate profiles/skills/education/experience/documents, programmes, vacancies, applications/status history, application messages/attachments, welfare cases/events/assignments/attachments/follow-up token hashes, privacy notice versions, consent events, disclosure approvals/logs, retention rules/legal holds, donations/payment attempts/provider events/refunds/receipts/recurring mandates, reviews/moderation, CMS pages/posts/media, notifications, audit events and system settings.

Database requirements: foreign keys, appropriate unique constraints, indexes for lookup/filter/queue jobs, enum/string constraints or validated state machine, UTC timestamps stored consistently, soft delete only where semantically appropriate, encrypted sensitive columns with key-management notes, no orphan records, transaction boundaries for multi-table state changes, idempotency keys for payment events, and reversible migrations. Add a schema diagram to `docs/ERD.md` and document data classification.

## 7. Required quality and security controls

- Form Request validation, output escaping, CSRF protection, SQL injection protection through ORM/parameterisation, safe rich-text sanitisation, SSRF protections if fetching remote media, upload MIME/content checks, size limits, private storage and malware scanning/quarantine.
- Rate limits for login, password reset, registration, anonymous report follow-up, donations and public forms; bot mitigation that preserves access to welfare reporting.
- Secure cookies, HTTPS, HSTS at deployment, CSP and security headers where compatible, no debug in production, secret management, dependency audit, no secrets committed.
- Object-level access tests for cross-user, cross-employer, cross-programme and unassigned welfare case access; staff privilege escalation tests; anonymous credential guessing tests; webhook spoof/replay tests; consent-withdrawal tests; retention/legal-hold tests.
- Backups encrypted and access-controlled, restore drill, recovery objectives, logs/metrics/error alerting, queue monitoring, scheduler monitoring and incident-response runbook.
- Privacy impact assessment and threat model for welfare data, international sharing and youth data. Confirm applicable Kenyan data protection, child/youth safeguarding, employment/recruitment and payment requirements with qualified advisers before launch.

## 8. UI/UX requirements

- Mobile-first, responsive layout; fast loading on constrained connections; clear navigation and primary calls to action.
- Distinct journeys: Candidate, Employer, Donate, Report a Concern, Programmes, News and Contact.
- Consistent design system with typography, spacing, colour contrast, form controls, alerts, loading/empty/error states, confirmation pages and breadcrumbs.
- No fake counters, fabricated impact statistics, invented testimonials, fabricated reviews or misleading placement claims. Use content placeholders until GSK provides verified facts.
- Use accessible pagination/filter states and preserve search filters; forms must explain errors and not erase user input after validation failure.
- Support appropriate locale/currency/timezone formatting; do not assume all international placements use one country or currency.

## 9. Test strategy and definition of done

For every module include unit tests, feature/integration tests, policy tests and browser/E2E tests for critical user journeys. CI must run on pull requests and main branch:

1. Composer validate and install from lock file.
2. Pint check.
3. Static analysis.
4. Dependency/security audit.
5. Migrations on a fresh MySQL database, seeders and migration rollback/reapply where safe.
6. Unit and feature tests with zero unexplained skips/incomplete tests.
7. Browser tests for candidate apply, employer verification/publish, anonymous welfare follow-up, consent-controlled sharing and successful/failing donation provider sandbox.
8. Coverage report and defined threshold (start at 70% overall, higher for authorization, payments and safeguarding domain services); avoid gaming coverage.
9. Build/deploy artifact check and secrets scan.

A story is done only when code, migration, validation, authorization, UI/API, tests, docs and telemetry are complete. Release is blocked by any critical/high security defect, any unscoped sensitive-data access, any fake payment success path, any unimplemented critical test, any migration failure, or unresolved launch-required policy value.

## 10. Required deliverables from the AI developer

1. Fully runnable repository with lock file and setup instructions.
2. Migrations, seeders (safe development-only), models, services, policies, controllers/actions, routes and frontend pages.
3. Working CI with actual green run evidence and exact commit SHA; never report green based only on workflow creation.
4. Test suite and coverage/security reports.
5. `docs/ERD.md`, `docs/API.md` (OpenAPI if practical), `docs/ROLES_AND_PERMISSIONS.md`, `docs/DATA_RETENTION.md`, `docs/SECURITY_THREAT_MODEL.md`, `docs/OPERATIONS_RUNBOOK.md`, `docs/DEPLOYMENT_CPANEL.md`, `docs/INTEGRATIONS.md`, `docs/USER_GUIDES.md`, `docs/OPEN_DECISIONS.md`.
6. `.env.example` with names only, no secrets; deployment scripts that are safe and repeatable.
7. Staging deployment, UAT checklist and rollback plan.
8. Final implementation matrix mapping each requirement to code paths, tests, status and evidence.

## 11. Phased execution order

### Phase 0 — Baseline and repair
- Run the scaffold; fix migrations, framework integration, relationships, casts, policies, seeding and tests.
- Add a CI workflow that actually runs on GitHub Actions using MySQL. Get green baseline before feature expansion.
- Output: working Laravel install, fresh migration + seed, tests green, baseline report.

### Phase 1 — Security and foundation
- Identity, verification, staff MFA, RBAC, audit, settings, queues, storage, notifications and test helpers.
- Output: tested permissions matrix and admin shell.

### Phase 2 — Public site and CMS
- Public pages, programme listing, news/blog, contact, SEO, accessibility and CMS.

### Phase 3 — Candidate/employer and placement workflows
- Profiles, organisation membership, verification, programmes, vacancies, applications, status history, secure messages, reviews and all object-level tests.

### Phase 4 — Welfare and safeguarding
- Anonymous/identified intake, follow-up portal, triage, assignment, escalation, restricted attachments, retention and safeguarding tests.

### Phase 5 — Consent, disclosures and privacy requests
- Notice versions, consent/withdrawal, international sharing approvals/logs, data requests, retention/legal hold/purge.

### Phase 6 — Donations and finance
- Sandbox M-Pesa and card adapter, webhook handling, reconciliation, receipts, refund controls and provider-gated recurring donations.

### Phase 7 — Reports, hardening and release
- Reports/exports, accessibility, security/performance tests, backups/restore, deployment rehearsal, UAT, monitoring and operational handover.

Do not skip a phase's exit criteria to claim overall completion. Parallelise only where dependencies and security boundaries allow.
