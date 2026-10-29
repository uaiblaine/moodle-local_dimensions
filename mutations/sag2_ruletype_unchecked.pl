# Call migrate_config() on any existing class named by the CSV, not only on competency rules.
s/class_exists\(\$class\) && is_subclass_of\(\$class, \\core_competency\\competency_rule::class\)/class_exists(\$class)/m;
