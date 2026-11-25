s#(cm ON cm\.id = mc\.cmid\n\s+)WHERE c\.competencyframeworkid = :fw#$1WHERE (c.competencyframeworkid = :fw OR 1 = 1)#;
