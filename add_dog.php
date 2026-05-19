<?php
$host = 'localhost';
$dbname = 'adbsystemm';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . htmlspecialchars($e->getMessage()));
}

// --- HELPER FUNCTIONS ---
function saveUploadedDogImage(?array $file): array {
    if (!isset($file) || !isset($file['error'])) return ['ok' => false, 'message' => 'No file uploaded.'];
    if ($file['error'] !== UPLOAD_ERR_OK) return ['ok' => false, 'message' => 'Upload error.'];
    $uploadDir = 'uploads/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
    $fileExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)) ?: 'jpg';
    $fileName = uniqid('dog_', true) . '.' . $fileExt;
    $uploadPath = $uploadDir . $fileName;
    if (!move_uploaded_file($file['tmp_name'], $uploadPath)) return ['ok' => false, 'message' => 'Failed to move file.'];
    return ['ok' => true, 'path' => $uploadPath];
}

function normalizeImageUrl(string $rawPath): string {
    $path = trim($rawPath);
    if ($path === '') return '';
    $path = str_replace('\\', '/', $path);
    if (preg_match('/^(https?:)?\/\//i', $path) || str_starts_with($path, 'data:')) return $path;
    $uploadsPos = stripos($path, 'uploads/');
    if ($uploadsPos !== false) return substr($path, $uploadsPos);
    return 'uploads/' . ltrim(basename($path), '/');
}

