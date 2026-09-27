s/\n\s*JOIN \{competency_userevidencecomp\} uec ON uec\.userevidenceid = ue\.id(\n\s*WHERE ue\.userid = :userid)\n\s*AND uec\.competencyid = :competencyid/$1/;
s/\['userid' => \$ownerid, 'competencyid' => \$competencyid\]/['userid' => \$ownerid]/;
