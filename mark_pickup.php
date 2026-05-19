<?php
session_start();

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

require_once __DIR__ . '/pickup_sync.php';

$isAjax = isset($_GET['ajax']) && $_GET['ajax'] === '1';

if (!isset($_GET['id']) || $_GET['id'] === '') {
    if ($isAjax) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid or missing record ID.']);
        exit();
    }
    die("Error: Invalid or missing pickup record ID.");
}

$record_id = (int) $_GET['id'];
$type = isset($_GET['type']) ? strtolower(trim($_GET['type'])) : 'adoption';
if (!in_array($type, ['adoption', 'claim'], true)) {
    $type = 'adoption';
}

$host = "localhost";
$dbname = "adbsystemm";
$username = "root";
$password = "";

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    if ($type === 'claim') {
        $pdo->beginTransaction();

        $exists = $pdo->prepare("SELECT id FROM notifications WHERE id = ?");
        $exists->execute([$record_id]);
        if (!$exists->fetchColumn()) {
            $pdo->rollBack();
            if ($isAjax) {
                header('Content-Type: application/json; charset=utf-8');
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Claim notification not found.']);
                exit();
            }
            die("Claim notification not found.");
        }

        $stmt = $pdo->prepare("
            UPDATE notifications
            SET is_scheduled = 0,
                message = CASE
                    WHEN COALESCE(message, '') LIKE '%[PICKED_UP]%' THEN message
                    ELSE CONCAT(COALESCE(message, ''), ' [PICKED_UP]')
                END
            WHERE id = :id
        ");
        $stmt->execute([':id' => $record_id]);

        $sync = syncClaimToAdoptionPickups($pdo, $record_id, 'picked_up');
        if (empty($sync['success'])) {
            $pdo->rollBack();
            if ($isAjax) {
                header('Content-Type: application/json; charset=utf-8');
                http_response_code(500);
                echo json_encode([
                    'success' => false,
                    'message' => $sync['message'] ?? 'Could not save claim pickup to adoption_pickups.',
                ]);
                exit();
            }
            die($sync['message'] ?? 'Could not save claim pickup to adoption_pickups.');
        }

        $pdo->commit();

        if ($isAjax) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success' => true,
                'type' => 'claim',
                'adoption_pickup_id' => $sync['adoption_pickup_id'] ?? null,
                'coupon_code' => $sync['coupon_code'] ?? null,
            ]);
            exit();
        }

        header("Location: approved_pickups.php");
        exit();
    }

    $sql = "
        UPDATE adoption_pickups
        SET pickup_status = 'picked_up',
            status = 'completed',
            updated_at = CURRENT_TIMESTAMP
        WHERE id = :id
          AND pickup_status = 'scheduled'
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id' => $record_id]);
    $updated = $stmt->rowCount();

    if ($isAjax) {
        header('Content-Type: application/json; charset=utf-8');
        if ($updated > 0) {
            echo json_encode(['success' => true, 'type' => 'adoption']);
            exit();
        }
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'No scheduled adoption pickup found with that ID. It may already be marked picked up.',
        ]);
        exit();
    }

    header("Location: approved_pickups.php");
    exit();

} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    if ($isAjax) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        exit();
    }
    die("Database Error: Could not update pickup status. " . $e->getMessage());
}
