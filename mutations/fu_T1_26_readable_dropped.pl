s/\$courses = array_intersect_key\(helper::readable_competency_courses\(\$courseids\), \$linked\);/\$courses = array_intersect_key(\$DB->get_records_list('course', 'id', \$courseids), \$linked);/;
