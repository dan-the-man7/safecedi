# Working in this repository

Owner of these rules: **M1 (Team Lead & Integration)**. Agreed by the team on Tue 17 Nov. Nobody changes them mid-project.

## 1. `main` is protected

- Nobody pushes to `main` directly, the lead included.
- Every change arrives through a pull request that **one other member** has approved.
- **No self-merges** from Phase 02 (Fri 20 Nov) onward.
- `main` must always deploy. If it breaks, fixing it beats every other task. That includes before anyone sleeps on integration night.

## 2. Branch names

```
<type>/<member>-<short-description>
```

| type    | use for                                   | example                        |
|---------|-------------------------------------------|--------------------------------|
| `feat`  | new behaviour                             | `feat/m3-language-rules`       |
| `fix`   | a bug                                     | `fix/m4-nav-overflow-390px`    |
| `db`    | schema, seed or migration changes         | `db/m2-rules-table`            |
| `sec`   | security hardening                        | `sec/m5-csrf-helper-docs`      |
| `test`  | tests, the corpus harness, test plan      | `test/m5-corpus-harness`       |
| `docs`  | documentation only                        | `docs/m1-deploy-guide`         |
| `chore` | tooling, config, housekeeping             | `chore/m1-gitignore`           |

`<member>` is your role code: `m1` … `m5`. Use lowercase with hyphens, and keep it short. Delete the branch after it merges.

## 3. Commits

- **Commit in your own name.** The marker reads `git log`. First, set your name and email once:
  ```bash
  git config user.name  "Your Real Name"
  git config user.email "the-email-on-your-github-account@example.com"
  ```
- Commit small and often, in every phase. Write the message in the imperative: `Add lockout after five failed logins`, not `stuff`.
- **Never commit `.env`**, API keys, database dumps or real people's phone numbers. Run `git status` before every commit. If a secret is ever committed, tell M1 at once: the key gets **rotated**, because deleting the file does not remove it from history.

## 4. Daily flow

```bash
git switch main && git pull
git switch -c feat/m3-language-rules
# ... work, commit ...
git push -u origin feat/m3-language-rules
# open a PR on GitHub, request a reviewer, fill in the template
```

- Keep pull requests small enough to review in ten minutes.
- From Fri 20 Nov, **M1 merges approved PRs at 9:00 PM every night**, then deploys `main` and checks the live site.

## 5. Code rules every PR is reviewed against

These are the engineering spine from the build plan:

1. Passwords only through `password_hash()` / `password_verify()`.
2. Every protected page calls `require_login()` or `require_role(...)` **on the page itself**.
3. Every query is a prepared statement. No string-concatenated SQL, ever.
4. Every state-changing form has `<?= csrf_field() ?>`, and its handler calls `csrf_verify()` before writing anything.
5. Every client-side validation rule is repeated on the server.
6. Every user-supplied value is printed through `e()`.
7. Secrets come from `config('KEY')`, never from a literal in code.
8. Errors go to `app_log()`. Never `var_dump`/`print_r`/`die($e->getMessage())` in committed code.
9. Responsive at 390px, checked on a real phone.

## 6. Feature freeze

**Wednesday 2 December, 12:00 noon.** After that, bug fixes only.
