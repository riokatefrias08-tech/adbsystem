<?php
session_start();
if (!isset($_SESSION['role']) || strtolower($_SESSION['role']) !== 'resident') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'] ?? 0;

function normalizeImageUrl(string $rawPath): string {
    $path = trim($rawPath);
    if ($path === '') return '';
    $path = str_replace('\\', '/', $path);
    if (preg_match('/^(https?:)?\/\//i', $path) || str_starts_with($path, 'data:')) return $path;
    if (str_starts_with($path, '/')) return $path;
    $uploadsPos = stripos($path, 'uploads/');
    if ($uploadsPos !== false) return substr($path, $uploadsPos);
    return 'uploads/' . ltrim(basename($path), '/');
}

try {
    $pdo = new PDO("mysql:host=localhost;dbname=adbsystemm;charset=utf8mb4", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    require_once __DIR__ . '/pickup_sync.php';
    $availablePets = availableRescuedPetsCondition('rp');
    $query_rescued = "SELECT rp.* FROM rescued_pets rp WHERE {$availablePets} ORDER BY rp.id DESC";
    $rescued_pets = $pdo->query($query_rescued)->fetchAll(PDO::FETCH_ASSOC);

    $stmt_my_adoptions = $pdo->prepare("SELECT pet_id FROM adoption_requests WHERE resident_id = ? AND status = 'pending'");
    $stmt_my_adoptions->execute([$user_id]);
    $my_pending_adoption_pet_ids = array_map(fn($row) => (int)($row['pet_id'] ?? 0), $stmt_my_adoptions->fetchAll(PDO::FETCH_ASSOC));
} catch (Exception $e) {
    $rescued_pets = [];
    $my_pending_adoption_pet_ids = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Rescued Pets - PetConnect</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --bg-deep: #0a0a0b; --accent-gold: #c48a3d; --text-warm: #d8d2cb; --glass: rgba(255, 255, 255, 0.03); --glass-border: rgba(255, 255, 255, 0.08); --accent-gold-glow: rgba(196, 138, 61, 0.3); }
        body { background-color: var(--bg-deep); color: var(--text-warm); font-family: 'Inter', sans-serif; padding: 40px; }
        .card { background: var(--glass); padding: 30px; border-radius: 28px; border: 1px solid var(--glass-border); }
        .rescued-pets-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 25px; margin-top: 20px; }
        .profile-group { background: rgba(255,255,255,0.02); border: 1px solid var(--glass-border); padding: 20px; border-radius: 18px; }
        .rescued-pet-card { padding: 0; overflow: hidden; display: flex; flex-direction: column; }
        .rescued-pet-photo img { width: 100%; height: 180px; object-fit: cover; }
        .rescued-pet-body { padding: 20px; flex: 1; }
        .profile-field { margin-bottom: 15px; }
        .profile-field label { display: block; font-size: 0.75rem; margin-bottom: 6px; color: var(--accent-gold); text-transform: uppercase; }
        .profile-field input { width: 100%; padding: 12px 14px; border-radius: 12px; border: 1px solid var(--glass-border); background: rgba(255,255,255,0.04); color: #fff; }
        .pet-action-row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-top: 18px; }
        .btn-pet { display: inline-flex; align-items: center; justify-content: center; gap: 8px; padding: 12px 14px; border-radius: 12px; font-weight: 700; font-size: 0.75rem; text-transform: uppercase; text-decoration: none; transition: 0.25s; border:none; cursor:pointer;}
        .btn-adopt { background: var(--accent-gold); color: #000; }
        .btn-donate { border: 1px solid var(--accent-gold); color: var(--accent-gold); background: transparent; }
        .modal-backdrop { position: fixed; inset: 0; background: rgba(0,0,0,0.75); display: none; align-items: center; justify-content: center; z-index: 2500; padding: 20px; }
        .modal-backdrop.active { display: flex; }
        .modal-card { width: min(780px, 100%); max-height: 90vh; overflow-y: auto; background: #131316; border: 1px solid var(--glass-border); border-radius: 20px; padding: 24px; }
        .report-form input, .report-form select, .report-form textarea { width: 100%; padding: 14px 18px; margin-bottom: 25px; background: rgba(255, 255, 255, 0.04); border: 1px solid var(--glass-border); color: #fff; border-radius: 14px; box-sizing: border-box; }
        .adoption-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
        .adoption-grid .full { grid-column: 1 / -1; }
        .checkmark { height: 18px; width: 18px; background: rgba(255, 255, 255, 0.02); border: 1px solid rgba(255, 255, 255, 0.15); border-radius: 5px; display: inline-block; position: relative; }
        .checkbox-container input { position: absolute; opacity: 0; }
        .checkbox-container input:checked ~ .checkmark { background-color: #c48a3d; border-color: #c48a3d; }
    </style>
</head>
<body>
    <div class="card">
        <h2>🐶 Recently Rescued Animals</h2>
        <div class="rescued-pets-grid">
            <?php if (empty($rescued_pets)): ?>
                <p>No rescued animals to display.</p>
            <?php else: ?>
                <?php foreach($rescued_pets as $pet): ?>
                    <?php
                        $petId = (int)($pet['id'] ?? 0);
                        $petName = $pet['name'] ?? 'Unknown';
                        $petBreed = $pet['breed'] ?? ($pet['species'] ?? 'Unknown');
                        $petImageUrl = normalizeImageUrl((string)($pet['image_path'] ?? ($pet['image'] ?? '')));
                        $alreadyRequested = in_array($petId, $my_pending_adoption_pet_ids, true);
                    ?>
                    <div class="profile-group rescued-pet-card">
                        <div class="rescued-pet-photo">
                            <img src="<?php echo htmlspecialchars($petImageUrl); ?>" onerror="this.src='https://via.placeholder.com/320x180?text=No+Image'">
                        </div>
                        <div class="rescued-pet-body">
                            <h3>Pet profile</h3>
                            <div class="profile-field"><label>Name</label><input type="text" value="<?php echo htmlspecialchars($petName); ?>" readonly></div>
                            <div class="profile-field"><label>Breed</label><input type="text" value="<?php echo htmlspecialchars($petBreed); ?>" readonly></div>
                            <div class="pet-action-row">
                                <?php if ($alreadyRequested): ?>
                                    <span class="btn-pet btn-adopt" style="opacity:0.55; cursor:not-allowed;">Requested</span>
                                <?php else: ?>
                                    <button class="btn-pet btn-adopt" data-pet-id="<?php echo $petId; ?>" data-pet-name="<?php echo htmlspecialchars($petName, ENT_QUOTES); ?>" onclick="openAdoptionModal(this)">Adopt</button>
                                <?php endif; ?>
                                <a class="btn-pet btn-donate" href="resident_dashboard.php#rescued">Donate</a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <div id="adoptionModal" class="modal-backdrop" onclick="closeAdoptionModal(event)">
        <div class="modal-card">
            <h3 id="modalPetName" style="color:var(--accent-gold)">Adopt Pet</h3>
            <form class="report-form" action="submit_adoption_request.php" method="POST">
                <input type="hidden" name="pet_id" id="modalPetId">
                <div class="adoption-grid">
                    <div><label>Full Name</label><input type="text" name="applicant_name" required></div>
                    <div><label>Phone Number</label><input type="text" name="phone_number" required></div>
                    <div class="full"><label>Address</label><textarea name="address" rows="2" required></textarea></div>
                    <div><label>Household Members</label><input type="number" name="household_members" required></div>
                    <div><label>Other Pets?</label><select name="has_other_pets"><option value="yes">Yes</option><option value="no">No</option></select></div>
                    <div class="full"><label>Reason for Adoption</label><textarea name="reason_for_adoption" rows="3" required></textarea></div>
                    <div class="full">
                        <label class="checkbox-container">
                            <input type="checkbox" name="agree_home_visit" value="yes" required>
                            <span class="checkmark"></span> Agree to post-adoption checkups.
                        </label>
                    </div>
                </div>
                <button type="submit" class="btn-pet btn-adopt" style="width:100%; margin-top:20px;">Submit Application</button>
            </form>
        </div>
    </div>

    <script>
        function openAdoptionModal(btn) {
            document.getElementById('modalPetId').value = btn.getAttribute('data-pet-id');
            document.getElementById('modalPetName').innerText = "Adopt " + btn.getAttribute('data-pet-name');
            document.getElementById('adoptionModal').classList.add('active');
        }
        function closeAdoptionModal(e) { if(e.target.id === 'adoptionModal') document.getElementById('adoptionModal').classList.remove('active'); }
    </script>
</body>
</html>