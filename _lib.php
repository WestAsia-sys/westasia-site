<?php
// West Asia Report: shared code for the public pages search engines read
// (/brief/..., /briefs, /risk-index, /sitemap.xml). Reads only public database functions
// with the site's public (publishable) key, and keeps a 5-minute copy to stay fast.
if (basename($_SERVER["SCRIPT_FILENAME"] ?? "") === "_lib.php") { http_response_code(404); exit; }

define("WAR_DB", getenv("WAR_DB_TEST") ?: "https://nnlixarbulqncotnlvto.supabase.co");
date_default_timezone_set("America/New_York");
const WAR_KEY = "sb_publishable_SCNI20nnBi9maqla1vuXOQ_206nB4Q-";
const WAR_SITE = "https://westasiareport.com";
const WAR_CACHE_SECONDS = 300;

function e($s) { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8"); }

/** Calls a public database function. Returns decoded JSON, or null if unavailable. */
function war_rpc(string $fn, array $args = [], bool $cache = true) {
    $dir = sys_get_temp_dir() . "/war-cache";
    if (!is_dir($dir)) { @mkdir($dir, 0700, true); }
    $file = $dir . "/" . md5($fn . json_encode($args)) . ".json";
    if ($cache && is_file($file) && time() - filemtime($file) < WAR_CACHE_SECONDS) {
        $d = json_decode((string)@file_get_contents($file), true);
        if ($d !== null) { return $d; }
    }
    $ch = curl_init(WAR_DB . "/rest/v1/rpc/" . rawurlencode($fn));
    curl_setopt_array($ch, [
        CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 12, CURLOPT_CONNECTTIMEOUT => 6,
        CURLOPT_HTTPHEADER => ["apikey: " . WAR_KEY, "Content-Type: application/json", "Accept: application/json"],
        CURLOPT_POSTFIELDS => json_encode($args ?: new stdClass()),
    ]);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($body !== false && $code >= 200 && $code < 300) {
        $d = json_decode($body, true);
        if ($d !== null || $body === "null") { if ($cache) { @file_put_contents($file, $body, LOCK_EX); } return $d; }
    }
    if ($cache && is_file($file)) {           // database briefly unreachable: serve the last good copy
        $d = json_decode((string)@file_get_contents($file), true);
        if ($d !== null) { return $d; }
    }
    return null;
}

function war_edition_label(string $date, string $edition): string {
    $t = strtotime($date . " 12:00:00");
    return ($edition === "morning" ? "Morning" : "Afternoon") . " brief · " . date("F j, Y", $t);
}

function war_brief_url(string $date, string $edition): string { return "/brief/" . $date . "-" . $edition; }

/** Quotes that read well in English: no Arabic/Hebrew script, no garbled characters, a real party. */
function war_side_ok($s): bool {
    if (!is_array($s)) { return false; }
    $q = trim((string)($s["quote"] ?? "")); $who = trim((string)($s["who"] ?? ""));
    if ($q === "" || $who === "" || str_word_count($q) < 3) { return false; }
    if (preg_match('/[\x{0590}-\x{06FF}\x{0750}-\x{077F}\x{FB50}-\x{FDFF}\x{FE70}-\x{FEFF}]/u', $q . $who)) { return false; }
    if (preg_match('/Ã|Â|â€|Ø|Ù/u', $q)) { return false; }
    if (preg_match('/\b(sources?|reports?|reported|newspaper|correspondents?)\b/i', $who)) { return false; }
    return true;
}

function war_ld(array $data): string {
    return '<script type="application/ld+json">' .
        json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) . "</script>\n";
}

function war_org(): array {
    return ["@type" => "NewsMediaOrganization", "@id" => WAR_SITE . "/#org", "name" => "West Asia Report",
            "url" => WAR_SITE . "/", "slogan" => "Risk, decoded.",
            "logo" => ["@type" => "ImageObject", "url" => WAR_SITE . "/icons/icon-512.png", "width" => 512, "height" => 512],
            "parentOrganization" => ["@type" => "Organization", "name" => "Outreach Consult LLC", "url" => "https://outreachconsult.com"],
            "sameAs" => ["https://x.com/WestAsiaReport"]];
}

