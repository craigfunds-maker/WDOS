<?php
/* Public landing page at the site root, for visitors who are not signed in. Wording and people are from the live site. */
declare(strict_types=1);

$stat = ['courses' => count(catalog()), 'gmn' => count(catalog('gmn')), 'nnn' => count(catalog('nnn')), 'learners' => 0, 'certified' => 0, 'countries' => 54];
try {
    $stat['learners'] = (int)val("SELECT COUNT(*) FROM users WHERE COALESCE(role,'student') NOT IN ('admin','super_admin')");
    $floor = (int)(jdec(val("SELECT `value` FROM site_flags WHERE `key` = 'landing_stats'"))['certified_floor'] ?? 0);
    $stat['certified'] = max($floor, (int)val('SELECT COUNT(DISTINCT uid) FROM certificates WHERE COALESCE(revoked,0) = 0'));
    $stat['countries'] = (int)val('SELECT COUNT(*) FROM country_communities') ?: 54;
} catch (Throwable $e) {}

$pick = []; foreach (['digital', 'financial', 'personal', 'handson', 'sdg', 'bpa'] as $t) { $first1 = null; foreach (catalog() as $c) if ($c['track'] === $t) { $first1 = $first1 ?? $c; if ($c['level'] === 'Beginner') { $first1 = $c; break; } } if ($first1) $pick[] = $first1; }
$listings = []; try { $listings = rows("SELECT title, category, seller_name, city, country, image_url FROM mp_listings WHERE COALESCE(status,'active') = 'active' AND COALESCE(sold,0) = 0 ORDER BY (boost_until IS NOT NULL AND boost_until > NOW()) DESC, created_at DESC LIMIT 4"); } catch (Throwable $e) {}
$board = [
    ['nnamdi-nwaodu', 'Prof. Nnamdi Okechukwu Nwaodu', 'Visiting Professor of International Relations, Veritas University, Abuja', 'Director General of the Centre for African American Research Studies (CAARS) and President of the Good Health for All Society. Convener of more than 30 continental and international conferences.'],
    ['ikechukwu-nwosu', 'Prof. Ikechukwu Jude Donald Nwosu', 'Professor of Political Science and International Relations, Imo State University, Owerri', 'Dean of the Faculty of Social Sciences at Imo State University, with over 18 years of university teaching and more than 40 scholarly works.'],
    ['rabiat-bello', 'Dr. Rabiat Muhtar Bello', 'Deputy Director, Programs and Head of Abuja Liaison Office, National Board for Technical Education (NBTE)', 'Academic, researcher and development practitioner in public administration, security studies and governance, and a strong advocate for women\'s education and gender-inclusive policy.'],
    ['shamsuddeen-ahmad', 'Dr. Shamsuddeen Musa Ahmad', 'Principal Lecturer, Computer Science, Kaduna Polytechnic', 'Lecturer and researcher in computer science and information technology, with work in artificial intelligence, cybersecurity and educational data analytics.'],
    ['talatu-umar', 'Dr. Talatu Raiya Umar', 'Chief Lecturer, Department of Management Studies, Kaduna Polytechnic', 'Management scholar and expert in organisational behaviour, human resource management and leadership, who has supervised undergraduate, MBA and PhD research.'],
    ['sani-dangana', 'Dr. Sani Alhaji Dangana', 'Chief Lecturer and Head of Section, Automobile Technology, Kaduna Polytechnic', 'Distinguished teacher and researcher in vocational technical education, with long service as an external examiner and a respected voice on sound examination standards.'],
    ['ahmed-balarabe-said', 'Ahmed Balarabe Sa\'id', 'Communications strategist and lecturer; Faculty Head, Kaduna Media Academy', 'Over 17 years of combined industry and academic experience in communications, media and public relations, and author of Aren\'t We All PR People?'],
    ['kelechi-nwaodu', 'Dr. Kelechi Nnamdi Nwaodu', 'Lecturer, Division of General Studies, European University of Nigeria, Abuja', 'Educator and researcher with over fifteen years of teaching and administrative experience in English language, literary studies and curriculum development.'],
    ['murtala-sakwa', 'Dr. Murtala Aliyu Sakwa', 'Director, Inspectorate Department, National Board for Technical Education (NBTE), Kaduna', 'Nurse educator, educational administrator and researcher with over 20 years of experience in nursing education, quality assurance and educational leadership.'],
];
$play = 'https://play.google.com/store/apps/details?id=org.thewoddi.institute';
$net = fn(string $k, string $name, string $age, string $line, string $logo) => '<a class="lp-net" href="' . url('register', ['net' => $k]) . '"><img src="' . asset($logo) . '" alt=""><span class="lp-age">' . $age . '</span><h3>' . $name . '</h3><p>' . $line . '</p><span class="b ' . ($k === 'gmn' ? '' : 'l') . ' full">Join ' . ($k === 'gmn' ? 'WGMN' : 'WNNN') . ' ' . ic('fa-arrow-right') . '</span></a>';

