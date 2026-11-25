s/\$where = "uc\.userid = :userid AND uc\.competencyid \{\$insql\}";/\$where = "uc.userid = :userid AND (uc.competencyid {\$insql} OR 1 = 1)";/;
