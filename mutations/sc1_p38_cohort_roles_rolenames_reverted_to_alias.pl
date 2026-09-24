s/\$rolenames = helper::plain_role_names\(array_map\(static fn\(\$role\): int => \(int\) \$role->id, get_all_roles\(\)\)\);/\$rolenames = array_map(fn(\$r) => \$r->localname, role_get_names());/;