ob_start(); ?>
<div class="lp">
<header class="lp-nav" id="lpnav"><div class="lp-wrap">
  <a class="lp-brand" href="<?= url('') ?>"><img src="<?= asset('logo.jpg') ?>" alt="WODDI logo"><span><b>WODDI Institute</b><small>Empowering women. Transforming Africa.</small></span></a>
  <nav aria-label="Sections"><a href="#networks">Networks</a><a href="#learn">Learning</a><a href="#how">How it works</a><a href="#board">Academic Board</a><a href="#market">Marketplace</a><a href="<?= url('verify') ?>">Verify a certificate</a></nav>
  <a class="b g s" href="<?= url('login') ?>">Sign in</a><a class="b s lp-hide-s" href="<?= url('register') ?>">Get started</a>
</div></header>

<section class="lp-hero"><div class="lp-wrap lp-hero-grid">
  <div class="lp-hero-text">
    <p class="lp-kick">Empowering women. Transforming Africa.</p>
    <h1>Become the woman <span>you were designed to be.</span></h1>
    <p class="lp-lead">Learn practical skills. Discover your potential. Build your future.</p>
    <p class="lp-sub">Access practical courses, supportive mentors, career and entrepreneurship guidance, and opportunities to turn your skills into income. Learn at your own pace, in your preferred language, and earn certificates that showcase what you have achieved.</p>
    <div class="lp-cta"><a class="b l" href="<?= url('register') ?>"><?= ic('fa-user-plus') ?> Create my free account</a><a class="b lp-ghost" href="<?= e($play) ?>" target="_blank" rel="noopener"><i class="fa-brands fa-google-play"></i> Get the app on Google Play</a></div>
    <p class="lp-note"><?= ic('fa-wifi') ?> Works offline <span>·</span> <?= ic('fa-language') ?> 8 languages <span>·</span> <?= ic('fa-certificate') ?> Free certificates</p>
  </div>
  <div class="lp-hero-nets"><p class="lp-tap">Tap on your preferred network</p>
    <a class="lp-pick" href="<?= url('register', ['net' => 'nnn']) ?>"><img src="<?= asset('logo-wnnn.png') ?>" alt=""><span><small>Ages 18 to 29</small><b>WODDI Nurture NextGen Network (WNNN)</b></span><?= ic('fa-chevron-right') ?></a>
    <a class="lp-pick" href="<?= url('register', ['net' => 'gmn']) ?>"><img src="<?= asset('logo-wgmn.png') ?>" alt=""><span><small>Ages 30 and above</small><b>WODDI Good Mother Network (WGMN)</b></span><?= ic('fa-chevron-right') ?></a>
  </div>
</div></section>

<section class="lp-stats" aria-label="WODDI Institute in numbers"><div class="lp-wrap">
  <div class="lp-stat"><?= ic('fa-book-open') ?><b><?= $stat['courses'] ?></b><span>Courses</span><small>WGMN <?= $stat['gmn'] ?> · WNNN <?= $stat['nnn'] ?></small></div>
  <div class="lp-stat"><?= ic('fa-language') ?><b>8</b><span>Languages</span><small>Text and video</small></div>
  <div class="lp-stat"><?= ic('fa-people-group') ?><b><?= $stat['learners'] ?></b><span>Women learning</span></div>
  <div class="lp-stat"><?= ic('fa-award') ?><b><?= $stat['certified'] ?></b><span>Women certified</span></div>
  <div class="lp-stat"><?= ic('fa-earth-africa') ?><b><?= $stat['countries'] ?></b><span>African countries</span></div>
</div></section>

<section class="lp-sec" id="networks"><div class="lp-wrap">
  <div class="lp-head"><h2>Two networks. One purpose. A place for you.</h2><p>Grow through learning at WODDI Institute and connect with a community for your stage of life. Join WODDI Nurture NextGen Network (WNNN) for young women aged 18 to 29, or WODDI Good Mother Network (WGMN) for women aged 30 and above. No woman is left out.</p></div>
  <div class="lp-nets"><?= $net('gmn', 'WODDI Good Mother Network (WGMN)', 'Women aged 30 and above', 'Nurturing women. Shaping generations.', 'logo-wgmn.png') . $net('nnn', 'WODDI Nurture NextGen Network (WNNN)', 'Young women aged 18 to 29', 'Becoming more. Becoming ready.', 'logo-wnnn.png') ?></div>
</div></section>