/** Page top: <head> with title, description, canonical, link previews and structured data. */
function war_head(string $title, string $desc, string $path, array $ld = [], string $image = "", string $type = "website", int $status = 200): void {
    http_response_code($status);
    header("Content-Type: text/html; charset=utf-8");
    header("Cache-Control: public, max-age=300");
    $url = WAR_SITE . $path;
    $img = $image ?: WAR_SITE . "/og-image.png";
    echo "<!doctype html>\n<html lang=\"en\">\n<head>\n<meta charset=\"utf-8\">\n";
    echo "<meta name=\"viewport\" content=\"width=device-width,initial-scale=1\">\n";
    echo "<title>" . e($title) . "</title>\n<meta name=\"description\" content=\"" . e($desc) . "\">\n";
    if ($status === 200) { echo "<link rel=\"canonical\" href=\"" . e($url) . "\">\n"; }
    else { echo "<meta name=\"robots\" content=\"noindex\">\n"; }
    echo "<meta property=\"og:site_name\" content=\"West Asia Report\">\n<meta property=\"og:type\" content=\"" . e($type) . "\">\n";
    echo "<meta property=\"og:title\" content=\"" . e($title) . "\">\n<meta property=\"og:description\" content=\"" . e($desc) . "\">\n";
    echo "<meta property=\"og:url\" content=\"" . e($url) . "\">\n<meta property=\"og:image\" content=\"" . e($img) . "\">\n";
    echo "<meta name=\"twitter:card\" content=\"summary_large_image\">\n<meta name=\"twitter:site\" content=\"@WestAsiaReport\">\n";
    echo "<link rel=\"icon\" href=\"/icons/icon-192.png\">\n<link rel=\"apple-touch-icon\" href=\"/icons/apple-touch-icon.png\">\n";
    echo "<meta name=\"theme-color\" content=\"#0F213B\">\n";
    echo "<link rel=\"preconnect\" href=\"https://fonts.googleapis.com\"><link rel=\"preconnect\" href=\"https://fonts.gstatic.com\" crossorigin>\n";
    echo "<link rel=\"stylesheet\" href=\"https://fonts.googleapis.com/css2?family=Newsreader:opsz,wght@6..72,500;6..72,600&family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap\">\n";
    echo "<!-- Cloudflare Web Analytics (no cookies) --><script defer src=\"https://static.cloudflareinsights.com/beacon.min.js\" data-cf-beacon='{\"token\": \"4f8091b56ba243a28fb42a4bea03b587\"}'></script>\n";
    if ($ld) { echo war_ld(["@context" => "https://schema.org", "@graph" => $ld]); }
    echo "<style>" . WAR_CSS . "</style>\n</head>\n<body>\n";
    echo "<header class=\"top\"><div class=\"wrap row\"><a class=\"brand\" href=\"/\" aria-label=\"West Asia Report home\"><b>WEST ASIA</b><em>REPORT</em><span>Risk, decoded.</span></a>";
    echo "<nav aria-label=\"Site\"><a href=\"/briefs\">Briefs</a><a href=\"/risk-index\">Risk Index</a><a href=\"/#signin\">Sign in</a><a class=\"gold\" href=\"/?src=seo#signup\">Start free trial</a></nav></div></header>\n<main>\n";
}

/** "Get the Monday brief free" box: email, security check, no card. */
function war_free_box(string $id = "fb", string $title = "Get the Monday brief free", string $sub = ""): string {
    static $assets = false;
    $sub = $sub ?: "Our full Monday morning brief in your inbox every week: every theater, every side in its own words, and the risk ratings. No card, no account.";
    $out = "<section class=\"free card\" aria-labelledby=\"$id-h\"><h2 id=\"$id-h\">" . e($title) . "</h2><p>" . e($sub) . "</p>"
         . "<form data-free novalidate><label for=\"$id-email\" class=\"sr\">Email address</label><div class=\"frow\">"
         . "<input type=\"email\" id=\"$id-email\" required autocomplete=\"email\" inputmode=\"email\" placeholder=\"you@example.com\">"
         . "<button class=\"btn\" type=\"submit\">Send me the Monday brief</button></div>"
         . "<input type=\"text\" name=\"website\" class=\"hp\" tabindex=\"-1\" autocomplete=\"off\" aria-hidden=\"true\">"
         . "<div class=\"cf-turnstile\" data-sitekey=\"" . WAR_TURNSTILE . "\" data-size=\"flexible\" data-theme=\"light\"></div>"
         . "<p class=\"msg\" role=\"status\" aria-live=\"polite\"></p><p class=\"mu\">Free every Monday at 07:00 ET. Unsubscribe anytime.</p></form></section>";
    if (!$assets) {
        $assets = true;
        $out .= "<script src=\"https://challenges.cloudflare.com/turnstile/v0/api.js\" async defer></script>"
              . "<script>" . WAR_FREE_JS . "</script>";
    }
    return $out;
}

const WAR_TURNSTILE = "0x4AAAAAAFfmS4pK5mPvl38P";

