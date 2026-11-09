-- S0 spike seed (PostgreSQL). Shape:
--   50,000 users; workers are ids 1..10,000 (2% suspended), the rest is site noise.
--   60 competencies: 1..30 regulatory, 31..45 technical, 46..60 behavioural (three frameworks).
--   Template 1 ("all workers, 30 competencies"): the worst case of the proposal, 300,000 pairs.
--   Templates 2..21: real-sized contracts, 500 workers x 15 competencies each.
--   5,000 completed plans of template 1 for non-workers (must be filtered out).
--   200 team cohorts of 50 workers, 400 noise cohorts of other users.
--   Evidence for 94% of required pairs, 1-3 records each (history), one link per record.
--   Status rows materialised with a realistic spread; "missing" pairs have no row.
SELECT setseed(0.42);

INSERT INTO m_user (id, username, firstname, lastname, idnumber, suspended)
SELECT g, 'u' || g, 'First' || (g % 997), 'Last' || (g % 1499), lpad(g::text, 6, '0'),
       CASE WHEN g <= 10000 AND random() < 0.02 THEN 1 ELSE 0 END
  FROM generate_series(1, 50000) g;

INSERT INTO m_competency (id, competencyframeworkid, shortname, idnumber)
SELECT g, CASE WHEN g <= 30 THEN 1 WHEN g <= 45 THEN 2 ELSE 3 END, 'Competency ' || g, 'C' || g
  FROM generate_series(1, 60) g;

INSERT INTO m_competency_templatecomp (templateid, competencyid) SELECT 1, g FROM generate_series(1, 30) g;
INSERT INTO m_competency_templatecomp (templateid, competencyid)
SELECT DISTINCT t, c FROM (
  SELECT t, ((t * 7 + j) % 30) + 1 AS c FROM generate_series(2, 21) t, generate_series(0, 9) j
  UNION ALL
  SELECT t, ((t * 5 + j) % 30) + 31 FROM generate_series(2, 21) t, generate_series(0, 4) j
) x;

-- Plans: status 1 = active, 2 = complete (core plan::STATUS_*).
INSERT INTO m_competency_plan (userid, templateid, status) SELECT g, 1, 1 FROM generate_series(1, 10000) g;
INSERT INTO m_competency_plan (userid, templateid, status)
SELECT ((t - 2) * 500) + j, t, 1 FROM generate_series(2, 21) t, generate_series(1, 500) j;
INSERT INTO m_competency_plan (userid, templateid, status) SELECT g, 1, 2 FROM generate_series(10001, 15000) g;

INSERT INTO m_cohort_members (cohortid, userid)
SELECT k, ((k - 1) * 50) + j FROM generate_series(1, 200) k, generate_series(1, 50) j;
INSERT INTO m_cohort_members (cohortid, userid)
SELECT 200 + k, 10000 + ((k - 1) * 100) + j FROM generate_series(1, 400) k, generate_series(1, 100) j;

-- Required pairs of active plans.
CREATE TEMP TABLE pairs AS
SELECT DISTINCT p.userid, tc.competencyid
  FROM m_competency_plan p JOIN m_competency_templatecomp tc ON tc.templateid = p.templateid
 WHERE p.status = 1;

CREATE TEMP TABLE evpairs AS
SELECT userid, competencyid, 1 + floor(random() * 3)::int AS n FROM pairs WHERE random() < 0.94;

CREATE TEMP TABLE ev AS
SELECT row_number() OVER () AS id, e.userid, e.competencyid, r, e.n
  FROM evpairs e, generate_series(1, 3) r WHERE r <= e.n;

INSERT INTO m_competency_userevidence (id, userid, name) SELECT id, userid, 'Evidence ' || id FROM ev;
INSERT INTO m_competency_userevidencecomp (userevidenceid, competencyid) SELECT id, competencyid FROM ev;

INSERT INTO m_local_evidencehub_meta (userevidenceid, userid, typeid, issuedate, expirydate, noexpiry, issuer,
                                      authcode, hours, verifystatus, timecreated, timemodified, usermodified)
SELECT id, userid, 1 + (competencyid % 5),
       to_char(d, 'YYYYMMDD')::int,
       CASE WHEN competencyid BETWEEN 31 AND 45 AND random() < 0.05 THEN NULL
            ELSE to_char(d + interval '730 days', 'YYYYMMDD')::int END,
       0, 'Issuer ' || (id % 40), 'AUTH-' || id, 8,
       CASE WHEN random() < 0.92 THEN 1 ELSE 0 END, 1761700000, 1761700000, 2
  FROM (SELECT ev.*, (current_date - ((n - r) * 730 + floor(random() * 700)::int)) AS d FROM ev) x;
UPDATE m_local_evidencehub_meta SET noexpiry = 1 WHERE expirydate IS NULL;

INSERT INTO m_local_evidencehub_status (userid, competencyid, status, metaid, effectiveexpiry, evidencecount,
                                        nexttransition, timechanged, timecomputed)
SELECT e.userid, e.competencyid,
       CASE WHEN x < 0.78 THEN 2 WHEN x < 0.85 THEN 3 WHEN x < 0.89 THEN 4 WHEN x < 0.95 THEN 5 ELSE 1 END,
       m.id, m.expirydate, e.n,
       extract(epoch FROM now())::bigint + floor(random() * 63072000)::bigint, 1761700000, 1761700000
  FROM (SELECT userid, competencyid, max(n) AS n, max(id) AS evid, random() AS x
          FROM ev GROUP BY userid, competencyid) e
  JOIN m_local_evidencehub_meta m ON m.userevidenceid = e.evid;

SELECT setval('m_user_id_seq', 50000);
ANALYZE;

SELECT 'users' AS t, count(*) FROM m_user UNION ALL
SELECT 'plans', count(*) FROM m_competency_plan UNION ALL
SELECT 'required pairs', count(*) FROM pairs UNION ALL
SELECT 'evidence', count(*) FROM m_competency_userevidence UNION ALL
SELECT 'meta', count(*) FROM m_local_evidencehub_meta UNION ALL
SELECT 'status', count(*) FROM m_local_evidencehub_status;
