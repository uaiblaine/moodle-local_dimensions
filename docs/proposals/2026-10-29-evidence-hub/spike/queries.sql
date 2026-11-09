-- S0 spike queries, portable SQL (PostgreSQL and MariaDB). Each one is what the hub would send
-- through $DB with placeholders bound: {CAPIN} is the IN list that
-- api::filter_users_with_capability_on_user_context_sql() returns for a viewer holding
-- userevidenceview only in 500 user contexts (tool_cohortroles); a system manager gets "> 0".
-- Template 1 = worst case (10,000 workers x 30 competencies); template 5 = a real contract
-- (500 x 15), cohort 33 = one team of 50 inside it. Status codes: 1 unverified, 2 valid,
-- 3 due60, 4 due30, 5 expired; no row = missing.

-- name: Q1 grid, worst case, no filter, sort by name, first page
SELECT u.id AS userid, u.firstname, u.lastname, u.idnumber, c.id AS competencyid, c.shortname, c.idnumber AS cidnumber,
       COALESCE(s.status, 0) AS status, s.effectiveexpiry, s.evidencecount, m.typeid, m.issuer, m.authcode, m.issuedate
  FROM m_competency_plan p
  JOIN m_user u ON u.id = p.userid AND u.deleted = 0 AND u.suspended = 0
  JOIN m_competency_templatecomp tc ON tc.templateid = p.templateid
  JOIN m_competency c ON c.id = tc.competencyid
  LEFT JOIN m_local_evidencehub_status s ON s.userid = p.userid AND s.competencyid = tc.competencyid
  LEFT JOIN m_local_evidencehub_meta m ON m.id = s.metaid
 WHERE p.templateid = 1 AND p.status = 1
 ORDER BY u.lastname, u.firstname, u.id, c.shortname
 LIMIT 50;

-- name: Q1c count for Q1's pager
SELECT COUNT(1)
  FROM m_competency_plan p
  JOIN m_user u ON u.id = p.userid AND u.deleted = 0 AND u.suspended = 0
  JOIN m_competency_templatecomp tc ON tc.templateid = p.templateid
 WHERE p.templateid = 1 AND p.status = 1;

-- name: Q2 grid, worst case, status Due, sort by expiry
SELECT u.id AS userid, u.firstname, u.lastname, c.id AS competencyid, c.shortname,
       s.status, s.effectiveexpiry, s.evidencecount, m.issuer, m.authcode
  FROM m_competency_plan p
  JOIN m_user u ON u.id = p.userid AND u.deleted = 0 AND u.suspended = 0
  JOIN m_competency_templatecomp tc ON tc.templateid = p.templateid
  JOIN m_competency c ON c.id = tc.competencyid
  JOIN m_local_evidencehub_status s ON s.userid = p.userid AND s.competencyid = tc.competencyid
  LEFT JOIN m_local_evidencehub_meta m ON m.id = s.metaid
 WHERE p.templateid = 1 AND p.status = 1 AND s.status IN (3, 4)
 ORDER BY s.effectiveexpiry, u.lastname, u.id
 LIMIT 50;

-- name: Q3 grid, worst case, status Pending (missing or unverified)
SELECT u.id AS userid, u.lastname, c.id AS competencyid, COALESCE(s.status, 0) AS status, s.evidencecount
  FROM m_competency_plan p
  JOIN m_user u ON u.id = p.userid AND u.deleted = 0 AND u.suspended = 0
  JOIN m_competency_templatecomp tc ON tc.templateid = p.templateid
  JOIN m_competency c ON c.id = tc.competencyid
  LEFT JOIN m_local_evidencehub_status s ON s.userid = p.userid AND s.competencyid = tc.competencyid
 WHERE p.templateid = 1 AND p.status = 1 AND (s.id IS NULL OR s.status = 1)
 ORDER BY u.lastname, u.id, c.id
 LIMIT 50;

