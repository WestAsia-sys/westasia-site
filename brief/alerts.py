"""Alerts for Developments: browser/phone push and email.

Policy (agreed October 10, 2026):
- A Major development is pushed immediately, even while it is still Developing.
  The title says so: "Developing: <headline>". The body names who is reporting it.
- When an alerted development is later confirmed or corrected, the people who got
  the first alert receive one short follow-up ("Confirmed: ..." / "Correction: ...").
  On phones and browsers the follow-up replaces the first notification.
- Notable (non-major) developments are pushed only to subscribers who chose
  "Every development"; everyone else sees them in the feed.
- Quiet hours 22:00-06:00 ET hold back non-major alerts. Major alerts always go.
- At most DAILY_CAP non-major alerts per subscriber per day.

Push needs VAPID keys: VAPID_PUBLIC_KEY goes in the website, VAPID_PRIVATE_KEY only
in GitHub secrets. Generate them once with:  python -m brief.vapid
"""
from __future__ import annotations

import hashlib
import json
import logging
import os
from datetime import datetime, timezone
from html import escape as html_escape

from .config import SECTION_TITLES, Settings

log = logging.getLogger("brief.alerts")
DAILY_CAP = 4
QUIET_START, QUIET_END = 22, 6
SITE = os.environ.get("SITE_URL", "https://westasiareport.com")
DEFAULT_PREFS = {"level": "major", "push": True, "email": True, "followups": True, "quiet": True}
LABEL = {"developing": "Developing", "confirmed": "Confirmed", "corrected": "Correction"}


def _outlets(dev: dict, n: int = 2) -> str:
    names = [s["outlet"] for s in dev.get("sources", [])][:n]
    return ", ".join(names)


def message(event: dict) -> dict:
    """Notification content. Same text for push and email subject."""
    dev = event["dev"]
    if event["kind"] == "followup":
        label = LABEL["corrected" if event["change"] == "corrected" else "confirmed"]
        body = dev["body"].split(". ")[0].rstrip(".") + "."
    else:
        label = LABEL[dev["status"]]
        who = _outlets(dev)
        body = (f"Reported by {who}. Not yet confirmed by a second independent source."
                if dev["status"] == "developing" else f"Confirmed by {who}.")
    return {"title": f"{label}: {dev['headline'].rstrip('.')}", "body": body,
            "tag": dev["id"], "url": f"{SITE}/?dv={dev['id']}#developments",   # opens this development
            "section": SECTION_TITLES.get(dev.get("section"), "")}


def is_quiet(now_et: datetime) -> bool:
    return now_et.hour >= QUIET_START or now_et.hour < QUIET_END


def should_send(event: dict, prefs: dict, now_et: datetime, sent_today: int,
                got_original: bool) -> bool:
    p = {**DEFAULT_PREFS, **(prefs or {})}
    if p["level"] == "off":
        return False
    major = event["dev"].get("level") == "major"
    if event["kind"] == "followup":
        if not (p["followups"] and got_original):
            return False
        # a follow-up to a non-major development is a non-major alert: quiet hours apply
        return major or not (p["quiet"] and is_quiet(now_et))
    if not major:
        if p["level"] != "all":
            return False
        if p["quiet"] and is_quiet(now_et):
            return False
        if sent_today >= DAILY_CAP:
            return False
    return True


# ------------------------------------------------------------------ senders

def send_push(sub: dict, msg: dict, store) -> bool:
    priv = os.environ.get("VAPID_PRIVATE_KEY")
    if not priv:
        log.warning("VAPID_PRIVATE_KEY not set; push skipped")
        return False
    from pywebpush import WebPushException, webpush
    try:
        if not (sub.get("endpoint") and sub.get("p256dh") and sub.get("auth")):
            log.warning("push subscription with missing keys skipped")
            return False
        webpush(subscription_info={"endpoint": sub["endpoint"],
                                   "keys": {"p256dh": sub["p256dh"], "auth": sub["auth"]}},
                data=json.dumps(msg), vapid_private_key=priv,
                vapid_claims={"sub": "mailto:editor@westasiareport.com"}, ttl=3 * 3600)
        return True
    except WebPushException as exc:
        code = getattr(getattr(exc, "response", None), "status_code", None)
        if code in (404, 410):  # device unsubscribed or expired
            store.delete_push(sub["endpoint"])
        else:
            log.warning("push failed (%s): %s", code, exc)
        return False
    except Exception as exc:   # network errors, bad keys: one device never stops the others
        log.warning("push failed: %s: %s", type(exc).__name__, exc)
        return False


