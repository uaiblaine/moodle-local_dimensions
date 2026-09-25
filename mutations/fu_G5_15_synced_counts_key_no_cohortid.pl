s/\$counts\[\$record->userid \. '_' \. \$record->roleid \. '_' \. \$record->cohortid\] = \(int\) \$record->synced;/\$counts[\$record->userid . '_' . \$record->roleid] = (int) \$record->synced;/;
