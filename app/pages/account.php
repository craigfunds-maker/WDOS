<?php
/* Home, profile, settings, Growth Points, messages and notifications, support, certificates, progress, public verification. */
declare(strict_types=1);

/* ───────── Public certificate verification ───────── */
if ($first === 'verify') {
    $code = strtoupper(trim((string)($_GET['code'] ?? ($seg[1] ?? '')))); $c = $code ? row('SELECT * FROM certificates WHERE credential_code = ?', [$code]) : null;
    $h = '<div class="au-top"><img src="' . asset('logo.jpg') . '" alt="WODDI logo"><div class="grow"><b style="font-family:Manrope,sans-serif">WODDI Institute</b><div class="xs mut">Certificate verification</div></div></div><div class="au-wrap"><form class="au-card" method="get" action="' . url('verify') . '" style="max-width:620px"><h1>Verify a certificate</h1><p class="mut">Type the credential code printed on the certificate.</p><div class="row"><input class="au-inp" name="code" value="' . e($code) . '" placeholder="WDI-2026-ABC123" required><button class="b au-b">Check</button></div>';
    if ($code && $c && empty($c['revoked'])) $h .= '<div class="row rt" style="background:var(--lems);border-radius:16px;padding:16px"><i class="fa-solid fa-circle-check" style="color:var(--lemt);font-size:26px"></i><div><p class="bold">Valid certificate</p><p>' . e($c['student_name']) . '</p><p class="sm">' . e($c['course_name'] ?: $c['title']) . '</p><p class="sm mut">' . (($co = (string)($c['cohort'] ?? '')) ? e($co) . ' cohort. ' : '') . 'Issued ' . e(gmdate('j F Y', strtotime((string)$c['issued_at']))) . ' by WODDI Institute. Certificate ID ' . e($c['credential_code']) . '.</p></div></div>';
    elseif ($code && $c) $h .= '<div class="flash err">This certificate was revoked and is no longer valid.</div>';
    elseif ($code) $h .= '<div class="flash err">No certificate was found with that code. Please check it and try again.</div>';
    page('Verify a certificate', $h . '<p class="xs mut">A WODDI Institute certificate confirms completion of a course. It is not a university degree.</p></form></div>', ['bare' => true]);
}

if ($first === 'delete-account' && !me()) page('Delete your account', '<div class="au-top"><a href="' . url('') . '"><img src="' . asset('logo.jpg') . '" alt="WODDI logo"></a><div class="grow"><b style="font-family:Manrope,sans-serif">WODDI Institute</b><div class="xs mut">Delete your account and data</div></div></div><div class="au-wrap"><div class="au-card" style="max-width:620px"><h1>Delete your WODDI account and data</h1><p>You can delete your WODDI Institute account and everything linked to it at any time.</p><div class="row rt"><span class="num">1</span><p class="grow">Sign in to your account.</p></div><div class="row rt"><span class="num">2</span><p class="grow">Open Settings and choose "Delete account and data".</p></div><div class="row rt"><span class="num">3</span><p class="grow">Type DELETE to confirm. Your profile, progress, posts, messages and certificates are removed straight away.</p></div><a class="b au-b" href="' . url('login') . '">Sign in to delete my account</a><p class="sm mut">Cannot sign in? Email support@thewoddi.org from the address on your account and we will delete it within 7 days. Certificate verification records are removed with the account.</p></div></div>', ['bare' => true]);
$me = require_login(); $uid = $me['uid'];

