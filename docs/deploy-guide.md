# Deploy guide: Phase 01 hosting

**Owner: M1.** This guide covers the M1 Phase 01 tasks that happen outside the code: the GitHub repo, the host, HTTPS, MySQL, outbound requests and the Safe Browsing key. Follow the steps in order. Each one ends with a check you can see for yourself.

---

## Step 1: Create the repository and protect `main` (Tue 17)

1. On GitHub, create a repository called `safecedi`. Add **all four teammates** as collaborators: *Settings → Collaborators → Add people*.
2. Push this folder from your laptop:
   ```bash
   cd safecedi
   git remote add origin https://github.com/<your-username>/safecedi.git
   git push -u origin main
   ```
3. Protect `main`: *Settings → Branches → Add branch ruleset* (or *Add rule* on older layouts), targeting `main`.
   - ✅ Require a pull request before merging, with **1 required approval**
   - ✅ Dismiss stale approvals when new commits are pushed
   - ✅ Block force pushes
   - ✅ Restrict deletions
   - ✅ Do not allow bypassing, so the rules apply to admins too

   > ⚠️ **Free GitHub accounts only enforce branch protection on *public* repositories.** You have two choices. You can make the repo public now (it must be public by 5 Dec anyway, and no secrets are in it). Or you can claim the free **GitHub Student Developer Pack**, which includes GitHub Pro, and keep it private until submission.

4. **Check:** on `main`, commit a trivial change and run `git push origin main`. GitHub must **reject** it. Then undo the test commit with `git reset --hard HEAD~1`.
5. Each member clones the repo and makes their **first commit in their own name**: they add their row to [`docs/TEAM.md`](TEAM.md) on a `docs/mN-team-row` branch and open a PR. M1 reviews and merges. **Check:** `git shortlog -sn` shows five names.

---

## Step 2: Hosting, MySQL and HTTPS (Tue 17)

### What the host must offer

Check every line **before** paying for anything or signing up:

