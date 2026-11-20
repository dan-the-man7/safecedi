## What this changes

<!-- One or two sentences. Link the bug-board item if there is one. -->

## How to check it

<!-- Steps a reviewer can follow on their own machine or the live URL. -->

## Spine checklist

- [ ] Protected pages call `require_login()` / `require_role()` on the page itself
- [ ] All SQL uses prepared statements (no variables concatenated into SQL)
- [ ] State-changing forms include `csrf_field()` and the handler calls `csrf_verify()` first
- [ ] Server-side validation matches every client-side rule
- [ ] Every user-supplied value is printed through `e()`
- [ ] No secrets, `.env`, or real personal data in the diff
- [ ] Checked at 390px wide (if it touches UI)
- [ ] I can explain every line of this in the viva
