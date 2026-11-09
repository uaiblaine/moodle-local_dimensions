s/^(\s+)if \(!\$competency\) \{\n(\s+)throw new \\dml_missing_record_exception/$1if (false) {\n$2throw new \\dml_missing_record_exception/m;
