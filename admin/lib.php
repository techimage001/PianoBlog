<?php
require_once dirname(__DIR__) . '/api/config.php';

/* Learn Piano Keys article publisher.
   Article records live outside public_html in lpk_private/articles.json.
   Published pages are rendered to static HTML under /blog/<slug>/index.html. */

define('LPK_ARTICLES_JSON', LPK_PRIVATE_DIR . '/articles.json');
define('LPK_BLOG_DIR', dirname(__DIR__) . '/blog');
define('LPK_UPLOADS_DIR', dirname(__DIR__) . '/uploads/articles');
define('LPK_ARTICLE_SITEMAP', dirname(__DIR__) . '/sitemap-articles.xml');

define('LPK_ADMIN_ATTEMPTS_JSON', LPK_PRIVATE_DIR . '/admin-login-attempts.json');

function lpk_admin_ip_key() {
  $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
  return hash('sha256', $ip . '|' . lpk_secret('salt', 'lpk-admin-static-salt'));
}

function lpk_admin_attempts_load() {
  $raw = @file_get_contents(LPK_ADMIN_ATTEMPTS_JSON);
  $data = json_decode((string)$raw, true);
  return is_array($data) ? $data : [];
}

function lpk_admin_attempts_save($data) {
  if (!is_dir(LPK_PRIVATE_DIR)) @mkdir(LPK_PRIVATE_DIR, 0750, true);
  @file_put_contents(LPK_ADMIN_ATTEMPTS_JSON, json_encode($data, JSON_PRETTY_PRINT), LOCK_EX);
  @chmod(LPK_ADMIN_ATTEMPTS_JSON, 0640);
}

function lpk_admin_login_allowed() {
  $data = lpk_admin_attempts_load();
  $key = lpk_admin_ip_key();
  $cut = time() - 900;
  $list = array_values(array_filter($data[$key] ?? [], fn($t) => (int)$t >= $cut));
  $data[$key] = $list;
  lpk_admin_attempts_save($data);
  return count($list) < 8;
}

function lpk_admin_login_fail() {
  $data = lpk_admin_attempts_load();
  $key = lpk_admin_ip_key();
  $cut = time() - 900;
  $list = array_values(array_filter($data[$key] ?? [], fn($t) => (int)$t >= $cut));
  $list[] = time();
  $data[$key] = $list;
  lpk_admin_attempts_save($data);
}

function lpk_admin_login_clear() {
  $data = lpk_admin_attempts_load();
  $key = lpk_admin_ip_key();
  unset($data[$key]);
  lpk_admin_attempts_save($data);
}

function lpk_h($v) { return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }


function lpk_strlen($s) {
  return function_exists('mb_strlen') ? mb_strlen((string)$s, 'UTF-8') : strlen((string)$s);
}

function lpk_admin_session_start() {
  if (session_status() === PHP_SESSION_ACTIVE) return;
  $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
  session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => $secure,
    'httponly' => true,
    'samesite' => 'Strict'
  ]);
  session_start();
}

function lpk_admin_require_login() {
  lpk_admin_session_start();
  header('X-Robots-Tag: noindex, nofollow', true);
  header('Cache-Control: no-store, private', true);
  if (empty($_SESSION['lpk_admin'])) {
    $next = $_SERVER['REQUEST_URI'] ?? '/admin/';
    header('Location: /admin/index.php?next=' . rawurlencode($next));
    exit;
  }
}

function lpk_csrf_token() {
  lpk_admin_session_start();
  if (empty($_SESSION['lpk_csrf'])) $_SESSION['lpk_csrf'] = bin2hex(random_bytes(24));
  return $_SESSION['lpk_csrf'];
}

function lpk_csrf_check() {
  lpk_admin_session_start();
  $sent = (string)($_POST['csrf'] ?? '');
  if ($sent === '' || empty($_SESSION['lpk_csrf']) || !hash_equals($_SESSION['lpk_csrf'], $sent)) {
    http_response_code(403);
    exit('Your admin session expired. Go back, refresh the page, and try again.');
  }
}

function lpk_slugify($s) {
  $s = trim(strtolower((string)$s));
  $s = str_replace(['&', '+'], [' and ', ' plus '], $s);
  if (function_exists('iconv')) {
    $x = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
    if ($x !== false) $s = $x;
  }
  $s = preg_replace('/[^a-z0-9]+/', '-', $s);
  return trim((string)$s, '-');
}

