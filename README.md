# SafeCedi

Paste a suspicious SMS or WhatsApp forward and get a risk verdict, with every piece of evidence shown, backed by a crowd-reported scam database.

CS341 Web Technologies final group project: PHP, MySQL, jQuery. No framework.

> This README is a Phase 01 starter written by M1. **M5 owns the final README**: schema diagram, test accounts, accuracy table and known limitations get added before submission.

## Run it locally

You need PHP 8.1+ (with `pdo_mysql`, `curl`, `mbstring`, `openssl`) and MySQL or MariaDB.

```bash
# 1. Configuration
cp .env.example .env            # then edit the DB_* values

# 2. Database
mysql -u root -p -e "CREATE DATABASE safecedi CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
mysql -u root -p safecedi < database/schema.sql

# 3. Check the setup
php ops/healthcheck.php

# 4. Serve
php -S localhost:8000 -t public
```

Open <http://localhost:8000>. To make a moderator:

```bash
php bin/create-user.php --email=you@example.com --name="Your Name" --role=moderator
```

To run the auth smoke test against a running server:

```bash
bash tests/auth_smoke.sh http://localhost:8000
```

## Layout

```
public/        the ONLY folder served to the web (document root)
includes/      bootstrap, config, errors, db, security, session, auth
templates/     shared header/footer
database/      schema.sql (+ seed.sql from M2)
engine/        M3's rule engine (evaluate(), SSRF guard)
bin/ ops/      command-line tools: create-user, healthcheck
tests/         smoke tests and the corpus harness
docs/          deploy guide, auth explainer, integration contract, team
storage/logs/  application log (git-ignored)
```

## Docs

- [Working in this repo](CONTRIBUTING.md): branch names, PR rules, commit rules
- [Deploy guide](docs/deploy-guide.md): hosting, HTTPS, MySQL, secrets, Safe Browsing key
- [How auth works](docs/auth.md): registration, login, sessions and roles, with viva answers
- [Integration contract](docs/integration-contract.md): which slice provides which function
- [Team](docs/TEAM.md)