/* ───────── Home ───────── */
if ($first === '') {
    $j = journey($uid); $pts = points($uid);
    $last = row("SELECT course_id FROM course_progress WHERE uid = ? AND phase IN ('lesson','module-quiz') ORDER BY updated_at DESC LIMIT 1", [$uid]);
    $cont = null;
    if ($last && ($it = catalog_item((string)$last['course_id'])) && $it['network'] === net() && ($co = course($it['id']))) { $s = course_state($uid, $co); $cont = ['title' => $co['title'], 'pct' => $s['pct'], 'label' => $s['next']['label'], 'url' => step_url($it['id'], $s['next']), 'course' => url('course/' . $it['id'])]; }
    if (!$cont) {
        if (!$j['root_done']) { $ph = phases(); $nx = null; foreach ($ph['rooting'] as $l) if (!in_array($l['id'], $j['rooting'], true)) { $nx = $l; break; } $cont = ['title' => 'Rooting Phase', 'pct' => (int)round(count($j['rooting']) / max(1, $j['root_total']) * 100), 'label' => $nx ? str_replace(' — ', ': ', $nx['title']) : 'Start here', 'url' => url('journey/rooting/' . ($nx['id'] ?? 'r1')), 'course' => url('journey')]; }
        else $cont = ['title' => $j['blue_done'] || !$j['gate'] ? 'Choose your first course' : (net() === 'nnn' ? 'Blueprint Academy' : 'Blueprint'), 'pct' => $j['blue_total'] ? (int)round($j['blue_n'] / $j['blue_total'] * 100) : 0, 'label' => $j['hub_open'] ? 'The Course Hub is open to you' : 'Continue your journey', 'url' => url($j['hub_open'] ? 'learn' : 'journey'), 'course' => url('journey')];
    }
    $days = array_column(rows('SELECT DISTINCT DATE(created_at) d FROM learning_events WHERE uid = ? AND created_at > ?', [$uid, gmdate('Y-m-d', time() - 8 * 86400)]), 'd');
    $hr = (int)gmdate('G', time() + 3600); $greet = $hr < 12 ? 'Good morning' : ($hr < 17 ? 'Good afternoon' : 'Good evening');
    $h = '<div class="split"><div class="grow col" style="gap:18px"><div class="row jb"><div><p class="mut sm">' . $greet . '</p><h1>' . e(display_name($me)) . '</h1></div><a class="pill l" style="min-height:40px;padding:0 14px" href="' . url('points') . '">' . ic('fa-seedling') . ' ' . $pts . ' points</a></div>'
        . '<div class="hero"><div class="row" style="gap:16px"><div class="ring" style="background:conic-gradient(#7CB518 ' . round($cont['pct'] * 3.6) . 'deg,rgba(255,255,255,.16) 0)"><span>' . $cont['pct'] . '%</span></div><div class="grow"><p class="kick">Continue learning</p><h2 style="margin:2px 0 4px">' . e($cont['title']) . '</h2><p class="sm soft">' . e($cont['label']) . '</p></div></div><div class="row"><a class="b l grow" href="' . $cont['url'] . '">' . ic('fa-play') . ' ' . ($cont['pct'] >= 100 ? 'Open' : ($cont['pct'] ? 'Resume' : 'Start')) . '</a><a class="b gl" href="' . $cont['course'] . '">Overview</a></div></div>'
        . '<div class="card col"><h3>This week</h3><div class="row" style="gap:4px">';
    for ($i = 6; $i >= 0; $i--) { $d = gmdate('Y-m-d', time() - $i * 86400); $on = in_array($d, $days, true); $h .= '<div class="day ' . ($i === 0 ? 't' : ($on ? 'd' : '')) . '"><b>' . ic($i === 0 ? 'fa-fire' : ($on ? 'fa-check' : 'fa-minus')) . '</b>' . gmdate('D', time() - $i * 86400)[0] . '</div>'; }
    $h .= '</div></div><div class="col"><div class="row jb"><h3>Your journey</h3><a class="pill n" href="' . url('journey') . '">Open map</a></div><div class="g3">';
    foreach ([['Rooting Phase', count($j['rooting']) . ' of ' . $j['root_total'], $j['root_done'], 'fa-tree', 'root', count($j['rooting']) / max(1, $j['root_total'])], [net() === 'nnn' ? 'Blueprint Academy' : 'Blueprint', $j['blue_n'] . ' of ' . $j['blue_total'], $j['blue_done'], 'fa-compass-drafting', 'blue', $j['blue_n'] / max(1, $j['blue_total'])], [net() === 'nnn' ? 'Professional Electives' : 'Course Hub', $j['hub_open'] ? 'Open' : 'Locked', false, 'fa-graduation-cap', 'hub', $j['hub_open'] ? 0.5 : 0]] as $s)
        $h .= '<a class="card flat col" style="gap:10px;padding:14px" href="' . url('journey', ['tab' => $s[4]]) . '"><span class="row"><span class="ic s ' . ($s[2] ? 'l' : '') . '">' . ic($s[3]) . '</span><span class="grow"><span class="bold" style="display:block">' . $s[0] . '</span><span class="mut xs">' . $s[1] . '</span></span>' . pill($s[2] ? 'Complete' : ($s[1] === 'Locked' ? 'Locked' : 'In progress'), $s[2] ? 'l' : 'n') . '</span>' . bar((int)round($s[5] * 100)) . '</a>';
    $h .= '</div></div><div class="col"><h3>Quick access</h3><div class="g4">';
    foreach ([['certificates', 'Certificates', 'fa-certificate'], ['points', 'Growth Points', 'fa-seedling'], ['radio', 'Radio', 'fa-radio'], ['market', 'Marketplace', 'fa-store'], ['community?tab=events', 'Events', 'fa-calendar-days'], ['alumni', 'Alumni', 'fa-user-graduate'], ['guide', 'App guide', 'fa-circle-play'], ['progress', 'My progress', 'fa-chart-line']] as $t) $h .= '<a class="tile" href="' . url($t[0]) . '"><span class="ic">' . ic($t[2]) . '</span>' . $t[1] . '</a>';
    $h .= '</div></div></div><div class="aside"><div class="card col"><div class="row jb"><h3>From WODDI</h3><a class="pill n" href="' . url('messages') . '">All</a></div>';
    $ann = rows("SELECT title, body, created_at FROM announcements WHERE COALESCE(status,'published') = 'published' AND (network IS NULL OR network IN ('all', ?, ?)) ORDER BY pinned DESC, created_at DESC LIMIT 3", [net(), net_code()]);
    foreach ($ann as $a) $h .= '<a class="row rt" href="' . url('messages') . '"><span class="ic s g">' . ic('fa-bullhorn') . '</span><span class="grow"><span class="bold" style="display:block">' . e($a['title']) . '</span><span class="mut sm">' . e(mb_strimwidth(strip_tags(str_replace('**', '', (string)$a['body'])), 0, 110, '...')) . '</span></span></a>';
    if (!$ann) $h .= '<p class="mut sm">No announcements yet.</p>';
    $h .= '</div><div class="card col"><h3>Stay in the loop</h3><p class="mut sm">Turn on notifications to get reminders, messages and certificate alerts on this phone.</p><button class="b g s" type="button" data-push>' . ic('fa-bell') . ' Turn on notifications</button></div></div></div>';
    page('WODDI Institute', $h, ['active' => '']);
}

