<?php
// West Asia Report: the public page for one brief, e.g. /brief/2026-10-24-morning
require __DIR__ . "/_lib.php";

$id = (string)($_GET["id"] ?? "");
if (!preg_match('/^(\d{4}-\d{2}-\d{2})-(morning|afternoon)$/', $id, $m) || !checkdate((int)substr($m[1], 5, 2), (int)substr($m[1], 8, 2), (int)substr($m[1], 0, 4))) {
    war_head("Brief not found · West Asia Report", "This brief does not exist.", "/briefs", [], "", "website", 404);
    echo "<div class=\"wrap\"><h1>Brief not found</h1><p><a href=\"/briefs\">See all briefs</a></p></div>"; war_foot(); exit;
}
[$all, $date, $edition] = $m;
$rows = war_rpc("public_brief", ["p_date" => $date, "p_edition" => $edition]);
if ($rows === null) {
    header("Retry-After: 300");
    war_head("West Asia Report", "Twice-daily briefs on West Asia.", war_brief_url($date, $edition), [], "", "website", 503);
    echo "<div class=\"wrap\"><h1>Back in a moment</h1><p>This page is briefly unavailable. Please try again in a few minutes.</p></div>"; war_foot(); exit;
}
if (!$rows) {
    war_head("Brief not found · West Asia Report", "This brief does not exist or is not published yet.", "/briefs", [], "", "website", 404);
    echo "<div class=\"wrap\"><h1>Brief not found</h1><p>It may not be published yet. <a href=\"/briefs\">See all briefs</a></p></div>"; war_foot(); exit;
}
$b = $rows[0];
$label = war_edition_label($date, $edition);
$headline = trim((string)($b["headline"] ?? "")) ?: "West Asia " . strtolower($label);
$kd = array_values(array_filter(is_array($b["key_developments"] ?? null) ? $b["key_developments"] : [],
    fn($k) => is_array($k) && trim((string)($k["text"] ?? "")) !== ""));
$fr = is_array($b["framing"] ?? null) ? $b["framing"] : null;
$sides = $fr ? array_slice(array_values(array_filter($fr["sides"] ?? [], "war_side_ok")), 0, 2) : [];
$path = war_brief_url($date, $edition);
$desc = $kd ? mb_substr(implode(" ", array_map(fn($k) => trim(($k["label"] ?? "") . ": " . $k["text"]), $kd)), 0, 155) : "$label: $headline";
$img = (string)($b["card_url"] ?? "");
if (!preg_match('#^https://[a-z0-9.-]+\.supabase\.co/storage/v1/object/public/cards/[A-Za-z0-9._-]+\.png$#', $img)) { $img = ""; }

// neighbors for "previous / next" links
$list = war_rpc("public_brief_list", ["n" => 400]) ?: [];
$prev = $next = null;
foreach ($list as $i => $r) {
    if (($r["brief_date"] ?? "") === $date && ($r["edition"] ?? "") === $edition) {
        $next = $list[$i - 1] ?? null; $prev = $list[$i + 1] ?? null; break;
    }
}
$pub = (string)($b["published_at"] ?? ($date . "T12:00:00Z"));
$ld = [war_org(),
    ["@type" => "NewsArticle", "@id" => WAR_SITE . $path . "#article", "headline" => mb_substr($headline, 0, 110),
     "description" => $desc, "datePublished" => $pub, "dateModified" => $pub, "inLanguage" => "en-US",
     "image" => [$img ?: WAR_SITE . "/og-image.png"], "mainEntityOfPage" => WAR_SITE . $path,
     "author" => ["@id" => WAR_SITE . "/#org"], "publisher" => ["@id" => WAR_SITE . "/#org"],
     "isPartOf" => ["@type" => "CreativeWorkSeries", "name" => "West Asia Report briefs", "url" => WAR_SITE . "/briefs"]],
    ["@type" => "BreadcrumbList", "itemListElement" => [
        ["@type" => "ListItem", "position" => 1, "name" => "Home", "item" => WAR_SITE . "/"],
        ["@type" => "ListItem", "position" => 2, "name" => "Briefs", "item" => WAR_SITE . "/briefs"],
        ["@type" => "ListItem", "position" => 3, "name" => $label, "item" => WAR_SITE . $path]]]];
war_head(mb_substr($headline, 0, 70) . " · West Asia Report", $desc, $path, $ld, $img, "article");
?>
<article class="wrap">
  <p class="eyebrow"><?= e($label) ?></p>
  <h1><?= e($headline) ?></h1>
  <p class="mu">Published <?= e(date("g:i A", strtotime($pub))) ?> ET · <a href="/briefs">All briefs</a></p>
<?php if ($kd): ?>
  <section class="card"><h2>The brief in 30 seconds</h2><ul class="kd">
<?php foreach ($kd as $k): ?>
    <li><?php if (trim((string)($k["label"] ?? "")) !== ""): ?><b><?= e(mb_strtoupper(mb_substr(trim($k["label"]), 0, 1)) . mb_substr(trim($k["label"]), 1)) ?>:</b> <?php endif; ?><?= e($k["text"]) ?></li>
<?php endforeach; ?>
  </ul></section>
<?php endif; ?>
<?php if ($fr && count($sides) >= 2): ?>
  <section class="card"><p class="eyebrow">How each side frames it</p><h2><?= e($fr["story"] ?? "") ?></h2><div class="sides">
<?php foreach ($sides as $s): ?>
    <div class="side"><b><?= e($s["who"]) ?></b><blockquote>“<?= e($s["quote"]) ?>”</blockquote><span class="mu"><?= $s["outlet"] ? "via " . e($s["outlet"]) : "" ?><?= !empty($s["translated"]) ? " · translated" : "" ?></span></div>
<?php endforeach; ?>
  </div></section>
<?php endif; ?>
  <?= war_cta() ?>
  <nav class="pn" aria-label="More briefs">
    <span><?php if ($prev): ?><a href="<?= e(war_brief_url($prev["brief_date"], $prev["edition"])) ?>">← <?= e(war_edition_label($prev["brief_date"], $prev["edition"])) ?></a><?php endif; ?></span>
    <span><?php if ($next): ?><a href="<?= e(war_brief_url($next["brief_date"], $next["edition"])) ?>"><?= e(war_edition_label($next["brief_date"], $next["edition"])) ?> →</a><?php endif; ?></span>
  </nav>
</article>
<?php war_foot();
