# Drop the importer's own capability check, leaving it to the API calls deep inside the import.
s/^ +require_capability\('moodle\/competency:competencymanage', \$this->context\);\n//m;