function lpk_article_categories() {
  return ['Piano Scales', 'Piano Chords', 'Piano Songs', 'Beginner Piano', 'Music Theory', 'Piano Technique'];
}

function lpk_article_seed() {
  $now = gmdate('c');
  return [[
    'id' => 'sample-piano-scales-beginners',
    'title' => 'Piano Scales for Beginners: A Simple Starting Guide',
    'slug' => 'piano-scales-for-beginners-guide',
    'seo_title' => 'Piano Scales for Beginners – Notes, Fingering & Practice',
    'meta_description' => 'Learn the essential piano scales for beginners, how scale fingering works, and how to practise smoothly without rushing.',
    'excerpt' => 'A practical introduction to piano scales, fingering and a simple daily practice routine.',
    'category' => 'Piano Scales',
    'featured_image' => '',
    'content' => "## What is a piano scale?\n\nA piano scale is a sequence of notes played in order. Scales help you learn the keyboard, build finger control and recognise the notes used inside songs and chords.\n\n## Start with C major\n\nThe C major scale uses only white keys:\n\n**C – D – E – F – G – A – B – C**\n\nFor the right hand, a common fingering is **1 – 2 – 3 – 1 – 2 – 3 – 4 – 5**.\n\n## A simple practice routine\n\n1. Play the notes slowly.\n2. Keep every note even.\n3. Say the note names as you play.\n4. Repeat the scale three times without stopping.\n5. Only increase the speed when the movement feels relaxed.\n\n## What should you learn next?\n\nAfter C major, try **G major**, then **F major**. Use the Learn Piano Keys scale explorer to see and hear each scale before you practise it.",
    'status' => 'draft',
    'created_at' => $now,
    'updated_at' => $now,
    'published_at' => ''
  ]];
}

function lpk_articles_load() {
  if (!is_dir(LPK_PRIVATE_DIR)) @mkdir(LPK_PRIVATE_DIR, 0750, true);
  if (!is_file(LPK_ARTICLES_JSON)) {
    lpk_articles_save_all(lpk_article_seed());
  }
  $raw = @file_get_contents(LPK_ARTICLES_JSON);
  $rows = json_decode((string)$raw, true);
  return is_array($rows) ? $rows : [];
}

