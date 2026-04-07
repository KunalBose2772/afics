<?php
// Manual Local Connection for CLI
$host = 'localhost';
$db = 'documantra';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    die("Database Error: " . $e->getMessage());
}

$domain = "https://documantraa.in"; 
$sitemap_file = 'sitemap.xml';

// Fetch all services - Handle potential errors if columns don't exist
try {
    $stmt = $pdo->query("SELECT slug FROM services");
    $services = $stmt->fetchAll();
} catch (Exception $e) {
    echo "Warning: Could not fetch services. Sitemap will be partial.\n";
    $services = [];
}

// Start XML
$xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
$xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

// Static Pages
$pages = [
    '' => 'daily',
    'about' => 'monthly',
    'services' => 'weekly',
    'index#contact' => 'monthly',
    'privacy-policy' => 'yearly',
    'terms-of-service' => 'yearly'
];

foreach ($pages as $slug => $freq) {
    $loc = $domain . ($slug ? '/' . $slug : '/');
    $date = date('Y-m-d');
    
    $xml .= "    <url>\n";
    $xml .= "        <loc>$loc</loc>\n";
    $xml .= "        <lastmod>$date</lastmod>\n";
    $xml .= "        <changefreq>$freq</changefreq>\n";
    $xml .= "        <priority>" . ($slug == '' ? '1.0' : '0.8') . "</priority>\n";
    $xml .= "    </url>\n";
}

// Dynamic Services
foreach ($services as $service) {
    $loc = $domain . '/service/' . $service['slug'];
    $date = date('Y-m-d'); // Default to today if updated_at is missing
    
    $xml .= "    <url>\n";
    $xml .= "        <loc>$loc</loc>\n";
    $xml .= "        <lastmod>$date</lastmod>\n";
    $xml .= "        <changefreq>weekly</changefreq>\n";
    $xml .= "        <priority>0.9</priority>\n";
    $xml .= "    </url>\n";
}

$xml .= '</urlset>';

// Write to file
if (file_put_contents($sitemap_file, $xml)) {
    echo "Sitemap generated successfully at $sitemap_file";
} else {
    echo "Failed to write sitemap.";
}
?>
