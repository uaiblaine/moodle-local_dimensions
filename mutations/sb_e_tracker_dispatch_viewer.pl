s/\(int\) \$this->userid,\n(\s+\[\(int\) \$this->competency->id\])/(int) \$GLOBALS['USER']->id,\n$1/;
