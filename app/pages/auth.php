<?php
/* Sign in, register (language, network, details, password), sign out, password reset, Google sign-in. */
declare(strict_types=1);

function sign_in_as(string $uid): never {
    session_regenerate_id(true); $_SESSION['uid'] = $uid;
    try { upd('auth_accounts', ['last_sign_in' => now()], 'uid = ?', [$uid]); } catch (Throwable $e) {}
    $to = $_SESSION['after_login'] ?? ''; unset($_SESSION['after_login']);
    if ($to && str_starts_with($to, '/')) { header('Location: ' . $to); exit; }
    /* Staff land on Operations; learners land on the app home. */
    $role = (string)val('SELECT role FROM users WHERE uid = ?', [$uid]);
    redirect(in_array($role, ['admin', 'super_admin', 'moderator', 'instructor', 'tutor'], true) ? 'ops' : '');
}
function find_account(string $id): ?array {
    $id = trim($id);
    if (str_contains($id, '@')) return row('SELECT * FROM auth_accounts WHERE email = ?', [mb_strtolower($id)]);
    $digits = preg_replace('/\D/', '', $id);
    if (strlen($digits) < 7) return null;
    $tail = substr($digits, -9);
    $a = row("SELECT * FROM auth_accounts WHERE REPLACE(REPLACE(REPLACE(phone,'+',''),' ',''),'-','') LIKE ?", ['%' . $tail]);
    if ($a) return $a;
    $u = row("SELECT uid FROM users WHERE REPLACE(REPLACE(REPLACE(COALESCE(phone, phone_number),'+',''),' ',''),'-','') LIKE ? LIMIT 1", ['%' . $tail]);
    return $u ? row('SELECT * FROM auth_accounts WHERE uid = ?', [$u['uid']]) : null;
}
function auth_shell(string $inner, string $topLabel, string $topHref): string {
    $pic = fn(string $f, string $cls, string $alt) => '<picture class="au-photo ' . $cls . '"><source srcset="' . asset("landing/$f.webp") . '" type="image/webp"><img src="' . asset("landing/$f.jpg") . '" alt="' . $alt . '" loading="eager"></picture>';
    $side = '<aside class="au-side"><span class="au-kick">' . ic('fa-earth-africa') . ' Empowering women. Transforming Africa.</span><h2 class="au-big">Become the woman <span>you were designed to be.</span></h2><p class="au-lead">Learn practical skills. Discover your potential. Build your future.</p>'
        . '<div class="au-photos">' . $pic('learner-phone', 'p1', 'A young woman learning on her phone') . $pic('learner-notes', 'p2', 'A woman taking notes while she learns') . '<span class="au-badge b1">' . ic('fa-certificate') . ' Free certificates</span><span class="au-badge b2">' . ic('fa-language') . ' 8 languages</span><span class="au-badge b3">' . ic('fa-heart') . ' For women, by WODDI</span></div></aside>';
    return '<div class="au-fx" aria-hidden="true"><i></i><i></i><i></i><i></i><i></i><i></i></div><div class="au-top"><a href="' . url('') . '"><img src="' . asset('logo.jpg') . '" alt="WODDI logo"></a><div class="grow"><b style="font-family:Manrope,sans-serif">WODDI <span class="bi">Institute</span></b><div class="xs mut">Learning platform</div></div><a class="b g s" href="' . url($topHref) . '">' . e($topLabel) . '</a></div><div class="au-wrap au-stage">' . $side . '<div class="au-main">'
        . implode('', array_map(fn($f) => '<div class="flash ' . e($f[0]) . '">' . e($f[1]) . '</div>', flash())) . $inner . '</div></div>';
}

if ($first === 'logout') { $_SESSION = []; session_destroy(); redirect('login'); }
if (me() && in_array($first, ['login', 'register'], true)) redirect('');

