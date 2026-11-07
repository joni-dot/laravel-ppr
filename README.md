# PPR – Project Procurement Register

A web app for project teams to plan and track everything a project must buy. Each purchasable item is a **package**. It works as a single-page app: pages change without full reloads.

## Tech stack

| Part                    | Tool                               |
| ----------------------- | ---------------------------------- |
| Backend                 | Laravel 13 (PHP 8.5)               |
| Frontend                | Vue 3 + TypeScript, via Inertia.js |
| Styling                 | Tailwind CSS                       |
| Database                | PostgreSQL 18                      |
| Cache, sessions, queues | Redis                              |
| Local setup             | Docker (Laravel Sail)              |

## Getting started

You only need **Docker** and **git**. Everything else runs inside containers.

```bash
cp .env.example .env                 # then set DB_PASSWORD to a long random value (Docker will not start without it)
docker run --rm -u "$(id -u):$(id -g)" -v "$PWD":/app -w /app composer:latest composer install
./vendor/bin/sail build              # build the app image (first time only)
./vendor/bin/sail up -d              # start the app, database, Redis, Mailpit and the background workers
./vendor/bin/sail artisan key:generate
./vendor/bin/sail artisan migrate
./vendor/bin/sail pnpm install
./vendor/bin/sail artisan app:create-user   # pick the Admin role; there is no public sign-up
./vendor/bin/sail pnpm run dev       # live-reloading frontend
```

Open http://localhost:8080. Admins invite other people from the **Users** page. Emails (invitations, password resets) are sent in the background by the `queue` container and show up in Mailpit at http://localhost:8026.

The ports differ from the SRM app's, so both can run at the same time. Change them in `.env` if needed.

After changing PHP code that runs in the background, restart the workers with `./vendor/bin/sail restart queue scheduler`.

## Roles

| Role   | Can do                                                    |
| ------ | --------------------------------------------------------- |
| Admin  | Everything, in every project, plus users and settings     |
| Member | Only what their role in each project allows (coming soon) |

Project roles (project manager, buyer, package owner, viewer) are set per project, so a person can lead one project and only look at another. People see only the projects they belong to.

Admins can't change their own role or deactivate themselves, so there is always at least one admin. Users are deactivated, never deleted, so their history stays intact.

## Settings

Admins set the company-wide **risk warning** defaults: a package turns amber or red when its spare time (days between expected delivery and the date it is needed) drops below these numbers. Each project can change them.

## Roadmap

1. Base app: sign-in, users, settings, security _(done)_
2. Projects, project members and numbering schemes
3. Packages and sub-packages, with quick adding
4. Milestones (planned, forecast, actual dates) and delivery calculation from lead times
5. Risk levels and in-app notifications
6. Dashboard and visuals
7. Merging and dividing packages, with full history
8. Supplier link to the SRM app, Excel export
9. Budget tracking

## Everyday commands

```bash
./vendor/bin/sail composer ci:check       # every check GitHub runs, except browser tests
./vendor/bin/sail artisan test            # run all tests
./vendor/bin/sail composer lint           # fix PHP code style
./vendor/bin/sail pnpm run check:fix      # fix frontend style and lint
./vendor/bin/sail pnpm run types:check    # find TypeScript errors
```

Tip: add `alias sail='./vendor/bin/sail'` to your shell config to type less.

## Browser tests

The tests in `tests/Browser` use a real Chrome browser to sign in as each role and click through the main features. Every test also fails on a JavaScript error or a blocked script.

```bash
tests/Browser/run.sh                 # run all of them
tests/Browser/run.sh member          # run only the files matching "member"
```

They run on a separate copy of the app that erases and refills the `testing` database, so your development data, files and emails are not touched. Don't run them at the same time as `sail artisan test`, which uses the same database. Stop `pnpm run dev` first.

If a test fails, the screenshot and a step-by-step trace are in `tests/Browser/.output`. On GitHub, they are attached to the failed run.

The Playwright version is set in three places that must match: `package.json`, `compose.yaml` and `.github/workflows/tests.yml`.

## Security rules for developers

- **No inline JavaScript.** The Content Security Policy only lets the browser run scripts from this app's own files. `<script>` blocks, `onclick="..."` attributes and scripts from other websites are blocked. If a Blade view truly needs an inline script, give it `nonce="{{ Vite::cspNonce() }}"`.
- **Never show user input as HTML.** Vue's `{{ }}` and Blade's `{{ }}` escape text automatically. Do not use `v-html` or `{!! !!}` with anything a user typed.
- **Check permissions on the server.** Hiding a button is only for convenience; every route needs a `->can(...)` check.
- **Keep secrets out of git.** This repository is public. Never commit `.env`, keys or real project data.

## Ground rules

- **Security first.** Every change is reviewed for security and performance before it is merged.
- **Readable code.** Code should explain itself through clear names and small functions. Comments are for _why_, not _what_.
- **Tests.** New behaviour comes with tests.