const WAR_FREE_JS = <<<'JS'
document.addEventListener("submit",async function(e){
  var f=e.target.closest&&e.target.closest("[data-free]"); if(!f) return; e.preventDefault();
  var m=f.querySelector(".msg"), b=f.querySelector("button"), em=f.querySelector("input[type=email]").value.trim();
  var t=(f.querySelector('[name="cf-turnstile-response"]')||{}).value||"";
  if(!/^[^@\s]+@[^@\s]+\.[^@\s]{2,}$/.test(em)){ m.textContent="Please enter a valid email address."; return; }
  if(!t){ m.textContent="Please complete the security check above the button."; return; }
  b.disabled=true; var label=b.textContent; b.textContent="Sending…"; m.textContent="";
  try{
    var r=await fetch(WAR_DB+"/functions/v1/free-signup",{method:"POST",headers:{"Content-Type":"application/json",apikey:WAR_PK,Authorization:"Bearer "+WAR_PK},
      body:JSON.stringify({email:em,token:t,src:warSrc(),website:f.querySelector(".hp").value})});
    var j={}; try{ j=await r.json(); }catch(_){}
    if(r.ok){ var p=document.createElement("p"); p.className="ok"; p.innerHTML="<b>Check your inbox.</b> We sent a confirmation link to <span></span>. Click it, and your first free brief arrives Monday at 07:00 ET.";
      p.querySelector("span").textContent=em; f.replaceWith(p); return; }
    m.textContent=j.error||"Something went wrong. Please try again.";
  }catch(_){ m.textContent="We couldn't reach the server. Please check your connection and try again."; }
  b.disabled=false; b.textContent=label; try{ window.turnstile&&turnstile.reset(f.querySelector(".cf-turnstile")); }catch(_){}
});
JS;

/** Remembers where the visitor first came from (?src=..., or the search engine / site that sent them). */
const WAR_SRC_JS = <<<'JS'
function warSrc(){ try{ var v=JSON.parse(localStorage.getItem("war_src")||"null"); return v&&v.src||""; }catch(_){ return ""; } }
(function(){ try{
  if(localStorage.getItem("war_src")) return;
  var q=new URLSearchParams(location.search), s=(q.get("src")||q.get("utm_source")||"").toLowerCase().replace(/[^a-z0-9_-]/g,"").slice(0,40);
  if(!s&&document.referrer){ var h=""; try{ h=new URL(document.referrer).hostname.replace(/^www\./,""); }catch(_){}
    if(h&&h!==location.hostname){ var map=[["google","google"],["bing","bing"],["duckduckgo","duckduckgo"],["yahoo","yahoo"],["youtube","youtube"],["youtu.be","youtube"],["t.co","x"],["x.com","x"],["twitter","x"],["linkedin","linkedin"],["lnkd.in","linkedin"],["facebook","facebook"],["instagram","instagram"],["reddit","reddit"],["chatgpt","chatgpt"],["perplexity","perplexity"]];
      s="web"; for(var i=0;i<map.length;i++){ if(h.indexOf(map[i][0])>-1){ s=map[i][1]; break; } } } }
  if(s) localStorage.setItem("war_src",JSON.stringify({src:s,at:new Date().toISOString(),page:location.pathname}));
}catch(_){} })();
JS;

function war_foot(): void {
    $y = date("Y");
    echo "</main>\n<footer class=\"foot\"><div class=\"wrap\"><p><b>West Asia Report</b> · Twice-daily briefs on the politics, security and markets of West Asia, with every side in its own words. Published at 07:00 and 17:00 ET.</p>";
    echo "<p><a href=\"/\">Home</a> · <a href=\"/briefs\">All briefs</a> · <a href=\"/risk-index\">Risk Index</a> · <a href=\"/#plans\">Plans and pricing</a> · <a href=\"/#privacy\">Privacy</a> · <a href=\"/#terms\">Terms</a></p>";
    echo "<p class=\"mu\">© $y Outreach Consult LLC</p></div></footer>\n";
    echo "<script>var WAR_DB=" . json_encode(WAR_DB) . ",WAR_PK=" . json_encode(WAR_KEY) . ";" . WAR_SRC_JS . "</script>\n</body>\n</html>\n";
}

function war_cta(): string {
    return "<section class=\"cta\"><h2>Read the full brief</h2><p>Every theater, what each side is saying in its own words, the risk dashboard and what it means for your exposure. Twice a day, by 07:00 and 17:00 ET.</p>"
         . "<p><a class=\"btn\" href=\"/?src=seo#signup\">Start your 15-day free trial</a> <a class=\"btn ghost\" href=\"/#plans\">See plans</a></p>"
         . "<p class=\"mu\">Essential from \$19 a month. Pro \$69 a month.</p></section>";
}

