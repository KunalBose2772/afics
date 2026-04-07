<?php
require_once __DIR__ . '/../config/db.php';

try {
    echo "Checking if 'slug' column exists in 'services' table...\n";
    $stmt = $pdo->query("SHOW COLUMNS FROM services LIKE 'slug'");
    if ($stmt->fetch()) {
        echo "Column 'slug' already exists.\n";
    } else {
        echo "Adding 'slug' column...\n";
        $pdo->exec("ALTER TABLE services ADD COLUMN slug VARCHAR(255) AFTER title");
        echo "Column added. Populating slugs...\n";

        $stmt = $pdo->query("SELECT id, title FROM services");
        $services = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $updateStmt = $pdo->prepare("UPDATE services SET slug = ? WHERE id = ?");
        $checkStmt = $pdo->prepare("SELECT id FROM services WHERE slug = ? AND id != ?");

        foreach ($services as $service) {
            $baseSlug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $service['title'])));
            $slug = trim($baseSlug, '-');
            
            // Uniqueness check
            $checkStmt->execute([$slug, $service['id']]);
            if ($checkStmt->fetch()) {
                $slug = $slug . '-' . $service['id'];
            }
            
            echo "Updating service ID {$service['id']} ('{$service['title']}') with slug '$slug'...\n";
            $updateStmt->execute([$slug, $service['id']]);
        }

        echo "Adding UNIQUE index...\n";
        $pdo->exec("ALTER TABLE services ADD UNIQUE INDEX idx_slug (slug)");
        echo "Done.\n";
    }
} catch (PDOException $e) {
    die("Error: " . $e->getMessage() . "\n");
}
?>