-- name: Q4 KPI status distribution, worst case
SELECT COALESCE(s.status, 0) AS status, COUNT(1) AS pairs
  FROM m_competency_plan p
  JOIN m_user u ON u.id = p.userid AND u.deleted = 0 AND u.suspended = 0
  JOIN m_competency_templatecomp tc ON tc.templateid = p.templateid
  LEFT JOIN m_local_evidencehub_status s ON s.userid = p.userid AND s.competencyid = tc.competencyid
 WHERE p.templateid = 1 AND p.status = 1
 GROUP BY COALESCE(s.status, 0);

-- name: Q5 KPI readiness rate, worst case
SELECT COUNT(1) AS workers, SUM(CASE WHEN x.notready = 0 THEN 1 ELSE 0 END) AS ready
  FROM (SELECT p.userid, SUM(CASE WHEN s.status IN (2, 3, 4) THEN 0 ELSE 1 END) AS notready
          FROM m_competency_plan p
          JOIN m_user u ON u.id = p.userid AND u.deleted = 0 AND u.suspended = 0
          JOIN m_competency_templatecomp tc ON tc.templateid = p.templateid
          LEFT JOIN m_local_evidencehub_status s ON s.userid = p.userid AND s.competencyid = tc.competencyid
         WHERE p.templateid = 1 AND p.status = 1
         GROUP BY p.userid) x;

-- name: Q6 grid, real contract + team cohort, sort by expiry
SELECT u.id AS userid, u.firstname, u.lastname, c.id AS competencyid, c.shortname,
       COALESCE(s.status, 0) AS status, s.effectiveexpiry, m.issuer
  FROM m_competency_plan p
  JOIN m_user u ON u.id = p.userid AND u.deleted = 0 AND u.suspended = 0
  JOIN m_competency_templatecomp tc ON tc.templateid = p.templateid
  JOIN m_competency c ON c.id = tc.competencyid
  LEFT JOIN m_local_evidencehub_status s ON s.userid = p.userid AND s.competencyid = tc.competencyid
  LEFT JOIN m_local_evidencehub_meta m ON m.id = s.metaid
 WHERE p.templateid = 5 AND p.status = 1
   AND EXISTS (SELECT 1 FROM m_cohort_members cm WHERE cm.userid = p.userid AND cm.cohortid = 33)
 ORDER BY COALESCE(s.effectiveexpiry, 99999999), u.lastname, u.id
 LIMIT 50;

-- name: Q7 KPI readiness, real contract + team cohort
SELECT COUNT(1) AS workers, SUM(CASE WHEN x.notready = 0 THEN 1 ELSE 0 END) AS ready
  FROM (SELECT p.userid, SUM(CASE WHEN s.status IN (2, 3, 4) THEN 0 ELSE 1 END) AS notready
          FROM m_competency_plan p
          JOIN m_user u ON u.id = p.userid AND u.deleted = 0 AND u.suspended = 0
          JOIN m_competency_templatecomp tc ON tc.templateid = p.templateid
          LEFT JOIN m_local_evidencehub_status s ON s.userid = p.userid AND s.competencyid = tc.competencyid
         WHERE p.templateid = 5 AND p.status = 1
           AND EXISTS (SELECT 1 FROM m_cohort_members cm WHERE cm.userid = p.userid AND cm.cohortid = 33)
         GROUP BY p.userid) x;

-- name: Q8 grid, worst case, viewer with 500 user-context grants, sort by expiry
SELECT u.id AS userid, u.lastname, c.id AS competencyid, COALESCE(s.status, 0) AS status, s.effectiveexpiry
  FROM m_competency_plan p
  JOIN m_user u ON u.id = p.userid AND u.deleted = 0 AND u.suspended = 0
  JOIN m_competency_templatecomp tc ON tc.templateid = p.templateid
  JOIN m_competency c ON c.id = tc.competencyid
  LEFT JOIN m_local_evidencehub_status s ON s.userid = p.userid AND s.competencyid = tc.competencyid
 WHERE p.templateid = 1 AND p.status = 1 AND u.id {CAPIN}
 ORDER BY COALESCE(s.effectiveexpiry, 99999999), u.lastname, u.id
 LIMIT 50;

-- name: Q9 scheduled task, rows whose next transition has passed
SELECT id, userid, competencyid FROM m_local_evidencehub_status WHERE nexttransition <= {NOWPLUSDAY};

