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

| Role   | Can do                                                |
| ------ | ----------------------------------------------------- |
| Admin  | Everything, in every project, plus users and settings |
| Member | Only what their role in each project allows           |

Members get their rights from their role in each project, so a person can lead one project and only look at another. People see only the projects they belong to; other projects don't show up anywhere, and their addresses answer "forbidden".

| Project role    | Can do                                                                   |
| --------------- | ------------------------------------------------------------------------ |
| Project manager | Runs the project: details, risk warnings, numbering, categories, members |
| Buyer           | Adds packages and edits every package in the project                     |
| Package owner   | Edits the packages they own, but can't hand them to someone else         |
| Viewer          | Looks at everything in the project but changes nothing                   |

Project managers can also add and edit every package. A package's owner must be a project manager, buyer or package owner in that project, and its responsible buyer a project manager or buyer. Removing someone from a project also removes them as owner or buyer of its packages.

Only admins create projects. Each new project gets a project manager straight away, and a project can never lose its last project manager: make someone else project manager first.

Admins can't change their own role or deactivate themselves, so there is always at least one admin. Users are deactivated, never deleted, so their history stays intact. Removing someone from a project only removes their membership; their account stays.

## Settings

Admins set the company-wide **risk warning** defaults: a package turns amber or red when its spare time (days between expected delivery and the date it is needed) drops below these numbers. A new project gets a copy of them, and its project manager can change the project's own copy. Changing the defaults later doesn't change existing projects.

## Packages

Each project's **Packages** tab shows all its packages as a tree that can be folded, searched and filtered by status and category.

- **Quick adding:** type a title, pick a category and press Enter. The package gets the next number straight away, and the form stays open for the next one. The other details (status, purchase type, quantity, owner, buyer, description, notes) are filled in later on the package's edit page.
- **Sub-packages** can be divided again without limit. A sub-package gets its parent's category unless another one, or none, is chosen.
- A top-level package needs a category when category codes are part of the project's numbers.
- Packages are never deleted, so their numbers stay unique; set the status to **Cancelled** instead. A category that packages use can't be removed.
- The list sends the whole tree at once, so folding, searching and filtering need no server round trip. Its query count doesn't grow with the number of packages.

For trying lists with realistic amounts of data, `./vendor/bin/sail artisan db:seed --class=DemoProjectSeeder` adds a made-up project with about 3,000 packages, most with milestone dates so some are late and some have time to spare (it takes a couple of minutes, and refuses to run in production).

## Milestones and lead times

**Milestone list.** Each project has its own list of milestones: the steps every package goes through. New projects start with Specification ready, Request for quotation sent, Offers received, Order placed, Delivered from supplier, Required on site and Installed / commissioned. Admins and the project's managers change the list on the project's **Milestones** tab: add (at the end, at most 30), rename (names are unique in the project, ignoring case), move up or down, and remove.

- One milestone is the **need date**: when the project needs the package ("Required on site" at the start). To change it, make another milestone the need date; the need date itself can't be removed.
- Removing a milestone also removes every package's dates and lead time for it. The tab shows how many packages have dates for each milestone, and the confirmation says how many will lose them.
- A lead time is the number of days from the previous milestone, so moving a milestone changes what existing lead times mean.

**A package's dates.** For every milestone a package has a planned, forecast and actual date and a lead time in calendar days. **Edit dates** on the package page changes them.

- Anyone who may edit the package can change forecasts, actual dates and lead times, and can fill in an empty planned date. Once a planned date is set, only a project manager or admin can change or clear it, so the original plan stays visible.
- Actual dates can't be in the future. "Today" is today where the date is furthest ahead (UTC+14), because the app doesn't know each person's time zone.
- Dates must be between the years 2000 and 2100, and lead times between 0 and 3,650 days.

**What the app works out:**

