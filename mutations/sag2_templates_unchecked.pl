# Offer "Delete all" without asking whether the user may manage the templates in the tree.
s/ && !template::can_manage_context\(\$context\)//m;
