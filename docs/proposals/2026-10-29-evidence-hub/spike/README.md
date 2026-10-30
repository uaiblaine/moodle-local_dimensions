# S0 spike — Evidence hub (2026-10-29)

Stage S0 of [the proposal](../README.md): measure the hub's queries on a realistic worst case on
both database families, prototype the drawer on core 5.3, and produce a dossier page with core's
TCPDF. Throwaway code; the files here reproduce every number below. Nothing ran against a live
site: the PostgreSQL database `spike_evh` (inside `m503-db-1`) and the MariaDB container
`spike-evh-mariadb` were created for this and removed afterwards.

## Verdict

| S0 exit criterion | Result |
|---|---|
| Decisions recorded | D1–D12 approved by the owner 2026-10-29 (proposal README) |
| Grid and KPI queries < 300 ms on the seed, both DB families | **Met, with one design change**: the grid needs a two-step "deferred join"; a report builder system report cannot meet it on MariaDB (or on PostgreSQL when sorted by expiry) |
| Side-sheet `core/modal` on 5.3 | **Works**: right edge, full height, focus trapped, Escape closes, focus returns to the opener |
| TCPDF page with Portuguese text and a QR code | **Works**: 55–90 ms a page; two traps found (table widths, `get_file_writer()`) |

## The seed

`schema_pg.sql` / `schema_my.sql` copy the columns the hub reads from eleven core tables, with the
indexes core 5.3 declares for them (read with `pg_dump` from m503), plus the two proposed
companion tables. `seed_pg.sql` / `seed_my.sql` load the same shape (PostgreSQL 9 s, MariaDB 18 s):

| | Rows |
|---|---|
| Users (10,000 workers, 2 % suspended, 40,000 others) | 50,000 |
| Plans (active: template 1 for every worker, templates 2–21 for 500 each; 5,000 completed) | 25,000 |
| Required pairs (template 1 alone: 10,000 × 30 = 300,000) | 350,000 |
| Core user evidence (94 % of pairs, 1–3 records each) + links | 659,000 |
| Companion metadata | 659,000 |
| Companion status rows | 329,000 |

Engines as the stacks run them: PostgreSQL 18.6 (128 MB shared buffers), MariaDB 12.3.3
(128 MB buffer pool, utf8mb4), both on this MacBook. `run.py pg|my` prints the median of five
server-side timings (`EXPLAIN ANALYZE` / `ANALYZE FORMAT=JSON`).

## Query results (median ms; worst case = template 1, 300,000 pairs)

| Query | PostgreSQL | MariaDB | Verdict |
|---|---:|---:|---|
| Q1 grid, all, sort by name (one statement) | 2.6 | **830** | fails on MariaDB |
| Q1d the same, deferred join | 0.9 | 195 | ok |
| Q1c pager count | 32 | 23 | ok |
| Q2 grid, status Due, sort by expiry | 14 | 155 | ok |
| Q2b grid, all, sort by expiry (one statement) | **478** | **731** | fails on both |
| Q2d the same, deferred join | 155 | 267 | ok |
| Q3 grid, Pending (missing + unverified) | 3.4 | 286 | ok, tight |
| Q4 KPI status distribution | 151 | 260 | ok, tight |
| Q5 KPI readiness rate | 179 | 279 | ok, tight |
| Q11 default view "needs attention", by expiry (one statement) | 283 | **445** | fails on MariaDB |
| **Q11d the same, deferred join: page of keys** | **134** | **256** | ok |
| **Q11e step 2: details of the page** | **0.5** | **0.2** | ok |
| Q11c count of "needs attention" | 119 | 245 | not needed: the KPI query already counts it |
| Q12 / Q12a / Q12n system-report shape (`set_main_table_sql`, derived main table, joins on top) | 434 / 296 / 2 | **674 / 489 / 739** | fails |
| Q6 / Q7 real contract (500 × 15) + team cohort: grid / readiness | 5.6 / 2.6 | 1.8 / 1.0 | ok |
| Q8 grid, viewer with 500 user-context grants (`IN` list) | 43 | 22 | ok |
| Q1w / Q1wf / Q1wu paged by worker: by name / with Due / most urgent | 0.5 / 196 / 156 | 17 / 77 / 297 | ok |
| Q9 scheduled task, `nexttransition <= now` | 1.1 | 0.6 | ok |
| Q10 engine input for one worker | 0.5 | 0.1 | ok |

### What the numbers decide

1. **The grid is not a report builder system report.** A system report emits one statement:
   main table, entity joins, `ORDER BY`, `LIMIT`. With 300,000 pairs that statement joins and sorts
   everything before the limit (MariaDB's plan: 300,000 `eq_ref` loops into status and metadata,
   then a filesort). 5.3's new `set_main_table_sql()` (MDL-88397) does not help: both planners
   flatten the derived table (Q12). The two-step query does: step 1 sorts only the narrow key
   (user, competency, sort value) and keeps 50; step 2 reads the details of those 50 by primary
   and unique keys.
   - **S4 changes accordingly**: a `hub_query` class (two steps, our own pager, Mustache rows
     with checkboxes), the download through core's `\core\dataformat::download_data()` over a
     recordset of the same filter (CSV, XLSX, ODS without a limit), and the **report builder
     datasource stays** for custom reports and e-mailed schedules, where a page takes longer.
