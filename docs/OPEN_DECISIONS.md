# GSK Open Decisions and Launch Dependencies

The six policy principles have been approved. The following operational details still require named owners and evidence before production launch; they must not be guessed by the developer.

| Decision | Required owner/evidence | Safe interim implementation |
|---|---|---|
| Retention periods by data category | GSK privacy/legal owner; documented schedule and legal-hold criteria | Retention engine implemented, destructive purge disabled until schedule approved; test in dry-run mode |
| Welfare severity and acknowledgement/response targets | Safeguarding lead; severity matrix, response targets, escalation contacts and rota | Configurable targets; non-production test schedule; do not claim 24/7 response |
| Emergency contact copy by country | Safeguarding/legal owner validates current local resources | Clearly state GSK is not an emergency service; no invented phone numbers |
| Employer verification checklist | Verification lead/legal adviser; acceptable documents, reviewer workflow, expiry/recheck rules | Employers remain unpublished until manually approved; do not auto-approve |
| Payment provider and payment rails | Finance owner; provider contract, sandbox credentials, webhook specification and recurring mandate confirmation | Sandbox adapter; live mode disabled; recurring option hidden/disabled until proven |
| International data transfers | Privacy/legal owner; recipient, country, purpose, data fields, lawful basis and safeguard assessment | Fail closed; do not disclose internationally until approval recorded |
| Applicable legal obligations | Qualified Kenyan and relevant destination-country advisers | Build configurable workflows; do not claim legal compliance without review |
| Verified public content | GSK content owner supplies mission, contact details, programme descriptions, impact data, testimonials and logos | Use labelled placeholders; never fabricate claims |
| Production operations | Hosting owner confirms backup retention, restore objectives, monitoring, queue worker, scheduler and on-call ownership | No production launch until runbook and restore/escalation drills pass |

For every decision record: accountable owner, approver, date, version, evidence/document link, review date and affected modules.
