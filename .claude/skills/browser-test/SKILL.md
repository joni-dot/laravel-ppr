---
name: browser-test
description: Run the real-browser tests in tests/Browser and explain any failure. Use when asked to run browser tests, or to confirm that a change works in the browser, for example after changing a Vue page, a route, a permission or the Content Security Policy.
---

# Browser tests

Run from the project root on the host, not inside Sail:

```bash
tests/Browser/run.sh                   # everything
tests/Browser/run.sh <text>            # only files matching <text>, for example: member
```

Pass the user's arguments through if they gave any. Docker must be running. Use a timeout of at least 5 minutes.

The tests use the `testing` database, so don't run them while `sail artisan test` is running. If the script says to stop the Vite dev server, tell the user rather than stopping it yourself.

## When a test fails

1. Read `error-context.md` in the failed test's folder under `tests/Browser/.output`. It shows what was on the page.
2. Decide whether the app or the test is wrong. Don't change a test just to make it pass. If the app is wrong, say so plainly.
3. Fix a test problem on the current branch. Report an unrelated app bug and suggest its own `fix/` branch.

## Writing tests

- Import `test` and `expect` from `tests/Browser/support/test.ts`, because that version also fails on browser errors.
- Put the file in the folder of the role it signs in as: `admin/` or `member/` (project roles get their own folders when projects arrive).
- Create new records in the test itself. Treat the seeded data in `support/test-data.ts` as read-only.
- Find elements the way a user does: by role and label.
- For a check that a role cannot see something, read the raw response with `page.request.get()`, and prove the same value appears for a role that can see it.

Report how many tests passed and failed.