-- name: Q10 engine recompute input for one worker (all linked evidence with details)
SELECT uec.competencyid, m.id, m.issuedate, m.expirydate, m.noexpiry, m.verifystatus, m.hours
  FROM m_competency_userevidence ue
  JOIN m_competency_userevidencecomp uec ON uec.userevidenceid = ue.id
  LEFT JOIN m_local_evidencehub_meta m ON m.userevidenceid = ue.id
 WHERE ue.userid = 4242;

-- name: Q1d grid, worst case, sort by name, deferred join (page the narrow key, then join the details)
SELECT k.userid, u.firstname, u.lastname, u.idnumber, c.id AS competencyid, c.shortname, c.idnumber AS cidnumber,
       COALESCE(s.status, 0) AS status, s.effectiveexpiry, s.evidencecount, m.typeid, m.issuer, m.authcode, m.issuedate
  FROM (SELECT p.userid, tc.competencyid, u2.lastname, u2.firstname
          FROM m_competency_plan p
          JOIN m_user u2 ON u2.id = p.userid AND u2.deleted = 0 AND u2.suspended = 0
          JOIN m_competency_templatecomp tc ON tc.templateid = p.templateid
         WHERE p.templateid = 1 AND p.status = 1
         ORDER BY u2.lastname, u2.firstname, p.userid, tc.competencyid
         LIMIT 50) k
  JOIN m_user u ON u.id = k.userid
  JOIN m_competency c ON c.id = k.competencyid
  LEFT JOIN m_local_evidencehub_status s ON s.userid = k.userid AND s.competencyid = k.competencyid
  LEFT JOIN m_local_evidencehub_meta m ON m.id = s.metaid
 ORDER BY k.lastname, k.firstname, k.userid, k.competencyid;

-- name: Q1w grid, worst case, paged by worker: first 20 workers by name (their pairs follow in one IN query)
SELECT u.id, u.firstname, u.lastname, u.idnumber
  FROM m_competency_plan p
  JOIN m_user u ON u.id = p.userid AND u.deleted = 0 AND u.suspended = 0
 WHERE p.templateid = 1 AND p.status = 1
 ORDER BY u.lastname, u.firstname, u.id
 LIMIT 20;

-- name: Q2b grid, worst case, no filter, sort by expiry (most urgent first)
SELECT u.id AS userid, u.lastname, c.id AS competencyid, COALESCE(s.status, 0) AS status, s.effectiveexpiry, m.issuer
  FROM m_competency_plan p
  JOIN m_user u ON u.id = p.userid AND u.deleted = 0 AND u.suspended = 0
  JOIN m_competency_templatecomp tc ON tc.templateid = p.templateid
  JOIN m_competency c ON c.id = tc.competencyid
  LEFT JOIN m_local_evidencehub_status s ON s.userid = p.userid AND s.competencyid = tc.competencyid
  LEFT JOIN m_local_evidencehub_meta m ON m.id = s.metaid
 WHERE p.templateid = 1 AND p.status = 1
 ORDER BY COALESCE(s.effectiveexpiry, 0), u.lastname, u.id
 LIMIT 50;

-- name: Q2d grid, worst case, no filter, sort by expiry, deferred join
SELECT k.userid, u.lastname, c.id AS competencyid, COALESCE(s.status, 0) AS status, k.sortexpiry, m.issuer
  FROM (SELECT p.userid, tc.competencyid, COALESCE(s2.effectiveexpiry, 0) AS sortexpiry
          FROM m_competency_plan p
          JOIN m_user u2 ON u2.id = p.userid AND u2.deleted = 0 AND u2.suspended = 0
          JOIN m_competency_templatecomp tc ON tc.templateid = p.templateid
          LEFT JOIN m_local_evidencehub_status s2 ON s2.userid = p.userid AND s2.competencyid = tc.competencyid
         WHERE p.templateid = 1 AND p.status = 1
         ORDER BY COALESCE(s2.effectiveexpiry, 0), p.userid, tc.competencyid
         LIMIT 50) k
  JOIN m_user u ON u.id = k.userid
  JOIN m_competency c ON c.id = k.competencyid
  LEFT JOIN m_local_evidencehub_status s ON s.userid = k.userid AND s.competencyid = k.competencyid
  LEFT JOIN m_local_evidencehub_meta m ON m.id = s.metaid
 ORDER BY k.sortexpiry, k.userid, k.competencyid;