if ($first === 'login') {
    if (is_post()) {
        $key = 'login_' . md5($_SERVER['REMOTE_ADDR'] ?? ''); $tries = $_SESSION[$key] ?? [0, 0];
        if ($tries[0] >= 8 && $tries[1] > time() - 600) { flash('Too many attempts. Please wait ten minutes and try again.', 'err'); redirect('login'); }
        $a = find_account((string)post('id'));
        if ($a && $a['password_hash'] && password_verify((string)post('password'), $a['password_hash'])) { unset($_SESSION[$key]); sign_in_as($a['uid']); }
        $_SESSION[$key] = [$tries[0] + 1, time()];
        flash('That phone number, email or password is not right. Please check and try again.', 'err'); redirect('login');
    }
    $g = google_client_id() ? '<div id="g_id_onload" data-client_id="' . e(google_client_id()) . '" data-login_uri="' . url('google') . '" data-auto_prompt="false"></div><div class="g_id_signin" data-type="standard" data-size="large" data-text="continue_with" data-width="320"></div><div class="au-or">or</div>' : '';
    $h = '<form class="au-card" method="post" style="max-width:560px">' . csrf_field() . '<span class="au-tag">Welcome back</span><h1>Sign in</h1><p class="mut">Use the phone number or email you registered with. No SMS code needed.</p>' . $g
        . '<label class="fld">Phone number or email<input class="au-inp" name="id" required autocomplete="username" placeholder="+234 801 234 5678"></label>'
        . '<label class="fld">Password<input class="au-inp" type="password" name="password" required autocomplete="current-password"></label>'
        . '<div class="row jb"><span></span><a class="bold" style="color:var(--magt)" href="' . url('forgot') . '">Forgot password?</a></div>'
        . '<button class="b au-b full">Sign in</button><p class="mut center">New to WODDI Institute? <a class="bold" style="color:var(--magt)" href="' . url('register') . '">Create an account</a></p></form>';
    page('Sign in', auth_shell($h, 'Register', 'register'), ['bare' => true, 'foot' => google_client_id() ? '<script src="https://accounts.google.com/gsi/client" async defer></script>' : '']);
}

if ($first === 'google') {
    $cid = google_client_id(); $cred = (string)($_POST['credential'] ?? '');
    if (!$cid || !$cred) redirect('login');
    $ch = curl_init('https://oauth2.googleapis.com/tokeninfo?id_token=' . urlencode($cred)); curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 12]); $info = json_decode((string)curl_exec($ch), true); curl_close($ch);
    if (!$info || ($info['aud'] ?? '') !== $cid || empty($info['email']) || ($info['email_verified'] ?? '') !== 'true') { flash('Google sign-in could not be confirmed. Please try again.', 'err'); redirect('login'); }
    $email = mb_strtolower($info['email']);
    $a = row('SELECT * FROM auth_accounts WHERE email = ? OR google_sub = ?', [$email, $info['sub']]);
    if (!$a) {
        $uid = uuid4(); $path = in_array($_SESSION['reg_net'] ?? '', ['gmn', 'nnn'], true) ? $_SESSION['reg_net'] : 'gmn';
        ins('auth_accounts', ['uid' => $uid, 'email' => $email, 'google_sub' => $info['sub']]);
        q('INSERT INTO users (uid, email, name, display_name, role, pathway, status, language, avatar_url, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?)', [$uid, $email, $info['name'] ?? $email, $info['name'] ?? '', 'student', $path, 'active', 'en', $info['picture'] ?? null, now(), now()]);
        notify($uid, 'Welcome to the WODDI Institute!', 'Start with the Rooting Phase. It takes a few minutes a day.', 'journey', 'welcome', false);
        sign_in_as($uid);
    }
    if (!$a['google_sub']) q('UPDATE auth_accounts SET google_sub = ? WHERE uid = ?', [$info['sub'], $a['uid']]);
    sign_in_as($a['uid']);
}

