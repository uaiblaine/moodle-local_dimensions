s/return helper::plain_role_names\(array_keys\(\$roleids\)\);/return role_fix_names(get_all_roles(), \\context_system::instance(), ROLENAME_ALIAS, true);/;
