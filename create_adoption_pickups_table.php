<?php
$host = "localhost";
$dbname = "adbsystemm";
$username = "root";
$password = "";

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $username,
        $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    $sql = <<<SQL
CREATE TABLE IF NOT EXISTS adoption_pickups (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    request_id INT UNSIGNED NOT NULL,
    resident_id INT UNSIGNED NOT NULL,
    pet_id INT UNSIGNED NOT NULL,
    pickup_date DATE NOT NULL,
    pickup_time TIME NOT NULL,
    coupon_code VARCHAR(50) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_request_id (request_id),
    INDEX idx_resident_id (resident_id),
    INDEX idx_pet_id (pet_id),
    UNIQUE KEY uq_coupon_code (coupon_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;

    $pdo->exec($sql);
    echo "Table 'adoption_pickups' created successfully or already exists.";
} catch (PDOException $e) {
    echo "Database Error: " . $e->getMessage();
}