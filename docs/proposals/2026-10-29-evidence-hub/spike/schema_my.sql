-- S0 spike schema (MariaDB), the same tables and indexes as schema_pg.sql, typed as XMLDB
-- generates them for MySQL-family databases (InnoDB, utf8mb4, DYNAMIC rows).

DROP TABLE IF EXISTS m_user, m_cohort_members, m_competency, m_competency_plan, m_competency_templatecomp,
  m_competency_userevidence, m_competency_userevidencecomp, m_local_evidencehub_meta, m_local_evidencehub_status;

CREATE TABLE m_user (
  id bigint(10) NOT NULL AUTO_INCREMENT PRIMARY KEY, mnethostid bigint(10) NOT NULL DEFAULT 1,
  username varchar(100) NOT NULL, deleted tinyint(1) NOT NULL DEFAULT 0, suspended tinyint(1) NOT NULL DEFAULT 0,
  firstname varchar(100) NOT NULL, lastname varchar(100) NOT NULL, idnumber varchar(255) NOT NULL DEFAULT '',
  KEY m_user_del_ix (deleted), KEY m_user_fir_ix (firstname), KEY m_user_las_ix (lastname),
  KEY m_user_idn_ix (idnumber), UNIQUE KEY m_user_mneuse_uix (mnethostid, username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

CREATE TABLE m_cohort_members (
  id bigint(10) NOT NULL AUTO_INCREMENT PRIMARY KEY, cohortid bigint(10) NOT NULL, userid bigint(10) NOT NULL,
  KEY m_cohomemb_coh_ix (cohortid), UNIQUE KEY m_cohomemb_cohuse_uix (cohortid, userid), KEY m_cohomemb_use_ix (userid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

CREATE TABLE m_competency (
  id bigint(10) NOT NULL AUTO_INCREMENT PRIMARY KEY, competencyframeworkid bigint(10) NOT NULL,
  shortname varchar(100), idnumber varchar(100), UNIQUE KEY m_comp_comidn_uix (competencyframeworkid, idnumber)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

CREATE TABLE m_competency_plan (
  id bigint(10) NOT NULL AUTO_INCREMENT PRIMARY KEY, userid bigint(10) NOT NULL, templateid bigint(10),
  status tinyint(1) NOT NULL, duedate bigint(10) DEFAULT 0,
  KEY m_compplan_stadue_ix (status, duedate), KEY m_compplan_tem_ix (templateid), KEY m_compplan_usesta_ix (userid, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

CREATE TABLE m_competency_templatecomp (
  id bigint(10) NOT NULL AUTO_INCREMENT PRIMARY KEY, templateid bigint(10) NOT NULL, competencyid bigint(10) NOT NULL,
  KEY m_comptemp_com_ix (competencyid), KEY m_comptemp_tem_ix (templateid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

CREATE TABLE m_competency_userevidence (
  id bigint(10) NOT NULL AUTO_INCREMENT PRIMARY KEY, userid bigint(10) NOT NULL, name varchar(100) NOT NULL,
  KEY m_compuser_use4_ix (userid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

CREATE TABLE m_competency_userevidencecomp (
  id bigint(10) NOT NULL AUTO_INCREMENT PRIMARY KEY, userevidenceid bigint(10) NOT NULL, competencyid bigint(10) NOT NULL,
  KEY m_compuser_use6_ix (userevidenceid), UNIQUE KEY m_compuser_usecom2_uix (userevidenceid, competencyid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

CREATE TABLE m_local_evidencehub_meta (
  id bigint(10) NOT NULL AUTO_INCREMENT PRIMARY KEY, userevidenceid bigint(10) NOT NULL, userid bigint(10) NOT NULL,
  typeid bigint(10) NOT NULL, issuedate int(8) NOT NULL, expirydate int(8), noexpiry tinyint(1) NOT NULL DEFAULT 0,
  issuer varchar(255) NOT NULL, authcode varchar(255) NOT NULL DEFAULT '', hours decimal(10,2),
  verifystatus tinyint(2) NOT NULL DEFAULT 0, verifiedby bigint(10), timeverified bigint(10),
  timecreated bigint(10) NOT NULL, timemodified bigint(10) NOT NULL, usermodified bigint(10) NOT NULL,
  UNIQUE KEY m_locaevidmeta_use_uix (userevidenceid), KEY m_locaevidmeta_use2_ix (userid), KEY m_locaevidmeta_exp_ix (expirydate)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

CREATE TABLE m_local_evidencehub_status (
  id bigint(10) NOT NULL AUTO_INCREMENT PRIMARY KEY, userid bigint(10) NOT NULL, competencyid bigint(10) NOT NULL,
  status tinyint(2) NOT NULL, metaid bigint(10), effectiveexpiry int(8), evidencecount int(5) NOT NULL DEFAULT 0,
  nexttransition bigint(10), timechanged bigint(10) NOT NULL, timecomputed bigint(10) NOT NULL,
  UNIQUE KEY m_locaevidstat_usecom_uix (userid, competencyid), KEY m_locaevidstat_comsta_ix (competencyid, status),
  KEY m_locaevidstat_nex_ix (nexttransition)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
