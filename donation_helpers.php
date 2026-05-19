<?php
/**
 * Donation schema helpers — optional pet link for per-animal donations.
 */

function ensureDonationPetColumns(PDO $pdo): void
{
    static $done = false;
    if ($done) {
        return;
    }

    $columns = $pdo->query('SHOW COLUMNS FROM donations')->fetchAll(PDO::FETCH_COLUMN, 0);

    if (!in_array('pet_id', $columns, true)) {
        $pdo->exec('ALTER TABLE donations ADD COLUMN pet_id INT UNSIGNED NULL DEFAULT NULL AFTER user_id');
    }
    if (!in_array('pet_name', $columns, true)) {
        $pdo->exec('ALTER TABLE donations ADD COLUMN pet_name VARCHAR(120) NULL DEFAULT NULL AFTER pet_id');
    }

    $done = true;
}

function formatDonationTypeLabel(string $type): string
{
    $labels = [
        'dog_food' => 'Dog Food',
        'cat_food' => 'Cat Food',
        'vitamins' => 'Vitamins',
        'supplies' => 'Pet Supplies',
        'money' => 'Money',
    ];
    return $labels[$type] ?? ucfirst(str_replace('_', ' ', $type));
}