/* ───────── Messages and notifications ───────── */
if ($first === 'messages') {
    if (is_post() && post('do') === 'readall') { q('UPDATE notifications SET read_at = ?, `read` = 1, unread = 0 WHERE (uid = ? OR user_id = ?) AND read_at IS NULL', [now(), $uid, $uid]); redirect('messages'); }
    if (is_post() && post('do') === 'dm') {
        $to = (string)post('to'); $body = mb_substr((string)post('body'), 0, 2000);
        if ($to && $body !== '' && val('SELECT 1 FROM users WHERE uid = ?', [$to]) && !val('SELECT 1 FROM user_blocks WHERE blocker_uid = ? AND blocked_uid = ?', [$to, $uid])) {
            ins('direct_messages', ['from_user_id' => $uid, 'from_user_name' => display_name($me), 'from_name' => display_name($me), 'to_user_id' => $to, 'content' => $body]);
            notify($to, 'New message from ' . first_name($me), mb_strimwidth($body, 0, 90, '...'), 'messages?with=' . $uid, 'message');
        }
        redirect('messages', ['with' => $to]);
    }
    $with = (string)($_GET['with'] ?? ''); $tab = $with ? 'chat' : ($_GET['tab'] ?? 'inbox');
    $h = '<div class="col" style="gap:14px;max-width:760px;width:100%;margin:0 auto">' . tabs(['inbox' => 'Notifications', 'news' => 'From WODDI', 'chat' => 'Chats'], $tab, url('messages'));
    if ($with && ($other = row('SELECT uid, name, display_name, avatar_url FROM users WHERE uid = ?', [$with]))) {
        q('UPDATE direct_messages SET read_at = ? WHERE to_user_id = ? AND from_user_id = ? AND read_at IS NULL', [now(), $uid, $with]);
        $h .= '<div class="card row">' . avatar($other) . '<p class="bold grow">' . e(display_name($other)) . '</p></div><div class="col" style="gap:8px">';
        foreach (rows('SELECT * FROM direct_messages WHERE (from_user_id = ? AND to_user_id = ?) OR (from_user_id = ? AND to_user_id = ?) ORDER BY created_at LIMIT 300', [$uid, $with, $with, $uid]) as $m) $h .= '<div class="bub ' . ($m['from_user_id'] === $uid ? 'me' : '') . '">' . e($m['content']) . '<span class="xs" style="display:block;opacity:.7">' . ago($m['created_at']) . '</span></div>';
        $h .= '</div><form method="post" class="row">' . csrf_field() . '<input type="hidden" name="do" value="dm"><input type="hidden" name="to" value="' . e($with) . '"><input class="inp" name="body" required placeholder="Write a message" autocomplete="off"><button class="ib" style="background:var(--mag);color:#fff" aria-label="Send">' . ic('fa-paper-plane') . '</button></form>';
    } elseif ($tab === 'chat') {
        $list = rows('SELECT u.uid, u.name, u.display_name, u.avatar_url, MAX(d.created_at) t, SUM(d.to_user_id = ? AND d.read_at IS NULL) unread FROM direct_messages d JOIN users u ON u.uid = IF(d.from_user_id = ?, d.to_user_id, d.from_user_id) WHERE d.from_user_id = ? OR d.to_user_id = ? GROUP BY u.uid, u.name, u.display_name, u.avatar_url ORDER BY t DESC', [$uid, $uid, $uid, $uid]);
        if (!$list) $h .= empty_state('fa-comments', 'No chats yet', 'Open a member in the community and send her a message.');
        else { $h .= '<div class="grp">'; foreach ($list as $c) $h .= '<a class="li" href="' . url('messages', ['with' => $c['uid']]) . '">' . avatar($c) . '<span class="grow"><span class="bold" style="display:block">' . e(display_name($c)) . '</span><span class="mut xs">' . ago($c['t']) . '</span></span>' . ($c['unread'] ? pill((string)$c['unread']) : '') . '</a>'; $h .= '</div>'; }
    } elseif ($tab === 'news') {
        $ann = rows("SELECT * FROM announcements WHERE COALESCE(status,'published') = 'published' AND (network IS NULL OR network IN ('all', ?, ?)) ORDER BY pinned DESC, created_at DESC LIMIT 50", [net(), net_code()]);
        if (!$ann) $h .= empty_state('fa-bullhorn', 'No announcements yet');
        foreach ($ann as $a) $h .= '<div class="card col"><div class="row"><span class="ic s g">' . ic('fa-bullhorn') . '</span><h3 class="grow">' . e($a['title']) . '</h3><span class="xs mut">' . ago($a['created_at']) . '</span></div><p style="white-space:pre-line">' . preg_replace('/\*\*([^*]+)\*\*/', '<strong>$1</strong>', e((string)$a['body'])) . '</p></div>';
    } else {
        $list = rows('SELECT * FROM notifications WHERE uid = ? OR user_id = ? ORDER BY created_at DESC LIMIT 80', [$uid, $uid]);
        $h .= '<form method="post" class="row jb">' . csrf_field() . '<input type="hidden" name="do" value="readall"><p class="mut sm">' . unread_count($uid) . ' unread</p><button class="b g s">' . ic('fa-check-double') . ' Mark all read</button></form>';
        if (!$list) $h .= empty_state('fa-bell', 'Nothing yet', 'Reminders, messages and certificate alerts will appear here.');
        foreach ($list as $n) { $un = !$n['read_at'] && empty($n['read']); $link = (string)($n['link_url'] ?: $n['link']); $h .= '<a class="card row rt" style="' . ($un ? 'border-color:var(--mag)' : '') . '" href="' . ($link ? (preg_match('#^https?://#', $link) ? e($link) : url($link)) : '#') . '"><span class="ic s">' . ic('fa-bell') . '</span><span class="grow"><span class="row jb"><span class="bold">' . e($n['title'] ?: 'WODDI Institute') . '</span><span class="xs mut">' . ago($n['created_at']) . '</span></span><span class="mut sm" style="display:block">' . e($n['body'] ?: $n['text']) . '</span></span></a>'; }
    }
    page('Messages', $h . '</div>', ['active' => 'messages', 'back' => '']);
}

