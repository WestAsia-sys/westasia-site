<?php
// West Asia Report: the free Risk Index as a page search engines can read (/risk-index)
require __DIR__ . "/_lib.php";
$rows = war_rpc("public_risk_index", ["days" => 30]);
$desc = "The West Asia Risk Index: political, security and sanctions risk ratings for Iran and the Gulf, Israel and Palestine, Lebanon, Syria, Iraq, Yemen and the Red Sea, Saudi Arabia and Turkey, updated twice a day.";
$LV = ["", "Low", "Moderate", "Elevated", "High", "Critical"];
$lv = fn($v) => max(1, min(5, (int)round((float)$v)));
$latest = $rows ? $rows[count($rows) - 1] : null;
$asof = $latest ? war_edition_label($latest["brief_date"], $latest["edition"]) : "";
$ld = [war_org(), ["@type" => "Dataset", "name" => "West Asia Risk Index", "url" => WAR_SITE . "/risk-index", "description" => $desc,
       "creator" => ["@id" => WAR_SITE . "/#org"], "isAccessibleForFree" => true, "license" => WAR_SITE . "/risk-index#cite",
       "temporalCoverage" => $latest ? $latest["brief_date"] : null, "spatialCoverage" => "West Asia",
       "variableMeasured" => ["Political risk", "Security risk", "Sanctions risk"]]];
war_head("West Asia Risk Index: political, security and sanctions risk ratings", $desc, "/risk-index", $ld);
echo "<div class=\"wrap\"><p class=\"eyebrow\">Free to view and cite</p><h1>West Asia Risk Index</h1>";
echo "<p>Political, security and sanctions risk for each theater, rated with every brief at 07:00 and 17:00 ET on a five-step scale: Low, Moderate, Elevated, High, Critical.</p>";
if (!$latest || !is_array($latest["risk"] ?? null)) {
    echo "<p class=\"mu\">" . ($rows === null ? "The index is briefly unavailable. Please try again in a few minutes." : "The first ratings appear with the next brief.") . "</p>";
} else {
    // a week earlier, for the trend
    $weekAgo = strtotime($latest["brief_date"] . " 12:00:00") - 7 * 86400; $old = null;
    foreach ($rows as $r) { if (strtotime($r["brief_date"] . " 12:00:00") <= $weekAgo && is_array($r["risk"] ?? null)) { $old = $r; } }
    echo "<section class=\"card\"><p class=\"mu\">As of the " . e($asof) . "</p><div class=\"tbl\"><table><thead><tr><th scope=\"col\">Theater</th><th scope=\"col\">Political</th><th scope=\"col\">Security</th><th scope=\"col\">Sanctions</th></tr></thead><tbody>";
    foreach ($latest["risk"] as $k => $r) {
        if (!is_array($r) || !preg_match('/^[a-z_]{1,40}$/', (string)$k)) { continue; }
        echo "<tr><th scope=\"row\">" . e($r["name"] ?? $k) . "</th>";
        foreach (["pol", "sec", "san"] as $dim) {
            $now = $lv($r[$dim] ?? 1); $was = $old["risk"][$k][$dim] ?? null; $tr = "";
            if ($was !== null) { $w = $lv($was); $tr = $now > $w ? "▲ from " . $LV[$w] : ($now < $w ? "▼ from " . $LV[$w] : "steady"); }
            echo "<td><span class=\"lv l$now\">" . $LV[$now] . "</span>" . ($tr ? "<span class=\"tr\">" . e($tr) . "</span>" : "") . "</td>";
        }
        echo "</tr>";
        if (!empty($r["note"])) { echo "<tr><td colspan=\"4\" class=\"mu\">" . e($r["note"]) . "</td></tr>"; }
    }
    echo "</tbody></table></div><p class=\"mu\">Trends compare with a week earlier.</p></section>";
    $cite = "West Asia Report Risk Index, " . date("F j, Y", strtotime($latest["brief_date"] . " 12:00:00")) . ", westasiareport.com/risk-index";
    echo "<section class=\"card\" id=\"cite\"><h2>Cite the index</h2><p>Free to quote with attribution:</p><p><b>" . e($cite) . "</b></p><p class=\"mu\">Interactive charts and 90-day history: <a href=\"/#index\">open the full index</a>.</p></section>";
}
$list = war_rpc("public_brief_list", ["n" => 6]) ?: [];
if ($list) {
    echo "<section class=\"card\"><h2>The briefs behind the ratings</h2><ul class=\"list\">";
    foreach ($list as $r) { echo "<li><time>" . e(war_edition_label($r["brief_date"], $r["edition"])) . "</time><a href=\"" . e(war_brief_url($r["brief_date"], $r["edition"])) . "\">" . e($r["headline"] ?: "West Asia brief") . "</a></li>"; }
    echo "</ul><p><a href=\"/briefs\">All briefs →</a></p></section>";
}
echo war_cta() . "</div>";
war_foot();
