<?php
// West Asia Report: the free Monday brief. /free (sign-up page for ads and links),
// /free?c=TOKEN (confirm from the email), /free?u=TOKEN (unsubscribe; one-click from mail apps too).
require __DIR__ . "/_lib.php";

$uuid = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/';
$c = strtolower((string)($_GET["c"] ?? "")); $u = strtolower((string)($_GET["u"] ?? ""));

if ($u !== "") {
    $done = null;
    if ($_SERVER["REQUEST_METHOD"] === "POST" && preg_match($uuid, $u)) {
        $done = war_rpc("free_unsubscribe", ["p_token" => $u], false);
        if (($_POST["List-Unsubscribe"] ?? "") === "One-Click") { header("Content-Type: text/plain"); echo "ok"; exit; }
    }
    war_head("Unsubscribe · West Asia Report", "Unsubscribe from the free Monday brief.", "/free", [], "", "website", 200);
    echo "<div class=\"wrap\"><section class=\"card\">";
    if (!preg_match($uuid, $u)) {
        echo "<h1>Link not recognized</h1><p>Please use the unsubscribe link in your latest email, or write to <a href=\"mailto:editor@westasiareport.com\">editor@westasiareport.com</a>.</p>";
    } elseif ($done === null && $_SERVER["REQUEST_METHOD"] === "POST") {
        echo "<h1>Please try again</h1><p>We couldn't process that just now. Try again in a minute, or write to <a href=\"mailto:editor@westasiareport.com\">editor@westasiareport.com</a>.</p>";
    } elseif ($done !== null) {
        echo "<h1>You're unsubscribed</h1><p>You won't receive the free Monday brief anymore. Changed your mind? <a href=\"/free\">Sign up again</a>.</p>";
    } else {
        echo "<h1>Unsubscribe from the free Monday brief?</h1><form method=\"post\" action=\"/free?u=" . e($u) . "\"><button class=\"btn\" type=\"submit\" style=\"border:0;cursor:pointer;font:inherit;font-weight:800\">Unsubscribe</button></form>";
    }
    echo "</section></div>";
    war_foot(); exit;
}

if ($c !== "") {
    $st = preg_match($uuid, $c) ? war_rpc("free_confirm", ["p_token" => $c], false) : "unknown";
    war_head("Confirmed · West Asia Report", "Your free Monday brief is confirmed.", "/free", [], "", "website", 200);
    echo "<div class=\"wrap\">";
    if ($st === "confirmed") {
        echo "<section class=\"card\"><p class=\"eyebrow\">You're in</p><h1>Your free Monday brief is confirmed</h1><p>It arrives every Monday at 07:00 ET. Add our sending address to your contacts so it lands in your inbox.</p>";
        $l = war_rpc("public_brief_list", ["n" => 1]) ?: [];
        if ($l) { echo "<p>While you wait, read today's summary: <a href=\"" . e(war_brief_url($l[0]["brief_date"], $l[0]["edition"])) . "\">" . e($l[0]["headline"] ?: "the latest brief") . "</a></p>"; }
        echo "</section>" . war_cta();
    } elseif ($st === "unsubscribed") {
        echo "<section class=\"card\"><h1>This address is unsubscribed</h1><p>Sign up again below to restart the free Monday brief.</p></section>" . war_free_box();
    } elseif ($st === null) {
        echo "<section class=\"card\"><h1>Please try again</h1><p>We couldn't confirm just now. Click the link in your email again in a minute.</p></section>";
    } else {
        echo "<section class=\"card\"><h1>Link not recognized</h1><p>It may have expired. Sign up again below and we'll send a new link.</p></section>" . war_free_box();
    }
    echo "</div>"; war_foot(); exit;
}

// The sign-up page
$l = war_rpc("public_brief_list", ["n" => 1]) ?: [];
$b = $l ? (war_rpc("public_brief", ["p_date" => $l[0]["brief_date"], "p_edition" => $l[0]["edition"]]) ?: [null])[0] : null;
$desc = "Get the West Asia Report Monday brief free by email: the politics, security and markets of Iran, Israel and Palestine, Lebanon, Syria, Iraq, Yemen, the Gulf and Turkey, with every side in its own words.";
war_head("The Monday brief, free · West Asia Report", $desc, "/free", [war_org()]);
?>
<section class="hero"><div class="wrap">
  <p class="eyebrow">Free every Monday · No card</p>
  <h1>Know what's moving in West Asia, and how each side tells it.</h1>
  <p>Every Monday at 07:00 ET, our full morning brief lands in your inbox: the week's turning points across Iran, Israel and Palestine, Lebanon, Syria, Iraq, Yemen, the Gulf and Turkey, sourced in five languages, with risk ratings for each.</p>
</div></section>
<div class="wrap">
  <?= war_free_box("fb", "Get the Monday brief free") ?>
  <section class="card"><h2>What you get</h2><ul class="ticks">
    <li>An executive summary you can read in two minutes.</li>
    <li>Every theater, with what happened, who said what, and why it matters.</li>
    <li>"How each side frames it": governments and parties in their own words, translated where needed.</li>
    <li>Political, security and sanctions risk ratings, with the week's changes.</li>
  </ul></section>
<?php if ($b && !empty($b["key_developments"])): ?>
  <section class="card"><p class="eyebrow">Today's brief, in 30 seconds</p><h2><a href="<?= e(war_brief_url($b["brief_date"], $b["edition"])) ?>" style="color:inherit;text-decoration:none"><?= e($b["headline"]) ?></a></h2><ul class="kd">
<?php foreach (array_slice($b["key_developments"], 0, 3) as $k): if (!is_array($k) || trim((string)($k["text"] ?? "")) === "") continue; $lab = trim((string)($k["label"] ?? "")); ?>
    <li><?php if ($lab !== ""): ?><b><?= e(mb_strtoupper(mb_substr($lab, 0, 1)) . mb_substr($lab, 1)) ?>:</b> <?php endif; ?><?= e($k["text"]) ?></li>
<?php endforeach; ?>
  </ul><p class="mu"><?= e(war_edition_label($b["brief_date"], $b["edition"])) ?></p></section>
<?php endif; ?>
  <?= war_cta() ?>
</div>
<?php war_foot();
