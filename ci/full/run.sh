#!/usr/bin/env bash
# Every WDOS suite (t93 to t112) on fresh databases, the way they run on the developer's machine.
# The suites expect /home/claude and a local MariaDB socket; this script recreates that layout on the CI runner.
set -u
WS="${GITHUB_WORKSPACE:-$(pwd)}"
sudo mkdir -p /home/claude && sudo chown "$(id -u)" /home/claude
ln -sfn "$WS/wdos-php" /home/claude/wdos-php
cp "$WS"/ci/full/* /home/claude/
# the suites call "mariadb --socket=/tmp/mdb.sock -uroot <db> ..."; on the runner the database is the service on port 3306
sudo tee /usr/local/bin/mariadb >/dev/null <<'W'
#!/usr/bin/env bash
a=(); for x in "$@"; do case "$x" in --socket=*|-uroot) ;; *) a+=("$x");; esac; done
exec mysql -h127.0.0.1 -P3306 -uroot -proot "${a[@]}"
W
sudo chmod +x /usr/local/bin/mariadb
cat > /tmp/env.sh <<'V'
export DB_HOST=127.0.0.1 DB_PORT=3306 DB_NAME=wdos DB_USER=root DB_PASS=root ADMIN_KEY=test-admin-key APP_URL=http://localhost:8080
. /tmp/vapid.env
V
: > /tmp/vapid.env
cd /home/claude
start() { pkill -f router.php; sleep 0.5; (cd /home/claude/wdos-php; . /tmp/env.sh; export $1; nohup php -d post_max_size=24M -d upload_max_filesize=20M -d memory_limit=256M -S 127.0.0.1:8080 /home/claude/router.php >/tmp/php.log 2>&1 &); for i in $(seq 1 30); do curl -sf localhost:8080/api/config >/dev/null && break; sleep 1; done; }
MAIL="FOLLOWUP_ANY_HOUR=1 BREVO_API_KEY=ci-not-real MAIL_FROM=wdos-ci@wdos-mail.org BREVO_API_URL=http://127.0.0.1:8099/v3/smtp/email"
(nohup python3 aimock.py >/tmp/aimock.out 2>&1 &)
fails=0
run() { bash reset.sh >/dev/null 2>&1; out=$(timeout 1200 python3 "$@" 2>&1); echo "$out" | tail -2; echo "$out" | grep -aq "FAILURES 0" || { echo "::error::$* failed"; echo "$out" | grep -aE "FAIL|Traceback|Error" | head -15; fails=$((fails+1)); }; }
start "$MAIL"
for t in t97 t98 t99 t100 t101 t102 t103 t104 t105 t108 t109 t110 t111 t112 t114 t115 t116 t117 t118 t119 t120 t121 t122 t123 t124 t125 t126 t127 t128 t129 t130 t131 t132 t133 t134 t135 t136 t137 t138 t139 t140; do echo "== $t"; run $t.py; done
start "DUMMY=1"
echo "== t93 then t96 (t96 continues from t93)"; bash reset.sh >/dev/null 2>&1; o=$(python3 t93.py 2>&1); echo "$o" | tail -1; echo "$o" | grep -aq "FAILURES 0" || { echo "::error::t93 failed"; fails=$((fails+1)); }
o=$(python3 t96.py 2>&1); echo "$o" | tail -1; echo "$o" | grep -aq "FAILURES 0" || { echo "::error::t96 failed"; echo "$o" | grep -aE "FAIL|Traceback" | head; fails=$((fails+1)); }
echo "== t106 (email off)"; run t106.py
echo "== t107 without an AI key"; run t107.py nokey
start "AI_MOCK_URL=http://127.0.0.1:8099"; echo "== t107 with the stand-in AI"; run t107.py mock
echo "== t113 work check (stand-in AI)"; run t113.py
echo "suites failed: $fails"; exit $fails
