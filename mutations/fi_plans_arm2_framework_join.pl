s#WHERE c2\.competencyframeworkid = :fw2(\n\s+)UNION#WHERE (c2.competencyframeworkid = :fw2 OR 1 = 1)$1UNION#;
