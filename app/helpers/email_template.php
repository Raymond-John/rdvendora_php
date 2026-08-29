<?php
/**
 * Shared professional HTML email layout for RD Vendora.
 */

if (!function_exists('rdv_email_base_url')) {
    function rdv_email_base_url() {
        return function_exists('rdv_app_base_url') ? rdv_app_base_url() : 'https://rdvendora.com';
    }
}

if (!function_exists('rdv_email_company_name')) {
    function rdv_email_company_name() {
        return 'RD Vendora';
    }
}

if (!function_exists('rdv_email_support_address')) {
    function rdv_email_support_address() {
        return 'support@rdvendora.com';
    }
}

if (!function_exists('rdv_email_logo_url')) {
    function rdv_email_logo_url() {
        if (function_exists('rdv_asset')) {
            $url = rdv_asset('assets/brand-logo.png');
            if (preg_match('#^https?://#i', (string) $url)) {
                return $url;
            }
        }
        return rtrim(rdv_email_base_url(), '/') . '/assets/brand-logo.png';
    }
}

if (!function_exists('rdv_email_logo_html')) {
    /**
     * @param array<string,mixed> $options
     */
    function rdv_email_logo_html(array $options = []) {
        $width = (int) ($options['width'] ?? 140);
        if ($width < 80) {
            $width = 80;
        }
        if ($width > 200) {
            $width = 200;
        }
        $showName = array_key_exists('show_name', $options) ? !empty($options['show_name']) : true;
        $centered = !empty($options['centered']);
        $logoUrl = htmlspecialchars(rdv_email_logo_url(), ENT_QUOTES, 'UTF-8');
        $company = htmlspecialchars(rdv_email_company_name(), ENT_QUOTES, 'UTF-8');

        $logo = '<img src="' . $logoUrl . '" alt="' . $company . '" width="' . $width . '" style="display:block;max-width:' . $width . 'px;height:auto;border:0;background:#ffffff;border-radius:8px;padding:4px 8px;">';

        if (!$showName) {
            return $logo;
        }

        $tableAlign = $centered ? ' align="center"' : '';

        return '
        <table border="0" cellpadding="0" cellspacing="0"' . $tableAlign . '>
            <tr>
                <td style="vertical-align:middle; padding-right:12px;">' . $logo . '</td>
                <td style="vertical-align:middle;">
                    <span style="font-size:22px; font-weight:700; color:#FFFFFF; letter-spacing:-0.3px; font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,Helvetica,Arial,sans-serif;">' . $company . '</span>
                </td>
            </tr>
        </table>';
    }
}

if (!function_exists('rdv_email_header_html')) {
    /**
     * @param string $badge Optional right-side badge text (e.g. Order #123)
     * @param array<string,mixed> $options centered, show_name, logo_width
     */
    function rdv_email_header_html($badge = '', array $options = []) {
        $centered = !empty($options['centered']);
        $showName = array_key_exists('show_name', $options) ? !empty($options['show_name']) : true;
        $logoWidth = (int) ($options['logo_width'] ?? ($centered ? 110 : 120));
        $safeBadge = htmlspecialchars((string) $badge, ENT_QUOTES, 'UTF-8');
        $logoHtml = rdv_email_logo_html([
            'width' => $logoWidth,
            'show_name' => $showName,
            'centered' => $centered,
        ]);

        if ($centered) {
            return '
            <table width="100%" border="0" cellpadding="0" cellspacing="0" style="background-color:#0A3D91; border-bottom:6px solid #D4AF37; border-radius:18px 18px 0 0;">
                <tr>
                    <td style="padding:22px 30px; text-align:center;">' . $logoHtml . '</td>
                </tr>
            </table>';
        }

        $badgeHtml = $safeBadge !== ''
            ? '<span style="font-size:14px; color:#D4AF37; font-weight:500; font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,Helvetica,Arial,sans-serif;">' . $safeBadge . '</span>'
            : '';

        return '
        <table width="100%" border="0" cellpadding="0" cellspacing="0" style="background-color:#0A3D91; border-bottom:6px solid #D4AF37; border-radius:18px 18px 0 0;">
            <tr>
                <td style="padding:22px 30px;">
                    <table width="100%" border="0" cellpadding="0" cellspacing="0">
                        <tr>
                            <td style="vertical-align:middle;">' . $logoHtml . '</td>
                            <td style="vertical-align:middle; text-align:right;">' . $badgeHtml . '</td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>';
    }
}

if (!function_exists('rdv_email_button_styles')) {
    function rdv_email_button_styles() {
        return [
            'primary' => [
                'bg' => '#1A56DB',
                'color' => '#FFFFFF',
                'shadow' => '0 4px 12px rgba(26,86,219,0.25)',
            ],
            'gold' => [
                'bg' => '#D4AF37',
                'color' => '#0A3D91',
                'shadow' => '0 4px 12px rgba(212,175,55,0.2)',
            ],
            'success' => [
                'bg' => '#16A34A',
                'color' => '#FFFFFF',
                'shadow' => '0 4px 12px rgba(22,163,74,0.25)',
            ],
            'outline' => [
                'bg' => '#FFFFFF',
                'color' => '#1A56DB',
                'shadow' => '0 2px 8px rgba(0,0,0,0.06)',
                'border' => '2px solid #1A56DB',
            ],
        ];
    }
}

