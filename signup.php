<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up - NewVisayasPetConnect</title>
    <style>
        :root {
            --bg-deep: #080705; 
            --accent-gold: #c48a3d; 
            --accent-wood: #3d342d; 
            --text-warm: #d8d2cb; 
            --glass: rgba(255, 255, 255, 0.03);
            --glass-border: rgba(255, 255, 255, 0.08);
            --white: #ffffff;
            --error-red: #ff4d4d;
        }

        * { box-sizing: border-box; }

        body {
            background-color: var(--bg-deep);
            background-image: 
                radial-gradient(at 0% 0%, rgba(196, 138, 61, 0.07) 0px, transparent 50%),
                radial-gradient(at 100% 100%, rgba(61, 52, 45, 0.1) 0px, transparent 50%);
            margin: 0;
            font-family: 'Segoe UI', Roboto, sans-serif;
            color: var(--text-warm);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 40px 0; /* Increased padding for longer form */
        }

        .outer-header {
            width: 90%; 
            max-width: 1000px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 15px 30px;
            margin-bottom: 20px;
            background: var(--glass);
            backdrop-filter: blur(10px);
            border-radius: 100px;
            border: 1px solid var(--glass-border);
        }

        .logo-text {
            font-size: 1.1rem;
            font-weight: 700;
            background: linear-gradient(to right, #fff, var(--text-warm));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .btn-back {
            padding: 8px 20px;
            border-radius: 50px;
            border: 1px solid var(--accent-gold);
            background: transparent;
            color: var(--accent-gold);
            cursor: pointer;
            text-decoration: none;
            font-size: 0.85rem;
            font-weight: 600;
            transition: 0.3s;
        }

        .btn-back:hover {
            background: var(--accent-gold);
            color: var(--bg-deep);
        }

        .signup-card {
            width: 90%;
            max-width: 1000px;
            display: flex;
            background: var(--glass);
            backdrop-filter: blur(15px);
            border-radius: 30px;
            border: 1px solid var(--glass-border);
            overflow: hidden;
            box-shadow: 0 50px 100px rgba(0,0,0,0.5);
            min-height: 700px;
        }

        .signup-image {
            flex: 1;
            background-image: linear-gradient(rgba(0,0,0,0.3), rgba(0,0,0,0.3)), url('bg.jpg'); 
            background-size: cover;
            background-position: center;
            display: none;
        }

        @media (min-width: 992px) { .signup-image { display: block; } }

        .signup-form-section {
            flex: 1.5;
            padding: 40px 50px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .signup-header h2 { font-size: 1.8rem; color: var(--white); margin: 0; }
        .signup-header p { font-size: 0.85rem; opacity: 0.7; margin-bottom: 20px;}

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }

        .input-group { margin-bottom: 12px; }
        .input-group label {
            display: block;
            font-size: 0.7rem;
            margin-bottom: 5px;
            color: var(--accent-gold);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .input-group input, .input-group select, .input-group textarea {
            width: 100%;
            padding: 10px 12px;
            border-radius: 8px;
            border: 1px solid var(--glass-border);
            background: rgba(255, 255, 255, 0.05);
            color: white;
            outline: none;
            transition: 0.3s;
            font-size: 0.9rem;
        }

        /* File Upload Styling */
        .input-group input[type="file"] {
            padding: 8px;
            font-size: 0.8rem;
            cursor: pointer;
        }

        .input-group select option {
            background: #1a1a1a;
            color: white;
        }

        .input-group input:focus, .input-group select:focus, .input-group textarea:focus {
            border-color: var(--accent-gold);
            background: rgba(255, 255, 255, 0.08);
        }

        .full-width { grid-column: span 2; }

        .btn-signup {
            width: 100%;
            padding: 14px;
            border-radius: 10px;
            border: none;
            background: var(--accent-gold);
            color: var(--bg-deep);
            font-weight: 700;
            font-size: 1rem;
            cursor: pointer;
            transition: 0.3s;
            margin-top: 15px;
            text-align: center;
            text-decoration: none;
            display: block;
        }

        .btn-signup:hover {
            filter: brightness(1.1);
            transform: translateY(-2px);
        }

        .login-link {
            text-align: center;
            margin-top: 20px;
            font-size: 0.85rem;
        }

        .login-link a { color: var(--accent-gold); text-decoration: none; font-weight: 600; }

        #notice-section, #duplicate-notice {
            display: none; 
            text-align: center;
            animation: fadeIn 0.6s ease-out forwards;
        }

        .notice-icon { font-size: 4rem; margin-bottom: 20px; display: block; }
        .notice-title { color: var(--white); font-size: 1.8rem; margin-bottom: 15px; }
        .notice-text { line-height: 1.6; margin-bottom: 30px; opacity: 0.9; }
        .highlight { color: var(--accent-gold); font-weight: 600; }
        .error-highlight { color: var(--error-red); font-weight: 600; }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body>

    <header class="outer-header">
        <span class="logo-text">NewVisayasPetConnect</span>
        <a href="index.html" class="btn-back">← Back to Home</a>
    </header>

    <div class="signup-card">
        <div class="signup-image"></div>

        <div class="signup-form-section">
            
            <div id="form-container">
                <div class="signup-header">
                    <h2>Create Account</h2>
                    <p>Register as a verified resident of Brgy. New Visayas.</p>
                </div>

                <form id="registrationForm" enctype="multipart/form-data">
                    <div class="form-grid">
                        <div class="input-group">
                            <label>First Name</label>
                            <input type="text" id="fname" placeholder="Juan" required>
                        </div>
                        <div class="input-group">
                            <label>Last Name</label>
                            <input type="text" id="lname" placeholder="Dela Cruz" required>
                        </div>
                    </div>

                    <div class="form-grid">
                        <div class="input-group">
                            <label>Email Address</label>
                            <input type="email" id="email" placeholder="juan@example.com" required>
                        </div>
                        <div class="input-group">
                            <label>Phone Number</label>
                            <input type="tel" id="phone" placeholder="0912 345 6789" required>
                        </div>
                    </div>

                    <div class="form-grid">
                        <div class="input-group">
                            <label>Purok / Area</label>
                            <select id="purok" required>
                                <option value="" disabled selected>Select Purok</option>
                                <option value="Purok 1">Purok 1</option>
                                <option value="Purok 2">Purok 2</option>
                                <option value="Purok 3">Purok 3</option>
                                <option value="Purok 4">Purok 4</option>
                                <option value="Purok 5">Purok 5</option>
                                <option value="Purok 6">Purok 6</option>
                                <option value="Other">Other Area</option>
                            </select>
                        </div>
                        <div class="input-group">
                            <label>Years of Residency</label>
                            <input type="number" id="residency_years" min="0" placeholder="e.g. 5" required>
                        </div>
                    </div>

                    <div class="input-group">
                        <label>Street Name / House Number (Full Address)</label>
                        <input type="text" id="address" placeholder="e.g. 123 Gold St., Brgy. New Visayas" required>
                    </div>

                    <div class="input-group">
                        <label>Proof of Residency (Brgy ID or Utility Bill)</label>
                        <input type="file" id="proof_id" accept="image/*,.pdf" required>
                    </div>

                    <div class="form-grid">
                        <div class="input-group">
                            <label>Password</label>
                            <input type="password" id="password" placeholder="••••••••" required>
                        </div>
                        <div class="input-group">
                            <label>Confirm</label>
                            <input type="password" id="confirm_password" placeholder="••••••••" required>
                        </div>
                    </div>

                    <button type="submit" id="submitBtn" class="btn-signup">Submit Registration</button>
                </form>

                <div class="login-link">
                    Already have an account? <a href="login.php">Log In</a>
                </div>
            </div>

            <div id="notice-section">
                <span class="notice-icon">⏳</span>
                <h2 class="notice-title">Verification Pending</h2>
                <p class="notice-text">
                    Your details and proof of residency have been submitted. Our <span class="highlight">Barangay Admin</span> 
                    will verify your address in New Visayas. This usually takes 24-48 hours.
                </p>
                <a href="index.html" class="btn-signup">Return to Home</a>
            </div>

            <div id="duplicate-notice">
                <span class="notice-icon">⚠️</span>
                <h2 class="notice-title">Application Exists</h2>
                <p class="notice-text">
                    An application with this email or address is <span class="error-highlight">already in the queue</span>. 
                    Please wait for the administrator to approve your account.
                </p>
                <a href="index.html" class="btn-signup">Back to Home</a>
            </div>

        </div>
    </div>

    <script>
        document.getElementById('registrationForm').addEventListener('submit', function(e) {
            e.preventDefault();

            const btn = document.getElementById('submitBtn');
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            if (password !== confirmPassword) {
                alert("Passwords do not match!");
                return;
            }

            btn.innerText = "Uploading Documents...";
            btn.disabled = true;

            // Use FormData for file uploads
            const formData = new FormData();
            formData.append('fname', document.getElementById('fname').value);
            formData.append('lname', document.getElementById('lname').value);
            formData.append('email', document.getElementById('email').value.toLowerCase());
            formData.append('phone', document.getElementById('phone').value);
            formData.append('purok', document.getElementById('purok').value);
            formData.append('residency_years', document.getElementById('residency_years').value);
            formData.append('address', document.getElementById('address').value);
            formData.append('password', password);
            
            // Add the file
            const fileInput = document.getElementById('proof_id');
            if (fileInput.files.length > 0) {
                formData.append('proof_id', fileInput.files[0]);
            }

            fetch('signup_handler.php', {
                method: 'POST',
                body: formData // No Content-Type header needed for FormData
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    document.getElementById('form-container').style.display = 'none';
                    document.getElementById('notice-section').style.display = 'block';
                } else if (data.status === 'exists') {
                    document.getElementById('form-container').style.display = 'none';
                    document.getElementById('duplicate-notice').style.display = 'block';
                } else {
                    alert("Error: " + data.message);
                    btn.innerText = "Submit Registration";
                    btn.disabled = false;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert("Could not connect to the server.");
                btn.innerText = "Submit Registration";
                btn.disabled = false;
            });
        });
    </script>
</body>
</html>