/* ───────── Profile hub, edit profile, settings ───────── */
if ($first === 'me') {
    $certN = (int)val('SELECT COUNT(*) FROM certificates WHERE uid = ? AND COALESCE(revoked,0) = 0', [$uid]); $pts = points($uid);
    $h = '<div class="col" style="gap:18px;max-width:860px;width:100%;margin:0 auto"><div class="card col center" style="gap:8px">' . avatar($me, 'big') . '<h1 style="font-size:1.5em">' . e(display_name($me)) . '</h1><p class="mut sm">' . e(implode(', ', array_filter([$me['city'] ?? '', $me['country'] ?? '']))) . ($me['profession'] ? '. ' . e($me['profession']) : '') . '</p><div class="row wrap" style="justify-content:center;gap:6px">' . pill(net_code() . ' member') . pill(level_for($pts), 'l') . '</div><a class="b g s" href="' . url('profile') . '">' . ic('fa-pen') . ' Edit profile</a></div><div class="g4">';
    foreach ([[$pts, 'Points', 'points'], [$certN, 'Certificates', 'certificates'], [(int)($me['streak'] ?? 0), 'Day streak', 'progress'], [net_code(), 'Network', 'settings']] as $s) $h .= '<a class="tile center" style="align-items:center;min-height:0" href="' . url($s[2]) . '"><span style="font-family:Manrope,sans-serif;font-weight:800;font-size:1.6em">' . e((string)$s[0]) . '</span><span class="mut xs">' . $s[1] . '</span></a>';
    $h .= '</div><div class="grp">';
    foreach ([['learn?mine=1', 'My courses', 'fa-book-open-reader'], ['journey', 'My journey', 'fa-road'], ['progress', 'My progress', 'fa-chart-line'], ['certificates', 'Certificates', 'fa-certificate'], ['points', 'Growth Points and rewards', 'fa-seedling'], ['market?tab=shop', 'My shop', 'fa-store'], ['messages', 'Messages', 'fa-envelope'], ['support', 'Help and support', 'fa-life-ring'], ['settings', 'Settings', 'fa-gear']] as $t) $h .= '<a class="li" href="' . url($t[0]) . '"><span class="ic s">' . ic($t[2]) . '</span><span class="grow bold">' . $t[1] . '</span>' . ic('fa-chevron-right', 'mut') . '</a>';
    page('Profile', $h . '</div><a class="b g" href="' . url('logout') . '">' . ic('fa-right-from-bracket') . ' Sign out</a></div>', ['active' => 'me']);
}
if ($first === 'profile') {
    $fields = ['display_name' => 'Full name', 'phone' => 'Phone number', 'country' => 'Country', 'state' => 'State or region', 'city' => 'City', 'profession' => 'Profession', 'interest_area' => 'Area of interest', 'age_category' => 'Age category', 'digital_access' => 'Digital access', 'status_update' => 'Status update'];
    if (is_post()) {
        $d = ['updated_at' => now(), 'profile_complete' => 1]; foreach ($fields as $k => $_) $d[$k] = mb_substr((string)post($k), 0, 300);
        $d['name'] = $d['display_name']; $d['bio'] = mb_substr((string)post('bio'), 0, 1000); $d['reason_joining'] = mb_substr((string)post('reason_joining'), 0, 1000);
        foreach (['volunteer_interest', 'leadership_interest', 'mentorship_interest'] as $k) $d[$k] = post($k) ? 1 : 0;
        if (!empty($_FILES['photo']['tmp_name']) && $_FILES['photo']['size'] < 3 * 1024 * 1024 && ($info = @getimagesize($_FILES['photo']['tmp_name'])) && in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) {
            $ext = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp'][$info[2]]; @mkdir(APP_ROOT . '/uploads/avatars', 0755, true); $fn = 'uploads/avatars/' . preg_replace('/[^a-z0-9]/', '', strtolower($uid)) . '-' . time() . '.' . $ext;
            if (move_uploaded_file($_FILES['photo']['tmp_name'], APP_ROOT . '/' . $fn)) $d['avatar_url'] = url($fn);
        }
        upd('users', $d, 'uid = ?', [$uid]); flash('Profile saved.'); redirect('me');
    }
    $h = '<form method="post" enctype="multipart/form-data" class="col" style="gap:16px;max-width:760px;width:100%;margin:0 auto">' . csrf_field() . '<div class="card row">' . avatar($me, 'big') . '<label class="fld grow">Profile photo<input class="inp" type="file" name="photo" accept="image/*"><small>A clear photo helps your community know you. JPG, PNG or WebP, up to 3 MB.</small></label></div><div class="card col"><div class="g2">';
    foreach ($fields as $k => $lab) $h .= '<label class="fld">' . $lab . '<input class="inp" name="' . $k . '" value="' . e($k === 'display_name' ? display_name($me) : ($me[$k] ?? '')) . '"></label>';
    $h .= '</div><label class="fld">Why I joined WODDI<textarea class="inp" name="reason_joining" rows="2">' . e($me['reason_joining'] ?? '') . '</textarea></label><label class="fld">Short bio<textarea class="inp" name="bio" rows="2">' . e($me['bio'] ?? '') . '</textarea></label></div><div class="grp">';
    foreach (['volunteer_interest' => 'Open to volunteering', 'leadership_interest' => 'Interested in leadership', 'mentorship_interest' => 'Open to mentoring others'] as $k => $lab) $h .= '<label class="li"><span class="grow bold">' . $lab . '</span><input class="sw" type="checkbox" name="' . $k . '" value="1"' . (!empty($me[$k]) ? ' checked' : '') . '></label>';
    page('Edit profile', $h . '</div><button class="b">' . ic('fa-check') . ' Save profile</button></form>', ['active' => 'me', 'back' => 'me']);
}
if ($first === 'settings') {
    if (is_post()) {
        $do = post('do');
        if ($do === 'lang' && isset(COURSE_LANGS[post('language')])) { upd('users', ['language' => post('language')], 'uid = ?', [$uid]); $_SESSION['clang'] = post('language'); flash('Language set to ' . COURSE_LANGS[post('language')] . '.'); }
        if ($do === 'prefs') { upd('users', ['reminders_opt_out' => post('reminders') ? 0 : 1], 'uid = ?', [$uid]); flash('Notification settings saved.'); }
        if ($do === 'password') { $a = row('SELECT * FROM auth_accounts WHERE uid = ?', [$uid]); if ($a && $a['password_hash'] && !password_verify((string)post('current'), $a['password_hash'])) flash('Your current password is not right.', 'err'); elseif (strlen((string)post('new')) < 6) flash('The new password needs at least 6 characters.', 'err'); else { q('UPDATE auth_accounts SET password_hash = ? WHERE uid = ?', [password_hash((string)post('new'), PASSWORD_BCRYPT), $uid]); flash('Password changed.'); } }
        if ($do === 'unblock') { q('DELETE FROM user_blocks WHERE blocker_uid = ?', [$uid]); flash('All members unblocked.'); }
        redirect('settings');
    }
    $blocked = (int)val('SELECT COUNT(*) FROM user_blocks WHERE blocker_uid = ?', [$uid]); $langSel = '';
    foreach (COURSE_LANGS as $k => $n) $langSel .= '<option value="' . $k . '"' . (($me['language'] ?? 'en') === $k ? ' selected' : '') . '>' . e($n) . '</option>';
    $h = '<div class="col" style="gap:20px;max-width:760px;width:100%;margin:0 auto"><a class="card row" href="' . url('profile') . '">' . avatar($me) . '<span class="grow"><span class="bold" style="display:block;font-size:1.1em">' . e(display_name($me)) . '</span><span class="mut sm">Profile, photo and contact details</span></span>' . ic('fa-chevron-right', 'mut') . '</a>'
        . '<div class="col" style="gap:8px"><h4>Network</h4><div class="grp"><div class="li"><img src="' . asset(net() === 'nnn' ? 'logo-wnnn.png' : 'logo-wgmn.png') . '" alt="" style="width:44px;height:44px;border-radius:50%;object-fit:contain;background:#fff"><div class="grow"><p class="bold">' . e(net_name()) . '</p><p class="mut xs">You only see this network\'s courses.</p></div></div><a class="li" href="' . url('support', ['topic' => 'Wrong network on my account']) . '"><span class="grow">Wrong network? Ask Support to correct it</span>' . ic('fa-chevron-right', 'mut') . '</a></div></div>'
        . '<div class="col" style="gap:8px"><h4>Language and display</h4><div class="grp"><form method="post" class="li">' . csrf_field() . '<input type="hidden" name="do" value="lang"><span class="ic s">' . ic('fa-language') . '</span><span class="grow bold">App and course language</span><select class="inp" name="language" onchange="this.form.submit()" style="width:auto;min-height:44px">' . $langSel . '</select></form><div class="li"><span class="ic s">' . ic('fa-circle-half-stroke') . '</span><span class="grow bold">Dark mode</span><button class="b g s" type="button" data-theme>' . ic('fa-moon') . '</button></div></div></div>'
        . '<div class="col" style="gap:8px"><h4>Notifications</h4><div class="grp"><div class="li"><div class="grow"><p class="bold">Push notifications on this phone</p><p class="mut xs">Reminders, messages and certificate alerts</p></div><button class="b g s" type="button" data-push>Turn on</button></div><form method="post" class="li">' . csrf_field() . '<input type="hidden" name="do" value="prefs"><div class="grow"><p class="bold">Gentle learning reminders</p><p class="mut xs">By email and in the app, when you have not learned for a few days</p></div><input class="sw" type="checkbox" name="reminders" value="1"' . (empty($me['reminders_opt_out']) ? ' checked' : '') . ' onchange="this.form.submit()"></form></div></div>'
        . '<div class="col" style="gap:8px"><h4>Password</h4><form method="post" class="card col">' . csrf_field() . '<input type="hidden" name="do" value="password"><div class="g2"><label class="fld">Current password<input class="inp" type="password" name="current" autocomplete="current-password"></label><label class="fld">New password<input class="inp" type="password" name="new" required minlength="6" autocomplete="new-password"></label></div><button class="b g s" style="align-self:flex-start">Change password</button></form></div>'
        . '<div class="col" style="gap:8px"><h4>Privacy and safety</h4><div class="grp"><div class="li"><span class="grow">Journal and reflections</span>' . pill('Private', 'n') . '</div><form method="post" class="li">' . csrf_field() . '<input type="hidden" name="do" value="unblock"><span class="grow">Blocked members: ' . $blocked . '</span>' . ($blocked ? '<button class="b g s">Unblock all</button>' : '') . '</form><a class="li" href="' . url('privacy') . '"><span class="grow">Privacy policy</span>' . ic('fa-arrow-up-right-from-square', 'mut') . '</a></div></div>'
        . '<div class="grp"><a class="li" href="' . url('support') . '"><span class="grow">Help and support</span>' . ic('fa-chevron-right', 'mut') . '</a><a class="li" href="' . url('logout') . '"><span class="grow">Sign out</span>' . ic('fa-right-from-bracket', 'mut') . '</a></div><a class="b g" style="color:var(--red)" href="' . url('delete-account') . '">' . ic('fa-trash') . ' Delete account and data</a></div>';
    page('Settings', $h, ['active' => 'settings', 'back' => 'me']);
}
if ($first === 'delete-account') {
    if (is_post()) {
        if (post('confirm') !== 'DELETE') { flash('Type DELETE in capital letters to confirm.', 'err'); redirect('delete-account'); }
        foreach (['course_progress' => 'uid', 'growth_transactions' => 'uid', 'growth_profiles' => 'uid', 'notifications' => 'uid', 'push_subscriptions' => 'uid', 'forum_likes' => 'uid', 'forum_comments' => 'uid', 'forum_posts' => 'uid', 'user_progress' => 'uid', 'certificates' => 'uid', 'learning_events' => 'uid', 'reflections' => 'uid', 'mp_listings' => 'user_id', 'direct_messages' => 'from_user_id', 'auth_accounts' => 'uid', 'users' => 'uid'] as $t => $col) { try { q("DELETE FROM `$t` WHERE `$col` = ?", [$uid]); } catch (Throwable $e) {} }
        try { ins('data_governance_log', ['run_at' => now(), 'table_name' => 'users', 'row_ref' => $uid, 'field' => 'account', 'new_value' => 'deleted', 'rule' => 'learner_request', 'actor' => 'learner']); } catch (Throwable $e) {}
        $_SESSION = []; session_destroy(); redirect('login');
    }
    page('Delete account', '<form method="post" class="card col" style="max-width:560px;width:100%;margin:0 auto">' . csrf_field() . '<h2>Delete account and data</h2><p>This removes your profile, progress, posts and certificates from WODDI Institute. It cannot be undone.</p><label class="fld">Type DELETE to confirm<input class="inp" name="confirm" required placeholder="DELETE" autocomplete="off"></label><button class="b r">' . ic('fa-trash') . ' Delete my account</button><p class="xs mut">You can also email support@thewoddi.org. Requests are completed within 7 days.</p></form>', ['active' => 'settings', 'back' => 'settings']);
}

