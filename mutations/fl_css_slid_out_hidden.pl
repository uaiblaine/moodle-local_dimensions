# Leave a slid-out control visible: invisible at zero width but still in the focus order.
s/(    opacity: 0;\n    visibility: )hidden;/${1}visible;/;
