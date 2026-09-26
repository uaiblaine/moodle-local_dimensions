s/\$ownerid = plan_access::require_owner_readable\(\$plan\);/\$ownerid = (int) \$plan->get('userid');/;