/* ───────── Growth Points ───────── */
if ($first === 'points') {
    $pts = points($uid); $rules = points_rules();
    if (is_post()) {
        if (post('do') === 'redeem' && ($r = row('SELECT * FROM rewards WHERE id = ? AND active = 1', [post('reward')]))) {
            if ($pts < (int)$r['cost_points']) flash('You need ' . ((int)$r['cost_points'] - $pts) . ' more points.', 'err');
            else { award($uid, -(int)$r['cost_points'], 'redeem:' . $r['id'] . ':' . time()); ins('reward_redemptions', ['uid' => $uid, 'reward_id' => $r['id'], 'reward_title' => $r['title'], 'cost_points' => $r['cost_points'], 'status' => 'pending']); flash($r['title'] . ' redeemed. WODDI will be in touch.'); }
        }
        if (post('do') === 'reflect' && trim((string)post('body')) !== '') { ins('reflections', ['uid' => $uid, 'context' => 'weekly', 'body' => mb_substr((string)post('body'), 0, 4000)]); award($uid, 5, 'reflection:' . gmdate('o-W')); flash('Reflection saved. It is private to you.'); }
        redirect('points', ['tab' => $_GET['tab'] ?? 'earn']);
    }
    $tab = $_GET['tab'] ?? 'earn'; $li = 0; foreach (LEVELS as $i => $l) if ($pts >= $l[1]) $li = $i; $nx = LEVELS[$li + 1] ?? null;
    $h = '<div class="split"><div class="grow col" style="gap:16px"><div class="hero"><div class="row"><div class="grow"><p class="kick">Growth Points</p><p style="font-family:Manrope,sans-serif;font-weight:800;font-size:44px;line-height:1.1">' . $pts . '</p><p class="sm soft">' . LEVELS[$li][0] . '. ' . ($nx ? ($nx[1] - $pts) . ' points to ' . $nx[0] : 'Top level reached') . '</p></div><span class="ic" style="background:rgba(255,255,255,.14);color:#BDEA52;width:60px;height:60px;font-size:24px">' . ic('fa-seedling') . '</span></div>' . bar($nx ? (int)round(($pts - LEVELS[$li][1]) / max(1, $nx[1] - LEVELS[$li][1]) * 100) : 100) . '</div>' . tabs(['earn' => 'Earn', 'rewards' => 'Rewards', 'history' => 'History', 'board' => 'Leaderboard'], $tab, url('points'));
    if ($tab === 'earn') {
        $h .= '<div class="grp">'; foreach ([['Finish a Rooting lesson', $rules['rooting_module'], 'fa-tree'], ['Finish a Blueprint lesson', $rules['blueprint_lesson'], 'fa-compass-drafting'], ['Pass a course module', $rules['course_module'], 'fa-book-open'], ['Earn a certificate', $rules['certificate'], 'fa-award'], ['Weekly reflection', 5, 'fa-pen']] as $r) $h .= '<div class="li"><span class="ic s">' . ic($r[2]) . '</span><span class="grow">' . $r[0] . '</span>' . pill('+' . (int)$r[1], 'l') . '</div>';
        $h .= '</div><form method="post" class="card col">' . csrf_field() . '<input type="hidden" name="do" value="reflect"><h3>Weekly reflection</h3><textarea class="inp" name="body" rows="3" placeholder="What changed for you this week?" required></textarea><button class="b l s" style="align-self:flex-start">' . ic('fa-bookmark') . ' Save reflection (+5)</button><p class="xs mut">' . ic('fa-lock') . ' Your journal is private.</p></form>';
    } elseif ($tab === 'rewards') {
        $seen = []; $h .= '<div class="g3">'; foreach (rows('SELECT * FROM rewards WHERE active = 1 ORDER BY cost_points') as $r) { if (isset($seen[$r['title']])) continue; $seen[$r['title']] = 1; $h .= '<form method="post" class="card col">' . csrf_field() . '<input type="hidden" name="do" value="redeem"><input type="hidden" name="reward" value="' . e($r['id']) . '"><span class="ic">' . ic('fa-gift') . '</span><p class="bold">' . e($r['title']) . '</p><p class="mut sm grow">' . e($r['description']) . '</p><button class="b ' . ($pts >= $r['cost_points'] ? 'l' : 'g') . ' s">' . (int)$r['cost_points'] . ' points</button></form>'; }
        $h .= '</div>'; if (!$seen) $h .= empty_state('fa-gift', 'No rewards yet');
    } elseif ($tab === 'history') {
        $tx = rows('SELECT * FROM growth_transactions WHERE uid = ? ORDER BY created_at DESC LIMIT 60', [$uid]); if (!$tx) $h .= empty_state('fa-seedling', 'No points yet', 'Finish a lesson to earn your first points.');
        else { $h .= '<div class="grp">'; foreach ($tx as $t) $h .= '<div class="li"><span class="grow"><span class="bold" style="display:block">' . e(ucfirst(str_replace([':', '_', '-'], ' ', preg_replace('/:\d{9,}$/', '', (string)$t['reason'])))) . '</span><span class="mut xs">' . ago($t['created_at']) . '</span></span>' . pill(($t['points'] > 0 ? '+' : '') . (int)$t['points'], $t['points'] > 0 ? 'l' : 'g') . '</div>'; $h .= '</div>'; }
    } else {
        $per = $_GET['p'] ?? 'all'; $since = $per === 'week' ? gmdate('Y-m-d', time() - 7 * 86400) : ($per === 'month' ? gmdate('Y-m-d', time() - 30 * 86400) : null);
        $board = $since ? rows('SELECT u.uid, u.name, u.display_name, u.avatar_url, SUM(t.points) pts FROM growth_transactions t JOIN users u ON u.uid = t.uid WHERE t.points > 0 AND t.created_at >= ? AND u.pathway = ? GROUP BY u.uid, u.name, u.display_name, u.avatar_url ORDER BY pts DESC LIMIT 50', [$since, $me['pathway'] ?? 'gmn'])
            : rows('SELECT u.uid, u.name, u.display_name, u.avatar_url, g.points pts FROM growth_profiles g JOIN users u ON u.uid = g.uid WHERE u.pathway = ? ORDER BY g.points DESC LIMIT 50', [$me['pathway'] ?? 'gmn']);
        $h .= '<div class="chips">'; foreach (['week' => 'This week', 'month' => 'This month', 'all' => 'All time'] as $k => $lab) $h .= '<a class="chip ' . ($per === $k ? 'on' : '') . '" href="' . url('points', ['tab' => 'board', 'p' => $k]) . '">' . $lab . '</a>'; $h .= '</div><div class="grp">';
        foreach ($board as $i => $b) $h .= '<div class="li" style="' . ($b['uid'] === $uid ? 'background:var(--mags)' : '') . '"><span style="font-family:Manrope,sans-serif;font-weight:800;width:26px;text-align:center">' . ($i + 1) . '</span>' . avatar($b) . '<span class="grow bold">' . e(display_name($b)) . ($b['uid'] === $uid ? ' (You)' : '') . '</span>' . pill((int)$b['pts'] . ' pts', 'l') . '</div>';
        $h .= '</div>'; if (!$board) $h .= empty_state('fa-trophy', 'No points in this period yet');
    }
    $h .= '</div><div class="aside"><div class="card col"><h4>Levels</h4>'; foreach (LEVELS as $i => $l) $h .= '<div class="row"><span class="ic s ' . ($i <= $li ? 'l' : 'n') . '">' . ic('fa-seedling') . '</span><span class="grow ' . ($i === $li ? 'bold' : '') . '">' . $l[0] . '</span><span class="xs mut">' . $l[1] . '+</span></div>';
    page('Growth Points', $h . '</div></div></div>', ['active' => 'points', 'back' => 'me']);
}

