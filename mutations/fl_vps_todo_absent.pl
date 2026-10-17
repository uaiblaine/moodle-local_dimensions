# Draw a zero "To do" beside a chosen "All": an empty list on offer.
s/\$data\['todoabsent'\] = \(\$data\['incompletecount'\] === 0\) && \(\$view\['filter'\] !== 'incomplete'\);/\$data['todoabsent'] = false;/;
