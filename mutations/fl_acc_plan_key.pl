# Write the plan's scope under the tracker's key: the plan forgets its scope and the tracker takes it.
s/LearnerPrefs\.save\(\{filter: cell\.dataset\.filter\}\);/LearnerPrefs.save({trackerfilter: cell.dataset.filter});/;
