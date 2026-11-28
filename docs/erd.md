# SafeCedi, Entity Relationship Diagram

OWNER: M2 (Data & Backend Core)

Matches `database/schema.sql`. GitHub renders the diagram below
automatically.

```mermaid
erDiagram
    users ||--o{ submissions : "submits (optional)"
    users ||--o{ reports : "files (optional)"
    users ||--o{ report_votes : "casts"
    users ||--o{ allowlist : "adds"
    users ||--o{ feedback : "gives (optional)"

    submissions ||--o{ extracted_urls : "contains"
    submissions ||--o{ verdicts : "scored into"

    extracted_urls ||--o{ url_resolutions : "redirects through"

    verdicts ||--o{ verdict_signals : "evidenced by"
    verdicts ||--o{ feedback : "challenged by"

    rules ||--o{ verdict_signals : "fires as"

    reports ||--o{ report_votes : "corroborated by"

    users {
        int id PK
        varchar email UK
        varchar display_name
        varchar password_hash
        enum role "reporter, moderator"
        tinyint is_active
        tinyint failed_login_count
        datetime locked_until
        datetime last_login_at
        datetime created_at
        datetime updated_at
    }

    submissions {
        int id PK
        int user_id FK "null, anonymous allowed"
        text raw_text
        varchar sender_number
        char ip_hash
        datetime submitted_at "indexed"
    }

    extracted_urls {
        int id PK
        int submission_id FK
        varchar raw_url
        varchar normalised_url
        varchar host "indexed"
        datetime created_at
    }

    url_resolutions {
        int id PK
        int url_id FK
        tinyint hop_number
        varchar hop_url
        varchar hop_host "indexed"
        varchar resolved_ip
        smallint status_code
        tinyint refused
        varchar refused_reason
        datetime resolved_at
    }

    rules {
        int id PK
        varchar rule_code UK
        enum family "language, sender, link, reputation"
        varchar pattern
        enum pattern_type "keyword, regex, flag"
        decimal weight "signed"
        tinyint active
        varchar description
    }

    verdicts {
        int id PK
        int submission_id FK
        decimal score
        enum band "looks_safe, unclear, suspicious, dangerous"
        varchar engine_version
        datetime created_at
    }

    verdict_signals {
        int id PK
        int verdict_id FK
        int rule_id FK "indexed"
        decimal weight_contributed
        varchar matched_text
    }

    reports {
        int id PK
        int user_id FK "null"
        enum report_type "number, host"
        varchar reported_value "indexed"
        varchar note
        datetime created_at
    }

    report_votes {
        int id PK
        int report_id FK
        int user_id FK
        tinyint agrees
        datetime created_at
    }

    allowlist {
        int id PK
        varchar host UK
        varchar reason
        int added_by FK "null"
        datetime created_at
    }

    reputation_cache {
        int id PK
        varchar host
        varchar source
        tinyint is_flagged
        text raw_response
        datetime fetched_at
        datetime expires_at "indexed"
    }

    feedback {
        int id PK
        int verdict_id FK
        int user_id FK "null"
        tinyint was_wrong
        varchar comment
        datetime created_at
    }
```

## Notes

**Primary keys are all `id`.** M1 set that convention with `users`, so the
rest follow it. Foreign keys carry the parent's name, `user_id`,
`submission_id`, and so on.

**Everything hangs off `submissions`,** which is why that table is
deliberately thin. One submission has many URLs, one URL has many redirect
hops, one verdict has many signals.

**`verdict_signals` is the table the product rests on.** The evidence panel,
the rule-health dashboard and the re-score path all read out of it. It
stores the matched text and the weight as applied at the time, so an old
verdict still explains itself after a weight is retuned.

**`reputation_cache` stands alone.** It is keyed by host and source rather
than by a foreign key, because the same host gets looked up by submissions
that have nothing to do with each other.

**Anonymous use.** `submissions.user_id`, `reports.user_id` and
`feedback.user_id` are all nullable, because the plan allows submitting and
reporting without an account.

**Deletes.** A submission cascades to its URLs, resolutions, verdict and
signals, since none of those mean anything on their own. Deleting a user
sets their rows' `user_id` to NULL instead, so the evidence they
contributed survives. `verdict_signals` does **not** cascade from `rules`,
on purpose: a rule cannot be deleted while verdicts still cite it, or old
evidence would quietly lose its reasons.

**The labelled corpus is not a table.** It lives in `database/corpus.csv`,
because it is test data rather than user data and M5's harness reads it
directly. Raise it with the group before adding a twelfth table.