# PPR – instructions for Claude

Project procurement register: project teams plan and track the items ("packages") each project buys. Laravel 13 (PHP 8.5), Inertia v3, Vue 3 + TypeScript, Tailwind 4, PostgreSQL 18 and Redis. It is a single-company app and a single-page app: no full page reloads. Its sister app is the SRM (supplier data), which PPR reads through the SRM API.

[README.md](README.md) explains setup, roles, business rules and the roadmap. Read the relevant section before changing a feature. This repository is public, so never put secrets or real project data in it.

## Commands

The host has only Docker and git. Run PHP and Node commands through Sail.

```bash
./vendor/bin/sail up -d                  # start everything
./vendor/bin/sail composer ci:check      # all checks; must pass before a pull request
./vendor/bin/sail artisan test --filter=SettingsTest   # one test class
./vendor/bin/sail composer lint          # fix PHP style
./vendor/bin/sail pnpm run check:fix     # fix frontend style and lint
./vendor/bin/sail restart queue scheduler  # after changing PHP that runs in the background
tests/Browser/run.sh                     # real-browser tests, run from the host; see /browser-test
```

`ci:check` runs the frontend checks, TypeScript, Pint, PHPStan and all tests. GitHub runs the same command and the browser tests on every pull request.

If a result makes no sense, Docker Desktop may have missed a quick file edit. Run `bin/sync-check --fix`, which compares changed files on the host with the container's copy and writes the stale ones again. If a file still differs, run `sail down` and then `sail up -d`, but only when no other agent is using the containers.

### When several agents work at once

All agents share this folder and these containers, because the containers only see this folder; a git worktree can't run tests. So:

```bash
bin/agent-test <name> --filter=SomeTest  # tests on the agent's own database (testing_agent_<name>), not the shared one
bin/agent-test --drop <name>             # remove it when the agent is done
bin/browser-test <spec>                  # browser tests, one run at a time across all agents
bin/lint-files <files...>                # format and lint only your own files
bin/sync-check --fix                     # see above
```

`ci:check` and the browser tests both use the `testing` database, so run them one after the other, never next to each other. Never switch branches in a folder where someone else has uncommitted work; check `git status` first.

## Git workflow

- Never commit to `main`. GitHub refuses direct pushes, and the `ci` check must pass before a merge.
- Use one branch per change, from an up-to-date `main`, named `<type>/<short-name>` (for example `feat/projects` or `fix/lead-time-date`).
- Use Conventional Commits: `type(optional scope): short imperative summary`.
- Commit, push and open pull requests only when asked. The user merges pull requests.
- Before committing, check that no secrets are staged.

## Rules for every change

- **Security must be flawless.** Follow the security rules in the README.
- **Performance is a top priority.** Avoid repeated queries in lists, and check new lists and filters against realistic demo data (thousands of packages). Long lists draw only the rows on screen (see `PackageTreeTable.vue`). Don't use CSS `:has()` rules such as Tailwind's `has-*` and `group-has-*` on elements that wrap the page: the browser re-checks them after every change, which made scrolling the package list stutter.
- **Code explains itself** through clear names and small functions. Comments are for why, not what.
- **Controllers follow CRUD where it makes sense.** Use only `index`, `create`, `store`, `show`, `edit`, `update` and `destroy`, and leave out the ones a feature doesn't need. An action that doesn't fit CRUD, or a controller that would have only one method, becomes a single-action controller named verb + noun (for example `MergePackagesController`).
- **Action classes are single-action.** Each class in `app/Actions` is named verb + noun and has one public `handle()` method, except where a framework interface requires another name (Fortify's `reset()`). An action that needs another action receives it through its constructor.
- **New behaviour comes with tests.** Add a browser test in `tests/Browser` when a feature adds a page or a user flow.
- **Ready for more languages.** The interface is English for now. Every text a person sees goes through `__()` in PHP or `$t()`/`trans()` in Vue, and is listed in `lang/en.json`; see Languages in the README.
- **Report the result.** Every change summary includes a short security and performance check.
- **Explain in plain language.** Keep it brief, define unavoidable technical terms and give the reason for each decision. Ask when a choice is really the user's to make.

## Things that are easy to break

- **People see only their own projects.** Admin is the only system-wide role; everyone else gets rights from their role in each project. Enforce this on the server by leaving data out of the response, not by hiding it in Vue.
- **Package numbers are never reused**, even after merging, dividing or closing a package. Only `AssignPackageNumber` gives numbers; it locks the project row and counts on from `projects.last_package_number` and `packages.last_sub_number`. Packages are never deleted.
- **Package permissions live in `PackagePolicy`.** The package list checks thousands of rows through `updateOwnedBy()` directly, so change the rule there, not in the list.
- **Numbering rules exist twice and must change together:** `app/Numbering/PackageNumberFormat.php` makes the real numbers and `resources/js/lib/packageNumber.ts` shows the live preview. The browser test in `tests/Browser/admin/projects.spec.ts` fails if they disagree.
- **Milestone date rules live only in `app/Scheduling`.** `PackageSchedule` works out expected and latest dates and float; `SubPackageRollUp` rolls dates up to parents. Pages and lists show its results; never recalculate dates in Vue or in a controller.
- **Risk rules live only in `app/Risk`.** `PackageRisk` decides the level and reasons; `ProjectRisks` works them out for a whole project with a constant number of queries, and the package list reuses the rows and schedules it already loaded. Levels are calculated live, never stored for display; never recalculate risk in Vue or in a controller. Vue only puts the reason codes into words.
- **Planned dates are the original plan.** Once set, only project managers and admins change them (`PackagePolicy::changePlannedDates`). The form request and `UpdatePackageMilestones` both enforce it.
- **A project always has exactly one need-date milestone.** `MakeMilestoneTheNeedDate` moves it under a project lock, and the need date can't be removed.
- **A project always keeps a project manager.** `EnsureProjectKeepsManager` refuses to demote or remove the last one.
- **Merging and dividing must stay traceable.** Keep the history of which packages became which.
- **Notifications are private and follow project access.** Read them only through `Notification::visibleTo()`, which gives a person their own notifications of projects they can still see, and route binding uses the same rule. Every kind extends `PackageNotification`, which fixes the stored layout the notifications page relies on.
- **Risk and date notifications come only from `CheckProjectRisks`.** Its actions send a notification only for the rows their own atomic `UPDATE … RETURNING` (`NotifyRiskChanges`) or `INSERT … ON CONFLICT DO NOTHING RETURNING` (`SendDateReminders`) gave back, so two checks running at once never tell anyone twice. A package's first check is silent: it only stores the level and records old overdue dates as reminded. Every save that can change a package's risk dispatches `CheckProjectRisks` with the person who saved; a new feature that changes risk inputs (dates, status, owner or buyer, milestones, warnings) must dispatch it too, or its risk changes are only noticed at the next daily run and told to the person who made them.
- **Records are kept.** Users are deactivated, never deleted.
- **Seeders must refuse to run in production.** `SeedersRefuseProductionTest` covers this, so add any new seeder to it.
- **Generated code:** `resources/js/actions`, `resources/js/routes` and `resources/js/wayfinder` are generated and ignored by git. Don't edit them.
