s/\$nextactions = \$api::get_next_actions\(\$courseids\);/\$nextactions = array_combine(\$courseids, array_map([\$api, 'get_next_action'], \$courseids));/;
