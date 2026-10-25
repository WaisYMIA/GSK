# GSK permission catalogue

Source of truth: `database/data/permissions.php`. A test fails if this document and the catalogue drift apart.

**Status:** 32 keys (21 from Task 1, 11 from Task 2), seeded as reference data only. **No role-to-permission mapping is defined or seeded.** Mappings need GSK review and approval before any role is created in production.
The Task 2 keys `content.sensitive_claims.approve` and `contact.messages.view` are proposals and need explicit approval.

Deferred modules (training cohorts, attendance, assessments, certificates, employer reviews) have no keys. `support.impersonate` is not included.

Authorisation is capability plus scope. A global assignment satisfies any scope. A scoped assignment (organisation, programme, partner, case) satisfies only the identical scope. An unscoped request needs a global assignment. Explicit deny overrides win. Allow overrides must expire. Break-glass grants only keys on the configured allow-list.

| Key | Module | Meaning |
|---|---|---|
| `organisations.view` | organisations | View organisations within scope |
| `organisations.members.manage` | organisations | Manage organisation members |
| `partners.scopes.manage` | organisations | Set partner organisation, programme and function scope |
| `reports.view` | reporting | View reports within scope |
| `reports.export` | reporting | Export reports (disabled by default, audited) |
| `welfare.cases.view_assigned` | welfare | View welfare cases assigned to the user (case scope) |
| `welfare.cases.view_all_authorised` | welfare | Safeguarding oversight of all authorised cases (every read audited) |
| `users.view` | identity | View user accounts |
| `users.manage` | identity | Manage user accounts and record staff identity checks |
| `users.invite` | identity | Invite a staff account |
| `users.suspend` | identity | Suspend and reactivate accounts |
| `users.revoke_sessions` | identity | Revoke a user's sessions |
| `users.reset_mfa` | identity | Reset another user's MFA (audited; privileged accounts need independent approval) |
| `roles.view` | identity | View roles |
| `roles.manage` | identity | Define roles and role permissions |
| `roles.assign` | identity | Assign and revoke roles within delegated scope |
| `roles.approve_privileged_grant` | identity | Independently approve a privileged role grant |
| `access.break_glass.activate` | access | Activate time-limited emergency access |
| `access.break_glass.review` | access | Review break-glass use afterwards |
| `audit.view` | audit | View audit events within scope |
| `settings.manage` | settings | Manage settings (secrets never shown after entry) |
| `content.pages.view` | content | View CMS pages in the staff area |
| `content.pages.create` | content | Create CMS pages |
| `content.pages.edit` | content | Edit CMS pages (creates a new draft version) |
| `content.pages.publish` | content | Publish and archive CMS pages (never your own draft) |
| `content.posts.view` | content | View news posts in the staff area |
| `content.posts.create` | content | Create news posts |
| `content.posts.edit` | content | Edit news posts (creates a new draft version) |
| `content.posts.publish` | content | Publish and archive news posts (never your own draft) |
| `content.posts.manage_media` | content | Upload and retire media |
| `content.sensitive_claims.approve` | content | Independently approve privacy, safeguarding, donation and legal content |
| `contact.messages.view` | content | View public contact messages (staff only) |
