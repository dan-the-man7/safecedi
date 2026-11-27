# Team

Each member adds **their own row** as their first commit, on a branch named `docs/mN-team-row`, under their own git name. See [CONTRIBUTING.md](../CONTRIBUTING.md#3-commits).

| Role | Member | GitHub username | Owns |
|---|---|---|---|
| M1 | | | Team Lead & Integration: repo, deploy, config/secrets, auth & sessions, nightly merge, submission |
| M2 | | | Data & Backend Core: schema, seed/corpus, db.php, queries, indexes |
| M3 | Daniel Darko | darko-jr | Signature Feature: rule engine `evaluate()`, SSRF guard, link resolver |
| M4 | | | Interface & Experience: CSS tokens, responsive shell, jQuery, empty/loading/error states |
| M5 | | | Security, QA & Evidence: threat model, validation table, CSRF/escaping, test plan, README |

## Schedule anchors

| When | What |
|---|---|
| Daily 9:00 PM from Fri 20 Nov | M1 merges approved PRs into `main` and deploys |
| Thu 19 Nov 8:00 PM | Phase 01 gate |
| Wed 25 Nov 8:00 PM | Phase 02 gate |
| Mon 30 Nov 8:00 PM | Phase 03 gate (signature feature) |
| Wed 2 Dec 12:00 noon | **Feature freeze** |
| Thu 3 Dec 8:00 PM | Phase 04 gate (clean install by a non-author) |
| Sat 5 Dec 12:00 noon | Submission |
