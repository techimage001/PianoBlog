<?php
require_once __DIR__ . '/lib.php';
lpk_admin_require_login();
$id = (string)($_GET['id'] ?? '');
$a = lpk_article_find($id);
if (!$a) { http_response_code(404); exit('Article not found.'); }
echo lpk_article_shell($a, true);
