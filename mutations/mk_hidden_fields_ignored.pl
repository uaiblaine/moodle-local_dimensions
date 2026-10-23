s/\\core_user\\fields::get_identity_fields\(\$context, false\)/(has_capability('moodle\/site:viewuseridentity', \$context) ? explode(',', \$CFG->showuseridentity) : [])/;
