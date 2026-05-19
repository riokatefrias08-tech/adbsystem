<?php
session_start();
if (!isset($_SESSION['role']) || strtolower($_SESSION['role']) !== 'resident') {
    header("Location: login.php");
    exit();
}
$user_id = $_SESSION['user_id'] ?? 0;

try {
    $pdo = new PDO("mysql:host=localhost;dbname=adbsystemm;charset=utf8mb4", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $stmt_user = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt_user->execute([$user_id]);
    $user_info = $stmt_user->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Profile Settings - PetConnect</title>
    <style>
        :root { --bg-deep: #0a0a0b; --accent-gold: #c48a3d; --text-warm: #d8d2cb; --glass: rgba(255, 255, 255, 0.03); --glass-border: rgba(255, 255, 255, 0.08); --accent-gold-glow: rgba(196, 138, 61, 0.3); }
        body { background-color: var(--bg-deep); color: var(--text-warm); font-family: 'Inter', sans-serif; padding: 40px; }
        .card { background: var(--glass); padding: 30px; border-radius: 28px; border: 1px solid var(--glass-border); max-width: 900px; margin: 0 auto; }
        .profile-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 20px; }
        .profile-group { background: rgba(255,255,255,0.02); border: 1px solid var(--glass-border); padding: 20px; border-radius: 18px; }
        .profile-group h3 { color: var(--accent-gold); margin-top: 0; text-transform: uppercase; font-size: 0.85rem;}
        .profile-field label { display: block; font-size: 0.75rem; color: var(--accent-gold); text-transform: uppercase; margin-bottom: 4px;}
        .profile-field input { width: 100%; padding: 10px; border-radius: 8px; background: rgba(255,255,255,0.05); border: 1px solid var(--glass-border); color: #fff; margin-bottom: 12px; box-sizing: border-box;}
        .profile-avatar { width: 120px; height: 120px; border-radius: 50%; border: 3px solid var(--accent-gold); overflow: hidden; margin: 0 auto 20px; position: relative; cursor: pointer;}
        .profile-avatar img { width:100%; height:100%; object-fit: cover; }
        .avatar-overlay { position: absolute; inset:0; background:rgba(0,0,0,0.6); display:flex; align-items:center; justify-content:center; opacity:0; transition:0.3s; color:#fff; font-size:0.7rem; text-transform:uppercase;}
        .profile-avatar:hover .avatar-overlay { opacity: 1; }
    </style>
</head>
<body>
    <div class="card">
        <h2 style="text-align:center; margin-bottom:30px;">👤 My Profile Settings</h2>
        
        <div style="text-align:center;">
            <form action="upload_profile_picture.php" method="POST" enctype="multipart/form-data">
                <label for="p_file">
                    <div class="profile-avatar">
                        <img src="uploads/profile/<?php echo htmlspecialchars($user_info['profile_picture'] ?? 'default.png'); ?>" onerror="this.src='https://via.placeholder.com/150'">
                        <div class="avatar-overlay"><span>Change</span></div>
                    </div>
                </label>
                <input type="file" id="p_file" name="profile_picture" accept="image/*" style="display:none;" onchange="this.form.submit()">
            </form>
            <h3><?php echo htmlspecialchars(($user_info['first_name'] ?? '') . ' ' . ($user_info['last_name'] ?? '')); ?></h3>
        </div>

        <div class="profile-grid">
            <div class="profile-group">
                <h3>Personal info</h3>
                <div class="profile-field"><label>First Name</label><input type="text" value="<?php echo htmlspecialchars($user_info['first_name'] ?? ''); ?>" readonly></div>
                <div class="profile-field"><label>Last Name</label><input type="text" value="<?php echo htmlspecialchars($user_info['last_name'] ?? ''); ?>" readonly></div>
                <div class="profile-field"><label>Purok</label><input type="text" value="<?php echo htmlspecialchars($user_info['purok'] ?? ''); ?>" readonly></div>
            </div>
            <div class="profile-group">
                <h3>Contact Info</h3>
                <div class="profile-field"><label>Email Address</label><input type="text" value="<?php echo htmlspecialchars($user_info['email'] ?? ''); ?>" readonly></div>
                <div class="profile-field"><label>Phone Number</label><input type="text" value="<?php echo htmlspecialchars($user_info['phone'] ?? ''); ?>" readonly></div>
            </div>
            <div class="profile-group">
                <h3>Account Info</h3>
                <div class="profile-field"><label>Role</label><input type="text" value="<?php echo htmlspecialchars($user_info['role'] ?? ''); ?>" readonly></div>
                <div class="profile-field"><label>Status</label><input type="text" value="<?php echo htmlspecialchars($user_info['status'] ?? ''); ?>" readonly></div>
            </div>
        </div>
    </div>
</body>
</html>