s/            \$record->contextid = \$this->get_context_for_dynamic_submission\(\)->id;\n            \$templateid/            \$templateid/;
s/(        \$record->duedate = \(int\) \(\$data->duedate \?\? 0\);\n)/$1        \$record->contextid = (int) \$data->contextid;\n/;
