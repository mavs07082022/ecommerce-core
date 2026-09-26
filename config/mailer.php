<?php
require_once __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

if (!function_exists('sendOTPEmail')) {
    function sendOTPEmail($toEmail, $toName, $otpCode, $purpose = 'register') {
        $creds = require __DIR__ . '/mail_credentials.php';

        // Subject line — avoid ALL CAPS and spam-trigger words
        $subject = 'Your E-Commerce Core verification code';

        $mail = new PHPMailer(true);
        try {
            // ---- SMTP transport ----
            $mail->isSMTP();
            $mail->Host       = $creds['smtp_host'];
            $mail->SMTPAuth   = true;
            $mail->Username   = $creds['smtp_user'];
            $mail->Password   = $creds['smtp_pass'];
            $mail->SMTPSecure = $creds['smtp_secure'];
            $mail->Port       = $creds['smtp_port'];
            $mail->CharSet    = 'UTF-8';
            $mail->Encoding   = 'base64';

            // ---- Prevent SPF/DKIM mismatches ----
            $mail->setFrom($creds['from_email'], $creds['from_name']);
            $mail->addReplyTo($creds['from_email'], $creds['from_name']);

            // ---- Recipient ----
            $mail->addAddress($toEmail, $toName);

            // ---- Anti-spam headers ----
            $mail->XMailer = 'E-Commerce Core Mailer 1.0';
            $mail->MessageID = sprintf('<%s@%s>', bin2hex(random_bytes(16)), parse_url($creds['from_email'], PHP_URL_HOST) ?: 'ecommerce-core.local');
            $mail->addCustomHeader('List-Unsubscribe', '<mailto:' . $creds['from_email'] . '?subject=unsubscribe>');
            $mail->addCustomHeader('List-Unsubscribe-Post', 'List-Unsubscribe=One-Click');
            $mail->addCustomHeader('Precedence', 'transactional');
            $mail->addCustomHeader('Auto-Submitted', 'auto-generated');

            // ---- Content ----
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = otpEmailTemplate($toName, $otpCode, $purpose);
            $mail->AltBody = "Hi $toName,\n\n"
                           . "Your verification code is: $otpCode\n\n"
                           . "This code expires in 10 minutes.\n\n"
                           . "If you didn't request this, you can ignore this email.\n\n"
                           . "— E-Commerce Core";

            $mail->send();
            return ['success' => true];
        } catch (Exception $e) {
            return ['success' => false, 'error' => $mail->ErrorInfo];
        }
    }
}

if (!function_exists('otpEmailTemplate')) {
    function otpEmailTemplate($name, $otp, $purpose) {
        $year = date('Y');
        $safeName = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
        return <<<HTML
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body style="margin:0;padding:0;background:#f4f7fb;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f7fb;padding:40px 20px;">
        <tr>
            <td align="center">
                <table role="presentation" width="520" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 4px 20px rgba(16,24,40,0.06);max-width:520px;">
                    <tr>
                        <td style="background:linear-gradient(135deg,#1e293b 0%,#111827 100%);padding:32px;text-align:center;">
                            <div style="display:inline-block;width:56px;height:56px;background:#2563eb;border-radius:14px;line-height:56px;color:#fff;font-weight:800;font-size:24px;">E</div>
                            <h1 style="color:#ffffff;font-size:20px;margin:14px 0 0;font-weight:700;">E-Commerce Core</h1>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:36px 32px;">
                            <h2 style="color:#111827;font-size:20px;margin:0 0 12px;font-weight:700;">Hi {$safeName},</h2>
                            <p style="color:#4b5563;font-size:15px;line-height:1.6;margin:0 0 24px;">
                                Use the verification code below to verify your email address. This code will expire in <strong>10 minutes</strong>.
                            </p>
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td align="center" style="padding:8px 0 24px;">
                                        <div style="display:inline-block;padding:20px 40px;background:#eff6ff;border:2px dashed #2563eb;border-radius:12px;">
                                            <div style="font-size:11px;color:#6b7280;text-transform:uppercase;letter-spacing:1.5px;font-weight:700;margin-bottom:8px;">Verification Code</div>
                                            <div style="font-size:36px;font-weight:800;color:#2563eb;letter-spacing:8px;font-family:'SF Mono',Consolas,Monaco,monospace;">{$otp}</div>
                                        </div>
                                    </td>
                                </tr>
                            </table>
                            <p style="color:#6b7280;font-size:13px;line-height:1.6;margin:24px 0 0;">
                                If you didn't request this code, you can safely ignore this email. Someone may have entered your email address by mistake.
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:20px;background:#f4f7fb;text-align:center;border-top:1px solid #e5e7eb;">
                            <p style="color:#9ca3af;font-size:12px;margin:0 0 6px;">This is an automated message — please do not reply.</p>
                            <p style="color:#9ca3af;font-size:12px;margin:0;">&copy; {$year} E-Commerce Core. All rights reserved.</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
HTML;
    }
}

if (!function_exists('generateOTP')) {
    function generateOTP($length = 6) {
        return str_pad(random_int(0, pow(10, $length) - 1), $length, '0', STR_PAD_LEFT);
    }
}