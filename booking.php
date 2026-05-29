<?php
@ini_set('display_errors', 1);
@ini_set('display_startup_errors', 1);
@error_reporting(E_ALL);
if (session_status() === PHP_SESSION_NONE) @session_start();
ob_start();

$langCode = isset($_GET['lang']) ? $_GET['lang'] : (isset($_SESSION['lang']) ? $_SESSION['lang'] : 'ar');
if (!in_array($langCode, array('ar','fr','en'))) $langCode = 'ar';
$_SESSION['lang'] = $langCode;

$configFile = __DIR__ . '/config/database.php';
$funcFile   = __DIR__ . '/includes/functions.php';
$langDir    = __DIR__ . '/public/lang';

if (!file_exists($configFile)) die('<!DOCTYPE html><html dir="rtl"><head><meta charset="UTF-8"><title>خطأ</title></head><body style="font-family:system-ui;padding:2rem;background:#fef2f2;color:#991b1b"><h1>🚫 config/database.php مفقود</h1></body></html>');
if (!file_exists($funcFile))   die('<!DOCTYPE html><html dir="rtl"><head><meta charset="UTF-8"><title>خطأ</title></head><body style="font-family:system-ui;padding:2rem;background:#fef2f2;color:#991b1b"><h1>🚫 includes/functions.php مفقود</h1></body></html>');
if (!is_dir($langDir))         die('<!DOCTYPE html><html dir="rtl"><head><meta charset="UTF-8"><title>خطأ</title></head><body style="font-family:system-ui;padding:2rem;background:#fef2f2;color:#991b1b"><h1>🚫 مجلد public/lang/ مفقود</h1></body></html>');

require $configFile;
require $funcFile;

if (!isset($pdo) || !($pdo instanceof PDO)) {
    die('<!DOCTYPE html><html dir="rtl"><head><meta charset="UTF-8"><title>خطأ DB</title></head><body style="font-family:system-ui;padding:2rem;background:#fef2f2;color:#991b1b"><h1>🗄️ اتصال قاعدة البيانات تالف</h1></body></html>');
}

$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host     = $_SERVER['HTTP_HOST'];
$scriptDir = dirname($_SERVER['SCRIPT_NAME']);
$basePath  = ($scriptDir === '/' || $scriptDir === '\\') ? '' : rtrim($scriptDir, '/\\');

if (!defined('BASE_URL'))  define('BASE_URL', $protocol . '://' . $host . $basePath);
if (!defined('ASSET_URL')) define('ASSET_URL', BASE_URL . '/assets');
if (!defined('API_URL'))   define('API_URL', BASE_URL . '/api');
if (!defined('CACHE_V'))   define('CACHE_V', time());
if (!defined('SITE_NAME')) define('SITE_NAME', 'وكالة عقارية');

$langFile = $langDir . '/' . $langCode . '.php';
if (!file_exists($langFile)) die('<!DOCTYPE html><html dir="rtl"><head><meta charset="UTF-8"><title>خطأ لغة</title></head><body style="font-family:system-ui;padding:2rem;background:#fef2f2;color:#991b1b"><h1>🌐 ملف اللغة مفقود: '.htmlspecialchars($langFile).'</h1></body></html>');
$lang = require $langFile;
if (!is_array($lang)) die('<!DOCTYPE html><html dir="rtl"><head><meta charset="UTF-8"><title>خطأ لغة</title></head><body style="font-family:system-ui;padding:2rem;background:#fef2f2;color:#991b1b"><h1>🌐 ملف اللغة لا يرجع مصفوفة</h1></body></html>');

$csrf = generate_csrf_token();
$propId = isset($_GET['id']) ? intval($_GET['id']) : 0;
$prop = null;
if ($propId > 0) {
    try {
        $stmt = $pdo->prepare("SELECT p.*, g.name_ar gov, d.name_ar del FROM properties p LEFT JOIN governorates g ON p.gov_id=g.id LEFT JOIN delegations d ON p.del_id=d.id WHERE p.id=?");
        $stmt->execute(array($propId));
        $prop = $stmt->fetch();    } catch(PDOException $e) { $prop = null; }
}