if ($first === 'register') {
    if (is_post()) {
        $err = '';
        $firstN = (string)post('first'); $sur = (string)post('surname'); $dob = (string)post('dob'); $email = mb_strtolower((string)post('email')); $phone = (string)post('phone');
        $path = post('pathway') === 'nnn' ? 'nnn' : 'gmn'; $pw = (string)post('password'); $lang = isset(COURSE_LANGS[post('language')]) ? post('language') : 'en';
        $age = $dob ? (int)floor((time() - strtotime($dob)) / 31557600) : 0;
        if (!admissions_open()) $err = 'Admissions are closed right now. Please join the waiting list on the Admissions page.';
        elseif ($firstN === '' || $sur === '') $err = 'Please enter your first name and surname.';
        elseif (!$dob || $age < 18) $err = 'WODDI Institute is for women aged 18 and above. Please check your date of birth.';
        elseif ($path === 'gmn' && $age < 30) $err = "You are $age. WGMN is for ages 30 and above. Please choose WNNN, the network for ages 18 to 29.";
        elseif ($path === 'nnn' && $age > 29) $err = "You are $age. WNNN is for ages 18 to 29. Please choose WGMN, the network for ages 30 and above.";
        elseif ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) $err = 'That email address does not look right. Check it, or leave it empty.';
        elseif (strlen(preg_replace('/\D/', '', $phone)) < 8) $err = 'Please enter your phone number with the country code.';
        elseif (strlen($pw) < 6) $err = 'Your password needs at least 6 characters.';
        elseif ($pw !== (string)post('password2')) $err = 'The two passwords do not match.';
        elseif (!post('agree')) $err = 'Please tick the box to agree before you continue.';
        elseif ($email !== '' && val('SELECT 1 FROM auth_accounts WHERE email = ?', [$email])) $err = 'An account with that email already exists. Please sign in.';
        elseif (find_account($phone)) $err = 'An account with that phone number already exists. Please sign in.';
        if ($err) { $_SESSION['reg_old'] = $_POST; flash($err, 'err'); redirect('register', ['step' => 3]); }
        $uid = uuid4(); $name = trim("$firstN $sur");
        $acctEmail = $email !== '' ? $email : (preg_replace('/\D/', '', $phone) . '@phone.woddiinstitute.com');
        ins('auth_accounts', ['uid' => $uid, 'email' => $acctEmail, 'phone' => $phone, 'password_hash' => password_hash($pw, PASSWORD_BCRYPT)]);
        q('INSERT INTO users (uid, email, name, display_name, role, pathway, status, language, phone, phone_number, phone_auth_enabled, age_category, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
            [$uid, $acctEmail, $name, $name, 'student', $path, 'active', $lang, $phone, $phone, $email === '' ? 1 : 0, $age >= 30 ? '30+' : '18-29', now(), now()]);
        try { ins('admissions', ['uid' => $uid, 'network' => strtoupper($path === 'nnn' ? 'WNNN' : 'WGMN'), 'cycle_name' => cohort_for(), 'cycle_year' => (int)gmdate('Y'), 'cycle_month' => (int)gmdate('n'), 'admitted_at' => now()]); } catch (Throwable $e) {}
        try { q('REPLACE INTO user_consents (uid, terms, privacy, community, conduct, comms, accepted_at) VALUES (?,1,1,1,1,?,?)', [$uid, post('updates') ? 1 : 0, now()]); } catch (Throwable $e) {}
        unset($_SESSION['reg_old']);
        notify($uid, 'Welcome to the WODDI Institute!', 'Your account is ready. Start with the Rooting Phase.', 'journey', 'welcome', false);
        if ($email !== '') send_mail($email, 'Welcome to WODDI Institute', '<p>Dear ' . e($firstN) . ',</p><p>Your WODDI Institute account is ready in the ' . e(net_name($path)) . '. Sign in at <a href="' . url('login') . '">' . url('login') . '</a> and begin with the Rooting Phase.</p><p>WODDI Institute</p>');
        sign_in_as($uid);
    }
    $o = $_SESSION['reg_old'] ?? []; if (!$o && in_array($_GET['net'] ?? '', ['gmn', 'nnn'], true)) $o = ['pathway' => $_GET['net']]; $v = fn(string $k) => e($o[$k] ?? ''); $step = (int)($_GET['step'] ?? 1); if ($step < 1 || $step > 4 || (!$o && $step > 1)) $step = 1;
    if (isset($o['pathway'])) $_SESSION['reg_net'] = $o['pathway'];
    $prog = fn(int $n) => '<div class="au-prog">' . implode('', array_map(fn($i) => '<i class="' . ($i < $n ? 'done' : ($i === $n ? 'cur' : '')) . '"></i>', [1, 2, 3, 4, 5])) . '</div>';
    $langs = ''; foreach (COURSE_LANGS as $k => $n) $langs .= '<option value="' . $k . '"' . (($o['language'] ?? 'en') === $k ? ' selected' : '') . '>' . e($n) . '</option>';
    $netCard = fn(string $k, string $code, string $name, string $age, string $desc) => '<button type="button" class="au-net" data-go="3" data-net="' . $k . '" data-netname-v="' . $code . '"><img src="' . asset($k === 'nnn' ? 'logo-wnnn.png' : 'logo-wgmn.png') . '" alt="' . e($name) . ' logo"><h3>' . e($name) . '</h3><span class="bold">' . ic('fa-user-group') . ' ' . e($age) . '</span><span class="mut">' . e($desc) . '</span><span class="bold" style="color:var(--magt)">Choose ' . $code . ' ' . ic('fa-arrow-right') . '</span></button>';
    $closed = admissions_open() ? '' : '<div class="flash err" style="width:100%;max-width:820px">Admissions are closed right now. The next intake opens on the 1st of February, June or September.</div>';
    $h = $closed . '<form method="post" data-steps="' . $step . '" style="width:100%;display:flex;flex-direction:column;align-items:center;gap:18px">' . csrf_field() . '<input type="hidden" name="pathway" value="' . $v('pathway') . '">'
      . '<div class="au-card step" data-step="1"><span class="au-tag">Become the Woman You Were Designed to Be</span>' . $prog(1) . '<h1>Welcome to WODDI Institute</h1><p class="mut">Join women across Africa and the diaspora who are learning practical skills for <strong style="color:var(--magt)">personal growth, leadership, service and lasting generational impact.</strong></p><p class="mut">Choose a language before you register. You can change it at any time.</p><label class="fld">Choose your language<select class="au-inp" name="language">' . $langs . '</select></label><button type="button" class="b au-b" style="align-self:flex-start" data-go="2">Continue ' . ic('fa-chevron-right') . '</button></div>'
      . '<div class="step col center" data-step="2" style="width:100%;gap:18px" hidden><div style="width:100%;max-width:1020px" class="col">' . $prog(2) . '<span class="au-step" style="align-self:flex-start">02 · Choose network</span></div><h1 style="text-align:center">Choose the network you are joining</h1><p class="mut" style="max-width:660px;text-align:center">WODDI has two membership networks for different age groups. Select the network that matches your age to open its registration form.</p><div class="au-nets">'
      . $netCard('gmn', 'WGMN', 'WODDI Good Mother Network', 'For Women Aged 30 and Above', 'A network for women to grow in purpose, strengthen family and societal influence, and build a lasting legacy.')
      . $netCard('nnn', 'WNNN', 'WODDI Nurture NextGen Network', 'For Women Aged 18 to 29', 'A network for young women to build a strong identity, discover purpose, develop leadership skills and become ready for greater influence.')
      . '</div><button type="button" class="b g au-b" data-go="1">Back</button></div>'
      . '<div class="au-card step" data-step="3" style="max-width:980px" hidden><h1>Join <span data-netname>' . (($o['pathway'] ?? '') === 'nnn' ? 'WNNN' : 'WGMN') . '</span></h1>' . $prog(3) . '<span class="au-step">03 · Simple details</span><div><h3>Your details</h3><p class="mut">Only the essential details now. No OTP and no long registration form.</p></div><div class="g2">'
      . '<label class="fld">First name<input class="au-inp" name="first" required value="' . $v('first') . '"></label><label class="fld">Surname<input class="au-inp" name="surname" required value="' . $v('surname') . '"></label>'
      . '<label class="fld">Date of birth<input class="au-inp" type="date" name="dob" required value="' . $v('dob') . '"><small>WGMN is for ages 30 and above. WNNN is for ages 18 to 29.</small></label><span></span>'
      . '<label class="fld">Email address (optional)<input class="au-inp" type="email" name="email" placeholder="name@example.com" value="' . $v('email') . '"><small>No email? Leave this empty and sign in with your phone number.</small></label>'
      . '<label class="fld">Phone number<input class="au-inp" type="tel" name="phone" required placeholder="+234 801 234 5678" value="' . $v('phone') . '"><small>With the country code. If you have no email, you sign in with this number.</small></label></div>'
      . '<div class="row"><button type="button" class="b g au-b" data-go="2">Back</button><button type="button" class="b au-b" data-go="4" data-check>Continue</button></div></div>'
      . '<div class="au-card step" data-step="4" hidden>' . $prog(4) . '<span class="au-step">04 · Secure your account</span><h3>Create a password</h3><div class="g2"><label class="fld">Password<input class="au-inp" type="password" name="password" required minlength="6" autocomplete="new-password" placeholder="At least 6 characters"></label><label class="fld">Type it again<input class="au-inp" type="password" name="password2" required autocomplete="new-password"></label></div>'
      . '<label class="row rt"><input type="checkbox" name="agree" value="1" style="width:24px;height:24px;flex:none"><span>I agree to the WODDI Institute terms and <a href="' . url('privacy') . '" style="text-decoration:underline">privacy policy</a>, and I confirm my details are true.</span></label><div class="row"><button type="button" class="b g au-b" data-go="3">Back</button><button class="b au-b">Create my account</button></div></div></form>';
    page('Register', auth_shell($h, 'Sign in', 'login'), ['bare' => true]);
}

