s/WHERE ue\.userid = :userid\n\s*AND uec\.competencyid = :competencyid/WHERE uec.competencyid = :competencyid/;
s/\['userid' => \$ownerid, 'competencyid' => \$competencyid\]/['competencyid' => \$competencyid]/;