const WAR_CSS = <<<'CSS'
:root{--navy:#0F213B;--ink:#131A24;--ink2:#3A4452;--mu:#687384;--line:#DDE2EA;--gold:#B08A36;--gold2:#D4B46A;--bg:#F2F4F7;--card:#fff;
--serif:"Newsreader",Georgia,serif;--sans:"Plus Jakarta Sans",system-ui,-apple-system,"Segoe UI",Arial,sans-serif}
*{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--ink);font:16px/1.6 var(--sans)}
a{color:#1F4E79}.wrap{max-width:860px;margin:0 auto;padding:0 18px}.row{display:flex;align-items:center;justify-content:space-between;gap:14px;flex-wrap:wrap}
.top{background:var(--navy);padding:14px 0}.brand{color:#fff;text-decoration:none;display:flex;align-items:baseline;gap:6px;flex-wrap:wrap}
.brand b{font-weight:800;letter-spacing:.04em}.brand em{font-style:normal;color:#6FA8DC;letter-spacing:.3em;font-size:12px;font-weight:700}
.brand span{color:var(--gold2);font-family:var(--serif);font-style:italic;font-size:14px;margin-left:6px}
.top nav{display:flex;gap:16px;align-items:center;flex-wrap:wrap}.top nav a{color:#C9D4E2;text-decoration:none;font-weight:700;font-size:14px}
.top nav a.gold{background:var(--gold2);color:var(--navy);padding:8px 14px;border-radius:999px}
main{padding:28px 0 10px}h1{font-family:var(--serif);font-weight:600;font-size:clamp(28px,5vw,40px);line-height:1.15;margin:6px 0 10px}
h2{font-family:var(--serif);font-weight:600;font-size:24px;margin:0 0 10px}.eyebrow{color:var(--gold);font-weight:800;font-size:12px;letter-spacing:.12em;text-transform:uppercase}
.mu{color:var(--mu);font-size:14px}.card{background:var(--card);border:1px solid var(--line);border-radius:14px;padding:22px;margin:18px 0}
.kd{list-style:none;padding:0;margin:0}.kd li{padding:10px 0;border-top:1px solid var(--line)}.kd li:first-child{border-top:0}.kd b{color:var(--navy)}
.sides{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:14px}.side{border-left:4px solid #63A8FF;padding:4px 0 4px 14px}
.side:nth-child(2){border-color:#FF7070}.side blockquote{margin:6px 0;font-family:var(--serif);font-size:20px;line-height:1.35}
.cta{background:var(--navy);color:#F2F5F9;border-radius:14px;padding:26px;margin:24px 0}.cta h2{color:#fff}.cta .mu{color:#A7B5C7}
.btn{display:inline-block;background:var(--gold2);color:var(--navy);font-weight:800;padding:11px 18px;border-radius:999px;text-decoration:none;margin:4px 6px 4px 0}
.btn.ghost{background:transparent;color:#fff;border:1px solid #A7B5C7}.pn{display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap;margin:10px 0 24px}
.list{list-style:none;padding:0;margin:0}.list li{padding:12px 0;border-top:1px solid var(--line)}.list time{display:block;color:var(--mu);font-size:13px;font-weight:600}
.list a{font-weight:600;text-decoration:none;color:var(--ink)}.list a:hover{text-decoration:underline}
table{width:100%;border-collapse:collapse;font-size:15px}th,td{text-align:left;padding:10px 8px;border-top:1px solid var(--line)}thead th{border-top:0;color:var(--mu);font-size:13px}
.lv{font-weight:700;border-radius:6px;padding:2px 8px;color:#fff;white-space:nowrap}.l1{background:#3F8F5F}.l2{background:#4A73A8}.l3{background:#A7822A}.l4{background:#B9612F}.l5{background:#A93838}
.tr{display:block;font-size:12px;color:var(--mu)}.foot{border-top:1px solid var(--line);padding:22px 0 30px;margin-top:20px;font-size:14px;color:var(--ink2)}
.tbl{overflow-x:auto}
.free h2{margin-bottom:6px}.frow{display:flex;gap:10px;flex-wrap:wrap;margin:12px 0}.frow input{flex:1 1 240px;min-width:0;font:inherit;padding:12px 14px;border:1px solid var(--line);border-radius:999px}
.frow .btn{margin:0;border:0;cursor:pointer;font:inherit;font-weight:800}.free .msg{color:#A93838;font-weight:600;min-height:1em;margin:8px 0 0}.hp{position:absolute;left:-9999px;width:1px;height:1px;opacity:0}
.ok{background:#EAF5EE;border:1px solid #BFE0CB;border-radius:12px;padding:16px}.sr{position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0 0 0 0)}
.hero{background:var(--navy);color:#F2F5F9;padding:34px 0 30px;margin-top:-28px}.hero h1{color:#fff}.hero p{color:#C9D4E2;max-width:640px}.ticks{padding-left:18px}.ticks li{margin:6px 0}
CSS;
