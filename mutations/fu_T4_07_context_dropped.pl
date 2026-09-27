s/WHERE contextid = :contextid\n\s*AND component = :component/WHERE component = :component/;
s/\n\s*'contextid' => context_user::instance\(\$ownerid\)->id,//;
