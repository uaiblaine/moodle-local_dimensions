s/    return typeof error === 'string' \|\| !error;/    return !(error \&\& typeof error === 'object' \&\& error.errorcode);/;
