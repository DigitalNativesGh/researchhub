# ResearchHub

ResearchHub is a thesis support service for undergraduate, MBA, MPhil and PhD researchers. The researcher submits
a title, project details and the sources the thesis should build on. **ResearchHub's team writes the thesis and
the data collection instrument(s) outside the platform**; an administrator uploads the finished documents and
the researcher downloads them. AI is used for exactly one thing: the optional **Title Assistant**, which suggests
possible titles.

The service runs in three stages:

| Stage | Who | What |
|---|---|---|
| 1 | Team | Writes Chapters 1–3 and the data collection instrument(s). The admin uploads and delivers them. |
| 2 | Researcher | Collects data with the instrument and uploads it. Unlocks only after Stage 1 is delivered. |
| 3 | Team | Downloads the data, writes the complete thesis. The admin uploads and delivers it. |

---

## Contents

1. [Requirements](#requirements)
2. [Installation](#installation)
3. [Environment variables](#environment-variables)
4. [Queue workers](#queue-workers)
5. [The Title Assistant (the only AI feature)](#the-title-assistant-the-only-ai-feature)
6. [Project statuses](#project-statuses)
7. [Uploads](#uploads)
8. [Notifications](#notifications)
9. [Security and privacy](#security-and-privacy)
10. [Payments (Paystack)](#payments-paystack)
11. [Administrator dashboard](#administrator-dashboard)
12. [Running the tests](#running-the-tests)
13. [Demo accounts](#demo-accounts)
14. [Trying it by hand](#trying-it-by-hand)
15. [Moving from the AI-writing workflow](#moving-from-the-ai-writing-workflow)
16. [Project layout](#project-layout)

---

## Requirements

| Component | Version |
|---|---|
| PHP | 8.3 or newer, with `pdo_pgsql` (or `pdo_mysql` / `pdo_sqlite`), `mbstring`, `intl`, `zip`, `gd`, `fileinfo` |
| Composer | 2.x |
| Node.js | 20 or newer (only to build the CSS) |
| Database | PostgreSQL 15+, MySQL 8, or SQLite for local use |
| Redis | optional: only if you want Horizon. The database queue works fine for this app |
| An Anthropic API key | optional: only for real title suggestions. Without one, set `CLAUDE_FAKE=true` |

A `docker-compose.yml` starts PostgreSQL and Redis for local development.

## Installation

```bash
composer install
npm install
npm run build

cp .env.example .env
php artisan key:generate
#   then edit .env: database, mail, Paystack keys, ANTHROPIC_API_KEY (or CLAUDE_FAKE=true)

php artisan migrate
php artisan db:seed            # optional: demo accounts and a sample project at every status

composer dev                   # web server + queue worker (see "Queue workers")
```

`composer dev` runs `php artisan serve` and a queue worker together. To run them by hand:

```bash
php artisan serve
php artisan queue:work --queue=default        # or: php artisan horizon (Redis only)
```

PHP's own upload limits must be at least as large as the per-file limits below. Locally PHP often ships with
`upload_max_filesize=2M` and `post_max_size=8M`; raise both (for example to `40M`) in `php.ini`. The app reads
PHP's limits and never offers more than PHP will accept, so smaller limits only make uploads stricter.

During development `MAIL_MAILER=log` writes emails (including verification links) to `storage/logs/laravel.log`.

## Environment variables

Everything is documented in `.env.example`. The ResearchHub-specific settings:

| Variable | Default | What it does |
|---|---|---|
| `ANTHROPIC_API_KEY` | — | Claude API key, used only by the Title Assistant. |
| `CLAUDE_MODEL` | `claude-haiku-5-5` | Model for title suggestions. |
| `CLAUDE_MAX_TOKENS` | `4000` | Upper bound per title request. |
| `CLAUDE_TIMEOUT` | `45` | Seconds before a title request gives up (the researcher sees a friendly retry message). |
| `CLAUDE_FAKE` | `false` | `true` returns clearly-labelled sample titles with no API call. For demos and tests. |
| `TITLE_SUGGESTIONS_PER_HOUR` | `20` | Title requests per researcher per hour (there is also a fixed 5 per minute). Also editable under Admin → Platform Settings. |
| `MIN_SOURCES` | `1` | Source files required before a project can be submitted. Also an admin setting. |
| `PAPER_MAX_COUNT` | `60` | Maximum source files per project. |
| `PAPER_MAX_KB`, `DATASET_MAX_KB`, `TRANSCRIPT_MAX_KB`, `DELIVERABLE_MAX_KB` | `32768` | Per-file limits in KB for sources, data files, transcripts and the team's documents. |
| `RESEARCHHUB_DISK` | `local` | Disk for every uploaded file. Must be private (`local` → `storage/app/private`, or `s3`). |
| `PAYSTACK_*`, `PAYMENT_CURRENCY`, `PRICE_*` | — | See [Payments](#payments-paystack). |
| `TAILWIND_ASSETS` | `auto` | `vite` = compiled CSS, `cdn` = Tailwind CDN, `auto` = compiled CSS when built. |
| `HORIZON_ADMINS` | empty | Emails allowed to open `/horizon` outside the local environment. |

Removed with the AI-writing workflow: `CLAUDE_LIGHT_MODEL`, `CLAUDE_CACHE_TTL`, `CLAUDE_STREAM`, `CLAUDE_EFFORT`,
`ANALYSIS_*`, `PANDOC_*`, `MAX_SECTION_ATTEMPTS`, `MAX_DOCUMENTS_PER_SECTION`, `MIN_PAPERS`, `PAPER_MAX_PAGES`,
`PIPELINE_QUEUE`, `GENERATION_RATE_LIMIT`. They are ignored if still present.

## Queue workers

One worker on the `default` queue is needed. It runs:

* **data file checks** (`ValidateDataset`): until a file is checked, the researcher cannot submit their data;
* **notification emails**, when the mailer is not synchronous.

Title suggestions run inside the web request (with a 45-second timeout), not on a queue, so they work even if no
worker is running. With `QUEUE_CONNECTION=database` use `php artisan queue:work --queue=default`; with Redis you
can use `php artisan horizon` (`config/horizon.php` has a single `supervisor-default`).

## The Title Assistant (the only AI feature)

On the Title & Details step the researcher chooses **"I have a title"** or **"Help me find a title"**. The
assistant asks for degree level (already chosen), discipline, topic or area of interest, keywords, context or
setting, research approach (quantitative, qualitative, mixed, or "not sure") and, optionally, a few abstracts or
background notes. It returns about five titles, each with a one-line explanation. The researcher can use one as
is, use one and edit it, ask for more (earlier titles are not repeated), start over, or write their own.

* The page reminds the researcher to **confirm the title with their supervisor**. The prompt forbids claims of
  originality or novelty, and the page makes none.
* Where the title came from is saved on the project as `title_source`: `own`, `suggested` or
  `suggested_edited` (a suggestion edited by more than a little). Admins see it on the project page.
* Rate limits: 5 requests per minute and `TITLE_SUGGESTIONS_PER_HOUR` per hour per researcher.
* Errors (timeouts, overload, invalid responses) show a plain message and keep what the researcher typed.
* Every call is logged in `ai_calls` with tokens, duration, estimated cost and errors; see **Admin → AI Usage &
  Cost**. Prices are in `config/services.php` → `claude.pricing`; check them against Anthropic's price list.
* The prompt is `resources/prompts/title_suggestions.md` (versioned; bump `version` when you change it). It is
  the only prompt left.

## Project statuses

`App\Enums\WorkflowStatus` is a state machine. `App\Services\Workflow\ProjectWorkflow` allows only these moves,
under a row lock, and records each one in `project_transitions` with the time and the acting user:

| From | Allowed next |
|---|---|
| `draft` | `submitted` |
| `submitted` | `stage1_in_progress`, `stage1_delivered`, `needs_info`, `on_hold` |
| `stage1_in_progress` | `submitted`, `stage1_delivered`, `needs_info`, `on_hold` |
| `stage1_delivered` | `data_submitted`, `on_hold` |
| `data_submitted` | `stage3_in_progress`, `completed`, `needs_info`, `on_hold` |
| `stage3_in_progress` | `data_submitted`, `completed`, `needs_info`, `on_hold` |
| `completed` | — |
| `needs_info`, `on_hold` | back to the status they were paused from (`resume_status`) |

* **Needs information**: the admin writes what is needed; the researcher sees it on the project page and replies.
  While it is open, sources (Stage 1) or data (Stage 3) unlock so the researcher can add files. The reply returns
  the project to where it was.
* **On hold**: an internal pause (for example a payment query). The researcher sees "On hold".
* Researchers see plain status messages ("Our team is working on your Chapters 1–3…"), never writer names,
  expected dates or internal notes.

## Uploads

| What | Who | Formats |
|---|---|---|
| Sources | Researcher, before submitting | PDF, Word (.docx, .doc), RTF, OpenDocument (.odt), text |
| Guidelines / templates | Researcher, before submitting | same as sources |
| Survey data | Researcher, Stage 2 | CSV, Excel (.xlsx), SPSS (.sav), Stata (.dta) |
| Interview transcripts | Researcher, Stage 2 | Word (.docx), PDF, text |
| Chapters 1–3, complete thesis | Admin | Word (.docx, .doc), PDF |
| Instrument(s) | Admin | Word (.docx, .doc), PDF, Excel (.xlsx) |

* Every file is checked by extension **and** content signature (`App\Support\FileSignature`), size and
  duplicates. Files are sent one per request so each shows its own progress bar and error.
* CSV and Excel data must open as a table; transcripts must contain readable text. Problems are listed per file,
  and the researcher can replace or remove files until they confirm the submission.
* Sources lock when the project is submitted and data locks when it is submitted, unless the team asks for
  information.
* The team's documents are **versioned**: an upload never overwrites an earlier one. Nothing reaches the
  researcher until the admin presses Deliver and confirms. Delivering a corrected version later does not change
  the stage; the researcher is told an updated version is available.

## Notifications

In-app always, and by email when the user has email alerts on and a verified address.

| To | When |
|---|---|
| Admins | A project is submitted · Data is submitted · A researcher answers a request for information |
| Researcher | Project received · Chapters 1–3 and instrument delivered (or updated) · Data received · Final thesis delivered (or updated) · The team needs information |

## Security and privacy

* Every file is on a **private** disk and served only through controller routes with authorisation checks.
  `ProjectPolicy` (also used for papers and data files) limits researchers to their own projects; every `/admin` route
  requires an administrator.
* `ProjectDocumentPolicy` lets a researcher download only **delivered** documents of their own project. Anything
  else answers 404. Legacy AI drafts from the old workflow (`project_documents.kind = ai_draft`) are kept in the
  database but are never served to anyone.
* Every upload, download, delivery and status change is recorded (`project_audit_logs`, `project_transitions`);
  admin account actions are in `admin_logs`.
* CSRF on every form, email verification, rate limits on sign-in and title suggestions.
* Deleting a project removes its files; deleting an account removes everything.

## Payments (Paystack)

Starting a project costs a one-off fee per research type. The wizard runs **Research Type → Payment → Title &
Details → Sources → Review & Submit**. The researcher pays on Paystack's hosted checkout (Mobile Money, Visa,
Mastercard); ResearchHub never handles card or wallet details.

1. Add `PAYSTACK_SECRET_KEY` and `PAYSTACK_PUBLIC_KEY` to `.env` (`sk_test_…` while developing). Mobile Money
   needs a GHS (or KES) Paystack account; set `PAYMENT_CURRENCY` to match.
2. Set the Paystack webhook URL to `https://your-domain/webhooks/paystack`.
3. Set prices with `PRICE_UNDERGRADUATE`, `PRICE_MBA`, `PRICE_MPHIL`, `PRICE_PHD`, or under Admin → Platform
   Settings. A price of 0 makes that type free.

## Administrator dashboard

`/admin` (an **Admin Dashboard** link appears in the sidebar for admins):

* **Overview**: what is waiting on the team, what is due soon, payments and accounts.
* **Work Queue**: projects waiting on the team, longest waiting first, with waiting time and deadline. Filters:
  stage, status, degree level, discipline, deadline (overdue, 7 days, 30 days); "also show" projects waiting on
  researchers or on hold.
* **Project page**: everything the researcher submitted (title and where it came from, details, guidelines,
  notes for the team), sources and guidelines (each or as `RH-0001_sources.zip`), data (each or as
  `RH-0001_data.zip`), **Mark as in progress** with internal writer, expected date and notes, document uploads
  with version history, **Deliver** (with a confirmation), request information, hold and resume, status history
  and audit trail.
* Users, projects, payments, AI usage and cost (title suggestions), failed jobs, activity log and settings.

Grant admin access from the server:

```bash
php artisan researchhub:admin you@example.com
```

## Running the tests

```bash
php artisan test
```

The Pest suite uses in-memory SQLite, the sync queue and a fake Claude gateway, so it needs no API key or
network. `tests/Feature/Service/` covers the service model: the Title Assistant (inputs, five titles, "more"
without repeats, title source, errors, rate limits and logging), submission and source uploads, every status
transition, the admin queue and project page, downloads and ZIPs, versioning and delivery, the data step,
notifications, access control, the migration mapping, and a test that **no thesis-generation code path remains**
(removed classes, prompts, routes, and a full project run that sends nothing to Claude).

## Demo accounts

```bash
php artisan migrate:fresh --seed     # erases the database. Use a scratch database
```

| Account | Email | Password |
|---|---|---|
| Researcher | `demo@researchhub.test` | `password` |
| Administrator | `admin@researchhub.test` | `password` |

The researcher has one project at every status (IDs on a fresh database):

| Project | Status |
|---|---|
| RH-0001 | Draft: two sources uploaded, not submitted |
| RH-0002 | Submitted: waiting 3 days, deadline in 10 days, suggested title |
| RH-0003 | Stage 1 in progress: writer Esi Owusu, edited suggested title |
| RH-0004 | Stage 1 delivered: ready for data (a matching CSV is written to `storage/app/demo/`) |
| RH-0005 | Data submitted (PhD) |
| RH-0006 | Stage 3 in progress: qualitative, writer Kofi Asante |
| RH-0007 | Completed |
| RH-0008 | Needs information |
| RH-0009 | On hold |

All papers, data and documents are invented sample files, labelled as such. Re-running the seeder replaces the
demo researcher's projects. Do not keep these accounts on a live server.

## Trying it by hand

With the demo data loaded and `composer dev` running (`CLAUDE_FAKE=true` if you have no API key):

1. **Title Assistant**: sign in as the researcher → New Project → pick a type (pay with a Paystack test card,
   or set the price to 0 under Admin → Platform Settings) → "Help me find a title". Fill in the form and get five
   titles. Try "Suggest more", "Use this title", then edit the title. Send six requests within a minute to see the
   rate-limit message.
2. **Submit**: add institution, approach, citation style, length/guidelines, deadline and notes → upload a few
   sources and a template (try a `.txt` renamed to `.pdf`: it is refused) → Review → tick the confirmation →
   Submit. Sources are now locked.
3. **Admin, Stage 1**: sign in as the admin → Work Queue → open the project → download a source and the ZIP →
   Mark as in progress (writer, date, notes) → upload a Chapters 1–3 Word file and an instrument → Deliver →
   confirm. Sign back in as the researcher: the internal details are not shown and the documents can be
   downloaded.
4. **Data**: as the researcher, open Your Data on RH-0004 → upload `storage/app/demo/*.csv` → replace it → submit
   with the confirmation.
5. **Admin, Stage 3**: download the data ZIP → upload the complete thesis → Deliver. The project is Completed.
6. **Needs information**: on RH-0008 as the researcher, add a file and reply; the project returns to the queue.
   On RH-0009 as the admin, press Resume.

## Moving from the AI-writing workflow

The migration `2026_11_04_000100_switch_to_service_workflow` adds the new columns and moves existing projects:

| Old status | New status |
|---|---|
| `draft` (not started) | `draft` |
| `draft` started under the original flow, any Stage 1 state, a Stage 1 generation failure | `submitted` |
| `stage1_delivered` | `stage1_delivered` |
| `data_uploaded`, any Stage 3 state, a Stage 3 generation failure | `data_submitted` |
| `completed` | `completed` |

Every move is logged in `project_transitions` ("Moved to the service workflow"). Edited documents become
deliverables. **Nothing is deleted**: AI drafts, sections, citations, analyses and the other AI tables stay in
the database, unused. A snapshot of the code before the change is in
`storage/app/archive/code-snapshot-before-service-model-2026-11-03.tar.gz`.

After pulling this change: `composer dump-autoload` (removed classes), `php artisan migrate`, `npm run build`,
and restart the queue worker.

## Project layout

```
app/Enums/WorkflowStatus.php            statuses, allowed transitions, researcher-facing messages
app/Services/Workflow/                  ProjectWorkflow (state machine, delivery, notifications), DocumentStore, ProjectTimeline
app/Services/Titles/TitleSuggester.php  the Title Assistant
app/Services/Claude/                    ClaudeService (structured calls + cost logging), SDK and fake gateways
app/Services/Data/                      CSV/XLSX reader and dataset validator
app/Support/FileSignature.php           content checks for uploads
app/Http/Controllers/                   researcher pages (wizard, sources, data, downloads)
app/Http/Controllers/Admin/             work queue, workflow actions, users, payments, settings
resources/prompts/title_suggestions.md  the only prompt
tests/Feature/Service/                  tests for the service model
```
