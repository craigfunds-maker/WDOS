#!/usr/bin/env bash
# Fresh test database, migrations, an HQ admin and synthetic members. CI only; nothing here is real data.
set -euo pipefail
J=(-H 'Content-Type: application/json' -H 'X-WDOS: 1')
mysql -h127.0.0.1 -P3306 -uroot -proot -e "DROP DATABASE IF EXISTS wdos; CREATE DATABASE wdos CHARACTER SET utf8mb4;"
curl -sf localhost:8080/api/setup -X POST "${J[@]}" -d '{"key":"test-admin-key","action":"migrate"}' >/dev/null
curl -sf localhost:8080/api/setup -X POST "${J[@]}" -d '{"key":"test-admin-key","action":"admin","first_name":"Azeez","last_name":"Olayinka","email":"hq@test.local","password":"hq-password-1"}' >/dev/null
curl -sf -c /tmp/hq.ck localhost:8080/api/auth/login -X POST "${J[@]}" -d '{"email":"hq@test.local","password":"hq-password-1"}' >/dev/null
curl -sf -b /tmp/hq.ck localhost:8080/api/policy -X POST "${J[@]}" -d '{"key":"chapter_auto_create","value":"on","note":"ci"}' >/dev/null
php ci/seed.php
