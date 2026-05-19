<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About Us - Barangay New Visayas</title>
    <style>
        :root {
            --bg-deep: #080705; 
            --bg-glow: #1a1510;
            --accent-gold: #c48a3d; 
            --accent-wood: #3d342d; 
            --text-warm: #d8d2cb; 
            --glass: rgba(255, 255, 255, 0.03);
            --glass-border: rgba(255, 255, 255, 0.08);
        }

        * {
            box-sizing: border-box; /* Ensures padding doesn't affect final width */
        }

        body {
            background-color: var(--bg-deep);
            background-image: 
                radial-gradient(at 0% 0%, rgba(196, 138, 61, 0.07) 0px, transparent 50%),
                radial-gradient(at 100% 0%, rgba(61, 52, 45, 0.1) 0px, transparent 50%),
                radial-gradient(at 50% 100%, rgba(26, 21, 16, 1) 0px, transparent 50%);
            margin: 0;
            font-family: 'Segoe UI', Roboto, sans-serif;
            color: var(--text-warm);
            line-height: 1.6;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        /* --- HEADER (Width matched to Container) --- */
        .outer-header {
            width: 90%; 
            max-width: 900px; /* Matched to container */
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 15px 30px;
            margin: 20px 0;
            background: var(--glass);
            backdrop-filter: blur(10px);
            border-radius: 100px;
            border: 1px solid var(--glass-border);
        }

        .logo-text {
            font-size: 1.2rem;
            font-weight: 700;
            background: linear-gradient(to right, #fff, var(--text-warm));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .btn-back {
            display: inline-flex;
            align-items: center;
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
            white-space: nowrap;
        }

        .btn-back:hover {
            background: var(--accent-gold);
            color: var(--bg-deep);
        }

        /* --- CONTAINER --- */
        .container {
            width: 90%;
            max-width: 900px; /* Matched to header */
            padding-bottom: 100px;
        }

        .hero-section {
            text-align: center;
            padding: 40px 0 30px;
        }

        .hero-section h1 {
            font-size: 3rem;
            margin-bottom: 10px;
            color: #fff;
        }

        /* --- STORY CARD --- */
        .story-card {
            background: var(--glass);
            border: 1px solid var(--glass-border);
            padding: 40px;
            border-radius: 30px;
            backdrop-filter: blur(5px);
            text-align: center;
            margin-bottom: 40px;
        }

        .story-card p {
            font-size: 1.05rem;
            opacity: 0.95;
            text-align: justify;
            margin: 0;
        }

        /* --- CONTACT SECTION --- */
        .contact-card {
            background: rgba(196, 138, 61, 0.05);
            border: 1px dashed var(--accent-gold);
            padding: 30px;
            border-radius: 30px;
            text-align: center;
        }

        .contact-card h3 {
            color: var(--accent-gold);
            margin-top: 0;
            margin-bottom: 15px;
        }

        .contact-info {
            display: flex;
            justify-content: center;
            gap: 30px;
            flex-wrap: wrap;
            font-size: 0.9rem;
        }

        /* --- CREDITS --- */
        .officials-section {
            margin-top: 80px;
            text-align: center;
        }

        .officials-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 40px;
            margin-top: 50px;
            justify-content: center;
        }

        .official-card {
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .official-photo {
            width: 140px;
            height: 140px;
            border-radius: 50%;
            border: 3px solid var(--accent-gold);
            margin-bottom: 15px;
            object-fit: cover;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.4);
        }

        .official-name {
            font-weight: 700;
            font-size: 1.1rem;
            color: #fff;
            display: block;
        }

        .official-role {
            font-size: 0.9rem;
            opacity: 0.7;
            color: var(--accent-gold);
            text-transform: uppercase;
            letter-spacing: 1px;
        }
    </style>
</head>
<body>

    <header class="outer-header">
        <div class="logo-section">
            <span class="logo-text">Barangay New Visayas</span>
        </div>
        <a href="index.html" class="btn-back">← Back to Home</a>
    </header>

    <div class="container">
        <section class="hero-section">
            <h1>About Our Project</h1>
        </section>

        <div class="story-card">
            <p>
                Addressing the serious challenge of over 12 million stray animals in the Philippines, <strong>NewVisayasPetConnect</strong> is a specialized tool developed for Barangay New Visayas, Panabo City, to manage the journey of pets from the streets to loving homes. By allowing residents to report strays via a simple "Upload" button and providing a transparent platform for adoptions and donations, our system ensures that every animal is tracked, fed, and treated without the risks associated with unorganized social media transactions. This initiative not only protects the animals but also enhances community safety by reducing road accidents and the spread of diseases like rabies, ultimately using technology to turn New Visayas into a kindness-driven, more organized environment for everyone.
            </p>
        </div>

        <div class="contact-card">
            <h3>Get in Touch</h3>
            <div class="contact-info">
                <span>📍 Barangay Hall, New Visayas</span>
                <span>📧 support@newvisayaspetconnect.ph</span>
                <span>📞 (084) 123-4567</span>
            </div>
        </div>

        <section class="officials-section">
            <h2>CREDITS</h2>
            <div class="officials-grid">
               <div class="official-card">
                    <img src="mika.jpg" alt="Myka Maningo" class="official-photo">
                    <span class="official-name">Myka Maningo</span>
                    <span class="official-role">Developer 1</span>
                </div>
                <div class="official-card">
                    <img src="rio.jpg" alt="Rio Kate Mancia" class="official-photo">
                    <span class="official-name">Rio Kate Mancia</span>
                    <span class="official-role">Developer 2</span>
                </div>
                <div class="official-card">
                    <img src="rabor.jpg" alt="Richmond Rabor" class="official-photo">
                    <span class="official-name">Richmond Rabor</span>
                    <span class="official-role">Developer 3</span>
                </div>
            </div> 
        </section>
    </div>

</body>
</html>