<?php
@ini_set('display_errors', 0); @error_reporting(0);
if(session_status()===PHP_SESSION_NONE) @session_start();

require_once __DIR__.'/config/database.php';
require_once __DIR__.'/includes/functions.php';

// 🌐 إعدادات الروابط الديناميكية
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host     = $_SERVER['HTTP_HOST'];
$scriptDir = dirname($_SERVER['SCRIPT_NAME']);
$basePath  = ($scriptDir === '/' || $scriptDir === '\\') ? '' : rtrim($scriptDir, '/\\');
$baseUrl   = $protocol . '://' . $host . $basePath;
$assetUrl  = $baseUrl . '/assets';

// 🔄 كاش ديناميكي
$cacheV = time();
$csrf = generate_csrf_token();

// 🌍 جلب الولايات
$govs = [];
try { $govs = $pdo->query("SELECT id, name_ar FROM governorates ORDER BY name_ar ASC")->fetchAll(PDO::FETCH_ASSOC); } 
catch(Exception $e) { $govs = []; }

// 🔍 معالجة الفلتر
$filterGov    = isset($_GET['gov_id']) ? intval($_GET['gov_id']) : 0;
$filterType   = isset($_GET['type']) ? trim($_GET['type']) : '';
$filterBudget = isset($_GET['budget']) ? trim($_GET['budget']) : '';
$filterPriority = isset($_GET['priority']) ? trim($_GET['priority']) : '';

$whereClauses = ["p.status = 'available'"];
$params = [];

if ($filterGov > 0) { $whereClauses[] = "p.gov_id = ?"; $params[] = $filterGov; }
if ($filterType !== '') { $whereClauses[] = "t.name_ar = ?"; $params[] = $filterType; }
if ($filterPriority !== '') { $whereClauses[] = "p.priority = ?"; $params[] = $filterPriority; }
if ($filterBudget !== '') {
    switch($filterBudget) {
        case 'lt300': $whereClauses[] = "p.price_tnd < 300000"; break;
        case '300_600': $whereClauses[] = "p.price_tnd BETWEEN 300000 AND 600000"; break;
        case '600_1m': $whereClauses[] = "p.price_tnd BETWEEN 600000 AND 1000000"; break;
        case 'gt1m': $whereClauses[] = "p.price_tnd > 1000000"; break;
    }
}

// 🏘️ جلب العقارات
$props = [];
$dbError = '';
try {
    $sql = "SELECT p.id, p.title_ar, p.title_fr, p.price_tnd, p.main_image, p.status, p.priority,                    p.area_sqm, p.bedrooms, p.bathrooms, p.features, p.latitude, p.longitude,
                   g.name_ar AS gov, d.name_ar AS del, t.name_ar AS type,
                   u.full_name AS agent_name, u.phone AS agent_phone
            FROM properties p 
            LEFT JOIN governorates g ON p.gov_id = g.id 
            LEFT JOIN delegations d ON p.del_id = d.id 
            LEFT JOIN property_types t ON p.type_id = t.id 
            LEFT JOIN users u ON p.agent_id = u.id
            WHERE " . implode(' AND ', $whereClauses) . " 
            ORDER BY 
                CASE p.priority WHEN 'urgent' THEN 1 WHEN 'high' THEN 2 WHEN 'medium' THEN 3 WHEN 'low' THEN 4 END ASC,
                p.created_at DESC 
            LIMIT 12";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $props = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach($props as &$prop) {
        $stmtImg = $pdo->prepare("SELECT image_path FROM property_images WHERE property_id = ? ORDER BY sort_order ASC LIMIT 5");
        $stmtImg->execute([$prop['id']]);
        $images = $stmtImg->fetchAll(PDO::FETCH_COLUMN);
        if(!empty($prop['main_image'])) array_unshift($images, $prop['main_image']);
        $prop['all_images'] = array_unique(array_filter($images));
    }
} catch (PDOException $e) {
    $dbError = 'تعذر جلب العقارات: ' . htmlspecialchars($e->getMessage());
    if(function_exists('logError')) logError("[Index DB] " . $e->getMessage());
}

// ⚙️ جلب إعدادات الموقع
$siteSettings = [];
try {
    $stmtSet = $pdo->query("SELECT setting_key, setting_value FROM site_settings WHERE group_name IN ('general', 'contact', 'social', 'basic')");
    while($row = $stmtSet->fetch(PDO::FETCH_ASSOC)) {
        $siteSettings[$row['setting_key']] = $row['setting_value'];
    }
} catch(Exception $e) {}

