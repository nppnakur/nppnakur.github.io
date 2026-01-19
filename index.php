<!DOCTYPE html>
<html lang="hi">
<head>
    <meta charset="UTF-8">
    <title>NAGAR PALIKA NAKUR</title>
    <link rel="icon" href="img1.png" type="image/png">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
            background-image: url('https://ptaxsnn.com/Login/images/banner.jpg');
            background-size: cover;
            background-position: center center;
            background-attachment: fixed;
            background-repeat: no-repeat;
            height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            color: #fff;
            position: relative;
            overflow-x: hidden;
        }

        h2 {
            position: absolute;
            top: 8px;
            width: 100%;
            text-align: center;
            font-size: 40px;
            color: #fff;
            z-index: 1;
            animation: glowing 1.5s infinite alternate;
        }

        @keyframes glowing {
            0% {
                text-shadow: 0 0 5px #ffcc00, 0 0 10px #ffcc00;
            }
            50% {
                text-shadow: 0 0 15px #ffcc00, 0 0 25px #ffcc00, 0 0 35px #ffcc00;
            }
            100% {
                text-shadow: 0 0 20px #ffcc00, 0 0 30px #ffcc00, 0 0 40px #ffcc00;
            }
        }

        .powered-by {
            position: fixed;
            bottom: 20px;
            right: 30px;
            font-size: 16px;
            color: #f2f2f1;
            white-space: nowrap;
            overflow: hidden;
            border-right: 2px solid #ffc107;
            width: 0;
            animation:
                typing 3s steps(28, end) forwards,
                blink 0.7s step-end infinite,
                floatUpDown 2s ease-in-out infinite;
        }

        @keyframes typing {
            from { width: 0 }
            to { width: 260px; }
        }

        @keyframes blink {
            50% { border-color: transparent; }
        }

        @keyframes floatUpDown {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-5px); }
        }

        .container {
            width: 80%;
            max-width: 1000px;
            border-radius: 20px;
            box-shadow: 0 8px 10px rgba(0, 0, 0, 0.2);
            display: flex;
            overflow: hidden;
            background-color: rgba(0, 0, 0, 0.19);
            padding: 0;
            flex-direction: row;
        }

        .logo-section {
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            padding: 25px;
            position: relative;
        }

        .logo-wrapper {
            width: 300px;
            height: 300px;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .rotating-logo {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            animation: flipUpright 5s ease infinite;
            transform-style: preserve-3d;
            object-fit: contain;
            border: 3px solid #ffcc00;
            background: rgba(255, 204, 0, 0.1);
            padding: 10px;
        }

        @keyframes flipUpright {
            0% {
                transform: rotateY(0deg) scaleX(1);
            }
            25% {
                transform: rotateY(90deg) scaleX(0.8);
            }
            50% {
                transform: rotateY(180deg) scaleX(-1);
            }
            75% {
                transform: rotateY(270deg) scaleX(0.8);
            }
            100% {
                transform: rotateY(360deg) scaleX(1);
            }
        }

        .form-section {
            flex: 1;
            padding: 40px;
        }

        .form-section h3 {
            font-size: 28px;
            margin-bottom: 20px;
            text-align: center;
            color: #ffcc00;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            font-size: 16px;
            margin-bottom: 5px;
            color: #fff;
        }

        .form-group input {
            width: 100%;
            padding: 10px;
            border: none;
            border-radius: 4px;
            background: rgba(0, 0, 0, 0.3);
            font-size: 14px;
            color: white;
        }

        .form-group input::placeholder {
            color: #aaa;
        }

        .form-group button {
            width: 100%;
            padding: 12px;
            font-size: 16px;
            font-weight: bold;
            color: #1a1818;
            background-color: #fbf2f2cf;
            border: none;
            border-radius: 23px;
            cursor: pointer;
            transition: background-color 0.3s ease;
            animation: waveButton 2s infinite alternate ease-in-out;
        }

        .form-group button:hover {
            background-color: #e6b800;
        }

        @keyframes waveButton {
            0% {
                transform: translateY(0);
            }
            30% {
                transform: translateY(-3px);
            }
            50% {
                transform: translateY(0);
            }
            75% {
                transform: translateY(3px);
            }
            100% {
                transform: translateY(0);
            }
        }

        .footer {
            text-align: center;
            margin-top: 20px;
            font-size: 14px;
        }

        .footer span {
            color: red;
        }

        /* Mobile Responsiveness */
        @media screen and (max-width: 768px) {
            h2 {
                font-size: 35px;
                top: 10px;
            }
            
            .container {
                flex-direction: column;
                max-width: 90%;
                padding: 20px;
            }
            
            .logo-section {
                padding: 15px;
            }
            
            .logo-wrapper {
                width: 250px;
                height: 250px;
            }
            
            .form-section {
                padding: 25px;
            }
            
            .form-section h3 {
                font-size: 24px;
            }
            
            .powered-by {
                right: 15px;
                bottom: 10px;
                font-size: 14px;
            }
        }

        @media screen and (max-width: 480px) {
            h2 {
                font-size: 30px;
                top: 5px;
            }
            
            .logo-wrapper {
                width: 200px;
                height: 200px;
            }
            
            .form-section {
                padding: 20px;
            }
            
            .form-section h3 {
                font-size: 22px;
            }
            
            .form-group input {
                font-size: 14px;
                padding: 8px;
            }
            
            .form-group button {
                padding: 10px;
                font-size: 14px;
            }
            
            .footer {
                font-size: 10px;
            }
            
            .powered-by {
                right: 10px;
                bottom: 5px;
                font-size: 12px;
            }
        }

        @media screen and (max-width: 360px) {
            .logo-wrapper {
                width: 180px;
                height: 180px;
            }
            
            h2 {
                font-size: 25px;
            }
        }
    </style>
</head>
<body>
    <h2>WELCOME TO NAGAR PALIKA NAKUR</h2>
    
    <div class="container">
        <div class="logo-section">
            <div class="logo-wrapper">
                <img src="img1.png" alt="Nagar Palika Nakur Logo" class="rotating-logo">
            </div>
        </div>
        
        <div class="form-section">
            <h3>LOG IN</h3>
            <form method="POST" action="login.php">
                <input type="hidden" name="user_role" value="1">
                
                <div class="form-group">
                    <label for="username"><b>USER NAME</b></label>
                    <input type="text" name="username" id="username" placeholder="ENTER YOUR USER ID" required>
                </div>
                
                <div class="form-group">
                    <label for="password"><b>PASSWORD</b></label>
                    <input type="password" name="password" id="password" placeholder="ENTER YOUR PASSWORD" required>
                </div>
                
                <div class="form-group">
                    <button type="submit" name="admin_login">LOG IN &#8594;</button>
                </div>
            </form>
            
            <div class="footer">
                <span>Secure Login Portal</span>
            </div>
        </div>
    </div>
    
    <div class="powered-by">POWERED-BY➤SUNNY DHAKA</div>

    <script>
        // ब्राउज़र टैब में लोगो सुनिश्चित करने के लिए
        document.addEventListener('DOMContentLoaded', function() {
            // फेविकॉन को फोर्स लोड करने के लिए
            const favicon = document.querySelector('link[rel="icon"]');
            if (favicon) {
                // कैशिंग से बचने के लिए timestamp जोड़ें
                favicon.href = 'img1.png?' + new Date().getTime();
                
                // फेविकॉन लोड होने की पुष्टि के लिए
                const img = new Image();
                img.src = 'img1.png';
                img.onload = function() {
                    console.log('Favicon loaded successfully');
                };
                img.onerror = function() {
                    console.log('Favicon loading failed');
                };
            }
            
            // लोगो को रीफ्रेश करने के लिए (यदि कैश में है)
            const logo = document.querySelector('.rotating-logo');
            if (logo) {
                logo.src = 'img1.png?' + new Date().getTime();
            }
        });
    </script>
</body>
</html>
