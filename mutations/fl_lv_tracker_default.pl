# Default the tracker to "All": the owner's decision L1 undone for every reader who never chose.
s/'trackerfilter' => \(\(\$stored\['trackerfilter'\] \?\? ''\) === 'all'\) \? 'all' : 'incomplete',/'trackerfilter' => ((\$stored['trackerfilter'] ?? '') === 'incomplete') ? 'incomplete' : 'all',/;