// --- POST ACTIONS HANDLER ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    $action = $_POST['action'];
    
    try {
        if ($action === 'delete') {
            $petId = (int)($_POST['petId'] ?? 0);
            $stmt = $pdo->prepare("SELECT image_path FROM rescued_pets WHERE id = ?");
            $stmt->execute([$petId]);
            $pet = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($pet && $pet['image_path'] && file_exists($pet['image_path'])) {
                unlink($pet['image_path']);
            }
            $pdo->prepare("DELETE FROM rescued_pets WHERE id = ?")->execute([$petId]);
            echo json_encode(['success' => true]);
            exit;
        }

        // We map the user-facing "dogName" input value directly to your $petType variable
        $petType = trim($_POST['dogName'] ?? ''); 
        $breed = trim($_POST['dogBreed'] ?? '');
        $healthStatus = trim($_POST['healthStatus'] ?? 'Unknown');
        if ($petType === '' || $breed === '') {
            echo json_encode(['success' => false, 'message' => 'Pet Type and breed required.']); exit;
        }

        if ($action === 'update') {
            $petId = (int)($_POST['petId'] ?? 0);
            $stmt = $pdo->prepare("SELECT * FROM rescued_pets WHERE id = :id");
            $stmt->execute([':id' => $petId]);
            $existingPet = $stmt->fetch(PDO::FETCH_ASSOC);
            $imagePath = $existingPet['image_path'] ?? '';
            
            if (isset($_FILES['dogFile']) && $_FILES['dogFile']['error'] === UPLOAD_ERR_OK) {
                $res = saveUploadedDogImage($_FILES['dogFile']);
                if ($res['ok']) {
                    if ($existingPet['image_path'] && file_exists($existingPet['image_path'])) unlink($existingPet['image_path']);
                    $imagePath = $res['path'];
                }
            }
            // Updated to SET pet_type instead of name
            $pdo->prepare("UPDATE rescued_pets 
                SET pet_type=:pt, breed=:b, health_status=:h, image_path=:i 
                WHERE id=:id")
            ->execute([
                ':pt'=>$petType,
                ':b'=>$breed,
                ':h'=>$healthStatus,
                ':i'=>$imagePath,
                ':id'=>$petId
            ]);
            echo json_encode(['success'=>true]);
            exit;
        }

        $res = saveUploadedDogImage($_FILES['dogFile'] ?? null);

        // Uses your accurate table layout details
        $pdo->prepare("INSERT INTO rescued_pets 
        (pet_type, breed, image_path, status, health_status, source_type, created_at) 
        VALUES (?, ?, ?, 'available', ?, 'admin', NOW())")
        ->execute([
            $petType,
            $breed,
            $res['path'] ?? '',
            $healthStatus
        ]);

        echo json_encode(['success'=>true]);
        exit;

    } catch (Exception $e) { 
        echo json_encode(['success'=>false, 'message'=>$e->getMessage()]); 
        exit;
    }
}

// --- SEARCH & FILTER LOGIC ---
$search = $_GET['search'] ?? '';
$date_filter = $_GET['date_filter'] ?? '';

require_once __DIR__ . '/pickup_sync.php';
$availablePets = availableRescuedPetsCondition('rescued_pets');

$query = "SELECT * FROM rescued_pets WHERE {$availablePets}";
$params = [];

if ($search !== '') {
    // Search scans pet_type and breed instead of name
    $query .= " AND (pet_type LIKE :search OR breed LIKE :search)";
    $params[':search'] = "%$search%";
}

if ($date_filter !== '') {
    $query .= " AND DATE(created_at) = :date";
    $params[':date'] = $date_filter;
}

$query .= " ORDER BY id DESC";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$rescuedPets = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Rescue | PetConnect Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-deep: #0a0a0b; 
            --accent-gold: #c48a3d; 
            --text-warm: #d8d2cb; 
            --glass: rgba(255, 255, 255, 0.03);
            --glass-border: rgba(255, 255, 255, 0.08);
            --sidebar-width: 280px;
            --danger: #ff6b6b;
        }

        body {
            background-color: var(--bg-deep);
            background-image: radial-gradient(circle at 50% 50%, #1a1a1c 0%, #0a0a0b 100%);
            color: var(--text-warm);
            font-family: 'Inter', sans-serif;
            margin: 0;
            display: flex;
            min-height: 100vh;
        }

        .sidebar {
            width: var(--sidebar-width);
            background: rgba(15, 15, 17, 0.9);
            border-right: 1px solid var(--glass-border);
            padding: 40px 20px;
            display: flex;
            flex-direction: column;
            backdrop-filter: blur(20px);
            position: fixed;
            height: 100vh;
            box-sizing: border-box;
            z-index: 100;
        }

        .nav-links { list-style: none; padding: 0; flex-grow: 1; }
        .nav-links a { 
            text-decoration: none; color: #888; padding: 14px 20px; 
            display: flex; align-items: center; gap: 15px; border-radius: 12px; 
            transition: 0.3s;
        }
        .nav-links a:hover, .nav-links a.active { 
            background: rgba(196, 138, 61, 0.1);
            color: var(--accent-gold);
            border-left: 4px solid var(--accent-gold);
        }

        .main-content {
            flex: 1;
            margin-left: var(--sidebar-width);
            padding: 50px;
            width: calc(100% - var(--sidebar-width));
        }

        h1 { color: var(--accent-gold); margin-bottom: 30px; font-weight: 600; }

        .filter-container {
            display: flex;
            gap: 15px;
            margin-bottom: 30px;
            align-items: center;
        }

        .search-input, .date-input {
            background: var(--glass);
            border: 1px solid var(--glass-border);
            color: white;
            padding: 10px 15px;
            border-radius: 10px;
            outline: none;
        }

        .search-input { flex-grow: 1; }

        .dog-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
            gap: 25px;
        }

        .card {
            background: var(--glass);
            border: 1px solid var(--glass-border);
            border-radius: 20px;
            padding: 20px;
            text-align: center;
            transition: 0.3s;
            backdrop-filter: blur(10px);
            position: relative;
        }

        .card:hover { border-color: var(--accent-gold); transform: translateY(-5px); }

        .dog-img {
            width: 100%;
            height: 180px;
            object-fit: cover;
            border-radius: 15px;
            margin-bottom: 15px;
            border: 1px solid var(--glass-border);
        }

        .delete-btn {
            position: absolute;
            top: 10px;
            right: 10px;
            background: rgba(255, 107, 107, 0.15);
            color: var(--danger);
            border: 1px solid var(--danger);
            width: 28px;
            height: 28px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-weight: bold;
            font-size: 18px;
            transition: 0.2s;
            z-index: 5;
        }
        .delete-btn:hover { background: var(--danger); color: white; }

        .add-card {
            border: 2px dashed var(--accent-gold);
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            cursor: pointer;
            min-height: 300px;
            background: transparent;
        }
        .add-card:hover { background: rgba(196, 138, 61, 0.05); }
        .plus-icon { font-size: 50px; color: var(--accent-gold); }
        .add-text { color: var(--accent-gold); font-weight: bold; margin-top: 10px; font-size: 0.9rem; }

        .overlay {
            display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0, 0, 0, 0.85); backdrop-filter: blur(10px);
            justify-content: center; align-items: center; z-index: 1000;
        }
        .modal {
            background: #121214; border: 1px solid var(--accent-gold);
            padding: 35px; border-radius: 24px; width: 90%; max-width: 450px;
        }

        input[type="text"], input[type="file"], input[type="date"] {
            width: 100%; padding: 12px; margin: 12px 0;
            background: var(--glass); border: 1px solid var(--glass-border);
            border-radius: 10px; color: white; box-sizing: border-box;
        }

        .btn {
            padding: 12px 24px; border-radius: 10px; border: none;
            cursor: pointer; font-weight: bold; transition: 0.2s;
        }
        .btn-save { background: var(--accent-gold); color: black; }
        .btn-cancel { background: transparent; color: var(--text-warm); border: 1px solid var(--glass-border); }
        .btn-edit {
            background: rgba(196, 138, 61, 0.15); color: var(--accent-gold);
            border: 1px solid var(--accent-gold); width: 100%; margin-top: 15px;
        }
    </style>
    <link rel="stylesheet" href="admin_sidebar.css">
