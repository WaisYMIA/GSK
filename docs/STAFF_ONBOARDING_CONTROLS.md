# Staff onboarding controls (duplicate-identity prevention)

Status: proposed process control for GSK review. Software supports it but cannot replace it.

## What the software enforces (Task 1, Stage 1A)
- Staff accounts are invitation-only. There is no public staff registration.
- One `staff_profiles` row per real person, unique on `person_ref` and `work_email`. A duplicate invitation is refused.
- An invitation cannot be accepted until an **identity check** is recorded by a different person who holds `users.manage`. Nobody can record their own identity check.
- Invitation tokens are single use, expire after 72 hours, and are stored only as a SHA-256 hash.
- Independent approval compares `staff_profile_id`. A second account linked to the same profile is the same person and cannot approve.
- An approval request is blocked, not left open, if no independent approver exists or the requester has no staff profile.

## What the software cannot enforce (process owners needed)
- It cannot detect a person who deliberately creates two profiles with different identities and different work emails.
- It cannot judge whether the identity evidence is genuine.

## Proposed process controls (GSK to approve and name owners)
1. A named person keeps a staff register (one entry per person, with the staff reference) outside the platform. Every invitation must match a register entry.
2. Identity is checked against an official document, in person or by a documented remote method, by someone other than the invitee. The method is recorded in the audit event.
3. Work emails are issued by GSK and tied to one named person. Shared mailboxes are not accepted.
4. Quarterly access review: compare active staff profiles with the register, and suspend leavers the same day.
5. Any exception (for example one person needing two accounts) needs written approval from a named owner, and the second account is linked to the **same** profile.

Role owners and independent approvers are **unresolved**. Until a different authorised person is appointed, approval-dependent actions stay disabled (`GSK_APPROVALS_ENABLED=false`).
