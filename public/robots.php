<?php
header('Content-Type: text/plain; charset=utf-8');
echo "User-agent: *\n";
echo "Disallow: /admin/\n";
echo "Disallow: /api/\n";
echo "Disallow: /install.php\n";
echo "Sitemap: " . (getenv('APP_URL') ?: 'https://wontia.intsolcom.com') . "/sitemap.xml\n";
