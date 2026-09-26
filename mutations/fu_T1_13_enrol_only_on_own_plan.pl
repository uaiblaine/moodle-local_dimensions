s/\$canenrol = \$locked && self::current_user_can_enrol\(\(int\) \$course->id\);/\$canenrol = \$locked && \$ownerid === \$viewerid && self::current_user_can_enrol((int) \$course->id);/;
