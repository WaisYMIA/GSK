# Two-factor authentication, sessions and sign-in (Task 1, Stage 1B)

Status: written, **not executed**. See `docs/TASK1_STAGE_1B_REPORT.md` for what is unverified.

## Decisions applied
- Server-side sessions with the database driver. No Sanctum tokens. `routes/api.php` has two public routes only.
- Staff MFA through Laravel Fortify: authenticator app (TOTP) plus recovery codes, with confirmed setup and a recent password confirmation.
- Staff accounts are invitation-only. Registration is not enabled. Candidate self-registration belongs to Task 3.

## Flows
1. **Invitation:** an authorised person invites (`users.invite`), someone else records the identity check (`users.manage`), the invitee opens `/staff/invitation/{token}`, chooses a password (12 or more characters, upper and lower case, a number) and the account is created as verified and active.
2. **Sign in:** `/login`. Only active, non-deleted accounts succeed. Wrong password, unknown email and suspended account give the same failure.
3. **MFA enrolment:** staff without confirmed MFA are redirected to `/account/security` for every page except setup, sign-out, reset and verification routes.
4. **MFA challenge:** after the password, staff with confirmed MFA reach `/two-factor-challenge` and need a code or a recovery code.
5. **Staff cannot turn MFA off.** A `DELETE /user/two-factor-authentication` from a staff account returns 403 and is audited. An administrator resets MFA through `MfaAdminService` (`users.reset_mfa`, a reason, and independent approval if the target holds a privileged role). A reset revokes the person's sessions.
6. **Password reset:** the request form gives the same answer for registered and unregistered addresses. A valid token works once and expires after 60 minutes. A reset revokes every session of that user.
7. **Suspension or role change:** sessions are deleted from the `sessions` table and the remember token is rotated. A suspended account in an open session is signed out on its next web request.

## Limits (rate and abuse)
- Sign-in: 5 attempts per minute per email and address, then HTTP 429.
- MFA challenge: 5 attempts per minute per sign-in attempt, then HTTP 429.
- Invitation page: 20 views and 10 submissions per minute.

## Honest limits and open items
- **Recovery codes are encrypted at rest by Fortify, not hashed.** Your decision allowed "hashed or otherwise securely protected". Encryption means anyone with the application key and database can read unused codes. If hashing is required, Fortify's storage must be replaced. Decision needed.
- Verification and reset emails go to the configured mailer. With the framework default (`log`) nothing is delivered. No email provider is approved.
- Password confirmation from the setup page sends the browser to the confirmation screen and then to the account page, not back to setup.
- CSRF protection is Laravel's default. Feature tests skip it, so SEC-03 needs an integration test. Session fixation (SEC-04) needs a cookie-aware test. Neither is written.
- Timing differences between known and unknown addresses are not removed.
- How the very first administrator account is created is **undecided**. No seeded or hard-coded account exists. Options are in the Stage 1B report.
