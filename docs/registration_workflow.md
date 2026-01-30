# Two-step Vehicle Registration Workflow (Summary)

## Overview
Implements: Application → Physical Verification → Issuance (RFID & Car Pass).

Key changes:
- `applications` table: new columns `registration_code` (VARCHAR) and `code_expiry` (DATETIME).
- `registrationStatus` enum extended with `expired` and `issued` values.
- Admin approval now generates a unique registration code valid for 48 hours and emails the applicant. Data is NOT moved to `vehicleowner` until actual issuance.
- Issuance (physical verification) creates `vehicleowner` and `vehicle` entries, assigns RFID and Car Pass, and marks the application as `issued`.
- Expiration script `php/cron/expire_applications.php` marks expired codes and notifies applicants.

## SQL Migration
Run: `sql/add_registration_code_columns.sql` against your database.

## New / Updated Endpoints
- POST `php/process_registration.php` (AJAX `action=approve`) — now issues a `registration_code` and `code_expiry` instead of creating `vehicleowner`.
- POST `php/review_application.php` (web form approval) — same behavior for non-AJAX approvals.
- POST `php/ajax/issue_both.php` — updated to accept `registrationCode` and will create `vehicleowner` + `vehicle` and then assign RFID & Car Pass in a single transaction.
- GET  `php/ajax/get_registration_by_code.php?code=REG...` — return applications associated with a registration code.
- Cron: `php/cron/expire_applications.php` — run regularly to expire codes and notify applicants.

## Frontend changes
- `php/rfid_management.php` now lists pending applications directly from `applications` (approved with valid code).
- `js/rfid_management.js` updated to pass `registrationCode` during issuance.
- `web-registration_v1_kaped/php/registration_applications.php` search now supports searching by `registration_code` and uses new status values.

## Scheduler / Deployment Notes
- On Windows: use Task Scheduler to run `php C:\xampp\htdocs\web-registration_v1\php\cron\expire_applications.php` every 10 minutes.
- Ensure `python/send_email.py` is configured with SMTP credentials (it is used by the existing email helper).

## Security & Notes
- Approval/Issuance routes should be protected to authorized roles only (existing session checks are kept). Review session checks if needed.
- Consider archival/deletion policy for expired applications (script currently marks as `expired` and sends email).

---
If you'd like, I can:
- Run the SQL migration locally (if you want me to apply it here),
- Run a quick test of the new endpoints with sample data,
- Add unit tests or an admin UI search filter for `expired` and `issued` statuses.