- **Expected date** of each milestone: the actual date if it has happened, else the forecast, else the previous milestone's expected date plus the lead time, else the planned date. So a late order pushes every later milestone back.
- **Latest date:** counting back from the need date with the lead times, the last day each earlier milestone can happen without making the package late. A missing lead time leaves the earlier latest dates unknown instead of guessing zero days.
- **Float:** the days between the expected need date and the need date. "12 days to spare", or "5 days late" when it is below zero.
- **Sub-packages:** a package isn't ready until its sub-packages are, so a parent also shows the latest expected date among itself and all its sub-packages, and the float that gives. Cancelled sub-packages are left out.

The package list shows each package's **next milestone** (the first one without an actual date) with its expected date, the need date, the expected need date including sub-packages, and the float ("−5 d" in red when late). The rules live in one place only, `app/Scheduling/PackageSchedule.php`; pages and lists only show its results.

## Package numbers

Each project has its own numbering, set by its project manager on the project's **Numbering** page:

- A number is built from up to four parts, in this order: an optional **prefix**, the **project code**, the **category code** and a **running number**. The prefix, project code and category code can each be left out.
- The parts are joined with one **separator** (hyphen, dot, slash, underscore or none). The running number is padded with zeros to the chosen number of digits and starts at the chosen first number.
- Sub-packages add their own number after a separator, which can't be "none": `P100-ELE-0007.01`, and its own sub-package `P100-ELE-0007.01.01`. A sub-package's number always follows its parent's, whatever its own category.
- The running number counts across the whole project, not per category, so every number is unique.
- Numbers are never reused. Changing the numbering, a code or a package's category only affects packages added afterwards; existing numbers never change. Lowering the first number doesn't reuse numbers already given.
- Each package also stores its place in the tree as a list of numbers (`number_path`, for example `{7,1,1}`). The list is sorted by it, so `.100` comes after `.99`, and a unique index makes duplicate numbers impossible even when two people add packages at the same moment.
- Categories (for example `ELE Electrical`) belong to one project. Project and category codes use only letters and digits, so they can never be mistaken for a separator.

The rules live in `app/Numbering/PackageNumberFormat.php`. The numbering page shows a live preview made by `resources/js/lib/packageNumber.ts`, and a browser test checks that both give the same result.

## Languages

The interface is in English, built so that other languages can be added later without changing the code.

- Write every text in English and pass it through `__('Text')` in PHP, `$t('Text')` in Vue templates or `trans('Text')` in Vue scripts (from `@/lib/i18n`). The English text is the key, so PHP and Vue share one language file per language.
- Fill in changing values with placeholders, never by joining texts together: `$t('Invitation sent to :email.', { email })`. Word order differs between languages.
- A text that is set before a page runs, such as a title in a page's `defineOptions`, is a function: `title: () => trans('Users')`. It is then translated when it is shown.
- Translations are shown as plain text. Never put them in `v-html` or `{!! !!}`.
- `lang/en.json` lists every text. `TranslationsTest` fails if a text is missing from it, if it lists a text the code no longer uses, or if a Vue template contains text without `$t()`.
- To add a language, copy `lang/en.json` to, for example, `lang/fi.json` and translate the values. The same test checks that the new file has every text and keeps every placeholder. Laravel's own messages (validation, password reset) come from separate files, created with `sail artisan lang:publish`.
- Not built yet: choosing the language per user (today the whole app uses `APP_LOCALE`), and texts with singular and plural forms.

## Roadmap

1. Base app: sign-in, users, settings, security _(done)_
2. Projects, project members and numbering schemes _(done)_
3. Packages and sub-packages, with quick adding _(done)_
4. Milestones (planned, forecast, actual dates) and delivery calculation from lead times _(done)_
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

Helpers for working with several AI agents at once, run from the host (see CLAUDE.md):

```bash
bin/agent-test <name> [options]   # tests on a database of that agent's own
bin/browser-test [filter]         # browser tests, one run at a time
bin/lint-files <files...>         # format and lint only these files
bin/sync-check [--fix]            # find files Docker Desktop hasn't noticed changing
```

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
