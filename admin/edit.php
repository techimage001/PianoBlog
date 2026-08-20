<?php
require_once __DIR__ . '/lib.php';
lpk_admin_require_login();
$id = (string)($_GET['id'] ?? $_POST['id'] ?? '');
$a = $id !== '' ? lpk_article_find($id) : null;
if ($id !== '' && !$a) { http_response_code(404); exit('Article not found.'); }
$errors = [];

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
  lpk_csrf_check();
  $intent = (string)($_POST['intent'] ?? 'save');
  if ($intent === 'delete') {
    if ($a && ($a['status'] ?? '') === 'published') lpk_remove_published_article($a);
    if ($a) lpk_article_delete_record($a['id']);
    lpk_regenerate_blog_hub();
    lpk_regenerate_article_sitemap();
    header('Location: /admin/?msg=' . rawurlencode('Article deleted.')); exit;
  }

  $title = trim((string)($_POST['title'] ?? ''));
  $slug = lpk_slugify((string)($_POST['slug'] ?? $title));
  $seo = trim((string)($_POST['seo_title'] ?? ''));
  $desc = trim((string)($_POST['meta_description'] ?? ''));
  $excerpt = trim((string)($_POST['excerpt'] ?? ''));
  $category = (string)($_POST['category'] ?? 'Beginner Piano');
  $content = trim((string)($_POST['content'] ?? ''));

  if ($title === '') $errors[] = 'Add an article title.';
  if ($slug === '') $errors[] = 'Add a valid URL slug.';
  if (lpk_article_by_slug($slug, $a['id'] ?? '')) $errors[] = 'That URL slug is already being used by another article.';
  if (!in_array($category, lpk_article_categories(), true)) $errors[] = 'Choose a valid category.';
  if ($intent === 'publish' && lpk_strlen(strip_tags($content)) < 250) $errors[] = 'Add more article content before publishing.';
  if (lpk_strlen($seo) > 70) $errors[] = 'Keep the SEO title at 70 characters or fewer.';
  if (lpk_strlen($desc) > 170) $errors[] = 'Keep the meta description at 170 characters or fewer.';

  if (!$errors) {
    $oldSlug = $a['slug'] ?? '';
    $oldStatus = $a['status'] ?? 'draft';
    $now = gmdate('c');
    $newId = $a['id'] ?? ('article-' . substr(bin2hex(random_bytes(8)), 0, 16));
    $featured = $a['featured_image'] ?? '';
    try {
      $uploaded = lpk_upload_featured_image($_FILES['featured_image'] ?? null, $slug);
      if ($uploaded) $featured = $uploaded;
    } catch (Throwable $e) { $errors[] = $e->getMessage(); }

    if (!$errors) {
      $status = $intent === 'publish' ? 'published' : (($intent === 'unpublish') ? 'draft' : ($a['status'] ?? 'draft'));
      $publishedAt = $a['published_at'] ?? '';
      if ($status === 'published' && $publishedAt === '') $publishedAt = $now;
      $article = [
        'id'=>$newId,'title'=>$title,'slug'=>$slug,'seo_title'=>$seo,'meta_description'=>$desc,
        'excerpt'=>$excerpt,'category'=>$category,'featured_image'=>$featured,'content'=>$content,
        'status'=>$status,'created_at'=>$a['created_at'] ?? $now,'updated_at'=>$now,'published_at'=>$publishedAt
      ];
      try {
        if ($oldStatus === 'published' && ($status !== 'published' || $oldSlug !== $slug)) lpk_remove_published_article($a);
        lpk_article_save($article);
        if ($status === 'published') lpk_publish_article($article);
        else { lpk_regenerate_blog_hub(); lpk_regenerate_article_sitemap(); }
      } catch (Throwable $e) { $errors[] = $e->getMessage(); }
      if (!$errors) {
        if ($intent === 'preview') { header('Location: /admin/preview.php?id=' . rawurlencode($newId)); exit; }
        $msg = $status === 'published' ? 'Article published.' : ($intent === 'unpublish' ? 'Article moved back to draft.' : 'Draft saved.');
        header('Location: /admin/edit.php?id=' . rawurlencode($newId) . '&msg=' . rawurlencode($msg)); exit;
      }
    }
  }
  $a = array_merge($a ?? [], ['id'=>$id,'title'=>$title,'slug'=>$slug,'seo_title'=>$seo,'meta_description'=>$desc,'excerpt'=>$excerpt,'category'=>$category,'content'=>$content]);
}

