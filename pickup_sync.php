<?php
/**
 * Mirror lost-pet claim pickups into adoption_pickups so admins see CLAIM-* codes in phpMyAdmin.
 */

function syncClaimToAdoptionPickups(PDO $pdo, int $notificationId, string $targetStatus = 'picked_up'): array
{
    $stmt = $pdo->prepare("
        SELECT id, user_id, rescued_pet_id, pickup_date, pickup_time, coupon_code, message, is_scheduled
        FROM notifications
        WHERE id = ?
    ");
    $stmt->execute([$notificationId]);
    $claim = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$claim) {
        return ['success' => false, 'message' => 'Notification not found.'];
    }

    if (empty($claim['rescued_pet_id'])) {
        return ['success' => false, 'message' => 'Notification is missing rescued_pet_id.'];
    }

    $coupon = trim((string) ($claim['coupon_code'] ?? ''));
    if ($coupon === '') {
        $coupon = 'CLAIM-' . strtoupper(substr(md5('notif-' . $notificationId . '-' . ($claim['user_id'] ?? 0)), 0, 10));
        $saveCoupon = $pdo->prepare("UPDATE notifications SET coupon_code = ? WHERE id = ?");
        $saveCoupon->execute([$coupon, $notificationId]);
    }

    $residentId = (int) $claim['user_id'];
    $petId = (int) $claim['rescued_pet_id'];
    $pickupDate = $claim['pickup_date'] ?: date('Y-m-d');
    $pickupTime = $claim['pickup_time'] ?: '10:00:00';

    $isPickedUp = $targetStatus === 'picked_up'
        || stripos((string) ($claim['message'] ?? ''), '[PICKED_UP]') !== false
        || (int) ($claim['is_scheduled'] ?? 1) === 0;

    $pickupStatus = $isPickedUp ? 'picked_up' : 'scheduled';
    $status = $isPickedUp ? 'completed' : 'scheduled';

    $requestId = 0;
    $reqLookup = $pdo->prepare("
        SELECT id FROM adoption_requests
        WHERE resident_id = ? AND pet_id = ?
        ORDER BY id DESC
        LIMIT 1
    ");
    $reqLookup->execute([$residentId, $petId]);
    $matchedRequest = $reqLookup->fetchColumn();
    if ($matchedRequest) {
        $requestId = (int) $matchedRequest;
    }

    $check = $pdo->prepare("SELECT id FROM adoption_pickups WHERE coupon_code = ? LIMIT 1");
    $check->execute([$coupon]);
    $existingId = $check->fetchColumn();

    if ($existingId) {
        $update = $pdo->prepare("
            UPDATE adoption_pickups
            SET request_id = CASE WHEN request_id = 0 AND ? > 0 THEN ? ELSE request_id END,
                resident_id = ?,
                pet_id = ?,
                pickup_date = ?,
                pickup_time = ?,
                pickup_status = ?,
                status = ?,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");
        $update->execute([
            $requestId, $requestId,
            $residentId, $petId, $pickupDate, $pickupTime,
            $pickupStatus, $status,
            $existingId,
        ]);
        return ['success' => true, 'adoption_pickup_id' => (int) $existingId, 'coupon_code' => $coupon];
    }

    $insert = $pdo->prepare("
        INSERT INTO adoption_pickups (
            request_id,
            resident_id,
            pet_id,
            pickup_date,
            pickup_time,
            coupon_code,
            status,
            pickup_status,
            created_at,
            updated_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
    ");
    $insert->execute([
        $requestId, $residentId, $petId, $pickupDate, $pickupTime,
        $coupon, $status, $pickupStatus,
    ]);

    return [
        'success' => true,
        'adoption_pickup_id' => (int) $pdo->lastInsertId(),
        'coupon_code' => $coupon,
    ];
}

function backfillPickedUpClaims(PDO $pdo): int
{
    $synced = 0;

    $stmt = $pdo->query("
        SELECT id
        FROM notifications
        WHERE coupon_code IS NOT NULL
          AND TRIM(coupon_code) <> ''
          AND rescued_pet_id IS NOT NULL
          AND rescued_pet_id > 0
          AND (
              message LIKE '%[PICKED_UP]%'
              OR (is_scheduled = 0 AND coupon_code LIKE 'CLAIM-%')
          )
    ");

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $result = syncClaimToAdoptionPickups($pdo, (int) $row['id'], 'picked_up');
        if (!empty($result['success'])) {
            $synced++;
        }
    }

    return $synced;
}

function backfillScheduledClaims(PDO $pdo): int
{
    $synced = 0;

    $stmt = $pdo->query("
        SELECT id
        FROM notifications
        WHERE is_scheduled = 1
          AND rescued_pet_id IS NOT NULL
          AND rescued_pet_id > 0
          AND (message IS NULL OR message NOT LIKE '%[PICKED_UP]%')
          AND coupon_code IS NOT NULL
          AND TRIM(coupon_code) <> ''
    ");

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $result = syncClaimToAdoptionPickups($pdo, (int) $row['id'], 'scheduled');
        if (!empty($result['success'])) {
            $synced++;
        }
    }

    return $synced;
}

function runClaimPickupSync(PDO $pdo): void
{
    try {
        backfillScheduledClaims($pdo);
        backfillPickedUpClaims($pdo);
    } catch (Throwable $e) {
        error_log('Claim pickup sync failed: ' . $e->getMessage());
    }
}

/** Hide pets already handed over (adoption + claim pickups). */
function availableRescuedPetsCondition(string $tableAlias = 'rp'): string
{
    $alias = preg_replace('/[^a-z_]/', '', $tableAlias) ?: 'rp';
    return "NOT EXISTS (
        SELECT 1 FROM adoption_pickups ap
        WHERE ap.pet_id = {$alias}.id AND ap.pickup_status = 'picked_up'
    )";
}
