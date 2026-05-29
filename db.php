<?php
/**
 * 🔧 Roles & Auth Fixer | Safe, Transactional & Idempotent
 * يعبئ جدول roles، يربط حساب admin، ويحل خطأ #1452 و 403 نهائياً
 * ⚠️ احذف هذا الملف فوراً بعد النجاح لأسباب أمنية
 */
@ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/config/database.php';
if (!isset($pdo) || !($pdo instanceof PDO)) {
    die('❌ فشل جلب اتصال قاعدة البيانات من config/database.php');
}

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<title>إصلاح الأدوار والصلاحيات</title>
<style>
  body{font-family:'Cairo',system-ui,sans-serif;background:#f8fafc;color:#0f172a;padding:2rem;line-height:1.6}
  .box{background:#fff;padding:2rem;border-radius:14px;box-shadow:0 6px 20px rgba(0,0,0,0.06);max-width:650px;margin:2rem auto}
  h1{color:#0b1220;margin-bottom:1rem;font-size:1.4rem}
  .success{color:#059669;font-weight:600;margin:0.5rem 0}
  .error{color:#dc2626;font-weight:600;margin:0.5rem 0}
  .info{background:#e0f2fe;color:#0369a1;padding:1rem;border-radius:10px;margin-top:1rem;font-size:0.9rem}
  .warn{background:#fff3cd;color:#856404;padding:1rem;border-radius:10px;margin-top:1rem;border:1px solid #fcd34d}
  code{background:#f1f5f9;padding:0.2rem 0.4rem;border-radius:4px;font-family:monospace}
</style>
</head>
<body>
<div class="box">
  <h1>🔧 إصلاح جدول الأدوار وربط الصلاحيات</h1>
  <?php
  try {
      $pdo->beginTransaction();

      // 1️⃣ تعبئة جدول roles بالأدوار الأساسية (آمن للتكرار)
      $pdo->exec("INSERT IGNORE INTO `roles` (`id`, `role_name`, `display_name`) VALUES 
                  (1, 'admin', 'مدير النظام'), 
                  (2, 'agent', 'وكيل عقاري')");
      echo "<div class='success'>✅ تم تعبئة جدول roles بنجاح (admin=1, agent=2).</div>";

      // 2️⃣ ربط حساب admin بـ role_id = 1 (الآن الرابط الخارجي مقبول)
      $stmt = $pdo->prepare("UPDATE `users` SET `role_id` = 1 WHERE `username` = 'admin' LIMIT 1");
      $stmt->execute();
      echo "<div class='success'>✅ تم ربط حساب المدير بالدور الصحيح (role_id = 1).</div>";

      // 3️⃣ التحقق النهائي
      $check = $pdo->query("SELECT u.username, u.role_id, r.role_name FROM users u LEFT JOIN roles r ON u.role_id=r.id WHERE u.username='admin' LIMIT 1")->fetch();
      if ($check && $check['role_id'] == 1) {
          echo "<div class='success'>🎉 اكتمل الإصلاح بنجاح! النظام الآن جاهز للعمل بالصلاحيات الكاملة.</div>";
      } else {
          echo "<div class='error'>⚠️ تم التنفيذ لكن التحقق فشل. يرجى مراجعة البيانات يدوياً.</div>";
      }

      $pdo->commit();
      echo "<div class='info'>📌 الخطوة التالية: سجّل خروج ثم دخول مرة واحدة لتحديث الجلسة بالصلاحيات الجديدة. ستختفي رسالة 'غير مصرح' نهائياً.</div>";
      echo "<div class='warn'>⚠️ <strong>هام أمني:</strong> احذف ملف <code>fix_roles_auth.php</code> فوراً من السيرفر بعد قراءة هذه الرسالة.</div>";

  } catch (Exception $e) {
      if ($pdo->inTransaction()) $pdo->rollBack();
      echo "<div class='error'>❌ فشل الإصلاح: " . htmlspecialchars($e->getMessage()) . "</div>";
  }
  ?>
</div>
</body>
</html>