if (!$a) $a = ['id'=>'','title'=>'','slug'=>'','seo_title'=>'','meta_description'=>'','excerpt'=>'','category'=>'Piano Scales','featured_image'=>'','content'=>'','status'=>'draft'];
$msg = (string)($_GET['msg'] ?? '');
lpk_admin_layout_start($a['id'] ? 'Edit article' : 'New article');
?>
<div class="admin-heading"><div><p class="eyebrow">Article editor</p><h1><?= $a['id'] ? 'Edit article' : 'New article' ?></h1><p class="admin-muted">Write in simple Markdown. The publisher converts it into safe, crawlable HTML.</p></div><a class="btn btn-ghost" href="/admin/">← Articles</a></div>
<?php if ($msg): ?><div class="admin-notice"><?= lpk_h($msg) ?></div><?php endif; ?>
<?php if ($errors): ?><div class="admin-error-box"><strong>Please fix:</strong><ul><?php foreach ($errors as $e): ?><li><?= lpk_h($e) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<form class="editor-layout" method="post" enctype="multipart/form-data" id="articleForm">
<input type="hidden" name="csrf" value="<?= lpk_h(lpk_csrf_token()) ?>"><input type="hidden" name="id" value="<?= lpk_h($a['id']) ?>">
<div class="editor-main">
  <section class="admin-panel"><label>Article title<input id="articleTitle" name="title" required maxlength="140" value="<?= lpk_h($a['title']) ?>" placeholder="Piano Scale Fingering: A Beginner Guide"></label><label>URL slug<div class="slug-row"><span>/blog/</span><input id="articleSlug" name="slug" required maxlength="100" value="<?= lpk_h($a['slug']) ?>"><span>/</span></div></label></section>
  <section class="admin-panel"><div class="editor-toolbar" aria-label="Formatting toolbar"><button type="button" data-wrap="**">Bold</button><button type="button" data-prefix="## ">H2</button><button type="button" data-prefix="### ">H3</button><button type="button" data-prefix="- ">List</button><button type="button" data-prefix="1. ">Numbered</button><button type="button" data-link>Link</button></div><label>Article content<textarea id="articleContent" name="content" rows="28" required placeholder="## Start with the answer&#10;&#10;Write the useful answer here..."><?= lpk_h($a['content']) ?></textarea></label><p class="admin-muted markdown-help"><strong>Formatting:</strong> <code>## Heading</code> · <code>**bold**</code> · <code>- bullet</code> · <code>1. step</code> · <code>[link text](/page.html)</code>. You can also paste Markdown tables.</p></section>
</div>
<aside class="editor-side">
  <section class="admin-panel sticky-panel"><h2>Publish</h2><p><span class="status status-<?= lpk_h($a['status']) ?>"><?= strtoupper(lpk_h($a['status'])) ?></span></p><button class="btn btn-ghost admin-full" name="intent" value="save" type="submit">Save draft</button><button class="btn btn-ghost admin-full" name="intent" value="preview" type="submit">Save & preview</button><button class="btn btn-primary admin-full" name="intent" value="publish" type="submit"><?= ($a['status'] ?? '') === 'published' ? 'Update published article' : 'Publish article' ?></button><?php if (($a['status'] ?? '') === 'published'): ?><button class="btn btn-ghost admin-full" name="intent" value="unpublish" type="submit" data-confirm="Move this article back to draft and remove its public page?">Unpublish</button><?php endif; ?><?php if ($a['id']): ?><button class="admin-danger admin-full" name="intent" value="delete" type="submit" data-confirm="Delete this article permanently?">Delete article</button><?php endif; ?></section>
  <section class="admin-panel"><h2>Search preview</h2><label>SEO title <input name="seo_title" id="seoTitle" maxlength="70" value="<?= lpk_h($a['seo_title']) ?>"></label><div class="char-count" data-count-for="seoTitle"></div><label>Meta description<textarea name="meta_description" id="metaDescription" rows="4" maxlength="170"><?= lpk_h($a['meta_description']) ?></textarea></label><div class="char-count" data-count-for="metaDescription"></div><div class="serp-preview"><span class="serp-site">Learn Piano Keys</span><strong id="serpTitle"><?= lpk_h($a['seo_title'] ?: $a['title']) ?></strong><span id="serpUrl">learnpianokeys.com/blog/<?= lpk_h($a['slug']) ?>/</span><p id="serpDesc"><?= lpk_h($a['meta_description']) ?></p></div></section>
  <section class="admin-panel"><h2>Article details</h2><label>Category<select name="category"><?php foreach (lpk_article_categories() as $c): ?><option <?= $a['category']===$c?'selected':'' ?>><?= lpk_h($c) ?></option><?php endforeach; ?></select></label><label>Short excerpt<textarea name="excerpt" rows="4" maxlength="260" placeholder="Shown on the Blog page and related article cards."><?= lpk_h($a['excerpt']) ?></textarea></label><label>Featured image <input type="file" name="featured_image" accept="image/jpeg,image/png,image/webp"></label><?php if (!empty($a['featured_image'])): ?><img class="admin-thumb" src="<?= lpk_h($a['featured_image']) ?>" alt="Current featured image"><small><?= lpk_h($a['featured_image']) ?></small><?php endif; ?></section>
</aside>
</form>
<?php lpk_admin_layout_end(); ?>