</head>
<body class="admin-app">

<?php include __DIR__ . '/admin_sidebar.php'; ?>
<main class="main-content">
    <h1>My Pet Pack 🐾</h1>

    <form method="GET" class="filter-container">
        <input type="text" name="search" class="search-input" placeholder="Search by type or breed..." value="<?php echo htmlspecialchars($search); ?>">
        <input type="date" name="date_filter" class="date-input" value="<?php echo htmlspecialchars($date_filter); ?>">
        <button type="submit" class="btn btn-save">Filter</button>
        <?php if ($search !== '' || $date_filter !== ''): ?>
            <a href="?" class="btn btn-cancel" style="text-decoration:none;">Reset</a>
        <?php endif; ?>
    </form>

    <div class="dog-grid" id="dogGrid">
        <div class="card add-card" onclick="openModal()">
            <div class="plus-icon">+</div>
            <div class="add-text">ADD NEW RESCUE</div>
        </div>

        <?php foreach ($rescuedPets as $pet): ?>
            <?php
                $petId = (int)$pet['id'];
                $petImageUrl = normalizeImageUrl((string)($pet['image_path'] ?? ''));
            ?>
            <div class="card pet-card" id="pet-card-<?php echo $petId; ?>"
                 data-id="<?php echo $petId; ?>"
                 data-name="<?php echo htmlspecialchars($pet['pet_type']); ?>"
                 data-breed="<?php echo htmlspecialchars($pet['breed']); ?>"
                 data-health="<?php echo htmlspecialchars($pet['health_status'] ?? 'Unknown'); ?>">
                
                <div class="delete-btn" onclick="deletePet(event, <?php echo $petId; ?>)">&times;</div>

                <img src="<?php echo htmlspecialchars($petImageUrl); ?>" class="dog-img" onerror="this.src='https://via.placeholder.com/320x180?text=No+Image'">
                <h3 style="color: var(--accent-gold); margin: 5px 0;"><?php echo htmlspecialchars($pet['pet_type']); ?></h3>
                <p style="opacity: 0.7; font-size: 0.9rem;"><?php echo htmlspecialchars($pet['breed']); ?></p>
                <button class="btn btn-edit" onclick="openEditModal(<?php echo $petId; ?>)">Edit Record</button>
            </div>
        <?php endforeach; ?>
    </div>
