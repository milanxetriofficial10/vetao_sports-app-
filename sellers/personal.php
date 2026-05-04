<?php
session_start();
require_once __DIR__ . '/../databases/db.php';

if (!isset($_SESSION['seller_id'])) {
    header('Location: register.php');
    exit;
}

$seller_id = (int)$_SESSION['seller_id'];
$db = getDB();

$stmt = $db->prepare("SELECT full_name, email, phone FROM sellers WHERE id = ?");
$stmt->bind_param('i', $seller_id);
$stmt->execute();
$seller = $stmt->get_result()->fetch_assoc();
$stmt->close();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name'] ?? '');
    $email     = trim($_POST['email'] ?? '');
    $phone     = trim($_POST['phone'] ?? '');

    if (strlen($full_name) < 2) {
        $error = 'Full name is required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Valid email required.';
    } elseif (empty($phone)) {
        $error = 'Phone number is required.';
    } else {
        // Server-side phone validation: must start with +, contain only digits, spaces, hyphens, parentheses, min 8 max 20 chars
        if (!preg_match('/^[\+\d\s\-\(\)]{8,20}$/', $phone)) {
            $error = 'Invalid phone format. Please use full international format.';
        } else {
            // Check if email already used by another seller
            $stmt = $db->prepare("SELECT id FROM sellers WHERE email = ? AND id != ?");
            $stmt->bind_param('si', $email, $seller_id);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows > 0) {
                $error = 'This email address is already registered by another seller.';
            } else {
                $stmt->close();
                // Check if phone already used by another seller
                $stmt = $db->prepare("SELECT id FROM sellers WHERE phone = ? AND id != ?");
                $stmt->bind_param('si', $phone, $seller_id);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($result->num_rows > 0) {
                    $error = 'This phone number is already registered by another seller.';
                } else {
                    $stmt->close();
                    // Update seller information
                    $stmt = $db->prepare("UPDATE sellers SET full_name=?, email=?, phone=? WHERE id=?");
                    $stmt->bind_param('sssi', $full_name, $email, $phone, $seller_id);
                    if ($stmt->execute()) {
                        header("Location: shop.php");
                        exit;
                    } else {
                        $error = "Database error.";
                    }
                    $stmt->close();
                }
            }
            $stmt->close();
        }
    }
}

