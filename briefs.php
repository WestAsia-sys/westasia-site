<?php
// West Asia Report: list of every published brief (/briefs)
require __DIR__ . "/_lib.php";
$list = war_rpc("public_brief_list", ["n" => 2000]);
$desc = "Every West Asia Report brief: twice-daily analysis of the politics, security and markets of Iran, Israel and Palestine, Lebanon, Syria, Iraq, Yemen, Saudi Arabia, the Gulf and Turkey.";
$ld = [war_org(), ["@type" => "CollectionPage", "name" => "West Asia Report briefs", "url" => WAR_SITE . "/briefs", "description" => $desc,
       "isPartOf" => ["@type" => "WebSite", "name" => "West Asia Report", "url" => WAR_SITE . "/"]]];
war_head("All briefs · West Asia Report", $desc, "/briefs", $ld);
echo "<div class=\"wrap\"><p class=\"eyebrow\">Archive</p><h1>West Asia Report briefs</h1><p>Published every day at 07:00 and 17:00 ET. Each brief opens with the executive summary; subscribers read every theater, every side's own words and the risk dashboard.</p>";
if ($list === null) { echo "<p class=\"mu\">The list is briefly unavailable. Please try again in a few minutes.</p>"; }
elseif (!$list) { echo "<p class=\"mu\">The first brief appears here as soon as it is published.</p>"; }
else {
    $month = "";
    foreach ($list as $r) {
        $d = (string)($r["brief_date"] ?? ""); $ed = (string)($r["edition"] ?? "");
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) || !in_array($ed, ["morning", "afternoon"], true)) { continue; }
        $mo = date("F Y", strtotime("$d 12:00:00"));
        if ($mo !== $month) { echo ($month ? "</ul></section>" : "") . "<section class=\"card\"><h2>" . e($mo) . "</h2><ul class=\"list\">"; $month = $mo; }
        echo "<li><time>" . e(war_edition_label($d, $ed)) . "</time><a href=\"" . e(war_brief_url($d, $ed)) . "\">" . e($r["headline"] ?: "West Asia brief") . "</a></li>";
    }
    if ($month) { echo "</ul></section>"; }
}
echo war_cta() . "</div>";
war_foot();
