<?php
ob_start();
session_start();

error_reporting(E_ALL);
ini_set('display_errors', 1);

// ================== AUTOLOAD ==================
$autoloadPath = __DIR__ . '/../vendor/autoload.php';

if (!file_exists($autoloadPath)) {
    die("PHPMailer autoload not found. Run composer install.");
}

require_once $autoloadPath;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;


// ================== API (MUST BE FIRST) ==================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['api_action'])) {
    header('Content-Type: application/json');

    // ---------- SEND OTP ----------
    if ($_POST['api_action'] === 'send_otp') {

        $email = filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL);

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid email']);
            exit;
        }

        $otp = random_int(100000, 999999);

        $_SESSION['otp'] = $otp;
        $_SESSION['otp_time'] = time();
        $_SESSION['request_email'] = $email;

        $mail = new PHPMailer(true);

        try {
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username = 'milanxetriofficial11@gmail.com';
            $mail->Password = 'mpfk tkui dbue ojut'; // APP PASSWORD
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = 587;

            $mail->setFrom('milanxetriofficial11@gmail.com', 'Stellar Verify');
            $mail->addAddress($email);

            $mail->isHTML(true);
            $mail->Subject = "Your OTP Code";
            $mail->Body = "
            <html>
            <head><title>Stellar Verification</title></head>
            <body style='font-family: Arial, sans-serif;'>
                <div style='max-width: 500px; margin: 0 auto; background: #f5f7fc; padding: 30px; border-radius: 20px;'>
                    <h2 style='color: #0f172a;'>🔐 Your Verification Code</h2>
                    <p style='font-size: 16px; color: #334155;'>Use the following 6-digit code to complete your verification. This code expires in 5 minutes.</p>
                    <div style='background: #eef2ff; text-align: center; padding: 20px; border-radius: 16px; margin: 20px 0;'>
                        <span style='font-size: 42px; font-weight: 800; letter-spacing: 8px; color: #0f3b5c;'>$otp</span>
                    </div>
                    <p style='color: #475569; font-size: 14px;'>If you didn't request this, please ignore this email.</p>
                    <hr style='margin: 20px 0; border: none; border-top: 1px solid #e2e8f0;'>
                    <p style='color: #64748b; font-size: 12px;'>Stellar Secure Gateway</p>
                </div>
            </body>
            </html>
            ";

            $mail->send();

            echo json_encode([
                'status' => 'success',
                'message' => 'OTP sent successfully'
            ]);
        } catch (Exception $e) {
            echo json_encode([
                'status' => 'error',
                'message' => $mail->ErrorInfo
            ]);
        }

        exit;
    }

    // ---------- VERIFY OTP ----------
    if ($_POST['api_action'] === 'verify_otp') {

        $otp = trim($_POST['otp']);

        if (!isset($_SESSION['otp'])) {
            echo json_encode(['status' => 'error', 'message' => 'No OTP found']);
            exit;
        }

        if (time() - $_SESSION['otp_time'] > 300) {
            unset($_SESSION['otp']);
            echo json_encode(['status' => 'error', 'message' => 'OTP expired']);
            exit;
        }

        if ($otp == $_SESSION['otp']) {

            $_SESSION['verified_email'] = $_SESSION['request_email'];
            $_SESSION['email_verified'] = true;

            unset($_SESSION['otp'], $_SESSION['otp_time']);

            echo json_encode([
                'status' => 'success',
                'message' => 'Email verified'
            ]);
        } else {
            echo json_encode([
                'status' => 'error',
                'message' => 'Wrong OTP'
            ]);
        }

        exit;
    }
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>PlayZo - Seller Verify | Two-Factor Authentication</title>
    <link rel="shortcut icon" href="../img_logo/cropped_circle_image.png" type="image/x-icon">
    <!-- Google Fonts & Font Awesome -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', system-ui, -apple-system, 'Segoe UI', sans-serif;
            height: 100vh;
            width: 100%;
            overflow-x: hidden;
            background: #0b0f1c;
        }

        .split-layout {
            display: flex;
            flex-direction: row;
            width: 100%;
            min-height: 100vh;
        }

        .hero-image {
            flex: 1.1;
            background-image: url('https://images.pexels.com/photos/998641/pexels-photo-998641.jpeg?auto=compress&cs=tinysrgb&w=1600');
            background-size: cover;
            background-position: center 30%;
            background-repeat: no-repeat;
            position: relative;
        }

        @media (min-width: 1400px) {
            .hero-image {
                background-image: url('https://images.unsplash.com/photo-1534796636912-3b95b3ab5986?q=80&w=2071&auto=format&fit=crop');
                background-position: center 20%;
            }
        }

        .hero-image::after {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.2);
            pointer-events: none;
        }

        .form-panel {
            flex: 1;
            background:  #b94502;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem 1.8rem;
            box-shadow: -8px 0 30px rgba(53, 240, 11, 0.98);
            border-left: 1px solid rgba(10, 21, 233, 0.9);
        }

        .form-container {
            max-width: 500px;
            width: 100%;
            margin: 0 auto;
            animation: fadeSlideUp 0.5s ease-out;
        }

        .brand-logo {
            text-align: center;
            margin-bottom: 1.75rem;
        }

        .brand-logo img {
            width: 200px;
            height: 100px;
            object-fit: contain;
            filter: drop-shadow(0 8px 16px rgba(0,0,0,0.4));
            transition: transform 0.2s ease;
        }

        .brand-logo img:hover {
            transform: scale(1.02);
        }

        .brand-logo h3 {
            color: #a0c4ff;
            font-weight: 500;
            font-size: 0.9rem;
            letter-spacing: 1px;
            margin-top: 8px;
            background: linear-gradient(135deg, #c084fc, #38bdf8);
            background-clip: text;
            -webkit-background-clip: text;
            color: transparent;
        }

        .verify-title {
            text-align: center;
            margin-bottom: 1.8rem;
        }

        .verify-title h1 {
            font-size: 1.9rem;
            font-weight: 700;
            background: linear-gradient(130deg, #ffffff, #94a3b8);
            background-clip: text;
            -webkit-background-clip: text;
            color: transparent;
            letter-spacing: -0.3px;
            display: inline-flex;
            align-items: center;
            gap: 12px;
        }

        .verify-title p {
            color: #7dd3fc;
            font-size: 0.85rem;
            margin-top: 10px;
            border-bottom: 1px dashed #2dd4bf;
            display: inline-block;
            padding-bottom: 5px;
        }

        .input-group {
            margin-bottom: 26px;
        }

        .input-icon {
            position: relative;
            display: flex;
            align-items: center;
        }

        .input-icon i {
            position: absolute;
            left: 20px;
            color: #5b7a9a;
            font-size: 1.1rem;
            z-index: 2;
        }

        .input-icon input {
            width: 100%;
            padding: 14px 20px 14px 50px;
            background: #111827;
            border: 1.5px solid #2a3348;
            border-radius: 44px;
            font-size: 1rem;
            color: #f1f5f9;
            font-weight: 500;
            transition: all 0.2s;
            outline: none;
        }

        .input-icon input:focus {
            border-color: #38bdf8;
            box-shadow: 0 0 0 3px rgba(56, 189, 248, 0.25);
            background: #0f172a;
        }

        button {
            width: 100%;
            padding: 14px;
            background: linear-gradient(95deg, #0ea5e9, #3b82f6);
            border: none;
            border-radius: 44px;
            font-weight: 700;
            font-size: 1rem;
            cursor: pointer;
            color: white;
            transition: 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            letter-spacing: 0.3px;
            box-shadow: 0 4px 12px rgba(14, 165, 233, 0.3);
        }

        button:hover {
            transform: scale(0.98);
            background: linear-gradient(95deg, #0284c7, #2563eb);
            box-shadow: 0 6px 14px rgba(2, 132, 199, 0.4);
        }

        button:disabled {
            opacity: 0.7;
            transform: none;
            cursor: not-allowed;
        }

        .info-badge {
            display: flex;
            justify-content: center;
            gap: 24px;
            margin: 20px 0 10px;
            font-size: 0.7rem;
            color: #7e95b5;
        }

        .info-badge span i {
            margin-right: 5px;
            color: #38bdf8;
        }

        .otp-container {
            display: flex;
            gap: 12px;
            justify-content: center;
            margin: 25px 0 20px;
            flex-wrap: wrap;
        }

        .otp-box {
            width: 58px;
            height: 72px;
            text-align: center;
            font-size: 32px;
            font-weight: 700;
            background: #111827;
            border: 2px solid #2d3a60;
            border-radius: 20px;
            color: #38bdf8;
            font-family: 'Inter', monospace;
            caret-color: #38bdf8;
            transition: all 0.1s;
        }

        .otp-box:focus {
            border-color: #38bdf8;
            box-shadow: 0 0 0 3px rgba(56, 189, 248, 0.3);
            outline: none;
            background: #0f172a;
        }

        .timer {
            font-size: 0.8rem;
            color: #facc15;
            text-align: center;
            font-weight: 600;
            background: #1e293b90;
            padding: 6px 16px;
            border-radius: 40px;
            display: inline-block;
            width: auto;
            margin: 8px auto 6px;
            backdrop-filter: blur(4px);
        }

        .resend-link {
            background: none;
            color: #7aa9e0;
            font-size: 0.8rem;
            margin-top: 12px;
            padding: 8px 18px;
            width: auto;
            display: inline-block;
            text-decoration: underline;
            cursor: pointer;
            font-weight: 500;
            transition: 0.1s;
        }

        .resend-link:hover {
            color: #b9e0ff;
            background: rgba(56, 189, 248, 0.05);
            border-radius: 60px;
            text-decoration: none;
        }

        .message-area {
            margin-top: 20px;
            text-align: center;
            padding: 12px;
            border-radius: 60px;
            font-size: 0.85rem;
            font-weight: 500;
            background: rgba(0,0,0,0.5);
            backdrop-filter: blur(4px);
        }

        .success {
            color: #4ade80;
            background: rgba(74, 222, 128, 0.12);
            border-left: 3px solid #4ade80;
        }

        .error {
            color: #f87171;
            background: rgba(248, 113, 113, 0.12);
            border-left: 3px solid #f87171;
        }

        .hidden {
            display: none;
        }

        @keyframes fadeSlideUp {
            from {
                opacity: 0;
                transform: translateY(18px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .flex-center {
            text-align: center;
        }

        .helper-note {
            font-size: 0.7rem;
            color: #7e95b5;
            background: rgba(0, 0, 0, 0.3);
            padding: 8px 12px;
            border-radius: 28px;
            margin-top: 12px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .helper-note i {
            color: #facc15;
        }

        @media (max-width: 780px) {
            .split-layout {
                flex-direction: column;
            }
            .hero-image {
                min-height: 260px;
                flex: none;
                width: 100%;
            }
            .form-panel {
                padding: 2rem 1.2rem;
                border-left: none;
                border-top: 1px solid rgba(56, 189, 248, 0.2);
            }
            .otp-box {
                width: 48px;
                height: 62px;
                font-size: 28px;
            }
            .brand-logo img {
                width: 65px;
                height: 65px;
            }
        }

        @media (max-width: 480px) {
            .otp-container {
                gap: 8px;
            }
            .otp-box {
                width: 42px;
                height: 54px;
                font-size: 24px;
            }
        }

        #displayEmail {
            color: #7dd3fc;
            font-weight: 600;
            background: #0f172a;
            padding: 2px 10px;
            border-radius: 24px;
            display: inline-block;
        }

        .otp-instruction {
            font-size: 0.8rem;
            color: #9ab3d1;
            margin-bottom: 8px;
        }
    </style>
</head>
<body>

<div class="split-layout">
    <div class="hero-image" aria-label="Night sky with sparkling stars"></div>
    
    <div class="form-panel">
        <div class="form-container">
            <div class="brand-logo">
                <img src="../img_logo/playzo.w.png" alt="Stellar Secure Logo" 
                     onerror="this.src='https://cdn-icons-png.flaticon.com/512/6566/6566555.png'; this.style.width='80px'; this.style.height='80px';">
                <h3><i class="fas fa-shield-alt"></i> encrypted gateway</h3>
            </div>
            
            <div class="verify-title">
                <h1><i class="fas fa-envelope-circle-check"></i> Verify Your Business Email</h1>
                <p>Verify your email to activate your seller Form access.</p>
            </div>
            
            <div id="stepEmail">
                <div class="input-group">
                    <div class="input-icon">
                        <i class="fas fa-envelope"></i>
                        <input type="email" id="emailInput" placeholder="your@business.com" autocomplete="email">
                    </div>
                </div>
                <button id="sendOtpBtn">
                    <i class="fas fa-paper-plane"></i> Send verification code
                </button>
                <div class="info-badge">
                    <span><i class="fas fa-clock"></i> 5 min validity</span>
                    <span><i class="fas fa-lock"></i> Gmail secure SMTP</span>
                    <span><i class="fas fa-sms"></i> SMS support coming</span>
                </div>
            </div>
            
            <div id="stepOtp" class="hidden">
                <div class="flex-center" style="margin-bottom: 5px;">
                    <i class="fas fa-envelope-open-text" style="font-size: 38px; color:#38bdf8; opacity:0.9;"></i>
                </div>
                <p class="otp-instruction" style="text-align:center;">
                    ✉️ A 6‑digit code was sent to <strong id="displayEmail"></strong>
                </p>
                <div class="flex-center" style="margin-bottom: 8px;">
                    <div class="helper-note">
                        <i class="fas fa-info-circle"></i> Check spam folder • Auto-verifies when all digits filled
                    </div>
                </div>
                <div class="otp-container" id="otpContainer">
                    <input type="text" maxlength="1" class="otp-box" id="otp1" autofocus>
                    <input type="text" maxlength="1" class="otp-box" id="otp2">
                    <input type="text" maxlength="1" class="otp-box" id="otp3">
                    <input type="text" maxlength="1" class="otp-box" id="otp4">
                    <input type="text" maxlength="1" class="otp-box" id="otp5">
                    <input type="text" maxlength="1" class="otp-box" id="otp6">
                </div>
                <div class="flex-center">
                    <div class="timer" id="timerDisplay">⏳ OTP expires in 05:00</div>
                </div>
                <button id="verifyOtpBtn">
                    <i class="fas fa-shield-haltered"></i> Verify & continue
                </button>
                <div class="flex-center">
                    <span id="resendOtpLink" class="resend-link">
                        <i class="fas fa-rotate-right"></i> Resend OTP
                    </span>
                </div>
                <div class="flex-center" style="margin-top: 16px;">
                    <span class="helper-note">
                        <i class="fas fa-sms"></i> Need SMS code? Contact support for backup options
                    </span>
                </div>
            </div>
            
            <div id="messageBox" class="message-area hidden"></div>
        </div>
    </div>
</div>

<script>
    // DOM elements
    const stepEmailDiv = document.getElementById('stepEmail');
    const stepOtpDiv = document.getElementById('stepOtp');
    const sendBtn = document.getElementById('sendOtpBtn');
    const emailInput = document.getElementById('emailInput');
    const verifyBtn = document.getElementById('verifyOtpBtn');
    const resendLink = document.getElementById('resendOtpLink');
    const messageBox = document.getElementById('messageBox');
    const displayEmailSpan = document.getElementById('displayEmail');
    const timerDisplay = document.getElementById('timerDisplay');

    const otpInputs = [
        document.getElementById('otp1'),
        document.getElementById('otp2'),
        document.getElementById('otp3'),
        document.getElementById('otp4'),
        document.getElementById('otp5'),
        document.getElementById('otp6')
    ];

    let expiryTimer = null;
    let currentEmail = '';

    function showMessage(msg, type) {
        messageBox.innerText = msg;
        messageBox.classList.remove('hidden', 'success', 'error');
        messageBox.classList.add(type === 'success' ? 'success' : 'error');
        setTimeout(() => {
            if (messageBox) messageBox.classList.add('hidden');
        }, 5000);
    }

    function clearOtpBoxes() {
        otpInputs.forEach(inp => { if(inp) inp.value = ''; });
        if (otpInputs[0]) otpInputs[0].focus();
    }

    function getOtpValue() {
        let otp = '';
        for (let i = 0; i < otpInputs.length; i++) {
            const val = otpInputs[i]?.value.trim();
            if (!val || !/^\d$/.test(val)) return null;
            otp += val;
        }
        return otp.length === 6 ? otp : null;
    }

    // Auto-submit when all 6 digits are entered
    function handleAutoSubmit() {
        const otp = getOtpValue();
        if (otp && verifyBtn && !verifyBtn.disabled) {
            verifyOtpRequest();
        }
    }

    function setupOtpNavigation() {
        otpInputs.forEach((input, idx) => {
            if (!input) return;
            input.addEventListener('input', () => {
                if (input.value.length === 1 && idx < otpInputs.length - 1) {
                    otpInputs[idx + 1].focus();
                }
                // Auto-submit trigger after last input gets a digit
                if (idx === otpInputs.length - 1 && input.value.length === 1) {
                    handleAutoSubmit();
                }
            });
            input.addEventListener('keydown', (e) => {
                if (e.key === 'Backspace' && input.value === '' && idx > 0) {
                    otpInputs[idx - 1].focus();
                }
            });
            input.addEventListener('keypress', (e) => {
                if (!/[0-9]/.test(e.key)) e.preventDefault();
            });
        });
    }

    function startOtpTimer() {
        if (expiryTimer) clearInterval(expiryTimer);
        let secondsLeft = 300;
        function updateDisplay() {
            const mins = Math.floor(secondsLeft / 60);
            const secs = secondsLeft % 60;
            timerDisplay.innerHTML = `⏳ OTP expires in ${mins.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
            if (secondsLeft <= 0) {
                clearInterval(expiryTimer);
                timerDisplay.innerHTML = '⌛ OTP expired! Request new code.';
                timerDisplay.style.color = '#f87171';
            }
        }
        updateDisplay();
        expiryTimer = setInterval(() => {
            if (secondsLeft <= 1) {
                clearInterval(expiryTimer);
                timerDisplay.innerHTML = '⌛ Code expired. Resend OTP.';
                timerDisplay.style.color = '#f87171';
            } else {
                secondsLeft--;
                updateDisplay();
            }
        }, 1000);
    }

    function stopTimer() {
        if (expiryTimer) {
            clearInterval(expiryTimer);
            expiryTimer = null;
        }
    }

    async function sendOtpRequest(email, isResend = false) {
        sendBtn.disabled = true;
        sendBtn.innerHTML = '<i class="fas fa-spinner fa-pulse"></i> Sending...';
        
        try {
            const formData = new URLSearchParams();
            formData.append('api_action', 'send_otp');
            formData.append('email', email);
            
            const response = await fetch(window.location.href, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: formData
            });
            
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }
            
            const data = await response.json();
            if (data.status === 'success') {
                showMessage(data.message, 'success');
                currentEmail = email;
                
                if (!isResend) {
                    stepEmailDiv.classList.add('hidden');
                    stepOtpDiv.classList.remove('hidden');
                    displayEmailSpan.innerText = email;
                    clearOtpBoxes();
                    startOtpTimer();
                } else {
                    clearOtpBoxes();
                    stopTimer();
                    startOtpTimer();
                    showMessage('🔄 New OTP delivered! Check your inbox or spam folder.', 'success');
                }
            } else {
                showMessage(data.message, 'error');
            }
        } catch (err) {
            console.error('Fetch error:', err);
            showMessage('⚠️ Network error: Could not reach server. Check your connection and try again.', 'error');
        } finally {
            sendBtn.disabled = false;
            sendBtn.innerHTML = '<i class="fas fa-paper-plane"></i> Send verification code';
        }
    }

    async function verifyOtpRequest() {
        const otpCode = getOtpValue();
        if (!otpCode) {
            showMessage('Please fill all 6 digits of the verification code', 'error');
            return;
        }
        
        verifyBtn.disabled = true;
        verifyBtn.innerHTML = '<i class="fas fa-spinner fa-pulse"></i> Verifying...';
        
        try {
            const formData = new URLSearchParams();
            formData.append('api_action', 'verify_otp');
            formData.append('otp', otpCode);
            
            const response = await fetch(window.location.href, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: formData
            });
            
            if (!response.ok) throw new Error(`HTTP ${response.status}`);
            
            const data = await response.json();
            if (data.status === 'success') {
                showMessage(data.message, 'success');
                stopTimer();
                setTimeout(() => {
                    window.location.href = 'seller_register.php';
                }, 1300);
            } else {
                showMessage(data.message, 'error');
                verifyBtn.disabled = false;
                verifyBtn.innerHTML = '<i class="fas fa-shield-haltered"></i> Verify & continue';
                clearOtpBoxes();
            }
        } catch (err) {
            showMessage('Verification failed due to network issue. Please retry.', 'error');
            verifyBtn.disabled = false;
            verifyBtn.innerHTML = '<i class="fas fa-shield-haltered"></i> Verify & continue';
        }
    }

    // Event listeners
    sendBtn.addEventListener('click', () => {
        const email = emailInput.value.trim();
        if (!email) {
            showMessage('Please enter your email address.', 'error');
            return;
        }
        const emailRegex = /^[^\s@]+@([^\s@]+\.)+[^\s@]+$/;
        if (!emailRegex.test(email)) {
            showMessage('Enter a valid email address (e.g., name@domain.com)', 'error');
            return;
        }
        sendOtpRequest(email, false);
    });

    verifyBtn.addEventListener('click', verifyOtpRequest);
    
    resendLink.addEventListener('click', () => {
        if (!currentEmail) {
            showMessage('Session expired. Please restart from email step.', 'error');
            setTimeout(() => window.location.reload(), 1200);
            return;
        }
        sendOtpRequest(currentEmail, true);
    });

    // Paste support for OTP
    if (otpInputs[0]) {
        otpInputs[0].addEventListener('paste', (e) => {
            e.preventDefault();
            const pasteData = (e.clipboardData || window.clipboardData).getData('text');
            if (pasteData && /^\d{6}$/.test(pasteData)) {
                const digits = pasteData.split('');
                otpInputs.forEach((input, idx) => {
                    if (input && digits[idx]) input.value = digits[idx];
                });
                if (otpInputs[otpInputs.length - 1]) otpInputs[otpInputs.length - 1].focus();
                // Auto-submit after paste
                handleAutoSubmit();
            } else {
                showMessage('Paste a valid 6-digit OTP', 'error');
            }
        });
    }
    
    setupOtpNavigation();
    otpInputs.forEach(inp => { if(inp) inp.setAttribute('autocomplete', 'off'); });
    messageBox.classList.add('hidden');
</script>
</body>
</html>