/* ───────── Certificates ───────── */
if ($first === 'certificates') {
    $list = rows('SELECT * FROM certificates WHERE uid = ? AND COALESCE(revoked,0) = 0 ORDER BY issued_at DESC', [$uid]); $open = (string)($_GET['open'] ?? '');
    $h = '<div class="col" style="gap:16px;max-width:860px;width:100%;margin:0 auto">';
    if (!$list) $h .= empty_state('fa-certificate', 'No certificates yet', 'Pass a course final assessment to earn your first certificate.', '<a class="b" href="' . url('learn') . '">Open the Course Hub</a>');
    foreach ($list as $c) {
        $v = url('verify', ['code' => $c['credential_code']]); $cid = 'cert-' . preg_replace('/[^A-Za-z0-9]/', '', (string)$c['id']); $course = (string)($c['course_name'] ?: $c['title']);
        $h .= '<div class="card col certbox" id="c' . e($c['id']) . '"><canvas class="certcv" id="' . $cid . '" data-cert width="1536" height="1024" role="img" aria-label="Certificate of completion for ' . e($c['student_name'] ?: display_name($me)) . ', ' . e($course) . '"'
            . ' data-name="' . e($c['student_name'] ?: display_name($me)) . '" data-course="' . e($course) . '" data-issued="' . e((string)$c['issued_at']) . '" data-hours="' . e(course_hours((string)($c['course_key'] ?? ''))) . '" data-code="' . e((string)$c['credential_code']) . '" data-cohort="' . e((string)($c['cohort'] ?: cohort_for((string)$c['issued_at']))) . '" data-verify="' . e($v) . '" data-host="' . e((string)parse_url(base(), PHP_URL_HOST) . rtrim((string)parse_url(base(), PHP_URL_PATH), '/')) . '"></canvas>'
            . '<div class="row wrap jb"><div><p class="bold">' . e($course) . '</p><p class="mut sm">Issued ' . e(gmdate('j F Y', strtotime((string)$c['issued_at']))) . '. Certificate ID ' . e((string)$c['credential_code']) . '</p></div></div>'
            . '<div class="row wrap noprint"><button class="b s grow" type="button" data-cert-pdf data-for="' . $cid . '">' . ic('fa-file-pdf') . ' Download PDF</button><button class="b g s" type="button" data-cert-png data-for="' . $cid . '">' . ic('fa-image') . ' Image</button><button class="b g s" type="button" data-cert-share data-for="' . $cid . '">' . ic('fa-share-nodes') . ' Share</button><a class="b g s" href="' . e($v) . '">' . ic('fa-shield-halved') . ' Verify</a></div></div>';
    }
    page('Certificates', $h . '</div>', ['active' => 'certificates', 'back' => 'me', 'foot' => '<script src="' . asset('qrcode.js') . '"></script><script src="' . asset('certificate.js') . '"></script>' . ($open ? '<script>var o=document.getElementById(' . json_encode('c' . $open) . ');if(o)o.scrollIntoView({block:"start"});</script>' : '')]);
}

