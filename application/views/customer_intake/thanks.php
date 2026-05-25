<?php
defined('BASEPATH') OR exit('No direct script access allowed');
$booking_label = !empty($context->BookingNumber) ? $context->BookingNumber : ('#' . $context->booking_id);
$submitted_human = !empty($submitted_at) ? date('j M Y, g:i a', strtotime($submitted_at)) : '';
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Thank you &mdash; <?php echo htmlspecialchars($booking_label); ?></title>
<style>
* { box-sizing: border-box; }
body {
    margin: 0;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
    background: #f4f6fa;
    color: #1f2533;
    line-height: 1.5;
}
.shell { max-width: 560px; margin: 0 auto; padding: 80px 16px; text-align: center; }
.card {
    background: #fff;
    padding: 40px 28px;
    border-radius: 14px;
    box-shadow: 0 1px 4px rgba(15,23,42,0.08);
}
.tick {
    width: 64px; height: 64px;
    border-radius: 50%;
    background: #e8f5ee;
    color: #1f8d4a;
    margin: 0 auto 18px;
    display: flex; align-items: center; justify-content: center;
    font-size: 30px;
}
h1 { margin: 0 0 10px; font-size: 22px; color: #1c3d5a; }
p { margin: 8px 0; color: #4a5266; font-size: 15px; }
.meta { display: inline-block; margin-top: 16px; padding: 10px 14px; background: #f4f6fa; border-radius: 8px; font-size: 13px; color: #4a5266; }
.meta strong { color: #1f2533; }
</style>
</head>
<body>
<div class="shell">
    <div class="card">
        <div class="tick">&check;</div>
        <h1>Thanks &mdash; we&rsquo;ve got your details.</h1>
        <p>Your booking team will take it from here and reach out shortly.</p>
        <div class="meta">
            Booking <strong><?php echo htmlspecialchars($booking_label); ?></strong>
            <?php if (!empty($submitted_human)): ?>
                &middot; Submitted <strong><?php echo htmlspecialchars($submitted_human); ?></strong>
            <?php endif; ?>
        </div>
    </div>
</div>
</body>
</html>