function lpk_articles_save_all($rows) {
  if (!is_dir(LPK_PRIVATE_DIR)) @mkdir(LPK_PRIVATE_DIR, 0750, true);
  $json = json_encode(array_values($rows), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
  if ($json === false) throw new RuntimeException('Could not encode article data.');
  $tmp = LPK_ARTICLES_JSON . '.tmp';
  $fp = @fopen($tmp, 'wb');
  if (!$fp) throw new RuntimeException('The private article store is not writable.');
  if (!flock($fp, LOCK_EX)) { fclose($fp); throw new RuntimeException('Could not lock the article store.'); }
  fwrite($fp, $json . "\n");
  fflush($fp);
  flock($fp, LOCK_UN);
  fclose($fp);
  if (!@rename($tmp, LPK_ARTICLES_JSON)) {
    @unlink($tmp);
    throw new RuntimeException('Could not save the article store.');
  }
  @chmod(LPK_ARTICLES_JSON, 0640);
}

function lpk_article_find($id) {
  foreach (lpk_articles_load() as $a) if (($a['id'] ?? '') === $id) return $a;
  return null;
}

function lpk_article_by_slug($slug, $excludeId = '') {
  foreach (lpk_articles_load() as $a) {
    if (($a['slug'] ?? '') === $slug && ($a['id'] ?? '') !== $excludeId) return $a;
  }
  return null;
}

function lpk_article_save($article) {
  $rows = lpk_articles_load();
  $found = false;
  foreach ($rows as $i => $a) {
    if (($a['id'] ?? '') === $article['id']) { $rows[$i] = $article; $found = true; break; }
  }
  if (!$found) $rows[] = $article;
  lpk_articles_save_all($rows);
  return $article;
}

function lpk_article_delete_record($id) {
  $rows = lpk_articles_load();
  $rows = array_values(array_filter($rows, fn($a) => ($a['id'] ?? '') !== $id));
  lpk_articles_save_all($rows);
}

function lpk_inline_md($text) {
  $text = lpk_h($text);
  $text = preg_replace_callback('/\[([^\]]+)\]\(([^)]+)\)/', function($m) {
    $href = html_entity_decode($m[2], ENT_QUOTES, 'UTF-8');
    $ok = preg_match('#^(https?://|/|\#|mailto:)#i', $href);
    return $ok ? '<a href="' . lpk_h($href) . '">' . $m[1] . '</a>' : $m[1];
  }, $text);
  $text = preg_replace('/\*\*([^*]+)\*\*/', '<strong>$1</strong>', $text);
  $text = preg_replace('/(?<!\*)\*([^*]+)\*(?!\*)/', '<em>$1</em>', $text);
  $text = preg_replace('/`([^`]+)`/', '<code>$1</code>', $text);
  return $text;
}

function lpk_markdown($markdown) {
  $markdown = str_replace(["\r\n", "\r"], "\n", trim((string)$markdown));
  if ($markdown === '') return '<p>No article content yet.</p>';
  $lines = explode("\n", $markdown);
  $out = [];
  $para = [];
  $list = null;
  $listItems = [];
  $quote = [];
  $table = [];

  $flushPara = function() use (&$para, &$out) {
    if ($para) { $out[] = '<p>' . lpk_inline_md(implode(' ', $para)) . '</p>'; $para = []; }
  };
  $flushList = function() use (&$list, &$listItems, &$out) {
    if ($list) {
      $out[] = '<' . $list . '>' . implode('', array_map(fn($x) => '<li>' . lpk_inline_md($x) . '</li>', $listItems)) . '</' . $list . '>';
      $list = null; $listItems = [];
    }
  };
  $flushQuote = function() use (&$quote, &$out) {
    if ($quote) { $out[] = '<blockquote><p>' . lpk_inline_md(implode(' ', $quote)) . '</p></blockquote>'; $quote = []; }
  };
  $flushTable = function() use (&$table, &$out) {
    if (count($table) >= 2) {
      $rows = array_map(function($line){ return array_map('trim', explode('|', trim($line, " |\t"))); }, $table);
      $head = array_shift($rows); array_shift($rows);
      $h = '<thead><tr>' . implode('', array_map(fn($c) => '<th>' . lpk_inline_md($c) . '</th>', $head)) . '</tr></thead>';
      $b = '<tbody>';
      foreach ($rows as $r) $b .= '<tr>' . implode('', array_map(fn($c) => '<td>' . lpk_inline_md($c) . '</td>', $r)) . '</tr>';
      $b .= '</tbody>';
      $out[] = '<div class="table-scroll"><table class="article-table">' . $h . $b . '</table></div>';
    } elseif ($table) {
      foreach ($table as $line) $out[] = '<p>' . lpk_inline_md($line) . '</p>';
    }
    $table = [];
  };

  for ($i = 0; $i < count($lines); $i++) {
    $line = rtrim($lines[$i]);
    $next = $i + 1 < count($lines) ? rtrim($lines[$i+1]) : '';
    if (strpos($line, '|') !== false && preg_match('/^\s*\|?\s*:?-{3,}/', trim($next))) {
      $flushPara(); $flushList(); $flushQuote();
      $table = [$line, $next]; $i += 2;
      while ($i < count($lines) && strpos($lines[$i], '|') !== false && trim($lines[$i]) !== '') { $table[] = rtrim($lines[$i]); $i++; }
      $i--; $flushTable(); continue;
    }
    if (trim($line) === '') { $flushPara(); $flushList(); $flushQuote(); continue; }
    if (preg_match('/^###\s+(.+)$/', $line, $m)) { $flushPara(); $flushList(); $flushQuote(); $out[] = '<h3>' . lpk_inline_md($m[1]) . '</h3>'; continue; }
    if (preg_match('/^##\s+(.+)$/', $line, $m)) { $flushPara(); $flushList(); $flushQuote(); $out[] = '<h2>' . lpk_inline_md($m[1]) . '</h2>'; continue; }
    if (preg_match('/^#\s+(.+)$/', $line, $m)) { $flushPara(); $flushList(); $flushQuote(); $out[] = '<h2>' . lpk_inline_md($m[1]) . '</h2>'; continue; }
    if (preg_match('/^>\s*(.*)$/', $line, $m)) { $flushPara(); $flushList(); $quote[] = $m[1]; continue; }
    if (preg_match('/^[-*]\s+(.+)$/', $line, $m)) { $flushPara(); $flushQuote(); if ($list && $list !== 'ul') $flushList(); $list='ul'; $listItems[]=$m[1]; continue; }
    if (preg_match('/^\d+[.)]\s+(.+)$/', $line, $m)) { $flushPara(); $flushQuote(); if ($list && $list !== 'ol') $flushList(); $list='ol'; $listItems[]=$m[1]; continue; }
    if (preg_match('/^---+$/', trim($line))) { $flushPara(); $flushList(); $flushQuote(); $out[]='<hr>'; continue; }
    $para[] = trim($line);
  }
  $flushPara(); $flushList(); $flushQuote(); $flushTable();
  return implode("\n", $out);
}

function lpk_article_cta($category) {
  $map = [
    'Piano Scales' => ['Practise the scale on a keyboard', '/piano-scale-finder.html', 'Open the scale finder'],
    'Piano Chords' => ['Turn the theory into sound', '/piano-chord-finder.html', 'Open the chord finder'],
    'Piano Songs' => ['Ready to play instead of only reading?', '/songs.html', 'Choose a song'],
    'Beginner Piano' => ['Keep learning in order', '/piano-lessons.html', 'Open the beginner lessons'],
    'Music Theory' => ['Practise reading what you just learned', '/how-to-read-music.html#trainer', 'Open the note trainer'],
    'Piano Technique' => ['Put it into a real practice session', '/app.html', 'Open the practice room']
  ];
  return $map[$category] ?? ['Try it on the piano', '/app.html', 'Open the practice room'];
}

function lpk_article_shell($a, $preview = false) {
  $title = trim((string)($a['title'] ?? 'Untitled article'));
  $seo = trim((string)($a['seo_title'] ?? '')) ?: $title . ' | Learn Piano Keys';
  $desc = trim((string)($a['meta_description'] ?? '')) ?: trim((string)($a['excerpt'] ?? ''));
  $slug = lpk_slugify($a['slug'] ?? $title);
  $url = '/blog/' . $slug . '/';
  $canonical = LPK_SITE . $url;
  $image = trim((string)($a['featured_image'] ?? '')) ?: '/og-image.png';
  $imageUrl = preg_match('#^https?://#i', $image) ? $image : LPK_SITE . '/' . ltrim($image, '/');
  $category = (string)($a['category'] ?? 'Beginner Piano');
  $published = substr((string)($a['published_at'] ?: $a['created_at'] ?? gmdate('c')), 0, 10);
  $modified = substr((string)($a['updated_at'] ?? gmdate('c')), 0, 10);
  $content = lpk_markdown($a['content'] ?? '');
  [$ctaTitle, $ctaHref, $ctaLabel] = lpk_article_cta($category);

  $all = array_values(array_filter(lpk_articles_load(), fn($x) => ($x['status'] ?? '') === 'published' && ($x['id'] ?? '') !== ($a['id'] ?? '')));
  usort($all, fn($x,$y) => strcmp((string)($y['published_at'] ?? ''), (string)($x['published_at'] ?? '')));
  $same = array_values(array_filter($all, fn($x) => ($x['category'] ?? '') === $category));
  $related = array_slice(array_merge($same, array_values(array_filter($all, fn($x) => ($x['category'] ?? '') !== $category))), 0, 3);
  $relHtml = '';
  if ($related) {
    $relHtml = '<section class="article-related"><p class="eyebrow">Keep learning</p><h2>Related piano articles</h2><div class="grid g3">';
    foreach ($related as $r) {
      $relHtml .= '<a class="path-card" href="/blog/' . lpk_h($r['slug']) . '/"><span class="card-num">' . lpk_h($r['category']) . '</span><h3>' . lpk_h($r['title']) . '</h3><p>' . lpk_h($r['excerpt']) . '</p><span class="path-go">Read article &rarr;</span></a>';
    }
    $relHtml .= '</div></section>';
  }

  $schema = [
    '@context' => 'https://schema.org',
    '@graph' => [
      ['@type'=>'Organization','@id'=>LPK_SITE.'/#org','name'=>'Learn Piano Keys','url'=>LPK_SITE.'/','logo'=>['@type'=>'ImageObject','url'=>LPK_SITE.'/favicon-512.png','width'=>512,'height'=>512]],
      ['@type'=>'WebSite','@id'=>LPK_SITE.'/#website','url'=>LPK_SITE.'/','name'=>'Learn Piano Keys','publisher'=>['@id'=>LPK_SITE.'/#org'],'inLanguage'=>'en-GB'],
      ['@type'=>'WebPage','@id'=>$canonical.'#webpage','url'=>$canonical,'name'=>$seo,'description'=>$desc,'isPartOf'=>['@id'=>LPK_SITE.'/#website'],'inLanguage'=>'en-GB','breadcrumb'=>['@id'=>$canonical.'#breadcrumb']],
      ['@type'=>'BreadcrumbList','@id'=>$canonical.'#breadcrumb','itemListElement'=>[
        ['@type'=>'ListItem','position'=>1,'name'=>'Home','item'=>LPK_SITE.'/'],
        ['@type'=>'ListItem','position'=>2,'name'=>'Blog','item'=>LPK_SITE.'/blog/'],
        ['@type'=>'ListItem','position'=>3,'name'=>$title,'item'=>$canonical]
      ]],
      ['@type'=>'Article','@id'=>$canonical.'#article','headline'=>$title,'description'=>$desc,'datePublished'=>$published,'dateModified'=>$modified,'mainEntityOfPage'=>['@id'=>$canonical.'#webpage'],'author'=>['@id'=>LPK_SITE.'/#org'],'publisher'=>['@id'=>LPK_SITE.'/#org'],'image'=>[$imageUrl],'articleSection'=>$category,'inLanguage'=>'en-GB','isAccessibleForFree'=>true]
    ]
  ];
  $json = json_encode($schema, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
  $robots = $preview ? 'noindex, nofollow' : 'index, follow, max-image-preview:large, max-snippet:-1';
  $previewBar = $preview ? '<div class="preview-bar">DRAFT PREVIEW — this page is visible only inside the admin session.</div>' : '';
  $dateText = $published ? date('j F Y', strtotime($published)) : '';

  return '<!DOCTYPE html>\n<html lang="en">\n<head>\n' .
    '  <meta charset="utf-8">\n  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">\n' .
    '  <title>' . lpk_h($seo) . '</title>\n' .
    '  <meta name="description" content="' . lpk_h($desc) . '">\n' .
    '  <meta name="robots" content="' . $robots . '">\n' .
    '  <meta name="theme-color" content="#14100F">\n' .
    '  <link rel="canonical" href="' . lpk_h($canonical) . '">\n' .
    '  <meta property="og:type" content="article">\n  <meta property="og:site_name" content="Learn Piano Keys">\n' .
    '  <meta property="og:title" content="' . lpk_h($seo) . '">\n  <meta property="og:description" content="' . lpk_h($desc) . '">\n' .
    '  <meta property="og:url" content="' . lpk_h($canonical) . '">\n  <meta property="og:image" content="' . lpk_h($imageUrl) . '">\n' .
    '  <meta name="twitter:card" content="summary_large_image">\n  <meta name="twitter:title" content="' . lpk_h($seo) . '">\n  <meta name="twitter:description" content="' . lpk_h($desc) . '">\n  <meta name="twitter:image" content="' . lpk_h($imageUrl) . '">\n' .
    '  <meta name="author" content="Learn Piano Keys">\n  <link rel="icon" href="/favicon.svg" type="image/svg+xml">\n  <link rel="icon" href="/favicon-48.png" sizes="48x48" type="image/png">\n  <link rel="apple-touch-icon" href="/apple-touch-icon.png" sizes="180x180">\n' .
    '  <link rel="preconnect" href="https://fonts.googleapis.com">\n  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>\n  <link href="https://fonts.googleapis.com/css2?family=Bodoni+Moda:ital,opsz,wght@0,6..96,400..700;1,6..96,400..600&family=IBM+Plex+Mono:wght@400;600&family=Instrument+Sans:wght@400;500;600&display=swap" rel="stylesheet">\n' .
    '  <link rel="stylesheet" href="/assets/styles.css?v=17">\n  <link rel="stylesheet" href="/assets/articles.css?v=1">\n' .
    '  <script>(function(){try{var t=localStorage.getItem(\'lpk.theme\');if(t)document.documentElement.setAttribute(\'data-theme\',t);}catch(e){}})();</script>\n</head>\n<body>\n' .
    $previewBar .
    '<a class="skip" href="#main">Skip to the main content</a>\n<header class="site-head"><div class="wrap"><a class="brand" href="/"><span class="brand-mark" aria-hidden="true"></span>Piano Keys</a><button class="nav-toggle" id="navToggle" aria-expanded="false" aria-controls="nav">Menu</button><nav class="nav" id="nav" aria-label="Main"><a href="/piano-keys-for-beginners.html">Start here</a><a href="/piano-lessons.html">Lessons</a><a href="/how-to-read-music.html">Read music</a><a href="/songs.html">Songs</a><a href="/chords.html">Chords</a><a href="/blog/" aria-current="page">Blog</a><a href="/tools.html">Tools</a><a href="/practice.html">Progress</a><a href="/#compare">What is free</a><button class="theme-toggle" id="themeToggle" aria-label="Switch between light and dark">Light</button><a class="btn btn-primary btn-sm nav-cta" href="/app.html">Practice room</a></nav></div></header>\n' .
    '<nav class="crumbs" aria-label="Breadcrumb"><div class="wrap"><a href="/">Home</a><span class="sep">/</span><a href="/blog/">Blog</a><span class="sep">/</span><span aria-current="page">' . lpk_h($title) . '</span></div></nav>\n' .
    '<main id="main"><article class="article-page"><header class="article-hero"><div class="wrap article-wrap"><p class="eyebrow">' . lpk_h($category) . '</p><h1>' . lpk_h($title) . '</h1><p class="lede">' . lpk_h($a['excerpt'] ?? '') . '</p><p class="article-meta">Published ' . lpk_h($dateText) . ' · Learn Piano Keys</p>' . ($image !== '/og-image.png' ? '<img class="article-feature" src="' . lpk_h($image) . '" alt="' . lpk_h($title) . '">' : '') . '</div></header>' .
    '<section><div class="wrap article-wrap article-content">' . $content . '<aside class="article-cta"><p class="eyebrow">Try it</p><h2>' . lpk_h($ctaTitle) . '</h2><p>Use the interactive Learn Piano Keys tools to turn the idea into something you can hear and play.</p><a class="btn btn-primary" href="' . lpk_h($ctaHref) . '">' . lpk_h($ctaLabel) . '</a></aside></div></section>' .
    '<div class="wrap article-wrap">' . $relHtml . '</div></article></main>\n' .
    '<footer class="site-foot"><div class="wrap"><div class="foot-cols"><div><h2>Learn</h2><a href="/piano-keys-for-beginners.html">Piano keys for beginners</a><a href="/piano-lessons.html">Piano lessons</a><a href="/how-to-read-music.html">How to read music notes</a></div><div><h2>Play</h2><a href="/songs.html">All songs</a><a href="/fur-elise-piano-notes.html">Für Elise</a><a href="/app.html">Practice room</a></div><div><h2>Blog</h2><a href="/blog/">Piano blog</a><a href="/scales.html">Piano scales</a><a href="/chords.html">Piano chords</a></div><div><h2>Tools</h2><a href="/piano-chord-finder.html">Chord finder</a><a href="/piano-scale-finder.html">Scale finder</a><a href="/online-piano-metronome.html">Metronome</a></div><div><h2>Site</h2><a href="/contact.html">Contact</a><a href="/privacy.html">Privacy</a><a href="/terms.html">Terms</a></div></div><p class="foot-legal">Learn Piano Keys · info@learnpianokeys.com<br>Every piece on this site is in the public domain and all sound is synthesised in your browser. No recordings are used.</p></div></footer>\n' .
    '<div class="hint" id="hint"></div><div id="sr" class="sr-only" role="status" aria-live="polite"></div>\n<script src="/assets/engine.js?v=17"></script>\n<script src="/assets/gate.js?v=17"></script>\n<script src="/assets/site.js?v=17"></script>\n<script type="application/ld+json">' . $json . '</script>\n</body>\n</html>\n';
}

function lpk_ensure_public_dirs() {
  if (!is_dir(LPK_BLOG_DIR) && !@mkdir(LPK_BLOG_DIR, 0755, true)) throw new RuntimeException('Could not create /blog/.');
  if (!is_dir(LPK_UPLOADS_DIR)) @mkdir(LPK_UPLOADS_DIR, 0755, true);
}

function lpk_publish_article($a) {
  lpk_ensure_public_dirs();
  $slug = lpk_slugify($a['slug']);
  $dir = LPK_BLOG_DIR . '/' . $slug;
  if (!is_dir($dir) && !@mkdir($dir, 0755, true)) throw new RuntimeException('Could not create the article folder.');
  if (@file_put_contents($dir . '/index.html', lpk_article_shell($a, false), LOCK_EX) === false) throw new RuntimeException('Could not write the public article page.');
  lpk_regenerate_blog_hub();
  lpk_regenerate_article_sitemap();
}

function lpk_remove_published_article($a) {
  $slug = lpk_slugify($a['slug'] ?? '');
  if ($slug !== '') {
    $file = LPK_BLOG_DIR . '/' . $slug . '/index.html';
    if (is_file($file)) @unlink($file);
    $dir = dirname($file);
    if (is_dir($dir)) @rmdir($dir);
  }
  lpk_regenerate_blog_hub();
  lpk_regenerate_article_sitemap();
}

function lpk_regenerate_blog_hub() {
  lpk_ensure_public_dirs();
  $rows = array_values(array_filter(lpk_articles_load(), fn($a) => ($a['status'] ?? '') === 'published'));
  usort($rows, fn($a,$b) => strcmp((string)($b['published_at'] ?? ''), (string)($a['published_at'] ?? '')));
  $cards = '';
  foreach ($rows as $a) {
    $cards .= '<a class="path-card" href="/blog/' . lpk_h($a['slug']) . '/"><span class="card-num">' . lpk_h($a['category']) . '</span><h2>' . lpk_h($a['title']) . '</h2><p>' . lpk_h($a['excerpt']) . '</p><span class="path-go">Read article &rarr;</span></a>';
  }
  if ($cards === '') $cards = '<div class="card"><h2>New articles are being prepared</h2><p class="muted">In the meantime, use the lessons, songs, scales, chords and interactive tools already on the site.</p><a class="btn btn-primary btn-sm" href="/piano-lessons.html">Start a lesson</a></div>';
  $body = '<section class="page-head"><div class="wrap"><p class="eyebrow">Learn Piano Keys blog</p><h1>Piano blog for beginners</h1><p class="lede">Practical piano articles connected to interactive lessons and tools, so you can learn the idea and then try it on the keyboard.</p></div></section><section><div class="wrap"><div class="grid g3 article-grid">' . $cards . '</div></div></section>';
  $schema = json_encode(['@context'=>'https://schema.org','@type'=>'Blog','name'=>'Piano blog for beginners','url'=>LPK_SITE.'/blog/','description'=>'Beginner piano articles connected to interactive lessons and tools.','isPartOf'=>['@type'=>'WebSite','url'=>LPK_SITE.'/']], JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
  $html = '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover"><title>Piano Blog for Beginners | Learn Piano Keys</title><meta name="description" content="Beginner piano articles about scales, chords, songs, music theory and technique, connected to interactive tools you can play in your browser."><meta name="robots" content="index, follow, max-image-preview:large, max-snippet:-1"><meta name="theme-color" content="#14100F"><link rel="canonical" href="' . LPK_SITE . '/blog/"><meta property="og:type" content="website"><meta property="og:site_name" content="Learn Piano Keys"><meta property="og:title" content="Piano Blog for Beginners | Learn Piano Keys"><meta property="og:description" content="Beginner piano articles connected to interactive tools you can play in your browser."><meta property="og:url" content="' . LPK_SITE . '/blog/"><meta property="og:image" content="' . LPK_SITE . '/og-image.png"><link rel="icon" href="/favicon.svg" type="image/svg+xml"><link rel="icon" href="/favicon-48.png" sizes="48x48" type="image/png"><link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link href="https://fonts.googleapis.com/css2?family=Bodoni+Moda:ital,opsz,wght@0,6..96,400..700;1,6..96,400..600&family=IBM+Plex+Mono:wght@400;600&family=Instrument+Sans:wght@400;500;600&display=swap" rel="stylesheet"><link rel="stylesheet" href="/assets/styles.css?v=17"><link rel="stylesheet" href="/assets/articles.css?v=1"><script>(function(){try{var t=localStorage.getItem(\'lpk.theme\');if(t)document.documentElement.setAttribute(\'data-theme\',t);}catch(e){}})();</script></head><body><a class="skip" href="#main">Skip to the main content</a><header class="site-head"><div class="wrap"><a class="brand" href="/"><span class="brand-mark" aria-hidden="true"></span>Piano Keys</a><button class="nav-toggle" id="navToggle" aria-expanded="false" aria-controls="nav">Menu</button><nav class="nav" id="nav" aria-label="Main"><a href="/piano-keys-for-beginners.html">Start here</a><a href="/piano-lessons.html">Lessons</a><a href="/how-to-read-music.html">Read music</a><a href="/songs.html">Songs</a><a href="/chords.html">Chords</a><a href="/blog/" aria-current="page">Blog</a><a href="/tools.html">Tools</a><a href="/practice.html">Progress</a><a href="/#compare">What is free</a><button class="theme-toggle" id="themeToggle" aria-label="Switch between light and dark">Light</button><a class="btn btn-primary btn-sm nav-cta" href="/app.html">Practice room</a></nav></div></header><nav class="crumbs" aria-label="Breadcrumb"><div class="wrap"><a href="/">Home</a><span class="sep">/</span><span aria-current="page">Blog</span></div></nav><main id="main">' . $body . '</main><footer class="site-foot"><div class="wrap"><div class="foot-cols"><div><h2>Learn</h2><a href="/piano-keys-for-beginners.html">Piano keys for beginners</a><a href="/piano-lessons.html">Piano lessons</a><a href="/how-to-read-music.html">How to read music notes</a></div><div><h2>Play</h2><a href="/songs.html">All songs</a><a href="/app.html">Practice room</a></div><div><h2>Blog</h2><a href="/blog/">Piano blog</a><a href="/scales.html">Piano scales</a><a href="/chords.html">Piano chords</a></div><div><h2>Tools</h2><a href="/piano-chord-finder.html">Chord finder</a><a href="/piano-scale-finder.html">Scale finder</a></div><div><h2>Site</h2><a href="/contact.html">Contact</a><a href="/privacy.html">Privacy</a><a href="/terms.html">Terms</a></div></div><p class="foot-legal">Learn Piano Keys · info@learnpianokeys.com</p></div></footer><div class="hint" id="hint"></div><div id="sr" class="sr-only" role="status" aria-live="polite"></div><script src="/assets/engine.js?v=17"></script><script src="/assets/gate.js?v=17"></script><script src="/assets/site.js?v=17"></script><script type="application/ld+json">' . $schema . '</script></body></html>';
  if (@file_put_contents(LPK_BLOG_DIR . '/index.html', $html, LOCK_EX) === false) throw new RuntimeException('Could not update the blog page.');
}

function lpk_regenerate_article_sitemap() {
  $rows = array_values(array_filter(lpk_articles_load(), fn($a) => ($a['status'] ?? '') === 'published'));
  $urls = ['  <url><loc>' . LPK_SITE . '/blog/</loc><lastmod>' . gmdate('Y-m-d') . '</lastmod></url>'];
  foreach ($rows as $a) {
    $last = substr((string)($a['updated_at'] ?? gmdate('c')), 0, 10);
    $urls[] = '  <url><loc>' . LPK_SITE . '/blog/' . lpk_h($a['slug']) . '/</loc><lastmod>' . $last . '</lastmod></url>';
  }
  $xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n" . implode("\n", $urls) . "\n</urlset>\n";
  if (@file_put_contents(LPK_ARTICLE_SITEMAP, $xml, LOCK_EX) === false) throw new RuntimeException('Could not update sitemap-articles.xml.');
}

function lpk_upload_featured_image($file, $slug) {
  if (empty($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return null;
  if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) throw new RuntimeException('The featured image upload failed.');
  if (($file['size'] ?? 0) > 3 * 1024 * 1024) throw new RuntimeException('Featured images must be 3 MB or smaller.');
  $finfo = new finfo(FILEINFO_MIME_TYPE);
  $mime = $finfo->file($file['tmp_name']);
  $exts = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
  if (!isset($exts[$mime])) throw new RuntimeException('Use a JPG, PNG or WebP image.');
  lpk_ensure_public_dirs();
  $name = lpk_slugify($slug) . '-' . substr(bin2hex(random_bytes(5)), 0, 10) . '.' . $exts[$mime];
  $dest = LPK_UPLOADS_DIR . '/' . $name;
  if (!move_uploaded_file($file['tmp_name'], $dest)) throw new RuntimeException('Could not save the featured image.');
  @chmod($dest, 0644);
  return '/uploads/articles/' . $name;
}

function lpk_admin_layout_start($title, $active = 'articles') {
  echo '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>' . lpk_h($title) . ' · Learn Piano Keys Admin</title><link rel="stylesheet" href="/assets/styles.css?v=17"><link rel="stylesheet" href="/assets/admin.css?v=1"></head><body class="admin-body"><header class="admin-top"><div class="admin-shell"><a class="admin-brand" href="/admin/">Piano Keys <span>Admin</span></a><nav><a class="' . ($active==='articles'?'active':'') . '" href="/admin/">Articles</a><a href="/api/leads.php">Leads</a><a href="/blog/" target="_blank" rel="noopener">View blog</a><a href="/admin/logout.php">Sign out</a></nav></div></header><main class="admin-shell admin-main">';
}

function lpk_admin_layout_end() {
  echo '</main><script src="/assets/admin.js?v=1"></script></body></html>';
}
