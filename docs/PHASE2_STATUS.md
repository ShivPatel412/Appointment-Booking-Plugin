# Phase 2 implementation status

The supplied Phase 2 brief was used as the implementation boundary. The referenced Master Implementation & Testing Tracker was not supplied, so authoritative `P2-*` identifiers and counts cannot be reported without inventing data.

| Area | Status | Primary files | Verification |
|---|---|---|---|
| Patient accounts and activation | Testing | `src/Accounts/AccountService.php`, `src/Portal/PortalFrontend.php` | Static parse; live email/authentication pending |
| Patient portal and profile | Build | `assets/portal.*`, `src/Rest/PortalApi.php` | JavaScript/static PHP checks; browser test pending |
| Patient appointments and repeat booking | Testing | `src/Rest/PortalApi.php`, `src/Domain/AppointmentService.php` | Server ownership and availability paths reviewed; live integration pending |
| Doctor portal | Build | `assets/portal.*`, `src/Rest/PortalApi.php` | Static checks; browser/role test pending |
| Clinical notes | Testing | `src/Rest/PortalApi.php`, `src/Infrastructure/Database.php` | Public/internal response shapes reviewed; live API test pending |
| Prescriptions | Testing | `src/Clinical/PrescriptionService.php`, `src/Rest/PortalApi.php` | Transaction and access-policy checks authored; database test pending |
| P2 email notifications | Build | `src/Notifications/NotificationService.php` | Secure-link content implemented; mail-provider test pending |
| Object-level security | Testing | `src/Security/AccessPolicy.php`, `tests/AccessPolicyTest.php` | Policy cases authored; WordPress REST integration pending |

## Required live security tests

- Unauthenticated portal and prescription requests return an authorization error.
- Patient A cannot request Patient B appointments or prescriptions by changing an ID.
- A patient cannot use any prescription write route.
- Doctor A cannot request or modify Doctor B appointments or prescriptions.
- An unlinked doctor-role account cannot select an arbitrary doctor ID.
- A receptionist without the clinical capability cannot create or edit prescriptions.
- Administrators retain explicitly permitted access.
- Patient responses and email messages never contain `internal_notes`.

Nothing is marked **Completed** until activation/migration, mail, browser, WordPress role, REST authorization, and MySQL integration tests pass in a real WordPress environment.
