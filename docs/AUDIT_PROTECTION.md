# Audit trail protection

Status: partial. Do not describe the audit trail as tamper-proof.

## Implemented (application level)
- `App\Models\AuditEvent` refuses `update` and `delete` through Eloquent (tests SEC-12).
- `AuditLogger` drops metadata keys that look sensitive (password, token, secret, recovery, credential, narrative, description, card, otp) and truncates long values.
- IP addresses are not written. This protects anonymous reporters.

## Not implemented (needs the hosting decision)
- Raw SQL and the query builder can still change or delete rows. The model guard does not stop that.
- No database trigger is created. Triggers may need privileges, or a server setting, that a cPanel account may not allow.

## Operator controls to apply on staging and production (documented, not yet verified)
1. Run migrations with a separate migration user that has DDL rights.
2. Run the web application with a user that has only `INSERT` and `SELECT` on `audit_events`. Grant `SELECT, INSERT, UPDATE, DELETE` on other tables as the app needs.
3. Keep database backups off-server and encrypted so that a deleted row can be recovered.
4. Restrict who can log in to the database host. Review database users each quarter.
5. Record the actual grants in the deployment runbook and capture `SHOW GRANTS` output as evidence.

Until step 2 is proven on the real host, the honest statement is: audit events are append-only at application level, and database-level protection is pending.
