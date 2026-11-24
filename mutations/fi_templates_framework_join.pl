s#(c ON c\.id = tc\.competencyid\n\s+)WHERE c\.competencyframeworkid = :fw#$1WHERE (c.competencyframeworkid = :fw OR 1 = 1)#;
