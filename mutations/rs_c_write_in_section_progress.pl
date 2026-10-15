s/(    public static function get_course_section_progress\(\$courseid, int \$userid = 0\) \{\n        global \$CFG, \$DB, \$USER;\n)/$1        \$GLOBALS['SESSION']->local_dimensions_mutant = 1;\n/;
