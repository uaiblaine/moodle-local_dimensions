# Showcase tools

How the demo site behind the README pictures of `local_dimensions` and `block_dimensions` is
built. Development sites only; written for the m503 stack (`http://localhost:8503`, container
`m503-webserver-1`, plugin at `/var/www/html/public/local/dimensions`).

| File | What it does |
|---|---|
| `showcase-data.json` | The single source: palettes, scales, 18 frameworks (165 competencies), 21 plan templates, 12 courses, 10 cohorts, 2 reviewer roles, learners and demo.student's story. Both scripts below read it. |
| `imagery/render.mjs` | Renders one picture per template, competency and new course from SVG compositions (category gradient, an abstract motif per category, a Font Awesome glyph per topic) to JPEG with headless Chrome. Output in `imagery/out/` (ignored by git). |
| `seed_showcase.php` | Idempotent CLI seeder: builds everything in the JSON through core's competency, cohort, course and enrolment APIs and this plugin's custom-field handlers, then stores the pictures. |
| `session.php` | Prints a session cookie for a user without a password (`--username=`, `--theme=boost`), or ends one (`--destroy=SID`). |

## Building the showcase

```sh
node docs/screenshots/tools/imagery/render.mjs          # about 30 s; --force re-renders, --svg keeps the sources
docker exec m503-webserver-1 php public/local/dimensions/docs/screenshots/tools/seed_showcase.php
```

The seeder expects the demo accounts and categories of block_compass' `seed_demo.php`
(`demo.student`, `demo.teacher`, eight classmates, the "Educator Academy" tree); it creates the
categories it lacks and stops on a missing user. Its own accounts (`showcase.*`) get random
passwords that nobody records: sign in through `session.php`.

Everything it owns is found again by an idnumber starting with `SC-` (frameworks, competencies,
the template identifier field) or `showcase` (courses, course modules, cohorts, users), so a
second run creates nothing. It appends "Digital" and "Safety" to the plugin's tag selects when
they are missing and never touches records it did not create.

## The story the data tells

- **Frameworks**: four at the system level (values, digital capability, leadership, wellbeing) and
  fourteen in course categories (teaching, assessment, digital pedagogy, online facilitation,
  leadership, coaching, inclusion, safeguarding, induction, health, technology). Each is a
  two-level tree on one of three scales; most domains carry an "all children" or "points" rule,
  and related competencies link practices inside a framework.
- **Templates** carry every local_dimensions field: display mode, subline source, colours that
  pass WCAG AA, tags, type, identifier, related-competency switches, locked-card mode, a card
  image and a background image.
- **Cohorts and roles**: plans come from cohort sync ("Early-career teachers 2026" is synced to
  "Induction pathway 2026" and "Welcome to the Academy"); tool_cohortroles gives "Mentor" and
  "Head of department" roles over cohorts, so Riley Carter and Nadia Rahman review the
  early-career teachers and Jordan Lee the teaching and leadership cohorts.
- **Courses**: twelve new courses with sections and activities linked to competencies (module
  competencies with "evidence" or "complete" outcomes), plus links on the existing demo courses.
  Completing an activity produced the evidence and the default ratings; reviewers' ratings and
  notes fill in the rest.
- **demo.student** (Alex Morgan) holds plans in every status: active, completed, draft, waiting for
  review and in review, prior-learning evidence, two competency review requests, and favourites
  on plans and competencies.

## Looking at pages

```sh
T=/var/www/html/public/local/dimensions/docs/screenshots/tools
docker exec m503-webserver-1 php $T/session.php --username=demo.student --theme=boost
# prints MoodleSession503=<sid>; set it as a cookie for localhost:8503, or pass it as COOKIE= to
# moodle-dev/docs/filter-line/shoot.mjs; end it afterwards with
docker exec m503-webserver-1 php $T/session.php --destroy=<sid>
```