<section class="lp-sec lp-tint" id="learn"><div class="lp-wrap lp-two">
  <div><h2>Learn on your device, at your own pace</h2><p class="lp-p">WODDI is raising a generation of women who know who they are, build strong families and lead in their communities. Every course is built for real life: short videos, practical activities and quizzes you can finish between your other responsibilities.</p>
    <div class="lp-chips"><span><?= ic('fa-circle-play') ?> Short video lessons</span><span><?= ic('fa-qrcode') ?> Verified with a QR code</span></div></div>
  <div class="lp-feats">
    <div><span class="ic"><?= ic('fa-mobile-screen') ?></span><h3>Made for your device</h3><p>Short video lessons that work on any smartphone, with an app that keeps working offline.</p></div>
    <div><span class="ic l"><?= ic('fa-language') ?></span><h3>Your language</h3><p>Lessons in English, French, Portuguese, Arabic, Swahili, Hausa, Yoruba and Igbo.</p></div>
    <div><span class="ic g"><?= ic('fa-hands') ?></span><h3>Hands-on skills</h3><p>Practical courses you can turn into income, from production to digital work.</p></div>
    <div><span class="ic p"><?= ic('fa-certificate') ?></span><h3>Certificates that count</h3><p>Each certificate has a unique ID and a QR code anyone can check online.</p></div>
  </div>
</div></section>

<section class="lp-sec" id="courses"><div class="lp-wrap">
  <div class="lp-head"><h2>Skills that change your story</h2><p>A few of the practical courses waiting for you. Every course is free and ends with a certificate you can share.</p></div>
  <div class="lp-courses"><?php foreach ($pick as $c): ?><div class="lp-course"><span class="ic"><?= ic(TRACKS[$c['track']][1]) ?></span><small><?= e(TRACKS[$c['track']][0]) ?> · <?= e($c['level']) ?></small><h3><?= e($c['title']) ?></h3><p><?= (int)$c['modules'] ?> modules · <?= (int)$c['lessons'] ?> lessons<?= !empty($c['video']) ? ' · video' : '' ?></p></div><?php endforeach; ?></div>
  <p class="lp-center"><a class="b" href="<?= url('register') ?>">Create a free account to see every course <?= ic('fa-arrow-right') ?></a></p>
</div></section>

<section class="lp-sec lp-dark" id="how"><div class="lp-wrap">
  <div class="lp-head"><h2>Rooted in purpose. Equipped for impact.</h2><p>Four connected stages to discover who you are, shape your future, build practical skills and earn your certificate, with a community to grow alongside you.</p></div>
  <ol class="lp-steps"><?php foreach ([['fa-seedling', 'Rooting', 'Settle your identity, values and foundation. Everything else grows from here.'], ['fa-compass-drafting', 'Blueprint', 'Design your life, family and leadership plan, with a clear picture of where you are going.'], ['fa-screwdriver-wrench', 'Skills Hub', 'Choose practical and digital skills courses and learn them step by step.'], ['fa-award', 'Certificate and community', 'Earn a verifiable certificate, join the alumni community and sell your skills on WODDI Marketplace.']] as $i => $s): ?><li><span class="n"><?= ic($s[0]) ?></span><small><?= $i + 1 ?> / 4</small><h3><?= $s[1] ?></h3><p><?= $s[2] ?></p></li><?php endforeach; ?></ol>
</div></section>

<section class="lp-sec" id="founder"><div class="lp-wrap lp-founder">
  <img src="<?= asset('founder.jpg') ?>" alt="H.E. Zinariya Nneoma Nkechi Rochas Okorocha" loading="lazy">
  <div><p class="lp-kick dark">Our Founder</p><h2>H.E. Zinariya Nneoma Nkechi Rochas Okorocha</h2><p class="lp-role">Founder, Women of Divine Destiny Initiative (WODDI)</p>
    <p class="lp-p">Driven by compassion and the belief that every life deserves dignity and opportunity, H.E. Zinariya Nneoma Nkechi Rochas Okorocha founded WODDI to nurture, empower and transform the lives of women, youth, adolescents and vulnerable individuals across Africa.</p>
    <p class="lp-p">Through education, mentorship, skills acquisition, leadership development and humanitarian support, WODDI strengthens individuals, families and communities.</p>
    <p class="lp-p">The WODDI Institute advances this vision through free, accessible online learning, equipping women with the knowledge, values and skills to transform their lives, strengthen families and positively influence their communities.</p>
    <p class="lp-motto">Nurturing Lives. Empowering Generations. Transforming Africa.</p><a class="b g" href="<?= url('about') ?>">Read more about our Founder <?= ic('fa-arrow-right') ?></a></div>
</div></section>

