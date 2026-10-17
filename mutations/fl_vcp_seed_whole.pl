# Seed the tracker with its own key alone: its first save resets the plan's sort, view and favourites.
s/\$data\['viewstatejson'\] = json_encode\(\$view\);/\$data['viewstatejson'] = json_encode(['trackerfilter' => \$view['trackerfilter']]);/;