| Requirement | Why |
|---|---|
| PHP **8.1 or newer** with `pdo_mysql`, `curl`, `openssl`, `mbstring` | The code uses PHP 8.1 features |
| MySQL 5.7+ or MariaDB 10.4+ | Schema |
| Free SSL certificate (Let's Encrypt / AutoSSL) | Gate is on HTTPS; secure cookies |
| **Outbound HTTP and HTTPS from PHP** | Plan B resolves links and calls Safe Browsing. *This is the one that kills Plan B if it's missing.* |
| `.htaccess` with `mod_rewrite`, or the ability to set the document root | Keeps `.env` and `includes/` off the web |
| No "browser check" interstitial page in front of the site | Some free hosts inject a JavaScript challenge that breaks form posts from scripts and AJAX |

Free hosting tiers often restrict outbound connections or add that challenge page. **Don't trust the sales page. Run the health check in Step 5 on day two**, and if outbound fails, switch host that same day while nothing depends on it. A school-provided server, a low-cost cPanel host, or a small VPS are all fine.

### Setup

1. **Create the database** in the hosting panel (*MySQL Databases*): database `safecedi`, plus a user with a long random password. Give that user only `SELECT, INSERT, UPDATE, DELETE` on that database (not `ALL`). Use a separate admin login to run schema changes.
2. **Load the schema**: phpMyAdmin → select the database → *Import* → `database/schema.sql`. Or from SSH:
   ```bash
   mysql -h HOST -u ADMIN_USER -p safecedi < database/schema.sql
   ```
3. **Upload the code.** The best option is SSH with `git clone`, then `git pull` to deploy. Otherwise, use FTP/SFTP to upload the whole folder. Do **not** upload your local `.env`.
4. **Document root:**
   - *Best:* point the domain's document root at the `public/` folder.
   - *If the host won't let you:* upload everything into the web root (e.g. `htdocs/` or `public_html/`). The root `.htaccess` rewrites every request into `public/`, and every private folder carries its own `Require all denied`.
   - **Check:** `https://YOUR-SITE/.env` and `https://YOUR-SITE/includes/config.php` must both return **403 or 404**, never file contents.
5. **Turn on SSL** in the panel (AutoSSL / Let's Encrypt). Wait for the certificate to issue.

---

## Step 3: Configuration and secrets (Wed 18)

The pattern: `includes/config.php` is **committed** and holds only code. All secrets live in `.env`, which is **git-ignored**. `.env.example` is committed so anyone can see which keys exist.

1. On the server, copy `.env.example` to `.env` (in the project root, next to `.env.example`, **not** inside `public/`).
2. Fill it in for production:
   ```ini
   APP_ENV=production
   APP_DEBUG=false
   APP_URL=https://YOUR-SITE
   FORCE_HTTPS=true
   DB_HOST=...   DB_PORT=3306   DB_NAME=...   DB_USER=...   DB_PASS=...
   SAFE_BROWSING_API_KEY=...       # step 4
   HEALTHCHECK_TOKEN=...           # php -r 'echo bin2hex(random_bytes(24)), PHP_EOL;'
   ```
3. Make sure `storage/logs/` is writable by PHP (chmod 755 or 775, depending on the host).
4. **Check:** from your laptop, `git status` must never list `.env`. `git log --all -- .env` must print nothing.

---

## Step 4: Get the Google Safe Browsing API key (Wed 18)

1. Go to <https://console.cloud.google.com/> → create a project named `safecedi`.
2. *APIs & Services → Library* → search **Safe Browsing API** → **Enable**.
3. *APIs & Services → Credentials → Create credentials → API key*.
4. **Restrict the key:** *API restrictions → Restrict key → Safe Browsing API only*. If your host has a fixed outbound IP address, also add it under *Application restrictions → IP addresses*.
5. Put the key **only** in the server's `.env` and your own local `.env`. Share it with teammates in person or by direct message, never in the repo, an issue, a PR or a group chat that might be screenshotted.
6. If it ever leaks: *Credentials → Regenerate key*, then update `.env`.

The Safe Browsing Lookup API is free for non-commercial use, which covers a course project. Don't call it on every keystroke: M3 caches answers in `reputation_cache`.

---

## Step 5: Confirm that everything works (Tue 17 – Wed 18)

Open:

```
https://YOUR-SITE/healthcheck.php?token=<HEALTHCHECK_TOKEN>
```

You want every line to read `PASS`:

```
[PASS] PHP version                        8.x
[PASS] Extension pdo_mysql / curl / openssl / mbstring
[PASS] APP_DEBUG off in production
[PASS] FORCE_HTTPS
[PASS] Request arrived over HTTPS
[PASS] storage/logs writable
[PASS] MySQL connection                   connected; users table has N row(s)
[PASS] Outbound HTTPS (port 443)          HTTP 204
[PASS] Outbound plain HTTP (port 80)      HTTP 200
[PASS] Safe Browsing API key              key works; test URL flagged as MALWARE
```

Then check these:

- `http://YOUR-SITE/` redirects to `https://YOUR-SITE/`
- The browser padlock shows a valid certificate on a **phone** on mobile data, not just on campus Wi-Fi

When the gate has passed, **blank `HEALTHCHECK_TOKEN`** in `.env`. The page then returns 404.

With SSH you can also run `php ops/healthcheck.php` on the server.

---

## Step 6: Create the moderator accounts (Thu 19)

Registration only ever creates **reporter** accounts. That's deliberate: nobody can sign themselves up as a moderator.

- **With SSH:**
  ```bash
  php bin/create-user.php --email=you@example.com --name="Your Name" --role=moderator
  ```
  It asks for the password with echo turned off.
- **Without SSH:** register normally on the site. Then, in phpMyAdmin, run:
  ```sql
  UPDATE users SET role = 'moderator' WHERE email = 'you@example.com';
  ```

Record test accounts (email + role, **never passwords**) for the README at the end of the project.

---

## Step 7: Smoke test the live site (Thu 19)

From a laptop:

```bash
bash tests/auth_smoke.sh https://YOUR-SITE
MOD_EMAIL=you@example.com MOD_PASSWORD='...' bash tests/auth_smoke.sh https://YOUR-SITE
```

Every line must be `PASS`. The script creates throwaway reporter accounts named `smoke-…@example.com`. Delete them afterwards if you want a clean table:

```sql
DELETE FROM users WHERE email LIKE 'smoke-%@example.com';
```

---

## Step 8: Gate rehearsal (Thu 19 Nov, 8:00 PM)

The Plan B Phase 01 gate: *every member pastes a scam message from their own phone into the live URL and gets a sensible score.*

- [ ] Every member opens the HTTPS URL on **their own phone**, logged out
- [ ] Each pastes a scam message and gets a result page (the score comes from M3's engine once `engine/evaluate.php` is merged)
- [ ] One member who didn't build auth registers, signs out and signs back in
- [ ] A reporter account is refused at `/moderate.php`, and a moderator gets in
- [ ] Nobody is driving from localhost, and the author of a path isn't the one demoing it

If anything fails, cut scope that evening and re-demo within 24 hours.
