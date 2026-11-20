# M1 Phase 01 checklist (Mon 16 – Thu 19 Nov)

Plan B (SafeCedi) M1 tasks, taken from the build plan, with where each one lives.

| Due | Task | Status | Where |
|---|---|---|---|
| Tue 17 | Repo created, `main` protected, branch naming agreed, everyone pushes a first commit | Code side done. **GitHub steps are manual** | `CONTRIBUTING.md`, `.github/pull_request_template.md`, `docs/TEAM.md`, deploy guide step 1 |
| Tue 17 | Hosting and MySQL provisioned, HTTPS confirmed, outbound requests confirmed from the host | Tooling done. **Provisioning is manual** | `public/healthcheck.php`, `ops/healthcheck.php`, `.htaccess`, deploy guide steps 2 and 5 |
| Wed 18 | `config.php` pattern with `.env.example`; Safe Browsing key obtained and kept out of git | Done. **Key creation is manual** | `includes/config.php`, `.env.example`, `.gitignore`, deploy guide steps 3–4 |
| Thu 19 | Auth with `password_hash`; anonymous submission allowed; accounts for reporters and moderators | Done and tested (31/31 smoke checks) | `includes/auth.php`, `includes/session.php`, `public/{register,login,logout,account,moderate,check}.php`, `bin/create-user.php`, `docs/auth.md` |
| Thu 19 | Chair the 8:00 PM gate demo | — | deploy guide step 8 |

## Handed to other members

- **M2**: `database/schema.sql` already contains `users`. Add the other eleven tables around it. `includes/db.php` is a minimal version for you to extend; keep the `db()` signature.
- **M3**: drop `engine/evaluate.php` in place and `check.php` starts showing scores. The contract is in `docs/integration-contract.md`.
- **M4**: `public/assets/css/app.css` is a placeholder; replace it with the token file.
- **M5**: `e()`, `csrf_field()`, `csrf_verify()` in `includes/security.php` are yours to review and harden. `tests/auth_smoke.sh` can go into the test plan.