2. **The default view is "needs attention", sorted by expiry** (Q11d + Q11e). "All, sorted by
   expiry" also meets the target through the two-step query (Q2d).
3. **The pager count comes from the KPI query.** The status distribution (Q4) already counts each
   status, so the page never runs a separate count: worst case per page view on MariaDB is
   Q4 + Q11d + Q11e ≈ 520 ms, on PostgreSQL ≈ 290 ms; a real contract stays under 10 ms.
4. **KPIs are the tight spot on MariaDB** (260–280 ms in the worst case). Cache them in MUC,
   keyed by (template, cohort, viewer scope, status generation), where the generation is a counter
   the engine bumps whenever it writes a status row. That invalidates by event as this family's
   rule requires, never by a TTL.
5. **Readiness by worker** is cheap (Q1w), so the matrix view (S6) pages by worker with no extra
   design.
6. The scheduled task and the engine's per-worker input are not a concern (≤ 1 ms).

## Side sheet on `core/modal` (m503, no login)

Run from the browser console on m503's login page, which loads the real 5.3 AMD modules: a
`core/modal` created with a body taller than the viewport, given a `local-evidencehub-sheet`
class and about 15 lines of CSS (dialogue at the right edge, full height, `translateX` slide,
`prefers-reduced-motion` honoured).

| Check | Result |
|---|---|
| Geometry at 1024 × 768 | left 544, right 1024, top 0, bottom 768; the body scrolls inside |
| `role` / `aria-modal` / `aria-labelledby` | `dialog` / `true` / the title's id (core's) |
| Focus on open | the dialogue |
| Seven Tab presses | focus still inside (cycled back to the first button) |
| Escape | closes; focus returns to the opener |

For S5: the class was added after `create()`, so the first slide-in did not animate. Ship a
`core/modal` subclass with its own `TEMPLATE` that carries the class from the start, plus a
plugin-scoped stylesheet rule. No offcanvas, no new dependency.

## Dossier page with TCPDF (m503, PHP 8.4)

`pdf_sample.php` builds one worker's page (heading, identity line, a 7-row table with Portuguese
text, a QR code to a verification URL with a 128-bit code) and then a ZIP with 50 of them through
`\core_files\archive_writer`. Rendered page: [`sample-page.png`](sample-page.png) (the PDF beside
it can be opened and its QR scanned with a phone).

| Font | One page | 50 workers | ZIP | Peak memory |
|---|---|---|---|---|
| `freesans` (TrueType, subset embedded) | 88 ms, 150 KB | 4.4 s | 6.9 MB | 46 MB |
| `helvetica` (PDF core font, Latin-1) | 57 ms, 73 KB | 2.8 s | 3.3 MB | 44 MB |

Both render ç, ã, ê and í. **Recommendation for S7:** helvetica when every string on the page
fits Latin-1, freesans otherwise. A name outside Latin-1 then still prints, and most dossiers are
half the size. At ~60 ms a worker, the sync threshold of 10 workers (D9) costs under a second of
PDF work. 50 workers already take 3–4 s, which confirms that larger selections belong in the
ad hoc task.

Traps, both of which belong in S7's code comments and tests:

- **`archive_writer::get_file_writer()` takes a file name, not a path.** It opens
  `make_request_directory() . '/' . $name`. Given `/tmp/x/sample.zip`, `fopen()` fails and
  ZipStream silently falls back to **stdout**: the first run printed the binary ZIP into the CLI
  output and left no file. Pass a bare name, read `get_path_to_zip()`, and copy the file into file
  storage before the request (or task) ends, since the request directory goes with it.
- **TCPDF's `writeHTML()` lays out each table row on its own.** The first sample gave widths only
  to the header, and the body columns drifted from it. Every cell of every row needs its `width`.

The QR code was rendered and inspected, not machine-decoded (no decoder on this host). S7's test
asserts the URL handed to `write2DBarcode()`; a phone scan of `sample-page.pdf` settles decoding
by hand.

## Reproduce

```sh
# PostgreSQL (inside m503's database container, separate database)
docker exec m503-db-1 psql -U moodle -d moodle -c "CREATE DATABASE spike_evh"
docker exec -i m503-db-1 psql -U moodle -d spike_evh -q < schema_pg.sql
docker exec -i m503-db-1 psql -U moodle -d spike_evh -q < seed_pg.sql
python3 -I run.py pg

# MariaDB (throwaway container)
docker run -d --name spike-evh-mariadb -e MARIADB_ROOT_PASSWORD=spike -e MARIADB_DATABASE=spike mariadb:12.3 \
  --character-set-server=utf8mb4 --collation-server=utf8mb4_unicode_ci
docker exec -i spike-evh-mariadb mariadb -uroot -pspike spike < schema_my.sql
docker exec -i spike-evh-mariadb mariadb -uroot -pspike spike < seed_my.sql
python3 -I run.py my

# PDF and ZIP (any 5.3 stack that mounts local_dimensions)
docker exec -u www-data m503-webserver-1 php \
  /var/www/html/public/local/dimensions/docs/proposals/2026-10-29-evidence-hub/spike/pdf_sample.php 50 /tmp/evh helvetica

# Clean up
docker rm -f spike-evh-mariadb
docker exec m503-db-1 psql -U moodle -d moodle -c "DROP DATABASE spike_evh"
```

`run.py pg|my --plan Q11d` prints that query's full plan.
