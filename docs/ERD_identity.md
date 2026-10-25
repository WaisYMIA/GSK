# Identity and access: column-level design (Task 1, Stage 1A)

Generated from the migrations in this branch (except the hand-written notes marked below), so it matches them. **Not approved.** GSK must review this column-level design before the migrations are accepted. Nothing here has been run against a database.

Design points already approved: staff identity is one `staff_profiles` row per real person; approval independence compares that row, not the account; `account_type` is a coarse class and grants no privileges; audit is append-only in the application, with database-level protection described in `docs/AUDIT_PROTECTION.md`; MFA columns are added by Laravel Fortify in Stage 1B.

Data class and retention: these are security records. Proposed retention follows the audit-log schedule (12 months online, longer on hold) and is **not legally approved**. Sensitive content is never stored in `audit_events.metadata`.

## `users` (hand-written note: framework table plus `2026_10_13_000000_prepare_users_for_gsk`)

The framework (or the migration's fallback) provides id, name, email (unique), email_verified_at, password, remember_token, created_at, updated_at. The prepare migration adds only the columns that are missing:

| Column | Type | Constraints |
|---|---|---|
| account_type | string | default candidate, indexed. Coarse class (candidate, employer, partner, staff). Grants no privileges |
| status | string | default active, indexed (active, suspended, ...) |
| last_login_at | timestamp | nullable |
| invited_at | timestamp | nullable |
| suspended_at | timestamp | nullable |
| deleted_at | timestamp | nullable (soft delete) |
| staff_profile_id | bigint | nullable, FK -> staff_profiles, set null on delete (added by the staff identity migration). Not mass assignable |

## `organisations` (created by `2026_10_13_000001_create_gsk_core_tables`)

| Column | Type | Constraints |
|---|---|---|
| id | bigint PK | auto |
| owner_user_id | bigint | on delete restrict, FK -> users |
| legal_name | string |  |
| registration_number | string | nullable, indexed |
| country_code | string(2) | default KE |
| verification_status | string | indexed, default pending |
| verification_notes | text | nullable |
| verified_at | timestamp | nullable |
| created_at | timestamp | nullable |
| updated_at | timestamp | nullable |
| deleted_at | timestamp | nullable |

## `staff_profiles` (created by `2026_10_14_000001_create_staff_identity_tables`)

| Column | Type | Constraints |
|---|---|---|
| id | bigint PK | auto |
| person_ref | string | unique |
| full_name | string |  |
| work_email | string | unique |
| status | string | indexed, default invited |
| identity_checked_by | bigint | nullable, on delete set null, FK -> users |
| created_at | timestamp | nullable |
| updated_at | timestamp | nullable |

## `staff_invitations` (created by `2026_10_14_000001_create_staff_identity_tables`)

| Column | Type | Constraints |
|---|---|---|
| id | bigint PK | auto |
| staff_profile_id | bigint | on delete restrict, FK -> staff_profiles |
| token_hash | string(64) | unique |
| expires_at | timestamp |  |
| accepted_at | timestamp | nullable |
| invited_by | bigint | nullable, on delete set null, FK -> users |
| created_at | timestamp | nullable |
| updated_at | timestamp | nullable |

## `roles` (created by `2026_10_14_000002_create_access_control_tables`)

| Column | Type | Constraints |
|---|---|---|
| id | bigint PK | auto |
| key | string | unique |
| name | string |  |
| description | text | nullable |
| is_system | boolean | default false |
| is_privileged | boolean | default false |
| requires_staff_profile | boolean | default true |
| created_at | timestamp | nullable |
| updated_at | timestamp | nullable |

## `permissions` (created by `2026_10_14_000002_create_access_control_tables`)

| Column | Type | Constraints |
|---|---|---|
| id | bigint PK | auto |
| key | string | unique |
| module | string | indexed |
| description | string |  |
| status | string | indexed, default active |

## `role_permissions` (created by `2026_10_14_000002_create_access_control_tables`)

| Column | Type | Constraints |
|---|---|---|
| id | bigint PK | auto |
| role_id | bigint | on delete cascade, FK -> roles |
| permission_id | bigint | on delete cascade, FK -> permissions |
| (constraint) | unique | ['role_id, permission_id'] |

## `role_assignments` (created by `2026_10_14_000002_create_access_control_tables`)

| Column | Type | Constraints |
|---|---|---|
| id | bigint PK | auto |
| user_id | bigint | on delete restrict, FK -> users |
| role_id | bigint | on delete restrict, FK -> roles |
| scope_type | string(32) | default global |
| granted_by | bigint | nullable, on delete set null, FK -> users |
| reason | text | nullable |
| expires_at | timestamp | nullable |
| revoked_at | timestamp | nullable |
| revoked_by | bigint | nullable, on delete set null, FK -> users |
| created_at | timestamp | nullable |
| updated_at | timestamp | nullable |
| (constraint) | index | ['user_id, revoked_at'] |
| (constraint) | index | ['scope_type, scope_id'] |

## `permission_overrides` (created by `2026_10_14_000002_create_access_control_tables`)

| Column | Type | Constraints |
|---|---|---|
| id | bigint PK | auto |
| user_id | bigint | on delete restrict, FK -> users |
| permission_id | bigint | on delete restrict, FK -> permissions |
| effect | string(8) |  |
| scope_id | unsignedBigInteger | nullable |
| reason | text |  |
| granted_by | bigint | nullable, on delete set null, FK -> users |
| expires_at | timestamp | nullable |
| revoked_at | timestamp | nullable |
| created_at | timestamp | nullable |
| updated_at | timestamp | nullable |
| (constraint) | index | ['user_id, revoked_at'] |

## `organisation_memberships` (created by `2026_10_14_000003_create_membership_scope_approval_tables`)

| Column | Type | Constraints |
|---|---|---|
| id | bigint PK | auto |
| organisation_id | bigint | on delete restrict, FK -> organisations |
| user_id | bigint | on delete restrict, FK -> users |
| membership_role | string | default member |
| approved_at | timestamp | nullable |
| created_at | timestamp | nullable |
| updated_at | timestamp | nullable |
| (constraint) | unique | ['organisation_id, user_id'] |

## `partner_programme_scopes` (created by `2026_10_14_000003_create_membership_scope_approval_tables`)

| Column | Type | Constraints |
|---|---|---|
| id | bigint PK | auto |
| organisation_id | bigint | on delete restrict, FK -> organisations |
| programme_id | bigint | on delete restrict, FK -> programmes |
| function | string(64) |  |
| approved_by | bigint | nullable, on delete set null, FK -> users |
| starts_at | timestamp | nullable |
| expires_at | timestamp | nullable |
| revoked_at | timestamp | nullable |
| created_at | timestamp | nullable |
| updated_at | timestamp | nullable |
| (constraint) | index | ['organisation_id, programme_id'] |

## `approval_requests` (created by `2026_10_14_000003_create_membership_scope_approval_tables`)

| Column | Type | Constraints |
|---|---|---|
| id | bigint PK | auto |
| action_type | string | indexed |
| subject_type | string | nullable |
| subject_id | unsignedBigInteger | nullable |
| requested_by | bigint | on delete restrict, FK -> users |
| requester_staff_profile_id | bigint | nullable, on delete restrict, FK -> staff_profiles |
| status | string | indexed, default pending |
| payload | json | nullable |
| expires_at | timestamp | nullable |
| decided_at | timestamp | nullable |
| consumed_at | timestamp | nullable |
| created_at | timestamp | nullable |
| updated_at | timestamp | nullable |

## `approval_decisions` (created by `2026_10_14_000003_create_membership_scope_approval_tables`)

| Column | Type | Constraints |
|---|---|---|
| id | bigint PK | auto |
| approval_request_id | bigint | on delete cascade, FK -> approval_requests |
| decided_by | bigint | on delete restrict, FK -> users |
| decider_staff_profile_id | bigint | on delete restrict, FK -> staff_profiles |
| decision | string(8) |  |
| created_at | timestamp | nullable |
| updated_at | timestamp | nullable |
| (constraint) | unique | ['approval_request_id, decider_staff_profile_id'] |

## `break_glass_grants` (created by `2026_10_14_000003_create_membership_scope_approval_tables`)

| Column | Type | Constraints |
|---|---|---|
| id | bigint PK | auto |
| user_id | bigint | on delete restrict, FK -> users |
| staff_profile_id | bigint | on delete restrict, FK -> staff_profiles |
| reason | text |  |
| permission_keys | json |  |
| activated_at | timestamp |  |
| expires_at | timestamp |  |
| ended_at | timestamp | nullable |
| review_status | string | indexed, default pending |
| reviewer_staff_profile_id | bigint | nullable, on delete set null, FK -> staff_profiles |
| reviewed_at | timestamp | nullable |
| review_notes | text | nullable |
| created_at | timestamp | nullable |
| updated_at | timestamp | nullable |

## `audit_events` (altered by `2026_10_14_000004_extend_audit_events`)

| Column | Type | Constraints |
|---|---|---|
| actor_staff_profile_id | unsignedBigInteger | nullable |
| outcome | string(16) | indexed, default success |
| reason | text | nullable |
| scope_type | string(32) | nullable |
| scope_id | string | nullable |

Existing columns (from `2026_10_13_000003_create_welfare_consent_and_audit`, hand-written note): id, actor_user_id (nullable FK users), action (indexed), subject_type, subject_id, request_id (indexed), ip_address (not written by the logger), metadata (json), created_at.


## `users` two-factor columns (hand-written note, Stage 1B migration `2026_10_14_000005_add_two_factor_columns_to_users`)

| Column | Type | Constraints |
|---|---|---|
| two_factor_secret | text | nullable. Encrypted by Laravel Fortify |
| two_factor_recovery_codes | text | nullable. Encrypted (not hashed) by Laravel Fortify. Each code works once |
| two_factor_confirmed_at | timestamp | nullable. Null means MFA is not confirmed. Staff with null are held at the setup page |
