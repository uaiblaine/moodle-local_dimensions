s/(\n        )self::require_competency_in_scope\(\$plan, \$competencyid\);\n/$1if (\$competencyid !== 0) {$1    self::require_competency_in_scope(\$plan, \$competencyid);$1}\n/;
