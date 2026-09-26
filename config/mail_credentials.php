<?php
/**
 * Gmail SMTP Credentials for sending OTP emails.
 * Use a Gmail App Password — NEVER your real Gmail password.
 * Guide: https://support.google.com/accounts/answer/185833
 */

return [
    'smtp_host'   => 'smtp.gmail.com',
    'smtp_port'   => 587,
    'smtp_secure' => 'tls',
    'smtp_user'   => 'ecommerce.core.sm@gmail.com',      // ← Replace with your Gmail
    'smtp_pass'   => 'yhndmdlpguyjqlky',          // ← Replace with 16-char app password (no spaces)
    'from_email'  => 'ecommerce.core.sm@gmail.com',      // ← Same as smtp_user usually
    'from_name'   => 'E-Commerce Core',
];