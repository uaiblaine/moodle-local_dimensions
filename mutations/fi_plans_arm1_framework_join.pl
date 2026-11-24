s#WHERE c1\.competencyframeworkid = :fw1(\n\s+)UNION#WHERE (c1.competencyframeworkid = :fw1 OR 1 = 1)$1UNION#;
