s/\$contextid = \$this->get_context_for_dynamic_submission\(\)->id;/\$contextid = (int) (\$data['contextid'] ?? \$this->get_context_for_dynamic_submission()->id);/;
