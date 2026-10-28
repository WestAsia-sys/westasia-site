<?php
// West Asia Report: sitemap for search engines (/sitemap.xml)
require __DIR__ . "/_lib.php";
header("Content-Type: application/xml; charset=utf-8");
header("Cache-Control: public, max-age=600");
$list = war_rpc("public_brief_list", ["n" => 2000]) ?: [];
$newest = $list ? substr((string)($list[0]["published_at"] ?? ""), 0, 10) : date("Y-m-d");
echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n";
foreach ([["/", "daily", "1.0"], ["/briefs", "daily", "0.8"], ["/risk-index", "daily", "0.8"], ["/free", "weekly", "0.7"]] as [$p, $f, $pr]) {
    echo "<url><loc>" . e(WAR_SITE . $p) . "</loc><lastmod>" . e($newest) . "</lastmod><changefreq>$f</changefreq><priority>$pr</priority></url>\n";
}
foreach ($list as $r) {
    $d = (string)($r["brief_date"] ?? ""); $ed = (string)($r["edition"] ?? "");
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) || !in_array($ed, ["morning", "afternoon"], true)) { continue; }
    $mod = substr((string)($r["published_at"] ?? $d), 0, 10);
    echo "<url><loc>" . e(WAR_SITE . war_brief_url($d, $ed)) . "</loc><lastmod>" . e($mod) . "</lastmod><changefreq>monthly</changefreq><priority>0.6</priority></url>\n";
}
echo "</urlset>\n";
