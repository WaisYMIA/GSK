# GSK AI Developer Task Pack — Aligned to Requirements Baseline v2.0

## Mandatory instructions
Read, in this order:
1. `docs/GSK_SRS_WORKFLOWS_AND_TASKS_v2.md` (current workflow and Task 1–8 baseline)
2. `docs/AI_DEVELOPER_MASTER_SPEC.md`
3. `docs/OPEN_DECISIONS.md`
4. `docs/POLICY_DECISIONS.md`
5. `docs/ACCEPTANCE_GATES.md`

The SRS v2 baseline controls workflow behaviour and task ordering. If documents conflict, do not guess: record the conflict, follow any explicit user-approved decision, and ask only if safe implementation is blocked. Never claim a command passed unless actually run and observed exit code 0. Do not use production credentials or real personal data. Do not weaken tests to make CI green. Implement server-side authorization and default-deny access. Every task must finish with changed files, commands and exit codes, tests and counts, negative authorization checks, risks, open blockers and next task.

## Task 0 — Repository and build baseline (required before feature work)
Inspect the existing scaffold and report what is complete, incomplete, inconsistent, or not executable. Validate Composer metadata, install dependencies if the environment permits, inspect migrations and route/policy wiring, and run tests in a disposable environment. Fix baseline/bootstrap/CI defects without pretending incomplete features are implemented. Record exact commands and results. If dependencies or MySQL are unavailable, state that limitation and do not claim tests passed. Exit: reproducible baseline or a precise blocker report.

## Task 1 — Identity, RBAC, scope and audit
Implement invitation-only staff accounts, verified email, staff MFA enforcement, login throttling, password reset, session revocation, granular permissions, organisation membership, programme/partner/country scope, default-deny policies, audit events, permission revocation and time-limited break-glass access with reason and retrospective review.
Acceptance: tests cover allowed and denied access, cross-user/organisation/programme access, staff MFA, permission revocation, sensitive reads/exports, role changes, break-glass expiry and audit completeness. Permission documentation matches code.

## Task 2 — Public site and CMS
Implement mobile-first accessible public pages, programmes index/detail, news/blog, contact routes, CMS pages/posts/media, SEO metadata, version history, draft/review/publish/rollback and sanitized rich text. Do not invent testimonials, impact metrics, legal claims, visa guarantees or tax-deductibility claims.
Acceptance: drafts are not public; publishing requires permission and any required second approval; uploaded media is validated; no private records appear in public search; accessibility smoke tests pass.

## Task 3 — Candidate, employer, vacancy and recruitment
Implement candidate registration/profile/skills/documents, versioned notices/consent ledger, programme-scoped profile review, organisation and representative onboarding, employer evidence checklist and verification, vacancy lifecycle, search/filter, applications, messages/attachments, interview/offer records and candidate withdrawal.
Acceptance: unverified employers cannot publish; candidate data is shared only for a specific vacancy and approved recipient; partner and employer isolation tests pass; material vacancy edits trigger review; no bulk candidate browsing/export.

## Task 4 — Placement management and follow-up
Implement match records, candidate interest, offer verification, destination-specific readiness checklist, document/consent gates, cross-border sharing dependency, pre-departure readiness, active placement, follow-up schedule, at-risk state, escalation linkage and closure/outcome reporting.
Acceptance: placement cannot activate with required gates missing; country/employer/recipient changes trigger re-review; follow-up tasks are created; employer cannot access welfare notes; no visa/job guarantee is implied.

## Task 5 — Welfare and safeguarding
Implement authenticated and anonymous intake, high-entropy one-time follow-up credential shown once and stored only as a hash, safe follow-up portal, rate limiting, private attachments, triage severity, restricted case notes, assignment and backup owner, escalation log, follow-up, closure review and access audit. Add plain-language text that the service is not an emergency service unless GSK has separately established an appropriate emergency response.
Acceptance: no account required for anonymous reports; credential cannot be recovered from storage/logs; P1/P2 escalation is tested against configured contacts; no 24/7 claim without actual coverage; conflicted users cannot access cases; notifications contain no sensitive case detail. Production gate requires named safeguarding lead/backup, staffed hours, escalation contacts and approved response targets.

## Task 6 — Privacy, consent, retention and international sharing
Implement versioned privacy notices, purpose-bound consent and withdrawal, data-subject request workflow, retention policies, legal holds, deletion/anonymisation jobs, data-sharing requests and approvals, recipient/country/purpose/data-category register, export logs, expiry/revocation and downstream deletion confirmation.
Acceptance: sharing fails closed without active scoped approval; changes to recipient/country/purpose/data categories trigger new review; legal holds prevent deletion; jobs are idempotent; every disclosure is audited. Production gate requires legal/privacy approval of lawful bases, transfer mechanisms, retention schedule and applicable ODPC obligations.

## Task 7 — Donations, payments and finance
Implement a provider adapter, one-time donation intent, hosted checkout, verified server-to-server callbacks/webhooks, signature validation, idempotency, payment-event log, receipt generation, refund request/approval, settlement reconciliation, exception queue and finance exports. Recurring donations remain hidden/disabled unless the chosen provider/rail’s complete mandate lifecycle is verified and approved.
Acceptance: no raw card data; spoofed/replayed callbacks fail safely; amount/currency mismatch is quarantined; browser redirects cannot prove payment; receipt requires confirmed payment; refunds have independent approval; provider sandbox evidence and finance sign-off are recorded.

## Task 8 — Notifications, reporting, hardening and release
Implement queued email and approved SMS adapter, safe templates, retries/failure queue, delivery status, operational dashboards, scope-limited reports and audited CSV exports, threat model, data classification, incident response, backup/restore drill, performance baseline, WCAG review, dependency/secrets scans, upload/rate-limit tests, queue/scheduler monitoring and cPanel VPS deployment/rollback runbook.
Acceptance: sensitive data absent from messages/logs; failed jobs are visible; reports enforce scope; encrypted offsite backup restores successfully; secrets are not committed; staging/UAT, rollback, security and accessibility gates pass; no unresolved critical/high security findings.

## Global definition of done
- Code review and focused changes completed.
- Migrations work from a clean database and rollback behaviour is documented.
- Automated tests include positive and negative authorization cases.
- Exact commands, exit codes and test counts are reported.
- Configuration examples and operational documentation are updated.
- No real personal data or production secrets are used in development fixtures.
- Open decisions have owner, action and blocking status.
- No production-readiness claim until the required human sign-offs and release gates are evidenced.

## Stop conditions
Stop and escalate if implementation would enable unapproved cross-border disclosure, under-18 international recruitment without approved child-safeguarding design, unverified employer publishing, payment success without verified provider evidence, welfare access beyond need-to-know, deletion contrary to a legal hold, or production release without required operational owners.

## Required report after every task
- Implemented: exact files and behaviours.
- Commands run: command plus observed exit code.
- Tests: passed/failed/skipped/incomplete counts and reasons.
- Security/access checks: positive and negative tests.
- CI evidence: run URL/ID and commit SHA, or explicitly “not run”.
- Open decisions/blockers: owner and action.
- Next task: ID and prerequisites.

Begin with Task 0, then Task 1 only. Do not implement Tasks 2–8 in one unreviewed change. After each task, stop at the gate and provide the report for review.
