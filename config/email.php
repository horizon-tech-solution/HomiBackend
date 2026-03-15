<?php
// config/email.php
// Sends emails via Resend API (https://resend.com)
// Install: "C:\wamp64\bin\php\php8.5.1\php.exe" composer.phar require resend/resend-php

class EmailService {
    private static $apiKey;
    private static $fromAddress;
    private static $fromName = 'HOMi';

    private static function init(): void {
        self::$apiKey      = $_ENV['RESEND_API_KEY'] ?? '';
        self::$fromAddress = $_ENV['RESEND_FROM_EMAIL'] ?? 'noreply@yourdomain.com';
    }

    // ── Core send method ──────────────────────────────────────────────────────
    public static function send(string $to, string $subject, string $html): bool {
        self::init();

        if (empty(self::$apiKey)) {
            error_log('EmailService: RESEND_API_KEY not set');
            return false;
        }

        $payload = json_encode([
            'from'    => self::$fromName . ' <' . self::$fromAddress . '>',
            'to'      => [$to],
            'subject' => $subject,
            'html'    => $html,
        ]);

        $ch = curl_init('https://api.resend.com/emails');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . self::$apiKey,
                'Content-Type: application/json',
            ],
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200 && $httpCode !== 201) {
            error_log('EmailService: Resend API error ' . $httpCode . ' — ' . $response);
            return false;
        }

        return true;
    }

    // ── OTP ───────────────────────────────────────────────────────────────────
    public static function sendOtp(string $to, string $name, string $otp, string $reason): bool {
        $reasonText = match($reason) {
            'password_change' => 'change your password',
            'email_change'    => 'change your email address',
            'account_delete'  => 'delete your account',
            'login_2fa'       => 'log in to your account',
            default           => 'verify your identity',
        };

        $html = self::baseTemplate("Your verification code", "
            <p style='margin:0 0 16px'>Hi {$name},</p>
            <p style='margin:0 0 24px'>You requested to {$reasonText}. Use the code below to verify your identity:</p>
            <div style='background:#FEF3C7;border:2px solid #F59E0B;border-radius:12px;padding:24px;text-align:center;margin:0 0 24px'>
                <span style='font-size:40px;font-weight:900;letter-spacing:12px;color:#92400E'>{$otp}</span>
            </div>
            <p style='margin:0 0 8px;color:#6B7280;font-size:14px'>This code expires in <strong>10 minutes</strong>.</p>
            <p style='margin:0;color:#6B7280;font-size:14px'>If you didn't request this, you can safely ignore this email.</p>
        ");

        return self::send($to, 'Your HOMi verification code: ' . $otp, $html);
    }

    // ── Password changed confirmation ─────────────────────────────────────────
    public static function sendPasswordChanged(string $to, string $name): bool {
        $html = self::baseTemplate("Password changed", "
            <p style='margin:0 0 16px'>Hi {$name},</p>
            <p style='margin:0 0 24px'>Your HOMi account password was successfully changed.</p>
            <div style='background:#FEF3C7;border-left:4px solid #F59E0B;padding:16px;border-radius:8px;margin:0 0 24px'>
                <p style='margin:0;font-size:14px;color:#92400E'>If you didn't make this change, please contact support immediately at <a href='mailto:support@yourdomain.com' style='color:#92400E'>support@yourdomain.com</a></p>
            </div>
        ");

        return self::send($to, 'Your HOMi password has been changed', $html);
    }

    // ── Email change confirmation ─────────────────────────────────────────────
    public static function sendEmailChanged(string $to, string $name, string $newEmail): bool {
        $html = self::baseTemplate("Email address changed", "
            <p style='margin:0 0 16px'>Hi {$name},</p>
            <p style='margin:0 0 24px'>Your HOMi account email has been updated to <strong>{$newEmail}</strong>.</p>
            <div style='background:#FEF3C7;border-left:4px solid #F59E0B;padding:16px;border-radius:8px;margin:0 0 24px'>
                <p style='margin:0;font-size:14px;color:#92400E'>If you didn't make this change, please contact support immediately.</p>
            </div>
        ");

        return self::send($to, 'Your HOMi email address has been updated', $html);
    }

    // ── Account deleted confirmation ──────────────────────────────────────────
    public static function sendAccountDeleted(string $to, string $name): bool {
        $html = self::baseTemplate("Account deleted", "
            <p style='margin:0 0 16px'>Hi {$name},</p>
            <p style='margin:0 0 24px'>Your HOMi account has been permanently deleted. All your data including favorites, saved searches, and history have been removed.</p>
            <p style='margin:0;color:#6B7280;font-size:14px'>We're sorry to see you go. If this was a mistake, please contact support as soon as possible.</p>
        ");

        return self::send($to, 'Your HOMi account has been deleted', $html);
    }

    // ── In-app notification mirror ────────────────────────────────────────────
    public static function sendNotification(string $to, string $name, string $title, string $message, string $type, ?array $data = null): bool {
        $iconEmoji = match($type) {
            'price_drop'  => '📉',
            'message'     => '💬',
            'new_listing' => '🏠',
            'favorite'    => '❤️',
            'alert'       => '🔔',
            default       => '📢',
        };

        $ctaButton = '';
        if (!empty($data['listing_id'])) {
            $url = ($_ENV['APP_URL'] ?? 'https://prop-ty.netlify.app/') . '/properties?property=' . $data['listing_id'];
            $ctaButton = "<a href='{$url}' style='display:inline-block;background:#D97706;color:#fff;padding:12px 24px;border-radius:8px;font-weight:700;text-decoration:none;margin-top:16px'>View Property</a>";
        }

        $html = self::baseTemplate($title, "
            <p style='margin:0 0 16px'>Hi {$name},</p>
            <div style='background:#F9FAFB;border-radius:12px;padding:20px;margin:0 0 24px'>
                <p style='margin:0 0 8px;font-size:24px'>{$iconEmoji}</p>
                <h3 style='margin:0 0 8px;font-size:18px;color:#111827'>{$title}</h3>
                <p style='margin:0;color:#6B7280'>{$message}</p>
            </div>
            {$ctaButton}
        ");

        return self::send($to, $iconEmoji . ' ' . $title, $html);
    }

    // ── Base HTML email template ──────────────────────────────────────────────
    private static function baseTemplate(string $heading, string $body): string {
        return "<!DOCTYPE html>
        <html>
        <head><meta charset='UTF-8'><meta name='viewport' content='width=device-width,initial-scale=1'></head>
        <body style='margin:0;padding:0;background:#F3F4F6;font-family:-apple-system,BlinkMacSystemFont,\"Segoe UI\",sans-serif'>
            <table width='100%' cellpadding='0' cellspacing='0' style='background:#F3F4F6;padding:40px 16px'>
                <tr><td align='center'>
                    <table width='600' cellpadding='0' cellspacing='0' style='max-width:600px;width:100%'>

                        <!-- Header -->
                        <tr>
                            <td style='background:#D97706;border-radius:12px 12px 0 0;padding:24px 32px;text-align:center'>
                                <span style='font-size:28px;font-weight:900;color:#fff;letter-spacing:-1px'>
                                    HOM<span style='color:#FEF3C7'>i</span>
                                </span>
                            </td>
                        </tr>

                        <!-- Body -->
                        <tr>
                            <td style='background:#fff;padding:32px;border-radius:0 0 12px 12px;color:#374151;font-size:16px;line-height:1.6'>
                                {$body}
                                <hr style='border:none;border-top:1px solid #E5E7EB;margin:32px 0'>
                                <p style='margin:0;font-size:12px;color:#9CA3AF;text-align:center'>
                                    © " . date('Y') . " HOMi · Cameroon's Real Estate Platform<br>
                                    <a href='#' style='color:#9CA3AF'>Unsubscribe</a>
                                </p>
                            </td>
                        </tr>

                    </table>
                </td></tr>
            </table>
        </body>
        </html>";
    }
}