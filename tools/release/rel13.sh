#!/bin/bash
# WDOS v109.13 release: selected files from GitHub commit 11d0a1007ce4ccce696fdc5828d11b19507b9ac9
exec 9>/home/woddicrm/rel13.lock; flock -n 9 || exit 0
[ -f /home/woddicrm/rel13.done ] && exit 0
R=/home/woddicrm/public_html; T=/home/woddicrm/rel13_tmp; B=/home/woddicrm/rel13_backup
rm -rf $T; mkdir -p $T $B
for f in api/index.php assets/css/app.css js/app.js js/goodmother.js js/goodmother_world.js js/pages/calls.js js/pages/meetreq.js js/ui.js lib/calls.php lib/core.php lib/followup_fns.php lib/notify_i18n.php lib/v109.php migrations/035_voice_calls.sql sw.js; do
  mkdir -p "$T/$(dirname $f)"
  curl -fsSL "https://raw.githubusercontent.com/craigfunds-maker/WDOS/11d0a1007ce4ccce696fdc5828d11b19507b9ac9/wdos-php/$f" -o "$T/$f" || { echo "fetch failed $f" > /home/woddicrm/rel13.err; exit 1; }
  [ -s "$T/$f" ] || { echo "empty $f" > /home/woddicrm/rel13.err; exit 1; }
done
for f in api/index.php assets/css/app.css js/app.js js/goodmother.js js/goodmother_world.js js/pages/calls.js js/pages/meetreq.js js/ui.js lib/calls.php lib/core.php lib/followup_fns.php lib/notify_i18n.php lib/v109.php migrations/035_voice_calls.sql sw.js; do mkdir -p "$B/$(dirname $f)"; [ -f "$R/$f" ] && cp -p "$R/$f" "$B/$f"; done
for f in api/index.php assets/css/app.css js/app.js js/goodmother.js js/goodmother_world.js js/pages/calls.js js/pages/meetreq.js js/ui.js lib/calls.php lib/core.php lib/followup_fns.php lib/notify_i18n.php lib/v109.php migrations/035_voice_calls.sql sw.js; do mkdir -p "$R/$(dirname $f)"; cp "$T/$f" "$R/$f"; done
rm -rf $T; date > /home/woddicrm/rel13.done
