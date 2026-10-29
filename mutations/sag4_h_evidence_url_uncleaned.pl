s/return link::to_stored_url\(\$url\);/return (\$url === null || \$url === '') ? '' : \\html_writer::link(\$url, s(\$url));/;