-- name: Q1wf paged by worker, worst case, workers with any Due pair, by name
SELECT u.id, u.firstname, u.lastname
  FROM m_competency_plan p
  JOIN m_user u ON u.id = p.userid AND u.deleted = 0 AND u.suspended = 0
 WHERE p.templateid = 1 AND p.status = 1
   AND EXISTS (SELECT 1 FROM m_local_evidencehub_status s
                 JOIN m_competency_templatecomp tc ON tc.competencyid = s.competencyid AND tc.templateid = p.templateid
                WHERE s.userid = p.userid AND s.status IN (3, 4))
 ORDER BY u.lastname, u.firstname, u.id
 LIMIT 20;

-- name: Q1wu paged by worker, worst case, most urgent workers first (earliest expiry among their pairs)
SELECT x.userid, MIN(x.e) AS urgency
  FROM (SELECT p.userid, COALESCE(s.effectiveexpiry, 0) AS e
          FROM m_competency_plan p
          JOIN m_user u ON u.id = p.userid AND u.deleted = 0 AND u.suspended = 0
          JOIN m_competency_templatecomp tc ON tc.templateid = p.templateid
          LEFT JOIN m_local_evidencehub_status s ON s.userid = p.userid AND s.competencyid = tc.competencyid
         WHERE p.templateid = 1 AND p.status = 1) x
 GROUP BY x.userid
 ORDER BY MIN(x.e), x.userid
 LIMIT 20;

-- name: Q11 grid, worst case, default view "needs attention" (pending, due, expired), sort by expiry
SELECT u.id AS userid, u.lastname, c.id AS competencyid, COALESCE(s.status, 0) AS status, s.effectiveexpiry, m.issuer
  FROM m_competency_plan p
  JOIN m_user u ON u.id = p.userid AND u.deleted = 0 AND u.suspended = 0
  JOIN m_competency_templatecomp tc ON tc.templateid = p.templateid
  JOIN m_competency c ON c.id = tc.competencyid
  LEFT JOIN m_local_evidencehub_status s ON s.userid = p.userid AND s.competencyid = tc.competencyid
  LEFT JOIN m_local_evidencehub_meta m ON m.id = s.metaid
 WHERE p.templateid = 1 AND p.status = 1 AND (s.id IS NULL OR s.status <> 2)
 ORDER BY COALESCE(s.effectiveexpiry, 0), u.lastname, u.id
 LIMIT 50;

-- name: Q12 system report shape: derived main table carrying the sort keys (set_main_table_sql, 5.3), entity joins on top, sort by expiry
SELECT b.userid, u.firstname, u.lastname, u.idnumber, c.shortname, b.status, b.sortexpiry, m.issuer, m.authcode
  FROM (SELECT p.userid, tc.competencyid, COALESCE(s.status, 0) AS status, COALESCE(s.effectiveexpiry, 0) AS sortexpiry,
               s.metaid, u2.lastname AS sortname
          FROM m_competency_plan p
          JOIN m_user u2 ON u2.id = p.userid AND u2.deleted = 0 AND u2.suspended = 0
          JOIN m_competency_templatecomp tc ON tc.templateid = p.templateid
          LEFT JOIN m_local_evidencehub_status s ON s.userid = p.userid AND s.competencyid = tc.competencyid
         WHERE p.templateid = 1 AND p.status = 1) b
  JOIN m_user u ON u.id = b.userid
  JOIN m_competency c ON c.id = b.competencyid
  LEFT JOIN m_local_evidencehub_meta m ON m.id = b.metaid
 ORDER BY b.sortexpiry, b.userid, b.competencyid
 LIMIT 50;

