-- S0 spike seed (MariaDB), the same shape as seed_pg.sql. Random values are materialised in
-- helper tables first: a derived table may be merged into the outer query, and every reference to
-- one of its RAND() columns would then draw a new number.
SET @seed = RAND(42);

INSERT INTO m_user (id, username, firstname, lastname, idnumber, suspended)
SELECT seq, CONCAT('u', seq), CONCAT('First', seq % 997), CONCAT('Last', seq % 1499), LPAD(seq, 6, '0'),
       IF(seq <= 10000 AND RAND() < 0.02, 1, 0)
  FROM seq_1_to_50000;

INSERT INTO m_competency (id, competencyframeworkid, shortname, idnumber)
SELECT seq, CASE WHEN seq <= 30 THEN 1 WHEN seq <= 45 THEN 2 ELSE 3 END, CONCAT('Competency ', seq), CONCAT('C', seq)
  FROM seq_1_to_60;

INSERT INTO m_competency_templatecomp (templateid, competencyid) SELECT 1, seq FROM seq_1_to_30;
INSERT INTO m_competency_templatecomp (templateid, competencyid)
SELECT DISTINCT t, c FROM (
  SELECT t.seq AS t, ((t.seq * 7 + j.seq) % 30) + 1 AS c FROM seq_2_to_21 t, seq_0_to_9 j
  UNION ALL
  SELECT t.seq, ((t.seq * 5 + j.seq) % 30) + 31 FROM seq_2_to_21 t, seq_0_to_4 j
) x;

INSERT INTO m_competency_plan (userid, templateid, status) SELECT seq, 1, 1 FROM seq_1_to_10000;
INSERT INTO m_competency_plan (userid, templateid, status)
SELECT ((t.seq - 2) * 500) + j.seq, t.seq, 1 FROM seq_2_to_21 t, seq_1_to_500 j;
INSERT INTO m_competency_plan (userid, templateid, status) SELECT seq, 1, 2 FROM seq_10001_to_15000;

INSERT INTO m_cohort_members (cohortid, userid)
SELECT k.seq, ((k.seq - 1) * 50) + j.seq FROM seq_1_to_200 k, seq_1_to_50 j;
INSERT INTO m_cohort_members (cohortid, userid)
SELECT 200 + k.seq, 10000 + ((k.seq - 1) * 100) + j.seq FROM seq_1_to_400 k, seq_1_to_100 j;

DROP TABLE IF EXISTS tmp_pairs, tmp_evpairs, tmp_ev;
CREATE TABLE tmp_pairs AS
SELECT DISTINCT p.userid, tc.competencyid
  FROM m_competency_plan p JOIN m_competency_templatecomp tc ON tc.templateid = p.templateid
 WHERE p.status = 1;

CREATE TABLE tmp_evpairs AS
SELECT userid, competencyid, 1 + FLOOR(RAND() * 3) AS n, RAND() AS x, RAND() AS keep FROM tmp_pairs;
DELETE FROM tmp_evpairs WHERE keep >= 0.94;

CREATE TABLE tmp_ev AS
SELECT ROW_NUMBER() OVER (ORDER BY e.userid, e.competencyid, r.seq) AS id, e.userid, e.competencyid, r.seq AS r, e.n,
       CURDATE() - INTERVAL ((e.n - r.seq) * 730 + FLOOR(RAND() * 700)) DAY AS d, RAND() AS noexp, RAND() AS ver
  FROM tmp_evpairs e JOIN seq_1_to_3 r ON r.seq <= e.n;

INSERT INTO m_competency_userevidence (id, userid, name) SELECT id, userid, CONCAT('Evidence ', id) FROM tmp_ev;
INSERT INTO m_competency_userevidencecomp (userevidenceid, competencyid) SELECT id, competencyid FROM tmp_ev;

INSERT INTO m_local_evidencehub_meta (userevidenceid, userid, typeid, issuedate, expirydate, noexpiry, issuer,
                                      authcode, hours, verifystatus, timecreated, timemodified, usermodified)
SELECT id, userid, 1 + (competencyid % 5), DATE_FORMAT(d, '%Y%m%d') + 0,
       IF(competencyid BETWEEN 31 AND 45 AND noexp < 0.05, NULL, DATE_FORMAT(d + INTERVAL 730 DAY, '%Y%m%d') + 0),
       IF(competencyid BETWEEN 31 AND 45 AND noexp < 0.05, 1, 0),
       CONCAT('Issuer ', id % 40), CONCAT('AUTH-', id), 8, IF(ver < 0.92, 1, 0), 1761700000, 1761700000, 2
  FROM tmp_ev;

INSERT INTO m_local_evidencehub_status (userid, competencyid, status, metaid, effectiveexpiry, evidencecount,
                                        nexttransition, timechanged, timecomputed)
SELECT e.userid, e.competencyid,
       CASE WHEN p.x < 0.78 THEN 2 WHEN p.x < 0.85 THEN 3 WHEN p.x < 0.89 THEN 4 WHEN p.x < 0.95 THEN 5 ELSE 1 END,
       m.id, m.expirydate, e.n, UNIX_TIMESTAMP() + FLOOR(RAND() * 63072000), 1761700000, 1761700000
  FROM (SELECT userid, competencyid, MAX(n) AS n, MAX(id) AS evid FROM tmp_ev GROUP BY userid, competencyid) e
  JOIN tmp_evpairs p ON p.userid = e.userid AND p.competencyid = e.competencyid
  JOIN m_local_evidencehub_meta m ON m.userevidenceid = e.evid;

ANALYZE TABLE m_user, m_cohort_members, m_competency, m_competency_plan, m_competency_templatecomp,
  m_competency_userevidence, m_competency_userevidencecomp, m_local_evidencehub_meta, m_local_evidencehub_status;

SELECT 'users' AS t, COUNT(*) FROM m_user UNION ALL
SELECT 'plans', COUNT(*) FROM m_competency_plan UNION ALL
SELECT 'required pairs', COUNT(*) FROM tmp_pairs UNION ALL
SELECT 'evidence', COUNT(*) FROM m_competency_userevidence UNION ALL
SELECT 'meta', COUNT(*) FROM m_local_evidencehub_meta UNION ALL
SELECT 'status', COUNT(*) FROM m_local_evidencehub_status;
DROP TABLE tmp_pairs, tmp_evpairs, tmp_ev;