</main>

<div id="modalOverlay" class="overlay">
    <div class="modal">
        <h2 id="modalTitle" style="color: var(--accent-gold); margin-top: 0;">New Pet Details</h2>
        <form id="dogForm">
            <input type="hidden" id="petId">
            <input type="text" id="dogName" placeholder="Pet Type (e.g. Dog, Cat)" required>
            <input type="text" id="dogBreed" placeholder="Breed (e.g. Golden Retriever, Shih Tzu)" required>
            <select id="healthStatus" required style="width:100%; padding:12px; margin:12px 0; background: var(--glass); border:1px solid var(--glass-border); border-radius:10px; color:white;">
                <option value="">Select Health Status</option>
                <option value="Healthy">Healthy</option>
                <option value="Injured">Injured</option>
                <option value="Critical">Critical</option>
            </select>
            
            <label style="font-size: 0.75rem; color: var(--accent-gold); margin-left: 5px;">PHOTO UPLOAD</label>
            <input type="file" id="dogFile" accept="image/*">
            
            <div style="display: flex; justify-content: flex-end; gap: 12px; margin-top: 25px;">
                <button type="button" class="btn btn-cancel" onclick="closeModal()">Cancel</button>
                <button type="submit" id="submitBtn" class="btn btn-save">Add Pet</button>
            </div>
        </form>
    </div>
</div>

<script>
    const modal = document.getElementById('modalOverlay');
    const dogForm = document.getElementById('dogForm');
    const petIdInput = document.getElementById('petId');
    const dogNameInput = document.getElementById('dogName');
    const dogBreedInput = document.getElementById('dogBreed');
    const dogFile = document.getElementById('dogFile');
    const modalTitle = document.getElementById('modalTitle');
    const submitBtn = document.getElementById('submitBtn');

    function openModal() {
        petIdInput.value = '';
        modalTitle.innerText = 'New Pet Details';
        submitBtn.innerText = 'Add Pet';
        dogFile.required = true;
        modal.style.display = 'flex';
    }

    function openEditModal(id) {
        const card = document.getElementById(`pet-card-${id}`);
        petIdInput.value = id;
        dogNameInput.value = card.dataset.name;
        dogBreedInput.value = card.dataset.breed;
        document.getElementById('healthStatus').value = card.dataset.health;
        dogFile.required = false;
        modalTitle.innerText = 'Edit Pet Details';
        submitBtn.innerText = 'Save Changes';
        modal.style.display = 'flex';
    }

    function closeModal() {
        modal.style.display = 'none';
        dogForm.reset();
    }

    function deletePet(event, id) {
        event.stopPropagation();
        if (confirm("Are you sure you want to delete this rescue record? This cannot be undone.")) {
            const formData = new FormData();
            formData.append('action', 'delete');
            formData.append('petId', id);

            fetch(window.location.href, { method: 'POST', body: formData })
            .then(r => r.json())
            .then(data => {
                if (data.success) location.reload();
                else alert(data.message);
            });
        }
    }

    dogForm.addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData();
        const isEdit = petIdInput.value !== '';
        formData.append('action', isEdit ? 'update' : 'create');
        if(isEdit) formData.append('petId', petIdInput.value);
        formData.append('dogName', dogNameInput.value);
        formData.append('dogBreed', dogBreedInput.value);
        formData.append('healthStatus', document.getElementById('healthStatus').value);
        if(dogFile.files[0]) formData.append('dogFile', dogFile.files[0]);

        fetch(window.location.href, { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => { 
            if(data.success) location.reload(); 
            else alert(data.message); 
        });
    });

    window.onclick = function(event) {
        if (event.target == modal) closeModal();
    }
</script>
</body>
</html>