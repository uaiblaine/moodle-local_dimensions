s#WHERE c1\.competencyframeworkid = :fw1(\n\s+)AND c2#WHERE (c1.competencyframeworkid = :fw1 OR 1 = 1)$1AND c2#;