if ($first === 'forgot') {
    if (is_post()) {
        $a = find_account((string)post('id'));
        if ($a) {
            $tok = bin2hex(random_bytes(32));
            q('INSERT INTO password_resets (token, uid, expires_at) VALUES (?,?,?)', [hash('sha256', $tok), $a['uid'], gmdate('Y-m-d H:i:s', time() + 3600)]);
            try { ins('password_reset_log', ['uid' => $a['uid'], 'email' => $a['email'], 'status' => 'requested']); } catch (Throwable $e) {}
            if ($a['email'] && !str_ends_with($a['email'], '@phone.woddiinstitute.com')) send_mail($a['email'], 'Reset your WODDI Institute password', '<p>Tap the link to choose a new password. It works for one hour.</p><p><a href="' . url('reset', ['t' => $tok]) . '">' . url('reset', ['t' => $tok]) . '</a></p>');
            else try { ins('support_threads', ['uid' => $a['uid'], 'subject' => 'Password reset needed (phone-only account)', 'status' => 'open', 'category' => 'account', 'source' => 'forgot-password', 'unread_admin' => 1]); } catch (Throwable $e) {}
        }
        flash('If that account exists, a reset link is on its way. Phone-only accounts are reset by WODDI support.'); redirect('login');
    }
    page('Forgot password', auth_shell('<form class="au-card" method="post" style="max-width:560px">' . csrf_field() . '<h1>Forgot password</h1><p class="mut">Enter your email or phone number. We will send a reset link to your email. If you registered with a phone number only, WODDI support will reset it for you.</p><label class="fld">Phone number or email<input class="au-inp" name="id" required></label><button class="b au-b full">Send reset link</button></form>', 'Sign in', 'login'), ['bare' => true]);
}