// 📱 معالجة موحدة لرقم الواتساب (ضمان التوحيد)
$siteWhatsApp = $siteSettings['phone_whatsapp'] ?? '21671000000';
// تنظيف إضافي للطوارئ (إذا كان الرقم في القاعدة غير نظيف)
$siteWhatsApp = preg_replace('/[^\d]/', '', $siteWhatsApp);
if (strpos($siteWhatsApp, '216') !== 0) {
    $siteWhatsApp = '216' . ltrim($siteWhatsApp, '0');
}
// ضمان أن الرقم ليس فارغاً
if (empty($siteWhatsApp) || strlen($siteWhatsApp) < 10) {
    $siteWhatsApp = '21671000000';
}
// قيم افتراضية
$siteName = $siteSettings['site_name'] ?? 'وكالة عقارية';
$sitePhone = $siteSettings['phone_primary'] ?? '+216 71 000 000';
$siteEmail = $siteSettings['email_info'] ?? 'info@agence.tn';
$siteAddress = $siteSettings['address_full'] ?? '';
$fbUrl = $siteSettings['facebook_url'] ?? '#';
$instaUrl = $siteSettings['instagram_url'] ?? '#';
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=no,viewport-fit=cover">
<title><?php echo htmlspecialchars($siteName); ?> | الرئيسية</title>
<meta name="description" content="<?php echo htmlspecialchars($siteSettings['meta_description_default'] ?? 'وكالة عقارية رائدة تقدم عقارات موثقة ووكلاء معتمدين'); ?>">
<link rel="canonical" href="<?php echo $baseUrl; ?>/">
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" media="print" onload="this.media='all'">
<noscript><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"></noscript>
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;500;600;700;800&family=Playfair+Display:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?php echo $assetUrl; ?>/css/frontend.css?v=<?php echo $cacheV; ?>">
<script>window.APP = { base: '<?php echo $baseUrl; ?>', api: '<?php echo $baseUrl; ?>/public/api', whatsapp: '<?php echo $siteWhatsApp; ?>' };</script>
<script src="<?php echo $assetUrl; ?>/js/frontend.js?v=<?php echo $cacheV; ?>" defer></script>
</head>
<body>
<div class="scroll-progress" id="scrollProgress"></div>

<nav id="navbar" class="navbar"><div class="nav-container">
  <a href="<?php echo $baseUrl; ?>/" class="logo"><i class="fa-solid fa-gem"></i> <?php echo htmlspecialchars($siteName); ?></a>
  <div class="nav-links">
    <a href="<?php echo $baseUrl; ?>/" class="nav-link active">الرئيسية</a>
    <a href="#properties" class="nav-link">العقارات</a>
    <a href="#services" class="nav-link">خدماتنا</a>
    <a href="#contact" class="nav-link">اتصل بنا</a>
  </div>
  <button id="mobileToggle" class="mobile-toggle" aria-label="القائمة"><i class="fa-solid fa-bars"></i></button>
</div></nav>

<div id="drawerOverlay" class="drawer-overlay"></div>
<div id="mobileDrawer" class="mobile-drawer" aria-hidden="true">
  <a href="<?php echo $baseUrl; ?>/" class="nav-link">الرئيسية</a>
  <a href="#properties" class="nav-link">العقارات</a>
  <a href="#services" class="nav-link">خدماتنا</a>
  <a href="#contact" class="nav-link">اتصل بنا</a>
</div>