/* ───────── Progress ───────── */
if ($first === 'progress') {
    $j = journey($uid); $certN = (int)val('SELECT COUNT(*) FROM certificates WHERE uid = ? AND COALESCE(revoked,0) = 0', [$uid]);
    $cp = rows("SELECT course_id, MAX(course_title) t, SUM(phase = 'lesson' AND completed = 1) lessons, SUM(phase = 'module-quiz' AND completed = 1) quizzes, MAX(updated_at) u FROM course_progress WHERE uid = ? GROUP BY course_id ORDER BY u DESC", [$uid]);
    $h = '<div class="col" style="gap:16px;max-width:860px;width:100%;margin:0 auto"><p class="mut">Every achievement across your full journey.</p><div class="g4">';
    foreach ([[points($uid), 'Growth Points', 'fa-seedling'], [$certN, 'Certificates', 'fa-award'], [count($cp), 'Courses started', 'fa-book-open'], [count($j['rooting']) + $j['blue_n'], 'Journey lessons', 'fa-road']] as $s) $h .= '<div class="tile" style="min-height:0"><span class="ic">' . ic($s[2]) . '</span><span style="font-family:Manrope,sans-serif;font-weight:800;font-size:1.5em">' . $s[0] . '</span><span class="mut xs">' . $s[1] . '</span></div>';
    $h .= '</div><div class="card col"><h3>Stages</h3><div class="col" style="gap:6px"><div class="row jb"><span class="bold">Rooting Phase</span>' . pill(count($j['rooting']) . ' of ' . $j['root_total'], $j['root_done'] ? 'l' : 'n') . '</div>' . bar((int)round(count($j['rooting']) / max(1, $j['root_total']) * 100)) . '</div><div class="col" style="gap:6px"><div class="row jb"><span class="bold">' . (net() === 'nnn' ? 'Blueprint Academy' : 'Blueprint') . '</span>' . pill($j['blue_n'] . ' of ' . $j['blue_total'], $j['blue_done'] ? 'l' : 'n') . '</div>' . bar((int)round($j['blue_n'] / max(1, $j['blue_total']) * 100)) . '</div></div><div class="card col"><h3>Courses</h3>';
    if (!$cp) $h .= '<p class="mut">You have not started a course yet.</p>';
    foreach ($cp as $c) { $it = catalog_item((string)$c['course_id']); $tot = $it['lessons'] ?? max(1, (int)$c['lessons']); $h .= '<a class="col" style="gap:6px" href="' . ($it ? url('course/' . $it['id']) : '#') . '"><div class="row jb"><span class="bold cut">' . e($it['title'] ?? ($c['t'] ?: $c['course_id'])) . '</span><span class="xs mut">' . (int)$c['lessons'] . ' / ' . $tot . ' lessons, ' . (int)$c['quizzes'] . ' quizzes</span></div>' . bar((int)round((int)$c['lessons'] / $tot * 100)) . '</a>'; }
    page('My progress', $h . '</div></div>', ['active' => 'progress', 'back' => 'me']);
}