if (!function_exists('rdv_email_button_html')) {
    /**
     * @param string $label
     * @param string $url
     * @param string $style primary|gold|success|outline
     */
    function rdv_email_button_html($label, $url, $style = 'primary') {
        $label = trim((string) $label);
        $url = trim((string) $url);
        if ($label === '' || $url === '') {
            return '';
        }

        $styles = rdv_email_button_styles();
        $cfg = $styles[$style] ?? $styles['primary'];
        $safeLabel = htmlspecialchars($label, ENT_QUOTES, 'UTF-8');
        $safeUrl = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
        $border = isset($cfg['border']) ? 'border:' . $cfg['border'] . ';' : '';

        return '
        <table align="center" border="0" cellpadding="0" cellspacing="0" style="display:inline-block; margin:0 6px 10px 6px;">
            <tr>
                <td style="background-color:' . $cfg['bg'] . '; border-radius:50px; padding:13px 30px; box-shadow:' . $cfg['shadow'] . '; ' . $border . '">
                    <a href="' . $safeUrl . '" style="color:' . $cfg['color'] . '; text-decoration:none; font-weight:600; font-size:15px; display:inline-block; font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,Helvetica,Arial,sans-serif;">' . $safeLabel . '</a>
                </td>
            </tr>
        </table>';
    }
}

if (!function_exists('rdv_email_buttons_row')) {
    /**
     * @param array<int, array{label:string,url:string,style?:string}> $buttons
     */
    function rdv_email_buttons_row(array $buttons) {
        $html = '';
        foreach ($buttons as $button) {
            if (!is_array($button)) {
                continue;
            }
            $html .= rdv_email_button_html(
                $button['label'] ?? '',
                $button['url'] ?? '',
                $button['style'] ?? 'primary'
            );
        }
        if ($html === '') {
            return '';
        }

        return '
        <table width="100%" border="0" cellpadding="0" cellspacing="0" style="margin:28px 0 8px 0;">
            <tr>
                <td style="text-align:center; padding:0 10px;">' . $html . '</td>
            </tr>
        </table>';
    }
}

if (!function_exists('rdv_email_footer_html')) {
    function rdv_email_footer_html($note = '') {
        $company = htmlspecialchars(rdv_email_company_name(), ENT_QUOTES, 'UTF-8');
        $website = htmlspecialchars(rdv_email_base_url(), ENT_QUOTES, 'UTF-8');
        $support = htmlspecialchars(rdv_email_support_address(), ENT_QUOTES, 'UTF-8');
        $year = date('Y');
        $noteHtml = $note !== ''
            ? '<br><span style="font-size:12px; color:#94A3B8;">' . htmlspecialchars($note, ENT_QUOTES, 'UTF-8') . '</span>'
            : '<br><span style="font-size:12px; color:#94A3B8;">This is an automated email from RD Vendora.</span>';

        return '
        <table width="100%" border="0" cellpadding="0" cellspacing="0" style="margin-top:32px; border-top:1px solid #E5E7EB; padding-top:20px;">
            <tr>
                <td style="text-align:center; font-size:13px; color:#94A3B8; line-height:1.6; font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,Helvetica,Arial,sans-serif;">
                    <span style="color:#1E293B; font-weight:600;">' . $company . '</span><br>
                    <a href="mailto:' . $support . '" style="color:#1A56DB; text-decoration:none;">' . $support . '</a>
                    &nbsp;|&nbsp;
                    <a href="' . $website . '" style="color:#1A56DB; text-decoration:none;">' . preg_replace('#^https?://#', '', $website) . '</a><br>
                    &copy; ' . $year . ' ' . $company . ' — All Rights Reserved.' . $noteHtml . '
                </td>
            </tr>
        </table>';
    }
}

if (!function_exists('rdv_email_wrap')) {
    /**
     * @param string $innerHtml Main body HTML (already safe or pre-escaped)
     * @param array<string,mixed> $options
     */
    function rdv_email_wrap($innerHtml, array $options = []) {
        $title = htmlspecialchars((string) ($options['title'] ?? ''), ENT_QUOTES, 'UTF-8');
        $badge = htmlspecialchars((string) ($options['badge'] ?? ''), ENT_QUOTES, 'UTF-8');
        $preheader = htmlspecialchars((string) ($options['preheader'] ?? ''), ENT_QUOTES, 'UTF-8');
        $footerNote = (string) ($options['footer_note'] ?? '');
        $buttons = $options['buttons'] ?? [];
        $buttonsHtml = is_array($buttons) ? rdv_email_buttons_row($buttons) : '';
        $headerHtml = rdv_email_header_html((string) ($options['badge'] ?? ''), [
            'centered' => !empty($options['header_centered']),
            'show_name' => array_key_exists('header_show_name', $options) ? !empty($options['header_show_name']) : true,
        ]);

        return '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . ($title !== '' ? $title : rdv_email_company_name()) . '</title>
</head>
<body style="margin:0; padding:0; background-color:#F5F7FB; font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,Helvetica,Arial,sans-serif;">
' . ($preheader !== '' ? '<div style="display:none;max-height:0;overflow:hidden;mso-hide:all;">' . $preheader . '</div>' : '') . '
<table align="center" border="0" cellpadding="0" cellspacing="0" width="100%" style="background-color:#F5F7FB; padding:40px 16px;">
    <tr>
        <td align="center">
            <table align="center" border="0" cellpadding="0" cellspacing="0" width="100%" style="max-width:600px; background-color:#FFFFFF; border-radius:18px; border:1px solid #E5E7EB; box-shadow:0 8px 30px rgba(0,0,0,0.04); overflow:hidden;">
                <tr>
                    <td style="padding:0;">' . $headerHtml . '</td>
                </tr>
                <tr>
                    <td style="padding:32px 30px 24px 30px; color:#1E293B; font-size:16px; line-height:1.7;">
                        ' . $innerHtml . '
                        ' . $buttonsHtml . '
                        ' . rdv_email_footer_html($footerNote) . '
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>';
    }
}
