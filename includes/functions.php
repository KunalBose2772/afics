<?php
// Fetch all settings as an associative array
function get_settings($pdo)
{
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
    return $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
}

// Fetch all services
function get_services($pdo)
{
    $stmt = $pdo->query("SELECT * FROM services ORDER BY display_order ASC");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Fetch all team members
function get_team_members($pdo)
{
    $stmt = $pdo->query("SELECT * FROM team_members ORDER BY sort_order ASC");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Fetch all FAQs
function get_faqs($pdo)
{
    $stmt = $pdo->query("SELECT * FROM faqs WHERE is_active = 1 ORDER BY sort_order ASC");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Check if a hex color is light or dark
function is_light_color($hex)
{
    $hex = str_replace('#', '', $hex);
    // Handle rgba(r,g,b,a) format or pure rgb if passed by mistake? 
    // Usually settings save HEX but Pickr can save rgba.
    // If RGBA, we need to handle it.

    if (strpos($hex, 'rgba') !== false || strpos($hex, 'rgb') !== false) {
        // Extract RGB values
        $matches = [];
        preg_match('/rgba?\((\d+),\s*(\d+),\s*(\d+)/', $hex, $matches);
        if (count($matches) >= 4) {
            $r = $matches[1];
            $g = $matches[2];
            $b = $matches[3];
            $luminance = (0.299 * $r + 0.587 * $g + 0.114 * $b) / 255;
            return $luminance > 0.5;
        }
        return false; // Fail safe
    }

    if (strlen($hex) == 3) {
        $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
    }

    // Check if valid hex
    if (!preg_match('/^[a-f0-9]{6}$/i', $hex))
        return false;

    $r = hexdec(substr($hex, 0, 2));
    $g = hexdec(substr($hex, 2, 2));
    $b = hexdec(substr($hex, 4, 2));

    // Calculate luminance
    $luminance = (0.299 * $r + 0.587 * $g + 0.114 * $b) / 255;

    return $luminance > 0.5;
}

// Fetch all data for About Page
function get_about_page_data($pdo)
{
    return [
        'settings' => get_settings($pdo),
        'services' => get_services($pdo),
        'team' => get_team_members($pdo),
        'faqs' => get_faqs($pdo)
    ];
}

// Render dynamic CSS variables based on settings
// Render dynamic CSS variables based on settings
function render_dynamic_css($settings)
{
    echo "<style>";
    // Typography Mapping
    $font_map = [
        'Times New Roman' => "'Times New Roman', Times, serif",
        'Roboto' => "'Roboto', sans-serif",
        'Nunito' => "'Nunito', sans-serif",
        'Manrope' => "'Manrope', sans-serif",
        'Cormorant Garamond' => "'Cormorant Garamond', serif",
        'Poppins' => "'Poppins', sans-serif",
        'Inter' => "'Inter', sans-serif", // Default fallback
        'Bebas Neue' => "'Bebas Neue', cursive" // Default fallback
    ];

    // Google Fonts Mapping (family=Name:wght@...)
    $google_fonts_map = [
        'Roboto' => 'Roboto:wght@300;400;500;700',
        'Nunito' => 'Nunito:wght@300;400;600;700',
        'Manrope' => 'Manrope:wght@300;400;600;700',
        'Cormorant Garamond' => 'Cormorant+Garamond:wght@300;400;600;700',
        'Poppins' => 'Poppins:wght@300;400;500;600;700',
        'Inter' => 'Inter:wght@300;400;600;800',
        'Bebas Neue' => 'Bebas+Neue'
    ];

    $body_font = $settings['font_family_body'] ?? 'Inter';
    $heading_font = $settings['font_family_heading'] ?? 'Bebas Neue';

    // Build Import URL
    $imports = [];
    foreach ([$body_font, $heading_font] as $f) {
        if (isset($google_fonts_map[$f])) {
            $imports[] = 'family=' . $google_fonts_map[$f];
        }
    }

    if (!empty($imports)) {
        // Unique fonts
        $imports = array_unique($imports);
        $url = "https://fonts.googleapis.com/css2?" . implode('&', $imports) . "&display=swap";
        echo "@import url('$url');";
    }

    echo ":root {";

    // Helper to get value or default
    $v = function ($key, $default) use ($settings) {
        return !empty($settings[$key]) ? $settings[$key] : $default;
    };

    // Colors
    echo "--primary: " . htmlspecialchars($v('primary_color', '#0d6efd')) . ";";
    echo "--secondary: " . htmlspecialchars($v('secondary_color', '#6c757d')) . ";";
    echo "--accent: " . htmlspecialchars($v('accent_color', '#FFD700')) . ";";
    echo "--bg-color: " . htmlspecialchars($v('bg_color', '#ffffff')) . ";";
    echo "--card-bg: " . htmlspecialchars($v('card_bg_color', '#f8f9fa')) . ";";
    echo "--text-color: " . htmlspecialchars($v('text_color', '#212529')) . ";";
    echo "--link-color: " . htmlspecialchars($v('link_color', $v('primary_color', '#0d6efd'))) . ";";
    echo "--link-hover: " . htmlspecialchars($v('link_hover_color', $v('secondary_color', '#6c757d'))) . ";";
    echo "--header-bg: " . htmlspecialchars($v('header_bg_color', '#F5F5F5')) . ";";
    echo "--footer-bg: " . htmlspecialchars($v('footer_bg_color', '#1A1A1A')) . ";";
    echo "--footer-text: " . htmlspecialchars($v('footer_text_color', '#CCCCCC')) . ";";
    echo "--footer-text: " . htmlspecialchars($v('footer_text_color', '#CCCCCC')) . ";";
    echo "--signature-gradient: " . ($settings['signature_gradient'] ?? 'linear-gradient(45deg, var(--primary), var(--secondary))') . ";";

    // Footer CTA
    echo "--footer-cta-bg: " . htmlspecialchars($v('footer_cta_bg_color', '#6c757d')) . ";";
    echo "--footer-cta-text: " . htmlspecialchars($v('footer_cta_text_color', '#ffffff')) . ";";
    echo "--footer-cta-btn-bg: " . htmlspecialchars($v('footer_cta_btn_bg_color', '#E66952')) . ";";
    echo "--footer-cta-btn-text: " . htmlspecialchars($v('footer_cta_btn_text_color', '#ffffff')) . ";";

    // Footer Colors
    echo "--footer-heading: " . htmlspecialchars($v('footer_heading_color', '#ffffff')) . ";";
    echo "--footer-link: " . htmlspecialchars($v('footer_link_color', 'rgba(255,255,255,0.5)')) . ";";
    echo "--footer-link-hover: " . htmlspecialchars($v('footer_link_hover_color', '#ffffff')) . ";";
    echo "--footer-bottom-bg: " . htmlspecialchars($v('footer_bottom_bg_color', '#e69c3d')) . ";";
    echo "--footer-bottom-text: " . htmlspecialchars($v('footer_bottom_text_color', '#333333')) . ";";

    // Mega Menu
    echo "--megamenu-bg: " . htmlspecialchars($v('megamenu_bg_color', '#ffffff')) . ";";
    echo "--megamenu-heading: " . htmlspecialchars($v('megamenu_heading_color', '#212529')) . ";";
    echo "--megamenu-text: " . htmlspecialchars($v('megamenu_text_color', '#6c757d')) . ";";
    echo "--megamenu-link: " . htmlspecialchars($v('megamenu_link_color', '#0d6efd')) . ";";
    echo "--megamenu-link-hover: " . htmlspecialchars($v('megamenu_link_hover_color', '#0a58ca')) . ";";

    // Mega Menu Buttons
    echo "--megamenu-btn-bg: " . htmlspecialchars($v('megamenu_btn_bg_color', '#0d6efd')) . ";";
    echo "--megamenu-btn-text: " . htmlspecialchars($v('megamenu_btn_text_color', '#ffffff')) . ";";
    echo "--megamenu-btn-border: " . htmlspecialchars($v('megamenu_btn_border_color', '#0d6efd')) . ";";
    echo "--megamenu-btn-hover-bg: " . htmlspecialchars($v('megamenu_btn_hover_bg_color', '#0b5ed7')) . ";";
    echo "--megamenu-btn-hover-text: " . htmlspecialchars($v('megamenu_btn_hover_text_color', '#ffffff')) . ";";

    // Header & Navbar
    echo "--header-top-bg: " . htmlspecialchars($v('header_top_bg_color', '#ffffff')) . ";";
    echo "--header-navbar-bg: " . htmlspecialchars($v('header_navbar_bg_color', '#f8f9fa')) . ";";
    echo "--header-navbar-text: " . htmlspecialchars($v('header_navbar_text_color', '#212529')) . ";";

    // Typography Output
    echo "--font-main: " . ($font_map[$body_font] ?? $body_font) . ";";
    echo "--font-heading: " . ($font_map[$heading_font] ?? $heading_font) . ";";
    echo "--font-size-base: " . ($settings['font_size_base'] ?? '16px') . ";";
    echo "--font-size-h1: " . ($settings['font_size_h1'] ?? '4rem') . ";";
    echo "--font-size-h2: " . ($settings['font_size_h2'] ?? '3rem') . ";";
    echo "--font-size-h3: " . ($settings['font_size_h3'] ?? '2rem') . ";";
    echo "--line-height-body: " . ($settings['line_height_body'] ?? '1.6') . ";";
    echo "--line-height-heading: " . ($settings['line_height_heading'] ?? '1.2') . ";";

    // Layout & Elements
    echo "--btn-radius: " . ($settings['button_radius'] ?? '0px') . ";";
    echo "--card-radius: " . ($settings['card_radius'] ?? '0px') . ";";
    echo "--section-padding: " . ($settings['section_padding'] ?? '20px') . ";";
    echo "--container-width: " . ($settings['container_width'] ?? '1200px') . ";";

    echo "}";

    // Apply Global Styles
    echo "body { font-family: var(--font-main); font-size: var(--font-size-base); line-height: var(--line-height-body); color: var(--text-color); background-color: var(--bg-color); }";
    echo "h1, h2, h3, h4, h5, h6 { font-family: var(--font-heading); line-height: var(--line-height-heading); }";
    echo "h1 { font-size: var(--font-size-h1); }";
    echo "h2 { font-size: var(--font-size-h2); }";
    echo "h3 { font-size: var(--font-size-h3); }";
    echo "a { color: var(--link-color); }";
    echo "a:hover { color: var(--link-hover); }";
    echo ".btn-custom { border-radius: var(--btn-radius); }";
    echo ".card { border-radius: var(--card-radius); background-color: var(--card-bg); }";
    echo ".container { max-width: var(--container-width); }";
    echo "section { padding: 20px 0; }";

    echo "</style>";
}

// Queue email for background processing
// Queue email and Attempt Immediate Send via PHPMailer
function queue_email($pdo, $to, $subject, $body, $from_user_id = null, $attachments = [])
{
    // Fetch Settings
    global $pdo; // ensure pdo access if not passed properly in some contexts
    if (!isset($pdo)) {
         // Should not happen if passed correctly, but fallback safety
         return false; 
    }

    // Insert into Queue first (Log)
    try {
        $files_json = !empty($attachments) ? json_encode($attachments) : null;
        $stmt = $pdo->prepare("INSERT INTO email_queue (to_email, subject, body, user_id, attachments, status, created_at) VALUES (?, ?, ?, ?, ?, 'pending', NOW())");
        $stmt->execute([$to, $subject, $body, $from_user_id, $files_json]);
        $queue_id = $pdo->lastInsertId();
    } catch (PDOException $e) {
        error_log("DB Queue Error: " . $e->getMessage());
        return false;
    }

    // Fetch SMTP Settings
    try {
        $s_stmt = $pdo->query("SELECT setting_key, setting_value FROM settings WHERE setting_key LIKE 'smtp_%'");
        $settings = $s_stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        
        $host = $settings['smtp_host'] ?? 'smtp.hostinger.com';
        $user = $settings['smtp_username'] ?? ($settings['smtp_user'] ?? '');
        $pass = $settings['smtp_password'] ?? ($settings['smtp_pass'] ?? '');
        $port = intval($settings['smtp_port'] ?? 465); 
    } catch (Exception $e) {
        return true; // Queued but settings failed?
    }

    // Send via PHPMailer
    require_once __DIR__ . '/../lib/phpmailer/src/Exception.php';
    require_once __DIR__ . '/../lib/phpmailer/src/PHPMailer.php';
    require_once __DIR__ . '/../lib/phpmailer/src/SMTP.php';

    $mail = new PHPMailer\PHPMailer\PHPMailer(true);

    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = $host;
        $mail->SMTPAuth   = true;
        $mail->Username   = $user;
        $mail->Password   = $pass;
        // Auto-detect encryption based on port
        if ($port == 465) {
            $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
        } else {
            $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        }
        $mail->Port       = $port;

        // Recipients
        $mail->setFrom($user, 'Documantraa OIMS');
        $mail->addAddress($to);

        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = nl2br($body);
        $mail->AltBody = strip_tags($body);

        $mail->send();
        
        // Update Queue Status
        $upd = $pdo->prepare("UPDATE email_queue SET status = 'sent', sent_at = NOW() WHERE id = ?");
        $upd->execute([$queue_id]);
        
        return true;

    } catch (Exception $e) {
        // Update Queue to failed
        $upd = $pdo->prepare("UPDATE email_queue SET status = 'failed', error_message = ? WHERE id = ?");
        $upd->execute([substr($mail->ErrorInfo, 0, 255), $queue_id]);
        return false;
    }
}

// Fetch and hydrate email template
function get_email_template($pdo, $slug, $data = [])
{
    $stmt = $pdo->prepare("SELECT subject, body FROM email_templates WHERE slug = ?");
    $stmt->execute([$slug]);
    $template = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$template) {
        return null;
    }

    // Replace placeholders
    foreach ($data as $key => $value) {
        $placeholder = '{' . $key . '}';
        $template['subject'] = str_replace($placeholder, $value, $template['subject']);
        $template['body'] = str_replace($placeholder, $value, $template['body']);
    }

    return $template;
}

// Render Favicon Links
function render_favicon($relative_depth = 0)
{
    // Generate path prefix based on depth (0 = root, 1 = ../)
    $prefix = $relative_depth > 0 ? str_repeat('../', $relative_depth) : '';
    $path = $prefix . 'assets/favicon/';

    echo '<link rel="apple-touch-icon" sizes="180x180" href="' . $path . 'apple-touch-icon.png">';
    echo '<link rel="icon" type="image/png" sizes="32x32" href="' . $path . 'favicon-32x32.png">';
    echo '<link rel="icon" type="image/png" sizes="16x16" href="' . $path . 'favicon-16x16.png">';
    echo '<link rel="manifest" href="' . $path . 'site.webmanifest">';
}

// Helper function for status dropdowns
if (!function_exists('is_disabled')) {
    function is_disabled($current, $target, $is_admin)
    {
        if ($is_admin)
            return '';
        if ($current == $target)
            return '';
        if ($current == 'In-Progress' && $target == 'Pending')
            return 'disabled';
        if ($current == 'Hold' && $target == 'Pending')
            return 'disabled';
        if ($current == 'Completed')
            return 'disabled';
        return '';
    }
}

// WhatsApp Notification Logic (Using CallMeBot Free API)
function send_whatsapp_notification($to_mobile, $message) {
    if (empty($to_mobile) || empty($message)) return false;

    // Remove non-numeric chars
    $to_mobile = preg_replace('/[^0-9]/', '', $to_mobile);
    if (strlen($to_mobile) == 10) $to_mobile = '91' . $to_mobile; // Default to India prefix
    
    // Fetch Settings
    global $pdo; 
    if (!isset($pdo)) return false;

    $stmt_s = $pdo->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('whatsapp_apikey', 'whatsapp_phone')");
    $settings = $stmt_s->fetchAll(PDO::FETCH_KEY_PAIR);
    $whatsapp_phone = $settings['whatsapp_phone'] ?? '917558834483';
    $apikey = $settings['whatsapp_apikey'] ?? ''; 

    if (empty($apikey)) return false;

    // CallMeBot Free API
    $encoded_msg = urlencode("To: $to_mobile\nMsg: $message");
    $url = "https://api.callmebot.com/whatsapp.php?phone=$whatsapp_phone&text=$encoded_msg&apikey=$apikey";
    @file_get_contents($url);
    return true;
}

// Calculate Automatic Fine based on TAT (5 day grace, 5% per day after)
function calculate_project_fine($project) {
    if (empty($project['allocation_date'])) return 0;
    
    $allocation = new DateTime($project['allocation_date']);
    $now = new DateTime();
    $interval = $allocation->diff($now);
    $diff = $interval->days;
    
    // Grace period: 5 days. Day 6 starts the fine.
    if ($diff < 5) return 0;
    
    $days_over = $diff - 4; // At 5 days (start of 6th), it's 1 day over grace.
    if ($days_over <= 0) return 0;

    $fine_percent = $days_over * 5; // 5% per day
    
    $total_price = ($project['price_hospital'] ?? 0) + ($project['price_patient'] ?? 0) + ($project['price_other'] ?? 0);
    
    $fine_amount = ($total_price * $fine_percent) / 100;
    return $fine_amount;
}
?>