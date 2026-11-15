. /tmp/env.sh
mariadb --socket=/tmp/mdb.sock -uroot -e "DROP DATABASE IF EXISTS wdos; CREATE DATABASE wdos CHARACTER SET utf8mb4; GRANT ALL ON wdos.* TO 'wdos'@'127.0.0.1';"
J='-H Content-Type:application/json -H X-WDOS:1'
curl -s localhost:8080/api/setup -X POST $J -d '{"key":"test-admin-key","action":"migrate"}' >/dev/null
curl -s localhost:8080/api/setup -X POST $J -d '{"key":"test-admin-key","action":"admin","first_name":"Azeez","last_name":"Olayinka","email":"hq@test.local","password":"hq-password-1"}' >/dev/null
[ -n "$GUARDRAIL" ] || { curl -s -c /tmp/hq.ck localhost:8080/api/auth/login -X POST $J -d '{"email":"hq@test.local","password":"hq-password-1"}' >/dev/null; curl -s -b /tmp/hq.ck localhost:8080/api/policy -X POST $J -d '{"key":"chapter_auto_create","value":"on","note":"test copy"}' >/dev/null; }
[ -n "$NOSEED" ] || php /home/claude/seed_local.php
