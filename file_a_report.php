<?php
session_start();
if (!isset($_SESSION['role']) || strtolower($_SESSION['role']) !== 'resident') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'] ?? 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>File a Report - PetConnect</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --bg-deep: #0a0a0b; 
            --accent-gold: #c48a3d; 
            --text-warm: #d8d2cb; 
            --glass: rgba(255, 255, 255, 0.03);
            --glass-border: rgba(255, 255, 255, 0.08);
        }
        body {
            background-color: var(--bg-deep);
            background-image: radial-gradient(circle at 50% 50%, #1a1a1c 0%, #0a0a0b 100%);
            color: var(--text-warm);
            font-family: 'Inter', sans-serif;
            padding: 40px;
        }
        .card {
            background: var(--glass);
            padding: 30px;
            border-radius: 28px;
            border: 1px solid var(--glass-border);
            max-width: 800px;
            margin: 0 auto;
        }
        .report-form label {
            display: block; margin-bottom: 8px; font-weight: 500;
            color: var(--accent-gold); font-size: 0.9rem; text-transform: uppercase;
        }
        .report-form input, .report-form select, .report-form textarea {
            width: 100%; padding: 14px 18px; margin-bottom: 25px;
            background: rgba(255, 255, 255, 0.04); border: 1px solid var(--glass-border);
            color: #fff; border-radius: 14px; box-sizing: border-box;
        }
        .report-form select option { background: #1b1b1f; color: #f5f5f5; }
        .btn-gold {
            background: var(--accent-gold); color: #000; border: none; 
            padding: 16px 40px; border-radius: 14px; cursor: pointer;
            font-weight: 800; text-transform: uppercase; width: 100%;
        }
    </style>
</head>
<body>
    <div class="card">
        <h2>📢 Report Stray or Lost Pet</h2>
        <form class="report-form" action="submit_report.php" method="POST" enctype="multipart/form-data">
            <label>What are you reporting?</label>
            <select name="report_type" required>
                <option value="stray">I found a Stray Animal</option>
                <option value="lost">I lost my own Pet</option>
            </select>

            <label>Animal Type</label>
            <select name="animal_type">
                <option value="Dog">Dog</option>
                <option value="Cat">Cat</option>
            </select>

            <label>Description</label>
            <textarea name="description" rows="3" placeholder="Describe the animal..." required></textarea>

            <label>Last Seen Location</label>
            <select name="location" required>
                <option value="" disabled selected>Select a Purok</option>
                <?php for($i=1; $i<=17; $i++): ?>
                    <option value="Purok <?php echo $i; ?>">Purok <?php echo $i; ?></option>
                <?php endfor; ?>
            </select>

            <label>Health Status of the Pet</label>
            <select name="health_status" required>
                <option value="Healthy">Healthy</option>
                <option value="Injured">Injured</option>
                <option value="Critical">Critical</option>
            </select>

            <label>Upload Photo</label>
            <input type="file" name="pet_image" accept="image/*" required>

            <button type="submit" class="btn-gold">Submit Report</button>
        </form>
    </div>
</body>
</html>