/* ───────── Support ───────── */
if ($first === 'support') {
    if (is_post()) {
        if (post('do') === 'new' && trim((string)post('body')) !== '') {
            $tid = ins('support_threads', ['uid' => $uid, 'subject' => mb_substr((string)post('subject', 'Help request'), 0, 200), 'status' => 'open', 'priority' => 'normal', 'category' => str_contains(strtolower((string)post('subject')), 'certificate') ? 'certificate' : 'general', 'source' => 'app', 'language' => $me['language'] ?? 'en', 'unread_admin' => 1, 'updated_at' => now()]);
            ins('support_messages', ['thread_id' => $tid, 'uid' => $uid, 'sender_role' => 'student', 'body' => mb_substr((string)post('body'), 0, 4000), 'is_admin' => 0]);
            foreach (rows("SELECT uid FROM users WHERE role IN ('admin','super_admin') LIMIT 20") as $a) notify($a['uid'], 'New support request', (string)post('subject'), 'ops/support', 'support');
            flash('Sent to WODDI. Track it here under My requests.'); redirect('support', ['t' => $tid]);
        }
        if (post('do') === 'reply' && ($t = row('SELECT * FROM support_threads WHERE id = ? AND uid = ?', [post('t'), $uid])) && trim((string)post('body')) !== '') { ins('support_messages', ['thread_id' => $t['id'], 'uid' => $uid, 'sender_role' => 'student', 'body' => mb_substr((string)post('body'), 0, 4000), 'is_admin' => 0]); upd('support_threads', ['unread_admin' => 1, 'status' => 'open', 'updated_at' => now()], 'id = ?', [$t['id']]); redirect('support', ['t' => $t['id']]); }
        redirect('support');
    }
    $h = '<div class="col" style="gap:16px;max-width:760px;width:100%;margin:0 auto">';
    if (($tid = (string)($_GET['t'] ?? '')) && ($t = row('SELECT * FROM support_threads WHERE id = ? AND uid = ?', [$tid, $uid]))) {
        upd('support_threads', ['unread_student' => 0], 'id = ?', [$tid]);
        $h .= '<div class="card row"><h3 class="grow">' . e($t['subject']) . '</h3>' . pill(ucfirst(str_replace('_', ' ', (string)$t['status'])), $t['status'] === 'resolved' ? 'l' : 'g') . '</div><div class="col" style="gap:8px">';
        foreach (rows('SELECT * FROM support_messages WHERE thread_id = ? ORDER BY created_at', [$tid]) as $m) $h .= '<div class="bub ' . (empty($m['is_admin']) && $m['sender_role'] !== 'admin' ? 'me' : '') . '">' . e($m['body']) . '<span class="xs" style="display:block;opacity:.7">' . (empty($m['is_admin']) && $m['sender_role'] !== 'admin' ? 'You' : 'WODDI support') . ', ' . ago($m['created_at']) . '</span></div>';
        $h .= '</div><form method="post" class="row">' . csrf_field() . '<input type="hidden" name="do" value="reply"><input type="hidden" name="t" value="' . e($tid) . '"><input class="inp" name="body" required placeholder="Write a reply"><button class="ib" style="background:var(--mag);color:#fff" aria-label="Send">' . ic('fa-paper-plane') . '</button></form>';
        page('Help and support', $h . '</div>', ['active' => 'support', 'back' => 'support']);
    }
    $topic = (string)($_GET['topic'] ?? '');
    $h .= '<div class="card row"><span class="ic p">' . ic('fa-headset') . '</span><div class="grow"><p class="bold">WODDI support</p><p class="mut sm">woddi.org@gmail.com, WhatsApp +234 906 202 2323</p></div><a class="ib f" href="https://wa.me/2349062022323" target="_blank" rel="noopener" aria-label="WhatsApp support"><i class="fa-brands fa-whatsapp"></i></a></div>';
    $h .= '<a class="card row" href="' . url('guide') . '"><span class="ic l">' . ic('fa-circle-play') . '</span><span class="grow"><span class="bold" style="display:block">Watch the app guide</span><span class="mut sm">A two-minute video tour of the app</span></span>' . ic('fa-chevron-right', 'mut') . '</a>';
    if ($topic) $h .= '<form method="post" class="card col">' . csrf_field() . '<input type="hidden" name="do" value="new"><input type="hidden" name="subject" value="' . e($topic) . '">' . pill($topic) . '<h3>Tell us what happened</h3><textarea class="inp" name="body" rows="4" required placeholder="Write it in your own words. You can use any language."></textarea><div class="row"><a class="b g" href="' . url('support') . '">Cancel</a><button class="b grow">' . ic('fa-paper-plane') . ' Send to WODDI</button></div></form>';
    else { $h .= '<h4>What do you need help with?</h4><div class="grp">'; foreach ([['I cannot sign in', 'fa-right-to-bracket'], ['I forgot my password', 'fa-key'], ['A course will not open', 'fa-book'], ['Video or audio not playing', 'fa-circle-play'], ['My certificate', 'fa-award'], ['Change language', 'fa-language'], ['Wrong network on my account', 'fa-people-arrows'], ['Install the app', 'fa-mobile-screen'], ['Marketplace', 'fa-store'], ['Report a problem or scam', 'fa-triangle-exclamation'], ['Something else', 'fa-ellipsis']] as $t) $h .= '<a class="li" href="' . url('support', ['topic' => $t[0]]) . '"><i class="fa-solid ' . $t[1] . '" style="width:22px;text-align:center;color:var(--magt)"></i><span class="grow">' . $t[0] . '</span>' . ic('fa-chevron-right', 'mut') . '</a>'; $h .= '</div>'; }
    $mine = rows('SELECT * FROM support_threads WHERE uid = ? ORDER BY updated_at DESC LIMIT 30', [$uid]);
    if ($mine) { $h .= '<h4>My requests</h4><div class="grp">'; foreach ($mine as $t) $h .= '<a class="li" href="' . url('support', ['t' => $t['id']]) . '"><span class="grow"><span class="bold" style="display:block">' . e($t['subject']) . '</span><span class="mut xs">' . ago($t['updated_at'] ?: $t['created_at']) . '</span></span>' . (!empty($t['unread_student']) ? pill('New reply') : '') . pill(ucfirst(str_replace('_', ' ', (string)$t['status'])), $t['status'] === 'resolved' ? 'l' : 'g') . '</a>'; $h .= '</div>'; }
    page('Help and support', $h . '</div>', ['active' => 'support', 'back' => 'me']);
}
