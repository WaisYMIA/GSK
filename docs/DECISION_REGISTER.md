# Decision register (visible items)

Last updated 14 October 2026. "Unresolved" means GSK has not decided. The developer must not decide these.

## Role owners and independent approvers
All named roles: **UNRESOLVED**. Walter Jungah is the interim named contact only (earlier instruction), pending the owner's confirmation. Not an independent approver.
No independent approver is appointed. Therefore these stay disabled: employer verification second approval, flagged-vacancy second approval, refunds, privileged role grants, international sharing approvals, welfare P1/P2 closure, break-glass review, bulk export, retention exceptions, sensitive-claim publication, candidate eligibility exceptions. Production switch: `GSK_APPROVALS_ENABLED=false`.
Role-to-permission mappings: **not approved**, not seeded.

## Six policy decisions
| # | Decision | Status |
|---|---|---|
| 1 | Anonymous welfare follow-up | Decided: two-way, one-time credential, hash only, no recovery. Open: portal design, message retention, rate limits |
| 2 | Retention periods | Provisional only. Not legally approved. Purge stays disabled |
| 3 | Recurring payments | Disabled until a provider confirms the lifecycle |
| 4 | Employer verification evidence and renewal | Unresolved |
| 5 | International sharing: recipients, countries, safeguards | Unresolved. Fails closed |
| 6 | Urgent welfare escalation: rota, hours, contacts | Unresolved. No 24/7 claim |

## Other open items
- Welfare categories: to be proposed at the Task 5 design review.
- VPS inspection and Laravel version: `docs/HOSTING_AND_LARAVEL_VERSION_CHECK.md`.
- Payment provider: not chosen (`docs/CHANGE_REQUESTS.md` CR-006).
- Third-party services: none approved (`docs/THIRD_PARTY_SERVICES.md`).
- Minors in international recruitment: disabled until a child-safeguarding process is approved.
- Break-glass alert recipients and next-day reviewer: unresolved.

## Task 2 decisions (14 October 2026)
- **Task 2 authorised** on branch `task2/public-site-cms`. Not merged. Unexecuted until CI runs.
- **Content approver and sensitive-area approver:** Walter Jungah (Admin), confirmed by the owner for these two duties. Role owners for everything else remain unresolved. This is implemented as permissions (`content.*.publish`, `content.sensitive_claims.approve`) on his account, not as a hard-coded name, and needs an approved role mapping.
  - Independence still applies. Walter cannot publish content he wrote, and cannot approve a sensitive request he made. The smallest working set is one drafting person plus Walter: the editor drafts and requests approval, Walter approves and publishes.
  - If Walter drafts sensitive content himself, a different person must publish it and a different person must approve it.
- **Contact emails:** each staff member who holds `contact.messages.view` is notified, plus a copy to the shared mailbox. This is narrower than "each staff": notifying every staff member would put every contact message in every inbox. Decision needed if the owner wants all staff.
- **Shared mailbox address:** UNCONFIRMED. The owner wrote `info@gsk.co,ke` (comma). Likely `info@gsk.co.ke`. Set `GSK_CONTACT_POOL_EMAIL` only after confirming.
- **Email content:** by default the notice carries no message text, only a link. `GSK_CONTACT_EMAIL_INCLUDES_MESSAGE=true` adds name, email and message to the email and to the shared mailbox. Needs the owner's decision (personal data in email, shared mailbox access).
- **Email provider:** none approved. The default mailer logs, so nothing is delivered until one is approved.
- **Images:** uploads are re-encoded with PHP GD and refused if GD is missing. The VPS has not been checked.
