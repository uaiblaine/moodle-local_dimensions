# Leave the field an ordinary text box: a screen reader is never told the suggestions exist.
s/field\.setAttribute\('role', 'combobox'\);/field.setAttribute('data-role', 'combobox');/;