$propImg = $prop ? resolveImage($prop['main_image'] ?? '') : '';
$propTitle = $prop ? htmlspecialchars($prop['title_ar']) : t('general_booking', $lang);
$propLocation = $prop ? htmlspecialchars(($prop['del'] ?? '') . '، ' . ($prop['gov'] ?? '')) : '';
$propPrice = $prop ? formatPrice($prop['price_tnd']) : '';
?>
<!DOCTYPE html>
<html lang="<?php echo $lang['lang_attr']; ?>" dir="<?php echo $lang['dir']; ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=no,viewport-fit=cover">
<title><?php echo t('booking_page_title', $lang); ?> | <?php echo htmlspecialchars(SITE_NAME); ?></title>
<meta name="description" content="<?php echo t('booking_page_desc', $lang); ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" media="print" onload="this.media='all'">
<noscript><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"></noscript>
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;500;600;700;800&family=Playfair+Display:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?php echo $protocol . '://' . $host . $basePath; ?>/assets/css/frontend.css?v=<?php echo CACHE_V; ?>">
<style>
:root{--navy:#080f24;--gold:#d4af37;--gold-light:#e8c547;--gold-text:#a68500;--gold-glow:rgba(212,175,55,0.18);--bg:#f8fafc;--bg-card:#ffffff;--text:#0b1220;--text-muted:#4a5568;--border:rgba(11,18,32,0.08);--glass:rgba(255,255,255,0.82);--shadow-md:0 8px 28px rgba(8,15,36,0.06);--shadow-lg:0 16px 44px rgba(8,15,36,0.08);--radius-md:18px;--radius-lg:24px;--radius-xl:32px;--ease:cubic-bezier(0.22,1,0.36,1);--font-body:'Cairo',system-ui,sans-serif;--font-display:'Playfair Display',Georgia,serif}
*,:before,:after{box-sizing:border-box;margin:0;padding:0}
body{font-family:var(--font-body);background:linear-gradient(160deg,#f8fafc 0%,#eef2f7 100%);color:var(--text);line-height:1.65;direction:rtl;overflow-x:hidden;min-height:100vh;padding-bottom:env(safe-area-inset-bottom)}
h1,h2,h3{font-family:var(--font-display);font-weight:700;line-height:1.2}
a{text-decoration:none;color:inherit}
button,input,select,textarea{font:inherit;outline:none;border:none;background:none}
:focus-visible{outline:2px solid var(--gold);outline-offset:3px;border-radius:10px}

/* 🧭 Navbar 2026 (Desktop & Mobile) */
.navbar{position:fixed;inset:0 0 auto 0;z-index:1000;padding:1rem 0;transition:background 0.35s var(--ease),padding 0.35s var(--ease),box-shadow 0.35s var(--ease);background:linear-gradient(180deg,rgba(8,15,36,0.85) 0%,transparent 100%)}
.navbar.scrolled{background:var(--glass);backdrop-filter:blur(18px) saturate(150%);box-shadow:var(--shadow-md);padding:0.7rem 0;border-bottom:1px solid rgba(212,175,55,0.12)}
.nav-container{max-width:1200px;margin:0 auto;padding:0 1.5rem;display:flex;align-items:center;justify-content:space-between}
.logo{font-size:1.3rem;font-weight:800;color:#fff;display:flex;align-items:center;gap:0.4rem;transition:color 0.3s var(--ease)}
.navbar.scrolled .logo{color:var(--navy)}
.logo i{color:var(--gold);transition:transform 0.3s var(--ease)}
.logo:hover i{transform:rotate(12deg) scale(1.08)}
.nav-links{display:flex;gap:1.6rem}
.nav-link{font-weight:600;font-size:0.9rem;color:rgba(255,255,255,0.95);position:relative;padding:0.2rem 0;transition:color 0.25s var(--ease)}
.navbar.scrolled .nav-link{color:var(--text)}
.nav-link:hover,.nav-link.active{color:var(--gold)}
.nav-link::after{content:'';position:absolute;bottom:-2px;inset-inline-start:0;width:0;height:2px;background:var(--gold);transition:width 0.3s var(--ease);border-radius:2px}
.nav-link:hover::after,.nav-link.active::after{width:100%}
.mobile-toggle{display:none;font-size:1.2rem;color:#fff;cursor:pointer;padding:0.4rem;border-radius:10px;transition:background 0.25s var(--ease)}
.mobile-toggle:hover{background:rgba(255,255,255,0.12)}
.navbar.scrolled .mobile-toggle{color:var(--navy)}
.navbar.scrolled .mobile-toggle:hover{background:rgba(0,0,0,0.04)}
.mobile-drawer{position:fixed;top:0;right:-100%;width:85%;max-width:300px;height:100vh;background:rgba(255,255,255,0.98);backdrop-filter:blur(20px);z-index:1001;padding:5.5rem 1.5rem 2rem;box-shadow:-10px 0 30px rgba(0,0,0,0.1);transition:right 0.4s var(--ease);display:flex;flex-direction:column;gap:0.5rem;border-inline-start:1px solid rgba(212,175,55,0.12);overflow-y:auto}
.mobile-drawer.open{right:0}.mobile-drawer .nav-link{color:var(--text);font-size:1rem;padding:0.8rem 0.5rem;border-bottom:1px solid var(--border);border-radius:8px;transition:background 0.25s var(--ease),color 0.25s var(--ease)}
.mobile-drawer .nav-link:hover{background:rgba(212,175,55,0.06);color:var(--gold)}
.mobile-drawer .nav-link:last-of-type{border-bottom:none}
.drawer-overlay{position:fixed;inset:0;background:rgba(8,15,36,0.3);z-index:1000;opacity:0;visibility:hidden;transition:opacity 0.3s var(--ease),visibility 0.3s var(--ease);backdrop-filter:blur(2px)}
.drawer-overlay.show{opacity:1;visibility:visible}

/* 📅 Booking Layout 2026 */
.booking-main{padding:6rem 1.5rem 4rem}
.booking-container{max-width:1100px;margin:0 auto;display:grid;grid-template-columns:1fr 1.3fr;gap:2.5rem;align-items:start}
.context-card{background:var(--bg-card);border-radius:var(--radius-xl);box-shadow:var(--shadow-lg);border:1px solid var(--border);overflow:hidden;position:sticky;top:6.5rem;transition:transform 0.35s var(--ease),box-shadow 0.35s var(--ease)}
.context-card:hover{transform:translateY(-5px);box-shadow:0 20px 50px rgba(8,15,36,0.1)}
.context-img-wrap{position:relative;aspect-ratio:16/10;background:#eef2f7}
.context-img{width:100%;height:100%;object-fit:cover}
.context-badge{position:absolute;top:1rem;inset-inline-start:1rem;background:rgba(8,15,36,0.88);color:var(--gold);padding:0.35rem 0.8rem;border-radius:50px;font-size:0.75rem;font-weight:700;backdrop-filter:blur(6px);box-shadow:0 4px 12px rgba(0,0,0,0.15)}
.context-info{padding:1.5rem}
.context-title{font-size:1.35rem;color:var(--navy);margin-bottom:0.5rem;line-height:1.3}
.context-location{color:var(--text-muted);font-size:0.88rem;display:flex;align-items:center;gap:0.4rem;margin-bottom:1rem}
.context-location i{color:var(--gold)}
.context-price{display:inline-flex;align-items:baseline;gap:0.35rem;background:linear-gradient(135deg,var(--gold),var(--gold-light));color:var(--navy);padding:0.45rem 1rem;border-radius:50px;font-weight:800;font-size:0.95rem;font-variant-numeric:tabular-nums;box-shadow:0 4px 14px rgba(212,175,55,0.25)}
.price-currency{font-size:0.72rem;font-weight:600;opacity:0.85;margin-inline-start:0.15rem}
.context-features{display:grid;grid-template-columns:repeat(3,1fr);gap:0.8rem;padding:1.2rem 1.5rem;border-top:1px solid var(--border);background:#f8fafc}
.feature-item{display:flex;flex-direction:column;align-items:center;text-align:center;gap:0.4rem;font-size:0.78rem;color:var(--text-muted);font-weight:500}
.feature-item i{font-size:1.1rem;color:var(--gold)}

.form-card{background:var(--bg-card);border-radius:var(--radius-xl);box-shadow:var(--shadow-lg);border:1px solid var(--border);padding:2rem;position:relative;overflow:hidden}
.form-card::before{content:'';position:absolute;top:-35%;inset-inline-end:-30%;width:150%;height:150%;background:radial-gradient(circle,rgba(212,175,55,0.05) 0%,transparent 60%);pointer-events:none}
.form-header{margin-bottom:1.8rem;text-align:center}
.form-title{font-size:1.45rem;color:var(--navy);margin-bottom:0.4rem}
.form-subtitle{color:var(--text-muted);font-size:0.9rem;max-width:520px;margin:0 auto;line-height:1.6}
.form-section{margin-bottom:1.4rem}
.form-row{display:grid;grid-template-columns:1fr 1fr;gap:1rem}
.input-wrap{position:relative}
.input-wrap.full{grid-column:1/-1}
.form-input,.form-select,.form-textarea{width:100%;padding:1.05rem 1rem 0.65rem;border:1.5px solid var(--border);border-radius:var(--radius-md);font-size:0.92rem;background:#fff;color:var(--text);transition:all 0.25s var(--ease);min-height:52px}
.form-textarea{resize:vertical;min-height:90px;padding-top:1.1rem}
.input-label{position:absolute;inset-inline-start:1rem;top:50%;transform:translateY(-50%);font-size:0.88rem;color:var(--text-muted);pointer-events:none;transition:all 0.25s var(--ease);background:#fff;padding:0 0.3rem}
.input-wrap.full .input-label{top:1.1rem;transform:translateY(0)}
.form-input:focus~.input-label,.form-input:not(:placeholder-shown)~.input-label,.form-textarea:focus~.input-label,.form-textarea:not(:placeholder-shown)~.input-label{top:0.35rem;transform:translateY(0);font-size:0.72rem;color:var(--gold-text);background:#fff;padding:0 0.4rem;font-weight:600}
.form-input:focus,.form-select:focus,.form-textarea:focus{border-color:var(--gold);box-shadow:0 0 0 3px var(--gold-glow);background:#fff}
.form-input:invalid:not(:placeholder-shown){border-color:#ef4444}
.form-input:valid:not(:placeholder-shown){border-color:#10b981}
.form-actions{margin-top:1.5rem}
.btn-submit{width:100%;padding:0.95rem;border-radius:var(--radius-md);font-weight:700;font-size:0.95rem;cursor:pointer;transition:all 0.25s var(--ease);text-align:center;min-height:54px;display:flex;align-items:center;justify-content:center;gap:0.5rem;position:relative;overflow:hidden;background:linear-gradient(135deg,var(--navy),#111b38);color:#fff;box-shadow:0 5px 16px rgba(8,15,36,0.16)}
.btn-submit:hover{background:linear-gradient(135deg,var(--gold),var(--gold-light));color:var(--navy);transform:translateY(-2px);box-shadow:0 7px 22px rgba(212,175,55,0.26)}
.btn-submit::after{content:'';position:absolute;top:0;left:-100%;width:50%;height:100%;background:linear-gradient(90deg,transparent,rgba(255,255,255,0.25),transparent);transition:left 0.5s var(--ease)}
.btn-submit:hover::after{left:120%}
.btn-loading .btn-text{opacity:0}
.btn-loading .btn-icon{display:none}
.btn-loading::after{content:'';width:20px;height:20px;border:2.5px solid rgba(255,255,255,0.3);border-top-color:#fff;border-radius:50%;animation:spin 0.6s linear infinite}
@keyframes spin{to{transform:rotate(360deg)}}.form-response{margin-top:1.2rem;display:flex;align-items:center;justify-content:center;gap:0.6rem;font-size:0.9rem;font-weight:600;opacity:0;transform:translateY(8px);transition:all 0.3s var(--ease);padding:0.8rem;border-radius:var(--radius-md)}
.form-response.show{opacity:1;transform:translateY(0)}
.form-response.success{color:#059669;background:rgba(16,185,129,0.08);border:1px solid rgba(16,185,129,0.2)}
.form-response.error{color:#dc2626;background:rgba(239,68,68,0.08);border:1px solid rgba(239,68,68,0.2)}
.form-response i{font-size:1.15rem}
.toast-container{position:fixed;bottom:1.5rem;inset-inline-end:1.5rem;z-index:9999;display:flex;flex-direction:column;gap:0.6rem;pointer-events:none}
.toast{background:#fff;color:var(--text);padding:0.9rem 1.2rem;border-radius:var(--radius-md);box-shadow:var(--shadow-lg);border-inline-start:4px solid var(--gold);font-size:0.88rem;font-weight:500;transform:translateX(120%);opacity:0;transition:all 0.35s var(--ease);pointer-events:auto;max-width:320px}
.toast.show{transform:translateX(0);opacity:1}
.toast.success{border-inline-start-color:#10b981}
.toast.error{border-inline-start-color:#ef4444}

@media(max-width:900px){
  .nav-links{display:none}
  .mobile-toggle{display:flex}
  .booking-container{grid-template-columns:1fr;gap:1.8rem}
  .context-card{position:static}
}
@media(max-width:600px){
  .booking-main{padding:5.5rem 1rem 3rem}
  .form-card{padding:1.5rem}
  .form-row{grid-template-columns:1fr;gap:0.8rem}
  .context-features{grid-template-columns:1fr;gap:0.6rem}
  .feature-item{flex-direction:row;justify-content:flex-start;text-align:start}
}
@media(prefers-reduced-motion:reduce){
  *,*:before,*:after{animation-duration:0.01ms!important;transition-duration:0.01ms!important}
}
</style>
<script>window.APP = { base: '<?php echo $protocol . '://' . $host . $basePath; ?>', api: '<?php echo $protocol . '://' . $host . $basePath; ?>/api' };</script>
</head>
<body class="booking-page">
<a href="#main-content" class="skip-link" style="position:absolute;top:-100%;left:50%;transform:translateX(-50%);background:var(--gold);color:var(--navy);padding:0.5rem 1rem;border-radius:0 0 10px 10px;font-weight:700;z-index:9999"><?php echo $langCode=='ar'?'تجاوز إلى المحتوى':'Skip'; ?></a>

<nav id="navbar" class="navbar">
  <div class="nav-container">
    <a href="<?php echo BASE_URL; ?>/" class="logo"><i class="fa-solid fa-gem"></i> <?php echo htmlspecialchars(SITE_NAME); ?></a>
    <div class="nav-links">
      <a href="<?php echo BASE_URL; ?>/" class="nav-link"><?php echo t('nav_home', $lang); ?></a>
      <a href="<?php echo BASE_URL; ?>/#properties" class="nav-link"><?php echo t('nav_props', $lang); ?></a>
      <a href="<?php echo BASE_URL; ?>/#contact" class="nav-link"><?php echo t('nav_contact', $lang); ?></a>
    </div>
    <button id="mobileToggle" class="mobile-toggle"><i class="fa-solid fa-bars"></i></button>
  </div>
</nav>
<div id="drawerOverlay" class="drawer-overlay"></div>
<div id="mobileDrawer" class="mobile-drawer">
  <a href="<?php echo BASE_URL; ?>/" class="nav-link"><?php echo t('nav_home', $lang); ?></a>
  <a href="<?php echo BASE_URL; ?>/#properties" class="nav-link"><?php echo t('nav_props', $lang); ?></a>
  <a href="<?php echo BASE_URL; ?>/#contact" class="nav-link"><?php echo t('nav_contact', $lang); ?></a>
</div>
<main id="main-content" class="booking-main">
  <div class="booking-container">
    <div class="context-card">
      <div class="context-img-wrap">
        <img src="<?php echo $propImg; ?>" alt="<?php echo $propTitle; ?>" class="context-img" loading="lazy">
        <div class="context-badge"><?php echo $prop ? t('selected_property', $lang) : t('general_booking', $lang); ?></div>
      </div>
      <div class="context-info">
        <h1 class="context-title"><?php echo $propTitle; ?></h1>
        <?php if($propLocation): ?><p class="context-location"><i class="fa-solid fa-location-dot"></i> <?php echo $propLocation; ?></p><?php endif; ?>
        <?php if($propPrice): ?><div class="context-price"><?php echo $propPrice; ?></div><?php endif; ?>
      </div>
      <div class="context-features">
        <div class="feature-item"><i class="fa-solid fa-shield-halved"></i> <?php echo t('secure_booking', $lang); ?></div>
        <div class="feature-item"><i class="fa-solid fa-clock"></i> <?php echo t('quick_response', $lang); ?></div>
        <div class="feature-item"><i class="fa-solid fa-user-tie"></i> <?php echo t('expert_agent', $lang); ?></div>
      </div>
    </div>

    <div class="form-card">
      <div class="form-header">
        <h2 class="form-title"><?php echo t('book_viewing_title', $lang); ?></h2>
        <p class="form-subtitle"><?php echo t('book_viewing_desc', $lang); ?></p>
      </div>
      <form id="bookingForm" novalidate>
        <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
        <input type="hidden" name="property_id" value="<?php echo $propId; ?>">
        <div class="form-section">
          <div class="form-row">
            <div class="input-wrap"><input type="text" name="name" id="bookName" class="form-input" placeholder=" " required autocomplete="name"><label for="bookName" class="input-label"><?php echo t('form_name', $lang); ?></label></div>
            <div class="input-wrap"><input type="tel" name="phone" id="bookPhone" class="form-input" placeholder=" " required autocomplete="tel"><label for="bookPhone" class="input-label"><?php echo t('form_phone', $lang); ?></label></div>
          </div>
          <div class="input-wrap"><input type="email" name="email" id="bookEmail" class="form-input" placeholder=" " autocomplete="email"><label for="bookEmail" class="input-label"><?php echo t('form_email', $lang); ?> (<?php echo t('optional', $lang); ?>)</label></div>
        </div>
        <div class="form-section">
          <div class="form-row">
            <div class="input-wrap"><input type="date" name="date" id="bookDate" class="form-input" required><label for="bookDate" class="input-label"><?php echo t('form_date', $lang); ?></label></div>
            <div class="input-wrap"><select name="time" id="bookTime" class="form-select" required><option value=""><?php echo t('form_time', $lang); ?></option><option value="09:00">09:00 - 10:00</option><option value="10:00">10:00 - 11:00</option><option value="11:00">11:00 - 12:00</option><option value="14:00">14:00 - 15:00</option><option value="15:00">15:00 - 16:00</option><option value="16:00">16:00 - 17:00</option></select></div>
          </div>
        </div>
        <div class="form-section">
          <div class="input-wrap full"><textarea name="note" id="bookNote" class="form-textarea" placeholder=" " rows="3"></textarea><label for="bookNote" class="input-label"><?php echo t('form_note', $lang); ?></label></div>
        </div>
        <div class="form-actions">
          <button type="submit" class="btn-submit" data-loading><span class="btn-text"><?php echo t('btn_confirm_booking', $lang); ?></span><i class="fa-solid fa-calendar-check btn-icon"></i></button>
        </div>
      </form>
      <div id="bookingResponse" class="form-response"></div>
    </div>  </div>
</main>

<div id="toastContainer" class="toast-container"></div>

<script>
(function(){
  var form = document.getElementById('bookingForm');
  var res = document.getElementById('bookingResponse');
  var submitBtn = form.querySelector('[data-loading]');
  var nav = document.getElementById('navbar');
  var tog = document.getElementById('mobileToggle');
  var drw = document.getElementById('mobileDrawer');
  var ovr = document.getElementById('drawerOverlay');
  var icn = tog ? tog.querySelector('i') : null;

  window.addEventListener('scroll', function(){
    if(nav) nav.classList.toggle('scrolled', window.scrollY > 40);
  }, {passive:true});

  function toggleDrawer(){
    var isOpen = drw.classList.toggle('open');
    ovr.classList.toggle('show');
    if(icn){icn.classList.toggle('fa-bars');icn.classList.toggle('fa-xmark');}
    document.body.style.overflow = isOpen ? 'hidden' : '';
  }
  if(tog) tog.addEventListener('click', toggleDrawer);
  if(ovr) ovr.addEventListener('click', toggleDrawer);

  var dateInput = document.getElementById('bookDate');
  if(dateInput){
    var tmr = new Date(); tmr.setDate(tmr.getDate()+1);
    dateInput.min = tmr.toISOString().split('T')[0];
  }

  function showToast(msg, type){
    type = type || 'info';
    var c = document.getElementById('toastContainer');
    if(!c) return;
    var t = document.createElement('div');
    t.className = 'toast ' + type; t.textContent = msg;
    c.appendChild(t);
    requestAnimationFrame(function(){t.classList.add('show');});
    setTimeout(function(){t.classList.remove('show');setTimeout(function(){t.remove();},350);},3000);
  }

  function setLoading(on){
    if(on){submitBtn.classList.add('btn-loading');submitBtn.disabled=true;}
    else{submitBtn.classList.remove('btn-loading');submitBtn.disabled=false;}
  }
  form.addEventListener('submit', function(e){
    e.preventDefault();
    setLoading(true);
    res.className = 'form-response'; res.innerHTML = '';
    var fd = new FormData(form);
    var xhr = new XMLHttpRequest();
    xhr.open('POST', window.APP.api + '/booking.php', true);
    xhr.onload = function(){
      try{
        var d = JSON.parse(xhr.responseText);
        if(d.success){
          res.className = 'form-response success show';
          res.innerHTML = '<i class="fa-solid fa-circle-check"></i> ' + d.success;
          showToast(d.success, 'success');
          form.reset();
          setTimeout(function(){window.location.href = window.APP.base + '/';}, 2500);
        } else {
          res.className = 'form-response error show';
          res.innerHTML = '<i class="fa-solid fa-circle-xmark"></i> ' + (d.error || 'حدث خطأ');
          showToast(d.error || 'حدث خطأ', 'error');
        }
      } catch(err){
        res.className = 'form-response error show';
        res.innerHTML = '<i class="fa-solid fa-triangle-exclamation"></i> رد غير صالح';
        showToast('رد غير صالح', 'error');
      }
      setLoading(false);
    };
    xhr.onerror = function(){
      res.className = 'form-response error show';
      res.innerHTML = '<i class="fa-solid fa-triangle-exclamation"></i> فشل الاتصال';
      showToast('فشل الاتصال', 'error');
      setLoading(false);
    };
    xhr.send(fd);
  });
})();
</script>
</body>
</html>