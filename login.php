<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Log In - NewVisayasPetConnect</title>
    <style>
        :root {
            --bg-deep: #080705; 
            --accent-gold: #c48a3d; 
            --accent-wood: #3d342d; 
            --text-warm: #d8d2cb; 
            --glass: rgba(255, 255, 255, 0.03);
            --glass-border: rgba(255, 255, 255, 0.08);
            --white: #ffffff;
        }

        body {
            background-color: var(--bg-deep);
            background-image: 
                radial-gradient(at 0% 0%, rgba(196, 138, 61, 0.07) 0px, transparent 50%),
                radial-gradient(at 100% 100%, rgba(61, 52, 45, 0.1) 0px, transparent 50%);
            margin: 0;
            font-family: 'Segoe UI', Roboto, sans-serif;
            color: var(--text-warm);
            height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            overflow: hidden;
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
            box-sizing: border-box;
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

        .login-card {
            width: 90%;
            max-width: 1000px;
            height: 600px; 
            display: flex;
            background: var(--glass);
            backdrop-filter: blur(15px);
            border-radius: 30px;
            border: 1px solid var(--glass-border);
            overflow: hidden;
            box-shadow: 0 50px 100px rgba(0,0,0,0.5);
        }

        .login-image {
            flex: 1;
            background-image: url('bg.jpg'); 
            background-size: cover;
            background-position: center;
            position: relative;
            display: none;
        }

        @media (min-width: 768px) {
            .login-image { display: block; }
        }

        .login-form-section {
            flex: 1;
            padding: 50px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .login-header { margin-bottom: 25px; }
        .login-header h2 { font-size: 2rem; color: var(--white); margin: 0; }

        .input-group { margin-bottom: 18px; }
        .input-group label {
            display: block;
            font-size: 0.85rem;
            margin-bottom: 8px;
            color: var(--accent-gold);
            font-weight: 600;
        }

        .input-group input, .input-group select {
            width: 100%;
            padding: 12px 15px;
            border-radius: 10px;
            border: 1px solid var(--glass-border);
            background: rgba(255, 255, 255, 0.05);
            color: white;
            outline: none;
            box-sizing: border-box;
            transition: 0.3s;
            font-size: 0.9rem;
        }

        .input-group select option {
            background-color: var(--bg-deep);
            color: white;
        }

        .btn-login {
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
            margin-top: 10px;
        }

        .btn-login:hover {
            transform: translateY(-2px);
            filter: brightness(1.1);
        }

        .signup-link {
            text-align: center;
            margin-top: 25px;
            font-size: 0.85rem;
        }

        .signup-link a { color: var(--accent-gold); text-decoration: none; font-weight: 600; }
    </style>
</head>
<body>

    <header class="outer-header">
        <span class="logo-text">NewVisayasPetConnect</span>
        <a href="index.html" class="btn-back">← Back to Home</a>
    </header>

    <div class="login-card">
        <div class="login-image"></div>

        <div class="login-form-section">
            <div id="login-container">
                <div class="login-header">
                    <h2>Welcome Back</h2>
                    <p>Access your rescue dashboard</p>
                </div>

                <form id="loginForm">
                    <div class="input-group">
                        <label for="role">Login As</label>
                        <select id="role" required>
                            <option value="Resident">Resident</option>
                            <option value="Admin">Administrator</option>
                        </select>
                    </div>

                    <div class="input-group">
                        <label for="email">Email Address</label>
                        <input type="email" id="email" placeholder="Enter your email" required>
                    </div>

                    <div class="input-group">
                        <label for="password">Password</label>
                        <input type="password" id="password" placeholder="••••••••" required>
                    </div>

                    <button type="submit" id="loginBtn" class="btn-login">Log In</button>
                </form>

                <div class="signup-link">
                    Don't have an account? <a href="signup.php">Sign Up</a>
                </div>
            </div>

            <div id="pending-notice" style="display: none; text-align: center;">
                <span style="font-size: 4rem; display: block; margin-bottom: 20px;">⏳</span>
                <h2 style="color: var(--white); font-size: 1.8rem; margin-bottom: 15px;">Approval Pending</h2>
                <p style="line-height: 1.6; opacity: 0.9; margin-bottom: 30px;">
                    Your account is currently <span style="color: var(--accent-gold); font-weight: 600;">awaiting administrator approval</span>. <br>
                    Please check back again later.
                </p>
                <button onclick="location.reload()" class="btn-login">Back to Login</button>
            </div>
        </div>
    </div>

    <script>
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            e.preventDefault();

            const btn = document.getElementById('loginBtn');
            const email = document.getElementById('email').value;
            const password = document.getElementById('password').value;
            const role = document.getElementById('role').value;

            btn.innerText = "Verifying...";
            btn.disabled = true;

            fetch('login_handler.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ 
                    email: email, 
                    password: password,
                    role: role 
                })
            })
            .then(response => response.json())
            .then(data => {
                // IMPORTANT: The PHP sends 'success', 'pending', or 'error'
                if (data.status === 'success') {
                    // Redirect to the path provided by PHP
                    window.location.href = data.redirect;
                } 
                else if (data.status === 'pending') {
                    document.getElementById('login-container').style.display = 'none';
                    document.getElementById('pending-notice').style.display = 'block';
                } 
                else {
                    alert(data.message || "Invalid credentials");
                    btn.innerText = "Log In";
                    btn.disabled = false;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert("Connection error. Check your console (F12).");
                btn.innerText = "Log In";
                btn.disabled = false;
            });
        });
    </script>
</body>
</html>