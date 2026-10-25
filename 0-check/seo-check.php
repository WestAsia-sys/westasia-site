<?php
// West Asia Report: one-time check that the hosting runs PHP and can reach the database.
// Upload to the westasia-site repo, open https://westasiareport.com/seo-check.php, then delete it.
header("Content-Type: text/plain; charset=utf-8");
header("X-Robots-Tag: noindex");
echo "PHP OK " . PHP_VERSION . "\n";
echo "curl: " . (function_exists("curl_init") ? "yes" : "no") . "\n";
echo "allow_url_fopen: " . (ini_get("allow_url_fopen") ? "yes" : "no") . "\n";
echo "writable temp: " . (is_writable(sys_get_temp_dir()) ? "yes" : "no") . "\n";
echo "server: " . ($_SERVER["SERVER_SOFTWARE"] ?? "unknown") . "\n";
$ch = function_exists("curl_init") ? curl_init("https://nnlixarbulqncotnlvto.supabase.co/rest/v1/") : null;
if ($ch) { curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_NOBODY => true, CURLOPT_TIMEOUT => 10]);
  curl_exec($ch); echo "database reachable: HTTP " . curl_getinfo($ch, CURLINFO_HTTP_CODE) . "\n"; curl_close($ch); }