<main id="main-content">
  <section class="hero" id="hero">
    <div class="hero-overlay"></div>
    <div class="hero-content">      <span class="hero-badge"><i class="fa-solid fa-crown"></i> <?php echo htmlspecialchars($siteSettings['hero_badge'] ?? 'الخيار الأول للعقارات الموثقة'); ?></span>
      <h1 class="text-hero"><?php echo htmlspecialchars($siteSettings['hero_title'] ?? 'اعثر على منزل أحلامك بثقة'); ?></h1>
      <p class="hero-desc"><?php echo htmlspecialchars($siteSettings['hero_desc'] ?? 'عقارات موثقة، وكلاء معتمدون، وتجربة حجز سلسة بمعايير عالمية.'); ?></p>
      <form method="GET" action="" class="search-box">
        <select name="gov_id" class="search-field">
          <option value="0">كل الولايات</option>
          <?php foreach($govs as $g): ?>
            <option value="<?php echo $g['id']; ?>" <?php echo $filterGov == $g['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($g['name_ar']); ?></option>
          <?php endforeach; ?>
        </select>
        <select name="type" class="search-field">
          <option value="">كل الأنواع</option>
          <option value="شقة" <?php echo $filterType === 'شقة' ? 'selected' : ''; ?>>شقة</option>
          <option value="فيلا" <?php echo $filterType === 'فيلا' ? 'selected' : ''; ?>>فيلا</option>
          <option value="أرض" <?php echo $filterType === 'أرض' ? 'selected' : ''; ?>>أرض</option>
          <option value="تجاري" <?php echo $filterType === 'تجاري' ? 'selected' : ''; ?>>تجاري</option>
        </select>
        <select name="priority" class="search-field">
          <option value="">كل الأولويات</option>
          <option value="urgent" <?php echo $filterPriority === 'urgent' ? 'selected' : ''; ?>>🔴 عاجل</option>
          <option value="high" <?php echo $filterPriority === 'high' ? 'selected' : ''; ?>>🟠 عالي</option>
          <option value="medium" <?php echo $filterPriority === 'medium' ? 'selected' : ''; ?>>🟡 متوسط</option>
        </select>
        <select name="budget" class="search-field">
          <option value="">الميزانية</option>
          <option value="lt300" <?php echo $filterBudget === 'lt300' ? 'selected' : ''; ?>>أقل من 300ك</option>
          <option value="300_600" <?php echo $filterBudget === '300_600' ? 'selected' : ''; ?>>300ك - 600ك</option>
          <option value="600_1m" <?php echo $filterBudget === '600_1m' ? 'selected' : ''; ?>>600ك - 1م</option>
          <option value="gt1m" <?php echo $filterBudget === 'gt1m' ? 'selected' : ''; ?>>أكثر من 1م</option>
        </select>
        <button type="submit" class="search-btn"><i class="fa-solid fa-magnifying-glass"></i> بحث</button>
      </form>
    </div>
  </section>

  <section id="properties" class="section section-alt">
    <div class="container">
      <div class="section-header">
        <h2 class="text-h2">أحدث العقارات المضافة</h2>
        <p class="text-body mt-2 max-w-620"><?php echo htmlspecialchars($siteSettings['properties_desc'] ?? 'اكتشف مجموعة مختارة من العقارات المتاحة للبيع والإيجار في أفضل المواقع.'); ?></p>
      </div>
      <div class="properties-grid">
        <?php if(!empty($dbError)): ?>
          <div class="empty-state"><p class="text-body"><?php echo $dbError; ?></p></div>
        <?php elseif(count($props) > 0): foreach($props as $p): 
          $images = $p['all_images'] ?? [$p['main_image'] ?? ''];
          $imgCount = count($images);
          $price = intval($p['price_tnd']);
          $gov = $p['gov'] ?? '';
          $del = $p['del'] ?? '';          $location = trim($del . ($del && $gov ? '، ' : '') . $gov);
          if(empty($location)) $location = 'الموقع غير محدد';
          $type = $p['type'] ?? 'عقار';
          $priority = $p['priority'] ?? 'medium';
          $priorityBadge = ['urgent'=>'🔴 عاجل','high'=>'🟠 مميز','medium'=>'🟡 قياسي','low'=>'🟢 عادي'][$priority] ?? '';
        ?>
        <article class="property-card reveal" data-id="<?php echo $p['id']; ?>" data-title="<?php echo htmlspecialchars($p['title_ar']); ?>" data-price="<?php echo $price; ?>" data-img="<?php echo resolveImage($images[0]); ?>">
          <div class="card-img-wrap">
            <div class="card-gallery">
              <?php foreach(array_slice($images, 0, 3) as $img): ?>
                <img src="<?php echo resolveImage($img); ?>" alt="<?php echo htmlspecialchars($p['title_ar']); ?>" class="gallery-img" loading="lazy">
              <?php endforeach; ?>
            </div>
            <?php if($imgCount > 1): ?>
            <div class="gallery-dots">
              <?php for($i=0; $i<min($imgCount,3); $i++): ?>
                <span class="g-dot <?php echo $i===0?'active':''; ?>"></span>
              <?php endfor; ?>
            </div>
            <?php endif; ?>
            <div class="card-badges">
              <span class="badge badge-type"><?php echo htmlspecialchars($type); ?></span>
              <?php if($priorityBadge): ?><span class="badge badge-priority"><?php echo $priorityBadge; ?></span><?php endif; ?>
              <span class="badge badge-status">متاح</span>
            </div>
            <div class="card-actions">
              <button class="action-btn fav-btn" data-id="<?php echo $p['id']; ?>"><i class="fa-regular fa-heart"></i></button>
              <!-- ✅ رابط واتساب موحد باستخدام الرقم النظيف -->
              <a href="<?php echo getWhatsAppLink($siteWhatsApp, $p['title_ar'], $p['id']); ?>" target="_blank" class="action-btn"><i class="fa-brands fa-whatsapp"></i></a>
            </div>
            <button class="share-btn" data-url="<?php echo $baseUrl.'/property.php?id='.$p['id']; ?>" data-title="<?php echo htmlspecialchars($p['title_ar']); ?>"><i class="fa-solid fa-share-nodes"></i></button>
            <div class="card-price"><?php echo formatPrice($price); ?></div>
          </div>
          <div class="card-body">
            <h3 class="card-title"><?php echo htmlspecialchars($p['title_ar']); ?></h3>
            <p class="card-location"><i class="fa-solid fa-location-dot"></i> <?php echo htmlspecialchars($location); ?></p>
            <div class="card-meta">
              <?php if(!empty($p['bedrooms'])): ?><span class="meta-item"><i class="fa-solid fa-bed"></i> <?php echo intval($p['bedrooms']); ?> غرف</span><?php endif; ?>
              <?php if(!empty($p['bathrooms'])): ?><span class="meta-item"><i class="fa-solid fa-bath"></i> <?php echo intval($p['bathrooms']); ?> حمامات</span><?php endif; ?>
              <?php if(!empty($p['area_sqm'])): ?><span class="meta-item"><i class="fa-solid fa-ruler-combined"></i> <?php echo formatArea($p['area_sqm']); ?></span><?php endif; ?>
            </div>
            <?php if(!empty($p['agent_name'])): ?>
            <div class="card-agent">
              <i class="fa-solid fa-user-tie"></i>
              <span><?php echo htmlspecialchars($p['agent_name']); ?></span>
            </div>
            <?php endif; ?>
            <div style="display:flex;gap:0.6rem;margin-top:0.8rem">
              <a href="<?php echo $baseUrl; ?>/property.php?id=<?php echo $p['id']; ?>" class="card-cta" style="flex:1;background:var(--navy);color:#fff;text-align:center;text-decoration:none;border-radius:var(--radius-md)">
                <i class="fa-solid fa-eye"></i> التفاصيل              </a>
              <button class="card-cta open-booking-modal" style="flex:1;background:var(--gold);color:var(--navy);border-radius:var(--radius-md)" 
                      data-id="<?php echo $p['id']; ?>" data-title="<?php echo htmlspecialchars($p['title_ar']); ?>" 
                      data-price="<?php echo formatPrice($price); ?>" data-img="<?php echo resolveImage($images[0]); ?>">
                <i class="fa-regular fa-calendar-check"></i> حجز موعد
              </button>
            </div>
          </div>
        </article>
        <?php endforeach; else: ?>
          <div class="empty-state"><p class="text-body">لا توجد عقارات مطابقة للبحث. جرّب تغيير المعايير أو <a href="tel:<?php echo preg_replace('/[^0-9+]/', '', $sitePhone); ?>" style="color:var(--gold);text-decoration:underline">اتصل بنا</a>.</p></div>
        <?php endif; ?>
      </div>
    </div>
  </section>

  <!-- 🔢 أرقام ديناميكية -->
  <div class="section-divider"></div>
  <section class="section section-alt">
    <div class="container">
      <div class="section-header">
        <h2 class="text-h2">أرقام نفتخر بها</h2>
        <p class="text-body mt-2 max-w-620">نتائج حقيقية تعكس ثقة عملائنا وخبرتنا في السوق العقاري.</p>
      </div>
      <div class="stats-grid">
        <?php 
        $stats = [];
        try {
            $stats['properties'] = $pdo->query("SELECT COUNT(*) FROM properties WHERE status='available' AND deleted_at IS NULL")->fetchColumn();
            $stats['deals'] = $pdo->query("SELECT COUNT(*) FROM contracts WHERE status='completed' AND deleted_at IS NULL")->fetchColumn();
            $stats['clients'] = $pdo->query("SELECT COUNT(*) FROM clients WHERE deleted_at IS NULL")->fetchColumn();
        } catch(Exception $e) {
            $stats = ['properties'=>1250, 'deals'=>840, 'clients'=>3200];
        }
        ?>
        <div class="stat-card reveal"><div class="stat-icon"><i class="fa-solid fa-house-chimney-window"></i></div><div class="stat-number" data-target="<?php echo $stats['properties']; ?>">0</div><div class="stat-label">عقار متاح</div></div>
        <div class="stat-card reveal"><div class="stat-icon"><i class="fa-solid fa-handshake"></i></div><div class="stat-number" data-target="<?php echo $stats['deals']; ?>">0</div><div class="stat-label">صفقة ناجحة</div></div>
        <div class="stat-card reveal"><div class="stat-icon"><i class="fa-solid fa-users"></i></div><div class="stat-number" data-target="<?php echo $stats['clients']; ?>">0</div><div class="stat-label">عميل سعيد</div></div>
      </div>
    </div>
  </section>

  <!-- 📞 قسم الاتصال -->
  <section id="contact" class="section">
    <div class="container">
      <div class="cta-section">
        <h2 class="cta-title"><?php echo htmlspecialchars($siteSettings['cta_title'] ?? 'جاهز لبيع أو شراء عقارك؟'); ?></h2>
        <p class="cta-desc"><?php echo htmlspecialchars($siteSettings['cta_desc'] ?? 'فريقنا من الوكلاء المعتمدين جاهز لتقديم استشارة مجانية وتقييم دقيق لعقارك خلال 24 ساعة.'); ?></p>
        <div class="cta-buttons">
          <button class="btn-light open-booking-modal" data-id="0" data-title="استشارة مجانية" data-price="0" data-img=""><i class="fa-regular fa-calendar-check"></i> احجز استشارة مجانية</button>          <a href="tel:<?php echo preg_replace('/[^0-9+]/', '', $sitePhone); ?>" class="btn-outline-light"><i class="fa-solid fa-phone"></i> اتصل الآن</a>
        </div>
      </div>
    </div>
  </section>
</main>

<footer class="footer" id="mainFooter">
  <div class="footer-container">
    <div class="footer-logo"><i class="fa-solid fa-gem"></i> <?php echo htmlspecialchars($siteName); ?></div>
    <p class="footer-desc"><?php echo htmlspecialchars($siteSettings['footer_desc'] ?? 'شريكك الموثوق في السوق العقاري. نقدم شفافية كاملة، خبرة قانونية، وخدمة عملاء استثنائية.'); ?></p>
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
<a href="<?php echo getWhatsAppLink($siteWhatsApp, 'استفسار من الموقع'); ?>" target="_blank" class="whatsapp-float" id="whatsappFloat" aria-label="واتساب"><i class="fa-brands fa-whatsapp"></i></a>
<button class="back-to-top" id="backToTop" aria-label="الأعلى"><i class="fa-solid fa-chevron-up"></i></button>
<div id="toastContainer" class="toast-container" role="status" aria-live="polite"></div>

<div id="cookieBanner" class="cookie-banner">
  <p class="text-sm">نستخدم ملفات تعريف الارتباط لتحسين تجربتك. <a href="<?php echo $baseUrl; ?>/privacy" class="underline" style="color:var(--gold)">سياسة الخصوصية</a>.</p>
  <button id="acceptCookies" class="btn-cookie">موافق</button>
</div>

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
        <input type="hidden" name="property_id" id="bmPropId" value="0">
        <div class="bm-grid">
          <div class="bm-field"><input type="text" name="name" id="bmName" class="bm-input" placeholder=" " required><label for="bmName" class="bm-label">الاسم الكامل</label></div>          <div class="bm-field"><input type="tel" name="phone" id="bmPhone" class="bm-input" placeholder=" " required><label for="bmPhone" class="bm-label">رقم الهاتف</label></div>
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

<script>
document.addEventListener('DOMContentLoaded', function() {
  document.querySelectorAll('.stat-number').forEach(function(el) {
    const target = parseInt(el.getAttribute('data-target'));
    let count = 0;
    const increment = Math.ceil(target / 30);
    const timer = setInterval(() => {
      count += increment;
      if(count >= target) { el.textContent = target.toLocaleString('ar-TN'); clearInterval(timer); }
      else { el.textContent = count.toLocaleString('ar-TN'); }
    }, 50);
  });
});
</script>
</body>
</html>