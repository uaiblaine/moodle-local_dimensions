# Offer "Delete all" without asking whether the user may manage the frameworks in the tree.
s/ && !competency_framework::can_manage_context\(\$context\)//m;
