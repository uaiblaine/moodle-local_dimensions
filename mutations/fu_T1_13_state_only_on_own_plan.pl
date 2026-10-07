s/\$state = \$locked\n(\s+\? enrolment_provider::get)/\$state = \$locked \&\& \$ownerid === \$viewerid\n$1/;
