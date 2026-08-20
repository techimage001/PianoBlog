<?php
require_once __DIR__ . '/lib.php';
lpk_admin_session_start();
header('X-Robots-Tag: noindex, nofollow', true);
header('Cache-Control: no-store, private', true);

if (lpk_admin_locked()) {
  http_response_code(503);
  ?><!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Admin locked</title><link rel="stylesheet" href="/assets/styles.css?v=17"><link rel="stylesheet" href="/assets/admin.css?v=1"></head><body class="admin-body"><main class="admin-login"><div class="admin-login-card"><p class="eyebrow">Learn Piano Keys</p><h1>Admin is locked</h1><p>No admin password was found in <code>lpk_private/secrets.php</code>. The public website is unaffected.</p><p>Use the same <code>admin_password</code> configuration already used by the leads admin.</p></div></main></body></html><?php
  exit;
}

if (empty($_SESSION['lpk_admin'])) {
  $err = '';
  if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $password = (string)($_POST['password'] ?? '');
    if (!lpk_admin_login_allowed()) {
      $err = 'Too many attempts. Try again in about 15 minutes.';
    } elseif ($password !== '' && hash_equals(lpk_secret('admin_password'), $password)) {
      session_regenerate_id(true);
      $_SESSION['lpk_admin'] = true;
      $_SESSION['lpk_csrf'] = bin2hex(random_bytes(24));
      lpk_admin_login_clear();
      $next = (string)($_POST['next'] ?? '/admin/');
      if (!str_starts_with($next, '/admin/')) $next = '/admin/';
      header('Location: ' . $next); exit;
    } else {
      lpk_admin_login_fail();
      $err = 'Wrong password.';
    }
  }
  $next = (string)($_GET['next'] ?? '/admin/');
  ?><!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Article admin</title><link rel="stylesheet" href="/assets/styles.css?v=17"><link rel="stylesheet" href="/assets/admin.css?v=1"></head><body class="admin-body"><main class="admin-login"><form class="admin-login-card" method="post" autocomplete="on"><p class="eyebrow">Learn Piano Keys</p><h1>Article admin</h1><p>Create, preview and publish search-friendly piano articles without editing HTML files.</p><?php if ($err): ?><p class="admin-error"><?= lpk_h($err) ?></p><?php endif; ?><input type="hidden" name="next" value="<?= lpk_h($next) ?>"><label>Password<input type="password" name="password" required autofocus autocomplete="current-password"></label><button class="btn btn-primary" type="submit">Sign in</button></form></main></body></html><?php
  exit;
}

$rows = lpk_articles_load();
usort($rows, fn($a,$b) => strcmp((string)($b['updated_at'] ?? ''), (string)($a['updated_at'] ?? '')));
$published = count(array_filter($rows, fn($a) => ($a['status'] ?? '') === 'published'));
$drafts = count($rows) - $published;
$msg = (string)($_GET['msg'] ?? '');

lpk_admin_layout_start('Articles');
?>
<div class="admin-heading">
  <div><p class="eyebrow">Publishing</p><h1>Articles</h1><p class="admin-muted"><?= count($rows) ?> total · <?= $published ?> published · <?= $drafts ?> draft<?= $drafts===1?'':'s' ?></p></div>
  <a class="btn btn-primary" href="/admin/edit.php">+ New article</a>
</div>
<?php if ($msg): ?><div class="admin-notice"><?= lpk_h($msg) ?></div><?php endif; ?>
<div class="admin-cards">
  <div class="admin-stat"><b><?= $published ?></b><span>Published</span></div>
  <div class="admin-stat"><b><?= $drafts ?></b><span>Drafts</span></div>
  <div class="admin-stat"><b><?= count(lpk_article_categories()) ?></b><span>Categories</span></div>
</div>
<div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Article</th><th>Category</th><th>Status</th><th>Updated</th><th>Actions</th></tr></thead><tbody>
<?php foreach ($rows as $a): ?>
<tr><td><strong><?= lpk_h($a['title']) ?></strong><small>/blog/<?= lpk_h($a['slug']) ?>/</small></td><td><?= lpk_h($a['category']) ?></td><td><span class="status status-<?= lpk_h($a['status']) ?>"><?= strtoupper(lpk_h($a['status'])) ?></span></td><td><?= lpk_h(substr((string)$a['updated_at'],0,10)) ?></td><td class="admin-actions"><a href="/admin/edit.php?id=<?= rawurlencode($a['id']) ?>">Edit</a><a href="/admin/preview.php?id=<?= rawurlencode($a['id']) ?>" target="_blank" rel="noopener">Preview</a><?php if (($a['status'] ?? '') === 'published'): ?><a href="/blog/<?= lpk_h($a['slug']) ?>/" target="_blank" rel="noopener">View</a><?php endif; ?></td></tr>
<?php endforeach; ?>
</tbody></table></div>
<div class="admin-help"><h2>How publishing works</h2><p>Drafts stay private. Publishing writes a real static HTML page at <code>/blog/your-slug/index.html</code>, refreshes the Blog page, and updates <code>sitemap-articles.xml</code>. Your existing piano pages stay untouched.</p></div>
<?php lpk_admin_layout_end(); ?>
