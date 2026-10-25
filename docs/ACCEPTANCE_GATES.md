# Release acceptance gates

- [ ] `composer install` succeeds on the selected supported PHP/Laravel versions.
- [ ] `php artisan migrate:fresh --seed` succeeds against disposable MySQL/MariaDB; migration rollback is checked.
- [ ] `php artisan test` passes; no incomplete critical safeguarding, payment, consent or authorization tests remain.
- [ ] All private resources enforce object-level policies; negative tests cover cross-user and cross-organisation access.
- [ ] Staff permissions are explicit and default-deny; privileged roles require MFA.
- [ ] Anonymous reports work without account; follow-up tokens are random, hashed at rest, rate-limited and never logged.
- [ ] Urgent case escalation has named coverage and tested primary/backup routes.
- [ ] One-time payments reconcile via authenticated/idempotent webhooks; recurring only enabled after provider capability is evidenced.
- [ ] International disclosures are blocked unless required approvals and legal basis are present; all disclosures are logged.
- [ ] Retention/deletion schedule is approved and jobs are tested, including legal hold and backup lifecycle.
- [ ] OWASP security review, dependency audit, accessibility review, performance/load test and backup restore drill pass.
- [ ] cPanel document root is `/public`; `.env`, source, logs, backups and private uploads are not web-accessible.
- [ ] Production `APP_DEBUG=false`, HTTPS enforced, secrets stored outside source control, least-privilege DB user and alerting configured.
