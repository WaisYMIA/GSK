# Change requests and scope decisions

Source: GSK owner decisions in the project conversation. Dates are 13 to 14 October 2026.

| ID | Change | Decision | Effect |
|---|---|---|---|
| CR-001 | Light-touch candidate readiness and referral capability | **Approved** as a formal scope change, limited as below | Adds readiness, skills-gap and referral records to Task 4 scope. Adds a career-adviser role. Needs its own acceptance tests and privacy review before build |
| CR-002 | SMS in the first release | **Not in the first release** | First release is email only. Any SMS adapter in Task 8 is an inactive extension point with no provider, no sending, no cost. SMS needs separate approval, costing and privacy review |
| CR-003 | Vacancy publication rule | **GSK approval before publication** (supersedes SRS v1.1 DEC-03) | Direct employer publishing is prohibited. Material edits return the vacancy to review |
| CR-004 | Training cohorts, attendance, assessments, certificates, employer reviews | **Out of the first release** | No tables, keys or tests. Needs a formal change request to add |
| CR-005 | Recurring donations | **Disabled** | Needs provider lifecycle confirmation and a formal change |
| CR-006 | Payment provider | **Not chosen** | One provider must support both M-Pesa and cards for GSK, with eligibility, settlement, fees, refunds, callbacks and reconciliation confirmed first |
| CR-007 | News section in the first release | **Approved** (14 Oct 2026) | News posts use the same versioned workflow as pages, up to 3 images per post |
| CR-008 | Programme pages | **Approved**: rendered from CMS pages of kind `programme` | The `programmes` table stays for later relational use |

## CR-001 scope (approved limits)
In scope: basic readiness indicators, skills-gap notes, referral records, follow-up dates.
Out of scope: cohorts, enrolment, attendance, assessments, certificates, scoring that ranks people, automated recommendations, use of sensitive traits.

### New requirements that CR-001 creates (proposed, for review)
Privacy
- Notes are human-written, purpose-limited and visible only to the candidate's assigned adviser and authorised recruitment staff. Candidates can see what is recorded about them where lawful, and can request correction.
- A referral to an outside organisation needs the candidate's notice and consent, or another approved lawful basis, recorded against the exact recipient and data shared (fails closed).
- No free-text collection of sensitive traits. Retention follows the approved schedule (not yet legally approved).

Access control
- New proposed keys (not approved yet, see `gsk_permission_catalogue_proposed.csv`): `readiness.view`, `readiness.record`, `referrals.create`, `referrals.view`.
- Scope is the adviser's assigned candidates. Employers and partners see nothing unless a referral or application grants it.
- A career adviser must not be able to see welfare cases, documents beyond those the candidate shared, or other advisers' candidates.

Acceptance (proposed test IDs GSK-134 to GSK-141 in the register)
- Adviser sees only assigned candidates. Candidate sees own record. Employer sees none.
- Referral blocked without consent or approved basis. Referral logged with recipient, purpose and data shared.
- Readiness notes cannot be exported without the export permission. Changes are audited.

Open: who counts as a career adviser role owner (unresolved), and whether advisers need MFA (proposed: yes, as staff).
