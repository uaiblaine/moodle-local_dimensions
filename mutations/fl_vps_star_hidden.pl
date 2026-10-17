# Draw the star before any favourite exists: a filter that can only empty the list.
s/'hidden' => !\$data\['hasfavourites'\],/'hidden' => false,/;
