# Third-party tools and services register

Rule: nothing that receives GSK or user data is enabled without a documented integration and a privacy review, approved by the GSK owner. Development and test tools that never see GSK data are listed separately.

## A. Development and test tools (no GSK or user data)
| Tool | Purpose | Status | CI or other implication |
|---|---|---|---|
| Composer, PHPUnit, Laravel Pint | Dependencies, tests, formatting | In use (kit) | None beyond existing |
| Playwright | Browser tests | **Proposed only** | Adds a browser install to CI and slower runs. Synthetic data only. Needs approval before it is added |
| k6 | Load testing | **Proposed only** | Run against staging with synthetic data, never production |
| axe-core | Automated accessibility checks | **Proposed only** | Complements manual keyboard and screen-reader checks. It does not replace them |
| GitHub private repository and Actions | Source control and CI | **Approved approach** | Repository stays private. No secrets or real data. Review Actions permissions |

## B. Services that may receive GSK or user data (none approved)
| Service | Purpose | Status |
|---|---|---|
| Payment provider (Pesapal, Safaricom Daraja, others) | Donations | **Not chosen.** One provider must support M-Pesa and cards. Recurring disabled |
| Postmark or Amazon SES | Transactional email | **Evaluation only. Not approved for production.** Needs DPA, region review, SPF/DKIM/DMARC plan |
| Cloudflare Turnstile | Spam protection on public forms | **Not enabled.** Needs integration and privacy review (sends visitor data to a third party) |
| Sentry or similar | Error monitoring | **Not enabled.** Needs redaction design and privacy review before any event is sent |
| SMS or WhatsApp provider | Notifications | **Not in the first release** (CR-002) |
| Analytics or tracking | Usage statistics | **None.** Default is no third-party tracking |

## C. Rules for adding an item
1. Written need and alternatives considered.
2. Data sent, where it goes, who is processor, contract and DPA, retention.
3. Failure behaviour and cost.
4. Privacy review result and owner approval, recorded in `docs/CHANGE_REQUESTS.md`.