require_once __DIR__ . '/sidenav.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Step 1: Personal Information | SportGhar Seller</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&family=Fraunces:ital,wght@0,400;0,700;1,400&display=swap" rel="stylesheet">
    <!-- intl-tel-input CSS and JS (flags + validation) -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/intl-tel-input@24.0.0/build/css/intlTelInput.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        html, body {
            height: 100%;
            font-family: 'Plus Jakarta Sans', sans-serif;
            overflow: hidden;
        }

        /* Sidenav width — adjust to match your actual sidenav */
        :root { --sidenav-width: 240px; }

        /* Wrapper that sits to the RIGHT of the sidenav */
        .page-wrapper {
            position: fixed;
            top: 0;
            left: var(--sidenav-width);
            right: 0;
            bottom: 0;
            display: flex;
        }

        /* LEFT PANEL: Full-bleed image (no border, no radius) */
        .panel-left {
            flex: 1;
            position: relative;
            overflow: hidden;
        }

        .full-image {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        /* Overlay text on the image */
        .image-overlay {
            position: absolute;
            bottom: 30px;
            left: 0;
            right: 0;
            text-align: center;
            z-index: 2;
            pointer-events: none;
        }

        .caption {
            font-family: 'Fraunces', serif;
            font-size: 1.6rem;
            font-weight: 700;
            color: #FFEAC5;
            text-shadow: 0 2px 12px rgba(0,0,0,0.4);
            background: rgba(0,0,0,0.4);
            display: inline-block;
            padding: 8px 24px;
            border-radius: 60px;
            backdrop-filter: blur(6px);
        }

        .sub-caption {
            font-size: 13px;
            color: #FFEAC5;
            margin-top: 12px;
            letter-spacing: 0.5px;
            font-weight: 500;
            background: rgba(0,0,0,0.3);
            display: inline-block;
            padding: 5px 16px;
            border-radius: 40px;
            backdrop-filter: blur(3px);
        }

        /* RIGHT PANEL: form styling (unchanged) */
        .panel-right {
            width: 400px;
            flex-shrink: 0;
            background: #fff;
            height: 100%;
            padding: 32px 24px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            overflow-y: auto;
            box-shadow: -4px 0 24px rgba(0,0,0,0.15);
        }

        .step-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            background: rgba(192,57,43,0.09);
            color: #C0392B;
            font-size: 10.5px;
            font-weight: 600;
            padding: 4px 11px;
            border-radius: 100px;
            margin-bottom: 10px;
            letter-spacing: .4px;
        }

        .step-dots {
            display: flex;
            gap: 4px;
            margin-bottom: 16px;
        }

        .step-dots span {
            display: block;
            width: 22px;
            height: 3px;
            border-radius: 2px;
            background: #E8E4DC;
        }

        .step-dots span.on { background: #C0392B; }

        h1 {
            font-family: 'Fraunces', serif;
            font-size: 1.3rem;
            color: #1C1612;
            margin-bottom: 4px;
            line-height: 1.2;
        }

        .subtitle {
            color: #8A7D72;
            font-size: 12px;
            margin-bottom: 22px;
            line-height: 1.65;
        }

        .error-box {
            font-size: 12px;
            color: #C0392B;
            background: #FFF5F5;
            border-left: 3px solid #E74C3C;
            padding: 9px 12px;
            border-radius: 8px;
            margin-bottom: 14px;
        }

        .form-group { margin-bottom: 13px; }

        label {
            display: block;
            font-size: 10px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: .55px;
            color: #5A4A3F;
            margin-bottom: 5px;
        }

        input[type="text"],
        input[type="email"] {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #E0DBD4;
            border-radius: 8px;
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 12.5px;
            background: #FDFAF7;
            color: #1C1612;
            outline: none;
            transition: border-color .15s, box-shadow .15s;
        }

        /* phone input container adjustments for intl-tel-input */
        .iti {
            width: 100%;
            display: block;
        }
        .iti__country-container {
            background: #FDFAF7;
        }
        input[type="tel"] {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #E0DBD4;
            border-radius: 8px;
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 12.5px;
            background: #FDFAF7;
            color: #1C1612;
            outline: none;
            transition: border-color .15s, box-shadow .15s;
        }
        input:focus, input[type="tel"]:focus {
            border-color: #C0392B;
            box-shadow: 0 0 0 3px rgba(192,57,43,0.08);
        }
        input::placeholder { color: #C8BFB8; }

        .btn {
            width: 100%;
            background: #C0392B;
            color: #fff;
            padding: 11px;
            border: none;
            border-radius: 8px;
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-weight: 600;
            font-size: 13.5px;
            cursor: pointer;
            margin-top: 8px;
            transition: background .15s;
        }

        .btn:hover  { background: #a93226; }
        .btn:active { transform: scale(.99); }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .page-wrapper {
                left: 0;
                flex-direction: column;
            }
            .panel-left  { flex: none; height: auto; min-height: 380px; padding: 20px; }
            .panel-right { width: 100%; height: auto; flex: 1; }
            .hero-jersey {
                max-width: 280px;
            }
            .caption {
                font-size: 1rem;
                padding: 6px 18px;
                margin-top: 18px;
            }
            .sub-caption {
                font-size: 10px;
            }
        }

        @media (max-width: 480px) {
            .hero-jersey {
                max-width: 220px;
            }
        }
    </style>
</head>
<body>

<div class="page-wrapper">

    <!-- LEFT PANEL: Full width/height sports image -->
    <div class="panel-left">
        <img class="full-image" 
             src="https://images.unsplash.com/photo-1589487391730-58f20eb2c308?w=1200&h=800&fit=crop" 
             alt="FC Barcelona Jersey - Contact support background"
             loading="eager">
        <div class="image-overlay">
            <div class="caption">Your Personal Information</div>
            <div class="sub-caption">please fill in all the details ... PlayZo Nepal</div>
        </div>
    </div>

    <!-- RIGHT PANEL: Personal Information Form -->
    <div class="panel-right">

        <div class="step-badge">● STEP 1 OF 6</div>

        <div class="step-dots">
            <span class="on"></span>
            <span></span><span></span><span></span><span></span><span></span>
        </div>

        <h1>Personal Information</h1>
        <p class="subtitle">Tell us about yourself. This will appear on your seller profile.</p>

        <?php if ($error): ?>
            <div class="error-box">⚠️ <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" id="profileForm">
            <div class="form-group">
                <label>Full Name</label>
                <input type="text" name="full_name"
                       value="<?= htmlspecialchars($seller['full_name'] ?? '') ?>"
                       placeholder="e.g. Gobind Adhikari" required>
            </div>
            <div class="form-group">
                <label>Email Address</label>
                <input type="email" name="email"
                       value="<?= htmlspecialchars($seller['email'] ?? '') ?>"
                       placeholder="you@example.com" required>
            </div>
            <div class="form-group">
                <label>Phone Number (International with country code)</label>
                <input type="tel" name="phone_raw" id="phoneInput"
                       value="<?= htmlspecialchars($seller['phone'] ?? '') ?>"
                       placeholder="+977 9812345678">
                <!-- Hidden input to store the full international number for submission -->
                <input type="hidden" name="phone" id="phoneHidden">
            </div>
            <button type="submit" class="btn">Continue →</button>
        </form>

    </div>
</div>

<!-- intl-tel-input library -->
<script src="https://cdn.jsdelivr.net/npm/intl-tel-input@24.0.0/build/js/intlTelInput.min.js"></script>
<script>
    // Initialize intl-tel-input
    const phoneInput = document.querySelector("#phoneInput");
    let iti;

    // Helper: format existing phone number if present
    function setupIntlTelInput() {
        iti = window.intlTelInput(phoneInput, {
            initialCountry: "np",       // default to Nepal
            separateDialCode: true,     // show country code separate
            utilsScript: "https://cdn.jsdelivr.net/npm/intl-tel-input@24.0.0/build/js/utils.js",
            preferredCountries: ["np", "in", "us", "gb", "au", "ae", "my"],
            nationalMode: false,        // always show full international format
            autoPlaceholder: "polite",
            formatOnDisplay: true,
            // allow dropdown with flags
        });
        
        // If there's an existing phone number stored
        let existingPhone = "<?= htmlspecialchars($seller['phone'] ?? '') ?>";
        if (existingPhone && existingPhone.trim() !== "") {
            if (existingPhone.startsWith('+')) {
                iti.setNumber(existingPhone);
            } else {
                iti.setNumber(existingPhone);
                if (/^[0-9]{9,10}$/.test(existingPhone.replace(/\s/g, ''))) {
                    iti.setCountry("np");
                }
            }
        }
        return iti;
    }

    // Function to block alphabetic characters (a-z, A-Z) from being typed/pasted
    function restrictPhoneInputToAllowedChars(e) {
        let input = e.target;
        let rawValue = input.value;
        // Replace any letter (a-z, A-Z) with empty string
        let filtered = rawValue.replace(/[A-Za-z]/g, '');
        if (filtered !== rawValue) {
            input.value = filtered;
            // Trigger input event to let intl-tel-input update
            input.dispatchEvent(new Event('input', { bubbles: true }));
        }
    }

    // Wait for DOM
    document.addEventListener("DOMContentLoaded", function() {
        setupIntlTelInput();

        // Block letters from phone input (no alphabets allowed)
        phoneInput.addEventListener('input', restrictPhoneInputToAllowedChars);
        // Also block keypress for letters
        phoneInput.addEventListener('keypress', function(e) {
            const key = e.key;
            // Allow only digits, plus, space, hyphen, parentheses, backspace, delete, arrow keys, tab
            const allowed = /[\d\+\s\-\(\)]|Backspace|Delete|ArrowLeft|ArrowRight|Tab|Enter/;
            if (!allowed.test(key) && key.length === 1) {
                e.preventDefault();
            }
        });

        const form = document.getElementById("profileForm");
        const hiddenPhone = document.getElementById("phoneHidden");

        form.addEventListener("submit", function(e) {
            // Validate phone number with intl-tel-input
            if (!iti.isValidNumber()) {
                e.preventDefault();
                let errorDiv = document.querySelector(".error-box");
                if (!errorDiv) {
                    errorDiv = document.createElement("div");
                    errorDiv.className = "error-box";
                    document.querySelector(".panel-right form").insertBefore(errorDiv, document.querySelector(".panel-right form").firstChild);
                }
                errorDiv.innerHTML = "⚠️ Please enter a complete and valid international phone number (with country code).";
                errorDiv.style.display = "block";
                return false;
            }
            // Get full international number (e.g., +9779812345678)
            const fullNumber = iti.getNumber();
            hiddenPhone.value = fullNumber;
            phoneInput.value = fullNumber;
            return true;
        });
    });
</script>
</body>
</html>