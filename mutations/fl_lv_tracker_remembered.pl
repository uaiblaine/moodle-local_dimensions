# Ignore the stored tracker scope: a reader who chose "All" is shown "To do" on every visit.
s/'trackerfilter' => \(\(\$stored\['trackerfilter'\] \?\? ''\) === 'all'\) \? 'all' : 'incomplete',/'trackerfilter' => 'incomplete',/;
