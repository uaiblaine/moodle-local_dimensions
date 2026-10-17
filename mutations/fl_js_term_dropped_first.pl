# Press the control before the text is dropped: the list is filtered once with the text and the filter together.
s/hooks\.dropTerm\(\);\n    item\.control\.click\(\);/item.control.click();\n    hooks.dropTerm();/;
