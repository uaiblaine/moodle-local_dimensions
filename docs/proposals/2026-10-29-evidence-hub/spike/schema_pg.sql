-- S0 spike schema (PostgreSQL). Core tables carry only the columns the hub reads, with the indexes
-- core 5.3 declares for them (pg_dump of m503, 2026-10-29). The two companion tables are the ones
-- proposed in ../README.md, "Data model". Prefix m_ as on the stacks' live sites.

DROP TABLE IF EXISTS m_user, m_cohort_members, m_competency, m_competency_plan, m_competency_templatecomp,
  m_competency_userevidence, m_competency_userevidencecomp, m_local_evidencehub_meta,
  m_local_evidencehub_status CASCADE;

CREATE TABLE m_user (
  id bigserial PRIMARY KEY, mnethostid bigint NOT NULL DEFAULT 1, username varchar(100) NOT NULL,
  deleted smallint NOT NULL DEFAULT 0, suspended smallint NOT NULL DEFAULT 0,
  firstname varchar(100) NOT NULL, lastname varchar(100) NOT NULL, idnumber varchar(255) NOT NULL DEFAULT ''
);
CREATE INDEX m_user_del_ix ON m_user (deleted);
CREATE INDEX m_user_fir_ix ON m_user (firstname);
CREATE INDEX m_user_las_ix ON m_user (lastname);
CREATE INDEX m_user_idn_ix ON m_user (idnumber);
CREATE UNIQUE INDEX m_user_mneuse_uix ON m_user (mnethostid, username);

CREATE TABLE m_cohort_members (id bigserial PRIMARY KEY, cohortid bigint NOT NULL, userid bigint NOT NULL);
CREATE INDEX m_cohomemb_coh_ix ON m_cohort_members (cohortid);
CREATE UNIQUE INDEX m_cohomemb_cohuse_uix ON m_cohort_members (cohortid, userid);
CREATE INDEX m_cohomemb_use_ix ON m_cohort_members (userid);

CREATE TABLE m_competency (
  id bigserial PRIMARY KEY, competencyframeworkid bigint NOT NULL, shortname varchar(100), idnumber varchar(100)
);
CREATE UNIQUE INDEX m_comp_comidn_uix ON m_competency (competencyframeworkid, idnumber);

CREATE TABLE m_competency_plan (
  id bigserial PRIMARY KEY, userid bigint NOT NULL, templateid bigint, status smallint NOT NULL, duedate bigint DEFAULT 0
);
CREATE INDEX m_compplan_stadue_ix ON m_competency_plan (status, duedate);
CREATE INDEX m_compplan_tem_ix ON m_competency_plan (templateid);
CREATE INDEX m_compplan_usesta_ix ON m_competency_plan (userid, status);

CREATE TABLE m_competency_templatecomp (id bigserial PRIMARY KEY, templateid bigint NOT NULL, competencyid bigint NOT NULL);
CREATE INDEX m_comptemp_com_ix ON m_competency_templatecomp (competencyid);
CREATE INDEX m_comptemp_tem_ix ON m_competency_templatecomp (templateid);

CREATE TABLE m_competency_userevidence (id bigserial PRIMARY KEY, userid bigint NOT NULL, name varchar(100) NOT NULL);
CREATE INDEX m_compuser_use4_ix ON m_competency_userevidence (userid);

CREATE TABLE m_competency_userevidencecomp (
  id bigserial PRIMARY KEY, userevidenceid bigint NOT NULL, competencyid bigint NOT NULL
);
CREATE INDEX m_compuser_use6_ix ON m_competency_userevidencecomp (userevidenceid);
CREATE UNIQUE INDEX m_compuser_usecom2_uix ON m_competency_userevidencecomp (userevidenceid, competencyid);

-- Companion tables (proposal). Dates are calendar dates as YYYYMMDD integers.
CREATE TABLE m_local_evidencehub_meta (
  id bigserial PRIMARY KEY, userevidenceid bigint NOT NULL, userid bigint NOT NULL, typeid bigint NOT NULL,
  issuedate integer NOT NULL, expirydate integer, noexpiry smallint NOT NULL DEFAULT 0,
  issuer varchar(255) NOT NULL, authcode varchar(255) NOT NULL DEFAULT '', hours numeric(10,2),
  verifystatus smallint NOT NULL DEFAULT 0, verifiedby bigint, timeverified bigint,
  timecreated bigint NOT NULL, timemodified bigint NOT NULL, usermodified bigint NOT NULL
);
CREATE UNIQUE INDEX m_locaevidmeta_use_uix ON m_local_evidencehub_meta (userevidenceid);
CREATE INDEX m_locaevidmeta_use2_ix ON m_local_evidencehub_meta (userid);
CREATE INDEX m_locaevidmeta_exp_ix ON m_local_evidencehub_meta (expirydate);

-- status: 1 unverified, 2 valid, 3 due60, 4 due30, 5 expired; "missing" (0) has no row.
CREATE TABLE m_local_evidencehub_status (
  id bigserial PRIMARY KEY, userid bigint NOT NULL, competencyid bigint NOT NULL, status smallint NOT NULL,
  metaid bigint, effectiveexpiry integer, evidencecount integer NOT NULL DEFAULT 0,
  nexttransition bigint, timechanged bigint NOT NULL, timecomputed bigint NOT NULL
);
CREATE UNIQUE INDEX m_locaevidstat_usecom_uix ON m_local_evidencehub_status (userid, competencyid);
CREATE INDEX m_locaevidstat_comsta_ix ON m_local_evidencehub_status (competencyid, status);
CREATE INDEX m_locaevidstat_nex_ix ON m_local_evidencehub_status (nexttransition);