<section class="lp-sec lp-tint" id="board"><div class="lp-wrap">
  <div class="lp-head"><h2>WODDI Institute curriculum is backed by a team of professional academics.</h2><p>Our Academic Board is made up of academics, researchers and professional leaders from universities, polytechnics, research institutes and professional bodies. They review our course content for accuracy, depth and relevance to the women who learn with us.</p></div>
  <div class="lp-board"><?php foreach ($board as $b): $cv = "assets/board/cv/{$b[0]}-cv.pdf"; ?><article><img src="<?= asset("board/{$b[0]}.jpg") ?>" alt="<?= e($b[1]) ?>" loading="lazy"><h3><?= e($b[1]) ?></h3><p class="lp-role"><?= e($b[2]) ?></p><p><?= e($b[3]) ?></p><?php if (is_file(APP_ROOT . '/' . $cv)): ?><a href="<?= url($cv) ?>" target="_blank" rel="noopener"><?= ic('fa-file-lines') ?> Read profile and CV</a><?php endif; ?></article><?php endforeach; ?></div>
</div></section>

<section class="lp-sec" id="market"><div class="lp-wrap lp-two">
  <div><h2>Hire skilled women. Buy what they make.</h2><p class="lp-p">Products and services from certified WODDI graduates. Send a request, agree directly, then rate their work with stars.</p><div class="lp-chips"><span><?= ic('fa-soap') ?> Soap and cleaning</span><span><?= ic('fa-shirt') ?> Fashion</span><span><?= ic('fa-laptop-code') ?> Digital skills</span><span><?= ic('fa-utensils') ?> Food</span></div><a class="b" href="<?= url('market') ?>"><?= ic('fa-store') ?> Browse marketplace</a></div>
  <div class="lp-list"><?php foreach ($listings as $L): ?><a href="<?= url('market') ?>"><?= !empty($L['image_url']) ? '<img src="' . e($L['image_url']) . '" alt="" loading="lazy">' : '<span class="ic">' . ic('fa-bag-shopping') . '</span>' ?><span><b><?= e($L['title']) ?></b><small><?= e(implode(', ', array_filter([$L['seller_name'], $L['city'], $L['country']]))) ?></small></span></a><?php endforeach; if (!$listings): ?><p class="lp-p">New listings from graduates appear here.</p><?php endif; ?></div>
</div></section>

<footer class="lp-foot"><div class="lp-wrap">
  <div class="lp-fgrid">
    <div><a class="lp-brand" href="<?= url('') ?>"><img src="<?= asset('logo.jpg') ?>" alt="WODDI logo"><span><b>WODDI Institute</b></span></a><p>Identity, leadership, practical skills and legacy building for women across Africa.</p><div class="lp-flogos"><img src="<?= asset('logo-wgmn.png') ?>" alt="WGMN"><img src="<?= asset('logo-wnnn.png') ?>" alt="WNNN"></div></div>
    <div><h4>Networks</h4><a href="<?= url('register', ['net' => 'gmn']) ?>">WODDI Good Mother Network (WGMN)</a><a href="<?= url('register', ['net' => 'nnn']) ?>">WODDI Nurture NextGen Network (WNNN)</a><a href="<?= url('membership') ?>">Membership</a><a href="<?= url('volunteer') ?>">Volunteer</a></div>
    <div><h4>Learning</h4><a href="#how">How it works</a><a href="#courses">Courses and languages</a><a href="<?= url('verify') ?>">Verify a certificate</a><a href="<?= url('market') ?>">Marketplace</a><a href="<?= url('alumni') ?>">Alumni community</a></div>
    <div><h4>WODDI</h4><a href="https://thewoddi.org" target="_blank" rel="noopener">thewoddi.org</a><a href="<?= e($play) ?>" target="_blank" rel="noopener">Android app</a><a href="<?= url('about') ?>">About</a><a href="<?= url('privacy') ?>">Privacy policy</a></div>
  </div>
  <p class="lp-copy">© <?= gmdate('Y') ?> WODDI Institute. Empowering women, transforming nations. <span>Made with care for Africa</span></p>
</div></footer>
<nav class="lp-tabbar" aria-label="Quick links"><a href="#lpnav"><?= ic('fa-house') ?>Home</a><a href="#networks"><?= ic('fa-people-group') ?>Networks</a><a class="go" href="<?= url('register') ?>"><?= ic('fa-user-plus') ?>Get started</a><a href="#learn"><?= ic('fa-graduation-cap') ?>Learning</a><a href="#market"><?= ic('fa-store') ?>Market</a></nav>
</div>
<?php
page('Free skills, leadership and income training for African women', (string)ob_get_clean(), ['bare' => true, 'foot' => '<script>(function(){var n=document.getElementById("lpnav");function s(){n.classList.toggle("scrolled",scrollY>8)}addEventListener("scroll",s,{passive:true});s();})();</script>']);