-- name: Q12a the same shape, default view "needs attention"
SELECT b.userid, u.firstname, u.lastname, u.idnumber, c.shortname, b.status, b.sortexpiry, m.issuer, m.authcode
  FROM (SELECT p.userid, tc.competencyid, COALESCE(s.status, 0) AS status, COALESCE(s.effectiveexpiry, 0) AS sortexpiry,
               s.metaid, u2.lastname AS sortname
          FROM m_competency_plan p
          JOIN m_user u2 ON u2.id = p.userid AND u2.deleted = 0 AND u2.suspended = 0
          JOIN m_competency_templatecomp tc ON tc.templateid = p.templateid
          LEFT JOIN m_local_evidencehub_status s ON s.userid = p.userid AND s.competencyid = tc.competencyid
         WHERE p.templateid = 1 AND p.status = 1) b
  JOIN m_user u ON u.id = b.userid
  JOIN m_competency c ON c.id = b.competencyid
  LEFT JOIN m_local_evidencehub_meta m ON m.id = b.metaid
 WHERE b.status <> 2
 ORDER BY b.sortexpiry, b.userid, b.competencyid
 LIMIT 50;

-- name: Q12n the same shape, sort by name
SELECT b.userid, u.firstname, u.lastname, u.idnumber, c.shortname, b.status, b.sortexpiry, m.issuer, m.authcode
  FROM (SELECT p.userid, tc.competencyid, COALESCE(s.status, 0) AS status, COALESCE(s.effectiveexpiry, 0) AS sortexpiry,
               s.metaid, u2.lastname AS sortname
          FROM m_competency_plan p
          JOIN m_user u2 ON u2.id = p.userid AND u2.deleted = 0 AND u2.suspended = 0
          JOIN m_competency_templatecomp tc ON tc.templateid = p.templateid
          LEFT JOIN m_local_evidencehub_status s ON s.userid = p.userid AND s.competencyid = tc.competencyid
         WHERE p.templateid = 1 AND p.status = 1) b
  JOIN m_user u ON u.id = b.userid
  JOIN m_competency c ON c.id = b.competencyid
  LEFT JOIN m_local_evidencehub_meta m ON m.id = b.metaid
 ORDER BY b.sortname, b.userid, b.competencyid
 LIMIT 50;

-- name: Q11d default view "needs attention", sort by expiry, deferred join (step 1: the page of keys)
SELECT p.userid, tc.competencyid, COALESCE(s.effectiveexpiry, 0) AS sortexpiry
  FROM m_competency_plan p
  JOIN m_user u ON u.id = p.userid AND u.deleted = 0 AND u.suspended = 0
  JOIN m_competency_templatecomp tc ON tc.templateid = p.templateid
  LEFT JOIN m_local_evidencehub_status s ON s.userid = p.userid AND s.competencyid = tc.competencyid
 WHERE p.templateid = 1 AND p.status = 1 AND (s.id IS NULL OR s.status <> 2)
 ORDER BY COALESCE(s.effectiveexpiry, 0), p.userid, tc.competencyid
 LIMIT 50;

-- name: Q11e step 2: details of that page's 50 pairs
SELECT u.id AS userid, u.firstname, u.lastname, u.idnumber, c.id AS competencyid, c.shortname,
       COALESCE(s.status, 0) AS status, s.effectiveexpiry, s.evidencecount, m.typeid, m.issuer, m.authcode, m.issuedate
  FROM m_user u
  JOIN m_competency c ON c.id IN (3, 7, 12, 19, 25)
  LEFT JOIN m_local_evidencehub_status s ON s.userid = u.id AND s.competencyid = c.id
  LEFT JOIN m_local_evidencehub_meta m ON m.id = s.metaid
 WHERE u.id IN (11, 57, 230, 498, 777, 1001, 1500, 2042, 3333, 4999);

-- name: Q11c count for the "needs attention" pager
SELECT COUNT(1)
  FROM m_competency_plan p
  JOIN m_user u ON u.id = p.userid AND u.deleted = 0 AND u.suspended = 0
  JOIN m_competency_templatecomp tc ON tc.templateid = p.templateid
  LEFT JOIN m_local_evidencehub_status s ON s.userid = p.userid AND s.competencyid = tc.competencyid
 WHERE p.templateid = 1 AND p.status = 1 AND (s.id IS NULL OR s.status <> 2);
