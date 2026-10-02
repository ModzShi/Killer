<?php
require_once __DIR__ . '/app/auth.php';
if (SK_OFFLINE) return;
try {
    $db = app_db();
    $pixelSettings = $db->query('SELECT google_ads_tag,facebook_ads_tag FROM app LIMIT 1')->fetch_assoc() ?: [];
    $db->close();
} catch (Throwable $e) {
    error_log('pixel settings: '.$e->getMessage());
    return;
}
$googleTag = strtoupper(trim((string)($pixelSettings['google_ads_tag'] ?? '')));
$facebookTag = trim((string)($pixelSettings['facebook_ads_tag'] ?? ''));
if (!preg_match('/^(?:G-[A-Z0-9]{6,16}|AW-[0-9]{6,16}|GT-[A-Z0-9]{6,16})$/D', $googleTag)) $googleTag = '';
if (!preg_match('/^[0-9]{6,25}$/D', $facebookTag)) $facebookTag = '';
?>
<?php if ($facebookTag !== ''): ?>
<script>
!function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,document,'script','https://connect.facebook.net/en_US/fbevents.js');
fbq('init', <?= json_encode($facebookTag, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>);
fbq('track', 'PageView');
</script>
<noscript><img height="1" width="1" style="display:none" alt="" src="https://www.facebook.com/tr?id=<?= rawurlencode($facebookTag) ?>&amp;ev=PageView&amp;noscript=1"></noscript>
<?php endif; ?>
<?php if ($googleTag !== ''): ?>
<script async src="https://www.googletagmanager.com/gtag/js?id=<?= rawurlencode($googleTag) ?>"></script>
<script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments)}gtag('js',new Date());gtag('config',<?= json_encode($googleTag, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>);</script>
<?php endif; ?>
