<?php
header('Content-Type: application/xml; charset=utf-8');
require __DIR__.'/config/database.php';
require __DIR__.'/includes/functions.php';
echo '<?xml version="1.0" encoding="UTF-8"?>';
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">
<?php
$protocol = (!empty($_SERVER['HTTPS']) ? 'https' : 'http') . '://';
$host = $_SERVER['HTTP_HOST'];
$base = $protocol . $host . dirname($_SERVER['SCRIPT_NAME']);
$base = rtrim($base, '/');

// Home
echo "<url><loc>$base/</loc><changefreq>daily</changefreq><priority>1.0</priority></url>\n";

// Properties
try{
  $stmt = $pdo->query("SELECT id, updated_at FROM properties WHERE status='available' ORDER BY updated_at DESC");
  while($r = $stmt->fetch()){
    $url = "$base/property/{$r['id']}";
    $mod = date('Y-m-d', strtotime($r['updated_at']));
    echo "<url><loc>$url</loc><lastmod>$mod</lastmod><changefreq>weekly</changefreq><priority>0.8</priority>";
    echo "<xhtml:link rel=\"alternate\" hreflang=\"ar\" href=\"$url?lang=ar\"/>";
    echo "<xhtml:link rel=\"alternate\" hreflang=\"fr\" href=\"$url?lang=fr\"/>";
    echo "<xhtml:link rel=\"alternate\" hreflang=\"en\" href=\"$url?lang=en\"/>";
    echo "</url>\n";
  }
}catch(Exception $e){}
?>
</urlset>