def send_email_alert(settings: Settings, to: str, msg: dict) -> bool:
    if not (settings.resend_api_key and settings.brief_from):
        return False
    from .deliver import _post
    title, body, url = (html_escape(str(msg[k])) for k in ("title", "body", "url"))
    html = (f'<div style="font-family:Arial,sans-serif;max-width:560px">'
            f'<p style="font-size:12px;letter-spacing:1px;text-transform:uppercase;color:#b08a36">West Asia Report · Developments</p>'
            f'<h2 style="font-family:Georgia,serif;color:#0f213b;margin:6px 0 10px">{title}</h2>'
            f'<p style="font-size:15px;color:#1f2933">{body}</p>'
            f'<p><a href="{url}" style="color:#1f4e79;font-weight:bold">Open this development</a></p>'
            f'<p style="font-size:12px;color:#687384">You get these because Developments alerts are on. '
            f'Change them in Developments › Alerts.</p></div>')
    payload = {"from": settings.brief_from, "to": [to], "subject": msg["title"],
               "html": html, "text": f'{msg["title"]}\n\n{msg["body"]}\n\n{msg["url"]}',
               **({"reply_to": settings.reply_to} if settings.reply_to else {})}
    # Retried on rate limits (Resend allows 2 a second) and outages; the key makes a retry
    # after a timeout safe. Same development + same text + same address = same email.
    idem = "alert-" + hashlib.sha256(f'{msg.get("tag")}|{msg["title"]}|{msg["body"]}|{to.lower()}'.encode()).hexdigest()[:40]
    ok, err = _post(settings.resend_api_key, payload, idem)
    if not ok:
        log.warning("alert email to %s failed: %s", to, err)
    return ok


def send_alerts(events: list[dict], now_et: datetime, settings: Settings, store) -> dict:
    """Decide who gets what, send, and log it. Returns counts."""
    stats = {"push": 0, "email": 0, "skipped": 0, "recipients": 0}
    if not events:
        return stats
    people = store.recipients()
    stats["recipients"] = len(people)
    day_start = now_et.replace(hour=0, minute=0, second=0, microsecond=0).astimezone(timezone.utc).isoformat()
    log_rows = store.alerts_since(day_start)
    # Major alerts don't count toward the daily cap (the in-run count below skips them too).
    try:
        major_ids = {d["id"] for d in store.list_day(now_et.date().isoformat()) if d.get("level") == "major"}
    except Exception as exc:   # stores without developments (or a read error): count everything
        log.warning("could not read today's developments for the alert cap: %s", exc)
        major_ids = set()
    # Count alerts, not deliveries: one alert sent by push AND email is still one alert.
    seen_pairs = {(r["user_id"], r["development_id"]) for r in log_rows
                  if r.get("kind", "new") == "new" and r["development_id"] not in major_ids}
    sent_today: dict[str, int] = {}
    for uid, _ in seen_pairs:
        sent_today[uid] = sent_today.get(uid, 0) + 1
    new_rows = []
    now_iso = now_et.astimezone(timezone.utc).isoformat()
    try:
        _send_events(events, people, now_et, settings, store, stats, sent_today, new_rows, now_iso)
    finally:   # log what went out even if a later send crashed: follow-ups and caps depend on it
        store.log_alerts(new_rows)
    log.info("alerts: %s", stats)
    return stats


def _send_events(events, people, now_et, settings, store, stats, sent_today, new_rows, now_iso) -> None:
    for ev in events:
        msg = message(ev)
        dev_id = ev["dev"]["id"]
        # Follow-ups go to whoever got the first alert, even on an earlier day. A "new" alert
        # never goes twice to the same person (re-runs, a development escalated to major).
        got_first = store.alerted_users(dev_id)
        for person in people:
            uid, prefs = person["user_id"], person.get("dev_alerts") or {}
            original = uid in got_first
            if ev["kind"] == "new" and original:
                stats["skipped"] += 1
                continue
            if not should_send(ev, prefs, now_et, sent_today.get(uid, 0), original):
                stats["skipped"] += 1
                continue
            p = {**DEFAULT_PREFS, **prefs}
            delivered = False
            if p["push"]:
                for sub in person.get("push_subs") or []:
                    if send_push(sub, msg, store):
                        stats["push"] += 1; delivered = True
                        new_rows.append({"user_id": uid, "development_id": dev_id, "kind": ev["kind"], "channel": "push", "sent_at": now_iso})
            if p["email"] and person.get("email") and send_email_alert(settings, person["email"], msg):
                stats["email"] += 1; delivered = True
                new_rows.append({"user_id": uid, "development_id": dev_id, "kind": ev["kind"], "channel": "email", "sent_at": now_iso})
            if delivered:
                sent_today[uid] = sent_today.get(uid, 0) + (0 if ev["dev"].get("level") == "major" else 1)
        if ev["kind"] == "new":
            try:
                store.mark_alerted(dev_id)
            except Exception as exc:
                log.warning("could not mark %s as alerted: %s", dev_id, exc)
