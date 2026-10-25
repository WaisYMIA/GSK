# Task 2 report: public website and CMS

Branch: `task2/public-site-cms`, built on `task1/identity-access-1b`. Not merged. Written 14 October 2026. Authorised by the GSK owner on 14 October 2026.

## Evidence status (read this first)
**Nothing in this branch has been executed.** No PHP, Composer, MySQL, migration, test or CI run was available. All 167 test methods in the repository are unrun. No commit SHA or CI run exists. Task 2 also sits on Task 1, which is itself unrun and depends on Laravel Fortify, which was never installed here. Expect first-run failures.
A static check (`scripts/php-static-check.py`, not a linter) found no unbalanced brackets and no missing imports in the new PHP files other than classes the framework supplies.

## Owner decisions applied
- Programme pages are CMS pages of kind `programme`. News section is in the first release.
- Content approver and sensitive-area approver: Walter Jungah (permission-based, not hard-coded). Independence still applies (see `docs/DECISION_REGISTER.md`).
- Contact emails go to staff holding `contact.messages.view` plus a copy to a shared mailbox. The mailbox address is unconfirmed. No email provider is approved.

## Delivered
- **Schema:** pages, posts, immutable content versions, media, contact messages (`2026_10_15_000001_create_cms_tables`).
- **Content as blocks, never HTML:** paragraph, heading, list, quote, callout, link, image. A plain editor syntax is parsed, validated and escaped on output. Links allow http, https, mailto and site-relative only.
- **Workflow service:** draft, review, publish, restore, archive. Every edit is a new immutable version. The public site reads only the published version. Nobody publishes content they wrote (judged by person, not account). Sensitive areas need an independent approval that fails closed. Slugs are reserved-checked and immutable.
- **Media:** re-encoded with PHP GD (drops photo metadata), refused if GD is missing, generated file names, alt text, source and rights confirmation required, people-consent flag.
- **Public site:** home, CMS pages by address, programmes, news with categories and pages, opportunities (only approved, published, open vacancies from verified employers), contact form (honeypot, rate limit, no IP stored), sitemap, robots. Private areas are `noindex`.
- **Staff screens:** content list, editor, workflow buttons, history and restore, preview (signed in, noindex), media library, contact messages (reads audited), approvals inbox.
- **Permissions:** catalogue grows from 21 to 32 keys (`docs/PERMISSIONS.md`). Two keys are proposals.
- **CI:** `gd` added to both workflows. Bootstrap runs `storage:link`.

## Tests
167 methods in 20 files. Task 2 added 66 in `tests/Feature/Content`.
Register coverage: 19 of 22 Task 2 rows had a test method before the last additions; GSK-059 is merged into GSK-148, and SEC-02 now has a test. GSK-038 (accessibility smoke test) is **not covered**: it needs axe-core or a manual keyboard and screen-reader check, neither approved or run.
Placeholders still incomplete (3, belonging to Tasks 3, 6, 7).

## Known gaps and risks
1. **No rich editor.** Staff write content in a simple text syntax (help is shown on the form). A visual block editor, image alignment preview and mobile preview are not built. Decision: is the text editor acceptable for the first release?
2. **No scheduled publishing** (cron not verified).
3. **Image processing depends on GD** on the VPS (unchecked). Without it uploads are refused.
4. **Placeholder content only.** No privacy, terms, safeguarding or donation text is supplied. The footer links to pages that return 404 until real, approved content is published.
5. **Pages cannot be un-archived,** and slugs cannot be changed. Both are deliberate simplifications.
6. **Opportunity pages** show vacancies but nobody can publish one until Task 3 exists. Candidate applications are not built.
7. **Email is not delivered** until a provider is approved. Contact messages are stored and visible in the staff area regardless.
8. **Approval inbox** lists requests but there is no notification to approvers yet.
9. **Accessibility** is a target. Skip link, landmarks, labels, error summaries and focus styles are built in, nothing is verified. Colour contrast has not been measured.
10. **Unverified framework assumptions** from Stage 1B still apply (Fortify names and behaviour). New ones here: `Notification::assertSentOnDemand`, replacing the notification channel manager in a test, `UploadedFile::fake()->image()` needing GD, and `paginate()->through()`.

## Decisions needed
1. Is the text-based editor acceptable, or should a visual editor be built (more code, more scope)?
2. Confirm the shared mailbox address and whether emails may include message text.
3. Who else besides Walter Jungah drafts content? Independence needs at least one other person.
4. VPS image processing check (see the to-do list).
5. Real copy, logo, brand colours and photos with confirmed rights and subject consent.
6. Role-to-permission mappings (still unapproved).

## Defects found by reading the code (not by running it)
A self-review before any run found and fixed two defects. Both have regression tests. This shows the code is likely to have more defects that only a real run will find.
1. **Approval action types were looked up with `config('gsk.approvals.actions.role.privileged_grant')`.** Laravel's dot notation cannot reach array keys that contain dots, so every approval request would have thrown "Unknown approval action type". Fixed in `ApprovalService` (on the Task 1A branch, then rebased up) and in the approval inbox. Test: `every_configured_action_type_resolves_to_a_permission_key`.
2. **A partial content edit reset fields.** Saving a page edit without a `kind`, `summary` or search fields would have turned a programme page into a normal page and cleared its summary. Fixed in `ContentWorkflowService::saveDraft`. Test: `a_partial_edit_keeps_the_kind_summary_and_search_fields`.
