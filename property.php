<?php
/**
 * Property Details Page | Dynamic Data Fetch | Production v2.2
 * يجلب كل البيانات من قاعدة البيانات مباشرة - أي تعديل في الداشبورد يظهر فوراً
 */
@ini_set('display_errors', 0); @error_reporting(0);
if(session_status()===PHP_SESSION_NONE) @session_start();

require_once __DIR__.'/config/database.php';
require_once __DIR__.'/includes/functions.php';

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if($id <= 0){ http_response_code(404); exit('<!DOCTYPE html><html lang="ar" dir="rtl"><head><meta charset="UTF-8"><title>404</title></head><body style="font-family:sans-serif;text-align:center;padding:4rem"><h1>عقار غير موجود</h1><a href="index.php">العودة للرئيسية</a></body></html>'); }

try{
    $stmt = $pdo->prepare("SELECT p.*, 
                                  g.name_ar AS gov, g.name_fr AS gov_fr, 
                                  d.name_ar AS del, d.name_fr AS del_fr, 
                                  t.name_ar AS type, t.name_fr AS type_fr,
                                  u.id AS agent_id, u.full_name AS agent_name, u.phone AS agent_phone, u.email AS agent_email,
                                  (SELECT COUNT(*) FROM property_images WHERE property_id = p.id) AS image_count
                          FROM properties p 
                          LEFT JOIN governorates g ON p.gov_id = g.id 
                          LEFT JOIN delegations d ON p.del_id = d.id 
                          LEFT JOIN property_types t ON p.type_id = t.id 
                          LEFT JOIN users u ON p.agent_id = u.id 
                          WHERE p.id = ? AND p.status = 'available' AND p.deleted_at IS NULL LIMIT 1");
    $stmt->execute([$id]);
    $prop = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if(!$prop){ 
        http_response_code(404); 
        exit('<!DOCTYPE html><html lang="ar" dir="rtl"><head><meta charset="UTF-8"><title>404</title></head><body style="font-family:sans-serif;text-align:center;padding:4rem"><h1>العقار غير متاح أو تم بيعه</h1><a href="index.php">العودة للرئيسية</a></body></html>'); 
    }

    $stmtImg = $pdo->prepare("SELECT image_path, sort_order FROM property_images WHERE property_id = ? ORDER BY sort_order ASC, id ASC");
    $stmtImg->execute([$id]);
    $dbImages = $stmtImg->fetchAll(PDO::FETCH_ASSOC);
    
    $images = [];
    if(!empty($prop['main_image'])) $images[] = $prop['main_image'];
    foreach($dbImages as $img) {
        if(!in_array($img['image_path'], $images)) $images[] = $img['image_path'];
    }
    if(empty($images)) $images = ['https://placehold.co/800x600/1e293b/94a3b8?text=No+Image'];

    $similarStmt = $pdo->prepare("SELECT p.id, p.title_ar, p.price_tnd, p.main_image, p.area_sqm, p.bedrooms, p.bathrooms, p.priority,
                                         g.name_ar AS gov, d.name_ar AS del, t.name_ar AS type, u.phone AS agent_phone
                                  FROM properties p
                                  LEFT JOIN governorates g ON p.gov_id = g.id                                  LEFT JOIN delegations d ON p.del_id = d.id
                                  LEFT JOIN property_types t ON p.type_id = t.id
                                  LEFT JOIN users u ON p.agent_id = u.id
                                  WHERE p.type_id = ? AND p.gov_id = ? AND p.id != ? AND p.status = 'available' AND p.deleted_at IS NULL
                                  ORDER BY 
                                      CASE p.priority WHEN 'urgent' THEN 1 WHEN 'high' THEN 2 WHEN 'medium' THEN 3 WHEN 'low' THEN 4 END ASC,
                                      p.created_at DESC
                                  LIMIT 4");
    $similarStmt->execute([$prop['type_id'] ?? 0, $prop['gov_id'] ?? 0, $id]);
    $similar = $similarStmt->fetchAll(PDO::FETCH_ASSOC);
    
    $features = [];
    if(!empty($prop['features'])){
        $decoded = json_decode($prop['features'], true);
        $features = is_array($decoded) ? $decoded : array_filter(array_map('trim', explode(',', $prop['features'])));
    }
    
}catch(Exception $e){ 
    error_log("Property Page Error: " . $e->getMessage());
    http_response_code(500); 
    exit('خطأ في جلب البيانات'); 
}

// 🌐 إعدادات الروابط والـ SEO ديناميكياً
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host     = $_SERVER['HTTP_HOST'];
$scriptDir = dirname($_SERVER['SCRIPT_NAME']);
$basePath  = ($scriptDir === '/' || $scriptDir === '\\') ? '' : rtrim($scriptDir, '/\\');
$baseUrl   = $protocol . '://' . $host . $basePath;
$assetUrl  = $baseUrl . '/assets';
$cacheV    = time();

$csrf = generate_csrf_token();
$propUrl = $baseUrl . '/property.php?id=' . $id;
$mainImg = resolveImage($images[0]);
$updated = !empty($prop['updated_at']) ? date('Y-m-d H:i', strtotime($prop['updated_at'])) : date('Y-m-d');

// 📍 تجهيز الموقع
$gov = $prop['gov'] ?? '';
$del = $prop['del'] ?? '';
$location = trim($del . ($del && $gov ? '، ' : '') . $gov);
if(empty($location)) $location = 'الموقع غير محدد';

// 📝 الوصف
$descText = $prop['desc_ar'] ?? $prop['description'] ?? $prop['description_ar'] ?? '';

// 💰 تنسيق السعر
$price = intval($prop['price_tnd']);
$formattedPrice = number_format($price, 0, '.', ',') . ' د.ت';
// 🏷️ الأولوية
$priority = $prop['priority'] ?? 'medium';
$priorityBadge = [
    'urgent' => ['text'=>'🔴 عاجل', 'class'=>'badge-urgent'],
    'high' => ['text'=>'🟠 مميز', 'class'=>'badge-high'],
    'medium' => ['text'=>'🟡 قياسي', 'class'=>'badge-medium'],
    'low' => ['text'=>'🟢 عادي', 'class'=>'badge-low']
][$priority] ?? ['text'=>'', 'class'=>''];

// ⚙️ جلب إعدادات الموقع
$siteSettings = [];
try {
    $stmtSet = $pdo->query("SELECT setting_key, setting_value FROM site_settings WHERE group_name IN ('general', 'contact', 'social', 'basic')");
    while($row = $stmtSet->fetch(PDO::FETCH_ASSOC)) {
        $siteSettings[$row['setting_key']] = $row['setting_value'];
    }
} catch(Exception $e) {}

$siteName = $siteSettings['site_name'] ?? 'وكالة عقارية';
$sitePhone = $siteSettings['phone_primary'] ?? '+216 71 000 000';

// 📱 معالجة موحدة لرقم الواتساب (نفس منطق index.php)
$siteWhatsApp = $siteSettings['phone_whatsapp'] ?? '21671000000';
$siteWhatsApp = preg_replace('/[^\d]/', '', $siteWhatsApp);
if (strpos($siteWhatsApp, '216') !== 0) {
    $siteWhatsApp = '216' . ltrim($siteWhatsApp, '0');
}
if (empty($siteWhatsApp) || strlen($siteWhatsApp) < 10) {
    $siteWhatsApp = '21671000000';
}

$fbUrl = $siteSettings['facebook_url'] ?? '#';
$instaUrl = $siteSettings['instagram_url'] ?? '#';

// 📊 Open Graph Tags ديناميكية
$ogTitle = htmlspecialchars($prop['title_ar'] . ' | ' . $siteName);
$ogDesc = htmlspecialchars(mb_substr(strip_tags($descText), 0, 150));
$ogImage = $mainImg;
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=no,viewport-fit=cover">
<title><?php echo htmlspecialchars($prop['title_ar']); ?> | <?php echo htmlspecialchars($siteName); ?></title>
<meta name="description" content="<?php echo $ogDesc; ?>">
<meta property="og:title" content="<?php echo $ogTitle; ?>">
<meta property="og:description" content="<?php echo $ogDesc; ?>">
<meta property="og:image" content="<?php echo $ogImage; ?>">
<meta property="og:url" content="<?php echo $propUrl; ?>"><meta property="og:type" content="website">
<link rel="canonical" href="<?php echo htmlspecialchars($propUrl); ?>">
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" media="print" onload="this.media='all'">
<noscript><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"></noscript>
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;500;600;700;800&family=Playfair+Display:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?php echo $assetUrl; ?>/css/frontend.css?v=<?php echo $cacheV; ?>">
<link rel="stylesheet" href="<?php echo $assetUrl; ?>/css/property.css?v=<?php echo $cacheV; ?>">
<script>window.APP = { base: '<?php echo $baseUrl; ?>', api: '<?php echo $baseUrl; ?>/public/api', whatsapp: '<?php echo $siteWhatsApp; ?>' };</script>
<script src="<?php echo $assetUrl; ?>/js/frontend.js?v=<?php echo $cacheV; ?>" defer></script>
<script src="<?php echo $assetUrl; ?>/js/property.js?v=<?php echo $cacheV; ?>" defer></script>
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "RealEstateListing",
  "name": "<?php echo addslashes($prop['title_ar']); ?>",
  "description": "<?php echo addslashes(strip_tags($descText)); ?>",
  "url": "<?php echo $propUrl; ?>",
  "image": "<?php echo $mainImg; ?>",
  "datePosted": "<?php echo $updated; ?>",
  "price": "<?php echo preg_replace('/[^0-9\.]/','',str_replace(',','.',(string)($prop['price_tnd']??'0'))); ?>",
  "priceCurrency": "TND",
  "address": {"@type":"PostalAddress","addressLocality":"<?php echo addslashes($gov); ?>","addressRegion":"<?php echo addslashes($del); ?>"},
  "numberOfRooms": <?php echo intval($prop['bedrooms'] ?? 0); ?>,
  "bedrooms": <?php echo intval($prop['bedrooms'] ?? 0); ?>,
  "bathrooms": <?php echo intval($prop['bathrooms'] ?? 0); ?>,
  "area": {"@type":"QuantitativeValue","value":<?php echo intval($prop['area_sqm'] ?? 0); ?>,"unitText":"m²"},
  "availability": "<?php echo $prop['status']==='available'?'https://schema.org/InStock':'https://schema.org/OutOfStock'; ?>"
}
</script>
</head>
<body>
<div class="scroll-progress" id="scrollProgress"></div>
<nav id="navbar" class="navbar"><div class="nav-container">
  <a href="<?php echo $baseUrl; ?>/" class="logo"><i class="fa-solid fa-gem"></i> <?php echo htmlspecialchars($siteName); ?></a>
  <div class="nav-links">
    <a href="<?php echo $baseUrl; ?>/" class="nav-link">الرئيسية</a>
    <a href="<?php echo $baseUrl; ?>/#properties" class="nav-link">العقارات</a>
    <a href="<?php echo $baseUrl; ?>/#services" class="nav-link">خدماتنا</a>
    <a href="<?php echo $baseUrl; ?>/#contact" class="nav-link">اتصل بنا</a>
  </div>
  <button id="mobileToggle" class="mobile-toggle" aria-label="القائمة"><i class="fa-solid fa-bars"></i></button>
</div></nav>
<div id="drawerOverlay" class="drawer-overlay"></div>
<div id="mobileDrawer" class="mobile-drawer" aria-hidden="true">
  <a href="<?php echo $baseUrl; ?>/" class="nav-link">الرئيسية</a>
  <a href="<?php echo $baseUrl; ?>/#properties" class="nav-link">العقارات</a>
  <a href="<?php echo $baseUrl; ?>/#services" class="nav-link">خدماتنا</a>
  <a href="<?php echo $baseUrl; ?>/#contact" class="nav-link">اتصل بنا</a>
</div>
<main id="main-content" class="prop-page">
  <div class="container">
    <nav class="breadcrumbs" aria-label="مسار التنقل">
      <a href="<?php echo $baseUrl; ?>/">الرئيسية</a> <span aria-hidden="true">/</span>
      <a href="<?php echo $baseUrl; ?>/#properties">العقارات</a> <span aria-hidden="true">/</span>
      <span aria-current="page"><?php echo htmlspecialchars($prop['title_ar']); ?></span>
    </nav>

    <div class="prop-layout">
      <section class="prop-gallery">
        <div class="gallery-main">
          <img src="<?php echo $mainImg; ?>" alt="<?php echo htmlspecialchars($prop['title_ar']); ?>" class="main-img" id="mainGalleryImg" fetchpriority="high">
          <button class="gallery-expand" id="openLightbox" aria-label="عرض ملء الشاشة"><i class="fa-solid fa-expand"></i></button>
        </div>
        <div class="gallery-thumbs">
          <?php foreach(array_slice($images, 0, 6) as $i=>$img): ?>
          <button class="thumb-btn <?php echo $i===0?'active':''; ?>" data-index="<?php echo $i; ?>" aria-label="صورة <?php echo $i+1; ?>">
            <img src="<?php echo resolveImage($img); ?>" alt="" loading="lazy">
          </button>
          <?php endforeach; ?>
          <?php if(count($images) > 6): ?>
          <button class="thumb-btn more-btn" aria-label="عرض جميع الصور">
            <span>+<?php echo count($images) - 6; ?></span>
          </button>
          <?php endif; ?>
        </div>
      </section>

      <div class="prop-content-grid">
        <div class="prop-main">
          <section class="prop-header">
            <div class="prop-badges">
              <span class="badge badge-type"><?php echo htmlspecialchars($prop['type'] ?? 'عقار'); ?></span>
              <?php if($priorityBadge['text']): ?><span class="badge <?php echo $priorityBadge['class']; ?>"><?php echo $priorityBadge['text']; ?></span><?php endif; ?>
              <span class="badge badge-status">متاح</span>
            </div>
            <h1 class="prop-title"><?php echo htmlspecialchars($prop['title_ar']); ?></h1>
            <p class="prop-location"><i class="fa-solid fa-location-dot"></i> <?php echo htmlspecialchars($location); ?></p>
            <div class="prop-price"><?php echo $formattedPrice; ?></div>
          </section>

          <section class="prop-stats">
            <?php if(!empty($prop['bedrooms'])): ?><div class="stat-box"><i class="fa-solid fa-bed"></i><span><?php echo intval($prop['bedrooms']); ?> غرف</span></div><?php endif; ?>
            <?php if(!empty($prop['bathrooms'])): ?><div class="stat-box"><i class="fa-solid fa-bath"></i><span><?php echo intval($prop['bathrooms']); ?> حمامات</span></div><?php endif; ?>
            <?php if(!empty($prop['area_sqm'])): ?><div class="stat-box"><i class="fa-solid fa-ruler-combined"></i><span><?php echo formatArea($prop['area_sqm']); ?></span></div><?php endif; ?>
            <?php if(!empty($prop['year_built'])): ?><div class="stat-box"><i class="fa-solid fa-calendar"></i><span>سنة البناء: <?php echo intval($prop['year_built']); ?></span></div><?php endif; ?>
          </section>

          <?php if(!empty($descText)): ?>          <section class="prop-desc-wrap">
            <h2 class="section-title">الوصف</h2>
            <div class="prop-desc" id="propDesc"><?php echo nl2br(htmlspecialchars($descText)); ?></div>
            <?php if(mb_strlen($descText) > 300): ?>
            <button class="desc-toggle" id="descToggle" aria-expanded="false"><span>قراءة المزيد</span> <i class="fa-solid fa-chevron-down"></i></button>
            <?php endif; ?>
          </section>
          <?php endif; ?>

          <?php if(!empty($features)): ?>
          <section class="prop-features">
            <h2 class="section-title">المميزات والمرافق</h2>
            <div class="features-grid">
              <?php 
              $iconMap = [
                'موقف' => 'fa-car', 'سيارات' => 'fa-car', 'كراج' => 'fa-car',
                'حديقة' => 'fa-tree', 'خضراء' => 'fa-tree', 'مساحة خارجية' => 'fa-tree',
                'تكييف' => 'fa-snowflake', 'مركزي' => 'fa-snowflake', 'تدفئة' => 'fa-temperature-high',
                'أمن' => 'fa-shield-halved', 'حراسة' => 'fa-shield-halved', 'كاميرات' => 'fa-video',
                'مطبخ' => 'fa-utensils', 'مجهز' => 'fa-utensils',
                'شرفة' => 'fa-door-open', 'بلكونة' => 'fa-door-open', 'تراس' => 'fa-door-open',
                'مسبح' => 'fa-water', 'سباحة' => 'fa-water', 'جاكوزي' => 'fa-hot-tub-person',
                'مصعد' => 'fa-elevator', 'أسانسير' => 'fa-elevator',
                'كهرباء' => 'fa-bolt', 'مولد' => 'fa-bolt', 'احتياطي' => 'fa-bolt',
                'إنترنت' => 'fa-wifi', 'فايبر' => 'fa-wifi', 'واي فاي' => 'fa-wifi',
                'غسيل' => 'fa-shirt', 'غسالة' => 'fa-shirt',
                'خادمة' => 'fa-user-nurse', 'مخزن' => 'fa-boxes-stacked', 'قبو' => 'fa-dungeon',
                'قريب' => 'fa-location-dot', 'مواصلات' => 'fa-bus', 'مترو' => 'fa-train-subway'
              ];
              $defaultIcon = 'fa-circle-check';
              foreach($features as $feat): 
                $icon = $defaultIcon;
                foreach($iconMap as $key => $val){
                  if(mb_strpos($feat, $key) !== false){ $icon = $val; break; }
                }
              ?>
              <div class="feat-item">
                <i class="fa-solid <?php echo $icon; ?>"></i>
                <span><?php echo htmlspecialchars($feat); ?></span>
              </div>
              <?php endforeach; ?>
            </div>
          </section>
          <?php endif; ?>

          <?php if(!empty($prop['latitude']) && !empty($prop['longitude'])): ?>
          <section class="prop-map">
            <h2 class="section-title">الموقع على الخريطة</h2>
            <div id="propMap" class="map-container" data-lat="<?php echo htmlspecialchars($prop['latitude']); ?>" data-lng="<?php echo htmlspecialchars($prop['longitude']); ?>"></div>
            <p class="map-address"><i class="fa-solid fa-map-pin"></i> <?php echo htmlspecialchars($location); ?></p>          </section>
          <?php endif; ?>
        </div>

        <aside class="prop-sidebar" id="propSidebar">
          <div class="sidebar-card">
            <div class="sidebar-price"><?php echo $formattedPrice; ?></div>
            <p style="color:var(--text-secondary);font-size:0.8rem;margin-bottom:0.9rem"><i class="fa-solid fa-clock" style="color:var(--gold);margin-inline-end:0.3rem"></i> آخر تحديث: <?php echo $updated; ?></p>
            <div class="sidebar-actions">
              <button class="btn-primary open-booking-modal" data-id="<?php echo $id; ?>" data-title="<?php echo htmlspecialchars($prop['title_ar']); ?>" data-img="<?php echo $mainImg; ?>" data-price="<?php echo $formattedPrice; ?>">
                <i class="fa-regular fa-calendar-check"></i> حجز موعد معاينة
              </button>
              <!-- ✅ رابط واتساب موحد للعقار -->
              <?php 
              // نستخدم رقم الواتساب من الإعدادات كقيمة افتراضية موحدة
              $waNumber = !empty($prop['agent_phone']) ? $prop['agent_phone'] : $siteWhatsApp;
              // تنظيف إضافي للرقم لضمان التوحيد
              $waNumber = preg_replace('/[^\d]/', '', $waNumber);
              if (strpos($waNumber, '216') !== 0) {
                  $waNumber = '216' . ltrim($waNumber, '0');
              }
              ?>
              <a href="<?php echo getWhatsAppLink($waNumber, $prop['title_ar'], $id); ?>" target="_blank" class="btn-whatsapp">
                <i class="fa-brands fa-whatsapp"></i> تواصل واتساب
              </a>
              <a href="tel:<?php echo htmlspecialchars(!empty($prop['agent_phone']) ? $prop['agent_phone'] : preg_replace('/[^0-9+]/', '', $sitePhone)); ?>" class="btn-call"><i class="fa-solid fa-phone"></i> اتصال مباشر</a>
            </div>
            <?php if(!empty($prop['agent_name'])): ?>
            <div class="agent-card">
              <div class="agent-avatar"><i class="fa-solid fa-user-tie"></i></div>
              <div class="agent-info">
                <h4><?php echo htmlspecialchars($prop['agent_name']); ?></h4>
                <p><?php echo htmlspecialchars($prop['agent_title'] ?? 'وكيل عقاري'); ?></p>
                <?php if(!empty($prop['agent_rating'])): ?><div class="agent-rating"><i class="fa-solid fa-star"></i> <?php echo htmlspecialchars($prop['agent_rating']); ?></div><?php endif; ?>
                <?php if(!empty($prop['agent_phone'])): ?><a href="tel:<?php echo htmlspecialchars($prop['agent_phone']); ?>" class="agent-contact"><i class="fa-solid fa-phone"></i> <?php echo formatPhoneDisplay($prop['agent_phone']); ?></a><?php endif; ?>
              </div>
            </div>
            <?php endif; ?>
            <div class="sidebar-trust">
              <div class="trust-item"><i class="fa-solid fa-eye"></i> <?php echo intval($prop['views'] ?? 0); ?> مشاهدة</div>
              <div class="trust-item"><i class="fa-solid fa-calendar"></i> أضيف في: <?php echo date('Y-m-d', strtotime($prop['created_at'])); ?></div>
            </div>
          </div>
        </aside>
      </div>
    </div>

    <?php if(count($similar) > 0): ?>
    <section class="similar-section">
      <h2 class="section-title">عقارات مشابهة قد تعجبك</h2>      <div class="similar-grid">
        <?php foreach($similar as $s): 
          $sImg = resolveImage($s['main_image'] ?? '');
          $sPrice = intval($s['price_tnd']);
          $sGov = $s['gov'] ?? '';
          $sDel = $s['del'] ?? '';
          $sLoc = trim($sDel . ($sDel && $sGov ? '، ' : '') . $sGov);
          if(empty($sLoc)) $sLoc = 'الموقع غير محدد';
          $sPriority = $s['priority'] ?? 'medium';
          $sPriorityBadge = ['urgent'=>'🔴 عاجل','high'=>'🟠 مميز','medium'=>'🟡 قياسي','low'=>'🟢 عادي'][$sPriority] ?? '';
          // ✅ معالجة موحدة لرقم واتساب العقارات المشابهة
          $sWaNumber = !empty($s['agent_phone']) ? $s['agent_phone'] : $siteWhatsApp;
          $sWaNumber = preg_replace('/[^\d]/', '', $sWaNumber);
          if (strpos($sWaNumber, '216') !== 0) {
              $sWaNumber = '216' . ltrim($sWaNumber, '0');
          }
        ?>
        <article class="similar-card" data-id="<?php echo $s['id']; ?>" data-title="<?php echo htmlspecialchars($s['title_ar']); ?>" data-price="<?php echo $sPrice; ?>" data-img="<?php echo $sImg; ?>">
          <div class="similar-img-wrap">
            <img src="<?php echo $sImg; ?>" alt="<?php echo htmlspecialchars($s['title_ar']); ?>" loading="lazy">
            <div class="similar-price"><?php echo formatPrice($sPrice); ?></div>
            <?php if($sPriorityBadge): ?><span class="badge badge-priority" style="position:absolute;top:8px;right:8px;font-size:0.7rem"><?php echo $sPriorityBadge; ?></span><?php endif; ?>
          </div>
          <div class="similar-body">
            <h3 class="similar-title"><?php echo htmlspecialchars($s['title_ar']); ?></h3>
            <p class="card-location" style="margin-bottom:0.5rem"><i class="fa-solid fa-location-dot"></i> <?php echo htmlspecialchars($sLoc); ?></p>
            <div class="similar-meta">
              <?php if(!empty($s['bedrooms'])): ?><span><i class="fa-solid fa-bed"></i> <?php echo intval($s['bedrooms']); ?></span><?php endif; ?>
              <?php if(!empty($s['area_sqm'])): ?><span><i class="fa-solid fa-ruler-combined"></i> <?php echo number_format($s['area_sqm'],0); ?> م²</span><?php endif; ?>
            </div>
            <div style="display:flex;gap:0.5rem;margin-top:0.7rem">
              <a href="<?php echo $baseUrl; ?>/property.php?id=<?php echo $s['id']; ?>" class="btn-primary" style="flex:1;font-size:0.82rem;padding:0.55rem"><i class="fa-solid fa-eye"></i> التفاصيل</a>
              <button class="btn-primary open-booking-modal" style="flex:1;background:var(--gold);color:var(--navy);font-size:0.82rem;padding:0.55rem" 
                      data-id="<?php echo $s['id']; ?>" data-title="<?php echo htmlspecialchars($s['title_ar']); ?>" 
                      data-price="<?php echo formatPrice($sPrice); ?>" data-img="<?php echo $sImg; ?>"
                      data-whatsapp="<?php echo getWhatsAppLink($sWaNumber, $s['title_ar'], $s['id']); ?>">
                <i class="fa-regular fa-calendar-check"></i> حجز
              </button>
            </div>
          </div>
        </article>
        <?php endforeach; ?>
      </div>
    </section>
    <?php endif; ?>
  </div>
</main>

<footer class="footer" id="mainFooter">
  <div class="footer-container">    <div class="footer-logo"><i class="fa-solid fa-gem"></i> <?php echo htmlspecialchars($siteName); ?></div>
    <p class="footer-desc"><?php echo htmlspecialchars($siteSettings['footer_desc'] ?? 'شريكك الموثوق في السوق العقاري.'); ?></p>
    <div class="footer-social">
      <?php if(!empty($fbUrl)): ?><a href="<?php echo htmlspecialchars($fbUrl); ?>" class="social-icon" aria-label="فيسبوك"><i class="fa-brands fa-facebook-f"></i></a><?php endif; ?>
      <?php if(!empty($instaUrl)): ?><a href="<?php echo htmlspecialchars($instaUrl); ?>" class="social-icon" aria-label="إنستغرام"><i class="fa-brands fa-instagram"></i></a><?php endif; ?>
      <!-- ✅ رابط واتساب موحد في الفوتر -->
      <a href="<?php echo getWhatsAppLink($siteWhatsApp, 'استفسار عام'); ?>" target="_blank" class="social-icon" aria-label="واتساب"><i class="fa-brands fa-whatsapp"></i></a>
    </div>
    <p class="footer-copy">&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($siteName); ?>. جميع الحقوق محفوظة.</p>
  </div>
</footer>

<!-- ✅ زر واتساب العائم برقم موحد -->
<a href="<?php echo getWhatsAppLink($siteWhatsApp, 'استفسار من صفحة العقار'); ?>" target="_blank" class="whatsapp-float" id="whatsappFloat" aria-label="واتساب"><i class="fa-brands fa-whatsapp"></i></a>
<button class="back-to-top" id="backToTop" aria-label="الأعلى"><i class="fa-solid fa-chevron-up"></i></button>
<div id="toastContainer" class="toast-container" role="status" aria-live="polite"></div>
<div id="cookieBanner" class="cookie-banner">
  <p class="text-sm">نستخدم ملفات تعريف الارتباط لتحسين تجربتك. <a href="<?php echo $baseUrl; ?>/privacy" class="underline" style="color:var(--gold)">سياسة الخصوصية</a>.</p>
  <button id="acceptCookies" class="btn-cookie">موافق</button>
</div>

<!-- Lightbox for Images -->
<div id="lightbox" class="lightbox-overlay" aria-hidden="true" role="dialog" aria-modal="true">
  <button class="lightbox-close" aria-label="إغلاق"><i class="fa-solid fa-xmark"></i></button>
  <button class="lightbox-nav prev" aria-label="السابق"><i class="fa-solid fa-chevron-right"></i></button>
  <button class="lightbox-nav next" aria-label="التالي"><i class="fa-solid fa-chevron-left"></i></button>
  <div class="lightbox-content">
    <img src="" alt="" class="lightbox-img" id="lightboxImg">
    <div class="lightbox-counter" id="lightboxCounter">1 / <?php echo count($images); ?></div>
  </div>
</div>

<!-- Booking Modal -->
<dialog id="bookingModal" class="bm-overlay" aria-modal="true">
  <div class="bm-box">
    <button class="bm-close" id="bmCloseBtn" aria-label="إغلاق"><i class="fa-solid fa-xmark"></i></button>
    <div class="bm-header">
      <div class="bm-title-row"><i class="fa-solid fa-calendar-check bm-icon"></i><h3 class="bm-title">حجز موعد معاينة</h3></div>
      <p class="bm-prop" id="bmPropTitle"></p>
      <div class="bm-price" id="bmPrice"></div>
    </div>
    <div class="bm-trust">
      <div class="bm-trust-item"><i class="fa-solid fa-shield-halved"></i><span>حجز آمن 100%</span></div>
      <div class="bm-trust-item"><i class="fa-solid fa-clock"></i><span>رد خلال ساعة</span></div>
      <div class="bm-trust-item"><i class="fa-solid fa-user-tie"></i><span>وكيل معتمد</span></div>
    </div>
    <div class="bm-form-wrap">
      <form id="bmForm" novalidate>
        <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
        <input type="hidden" name="property_id" id="bmPropId" value="0">        <div class="bm-grid">
          <div class="bm-field"><input type="text" name="name" id="bmName" class="bm-input" placeholder=" " required><label for="bmName" class="bm-label">الاسم الكامل</label></div>
          <div class="bm-field"><input type="tel" name="phone" id="bmPhone" class="bm-input" placeholder=" " required><label for="bmPhone" class="bm-label">رقم الهاتف</label></div>
          <div class="bm-field"><input type="date" name="date" id="bmDate" class="bm-input" required><label for="bmDate" class="bm-label">تاريخ الموعد</label></div>
          <div class="bm-field"><select name="time" id="bmTime" class="bm-input bm-select" required><option value="">وقت الموعد</option><option value="09:00">09:00 - 10:00</option><option value="10:00">10:00 - 11:00</option><option value="11:00">11:00 - 12:00</option><option value="14:00">14:00 - 15:00</option><option value="15:00">15:00 - 16:00</option><option value="16:00">16:00 - 17:00</option></select></div>
          <div class="bm-field full"><textarea name="note" id="bmNote" class="bm-input bm-textarea" placeholder=" " rows="3"></textarea><label for="bmNote" class="bm-label">ملاحظات (اختياري)</label></div>
        </div>
        <button type="submit" class="bm-submit" id="bmSubmitBtn"><span class="bm-btn-text">تأكيد الحجز الآن</span><i class="fa-solid fa-paper-plane bm-btn-icon"></i></button>
      </form>
      <div id="bmResponse" class="bm-response" role="status"></div>
    </div>
  </div>
</dialog>

<!-- Leaflet Map -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" media="print" onload="this.media='all'">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" defer></script>
</body>
</html>