if ($first === 'reset') {
    $tok = (string)($_GET['t'] ?? post('t')); $r = $tok ? row('SELECT * FROM password_resets WHERE token = ? AND expires_at > ?', [hash('sha256', $tok), now()]) : null;
    if (!$r) { flash('That reset link has expired. Please ask for a new one.', 'err'); redirect('forgot'); }
    if (is_post()) {
        $pw = (string)post('password');
        if (strlen($pw) < 6 || $pw !== (string)post('password2')) { flash('Passwords must match and have at least 6 characters.', 'err'); redirect('reset', ['t' => $tok]); }
        q('UPDATE auth_accounts SET password_hash = ? WHERE uid = ?', [password_hash($pw, PASSWORD_BCRYPT), $r['uid']]); q('DELETE FROM password_resets WHERE uid = ?', [$r['uid']]);
        flash('Password changed. Please sign in.'); redirect('login');
    }
    page('New password', auth_shell('<form class="au-card" method="post" style="max-width:560px">' . csrf_field() . '<input type="hidden" name="t" value="' . e($tok) . '"><h1>Choose a new password</h1><label class="fld">New password<input class="au-inp" type="password" name="password" required minlength="6"></label><label class="fld">Type it again<input class="au-inp" type="password" name="password2" required></label><button class="b au-b full">Save password</button></form>', 'Sign in', 'login'), ['bare' => true]);
}
