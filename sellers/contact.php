<?php
session_start();
require_once __DIR__ . '/../databases/db.php';

if (!isset($_SESSION['seller_id'])) {
    header('Location: register.php');
    exit;
}

$seller_id = (int)$_SESSION['seller_id'];
$db = getDB();

$stmt = $db->prepare("SELECT alt_phone, whatsapp, emergency_contact FROM sellers WHERE id = ?");
$stmt->bind_param('i', $seller_id);
$stmt->execute();
$seller = $stmt->get_result()->fetch_assoc();
$stmt->close();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $alt_phone   = trim($_POST['alt_phone'] ?? '');
    $whatsapp    = trim($_POST['whatsapp'] ?? '');
    $emergency   = trim($_POST['emergency_contact'] ?? '');

    // Validate alternative phone if provided
    if (!empty($alt_phone) && !preg_match('/^[\+\d\s\-\(\)]{8,20}$/', $alt_phone)) {
        $error = 'Invalid alternative phone format. Use full international format (e.g., +9779812345678).';
    }
    // Validate WhatsApp if provided
    elseif (!empty($whatsapp) && !preg_match('/^[\+\d\s\-\(\)]{8,20}$/', $whatsapp)) {
        $error = 'Invalid WhatsApp number format. Use full international format.';
    }
    else {
        $stmt = $db->prepare("UPDATE sellers SET alt_phone = ?, whatsapp = ?, emergency_contact = ? WHERE id = ?");
        $stmt->bind_param('sssi', $alt_phone, $whatsapp, $emergency, $seller_id);
        if ($stmt->execute()) {
            header('Location: review.php');
            exit;
        } else {
            $error = 'Database error: ' . $db->error;
        }
        $stmt->close();
    }
}

require_once __DIR__ . '/sidenav.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Step 5: Contact & Support | SportGhar Seller</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Fraunces:wght@400;700&display=swap" rel="stylesheet">
    <!-- intl-tel-input CSS and JS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/intl-tel-input@24.0.0/build/css/intlTelInput.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        html, body {
            height: 100%;
            font-family: 'Plus Jakarta Sans', sans-serif;
            overflow: hidden;
        }

        :root { --sidenav-width: 240px; }

        .page-wrapper {
            position: fixed;
            top: 0;
            left: var(--sidenav-width);
            right: 0;
            bottom: 0;
            display: flex;
        }

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

        .panel-right {
            width: 520px;
            flex-shrink: 0;
            background: #fff;
            height: 100%;
            padding: 32px 28px;
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
            margin-bottom: 6px;
            line-height: 1.2;
        }

        .subtitle {
            color: #8A7D72;
            font-size: 12px;
            margin-bottom: 24px;
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

        .form-group { margin-bottom: 20px; }

        label {
            font-weight: 700;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: block;
            margin-bottom: 6px;
            color: #5A4A3F;
        }

        /* intl-tel-input styling */
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
            border-radius: 10px;
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 13px;
            background: #FDFAF7;
            color: #1C1612;
            outline: none;
            transition: border-color .15s, box-shadow .15s;
        }
        input:focus, input[type="tel"]:focus {
            border-color: #C0392B;
            box-shadow: 0 0 0 3px rgba(192,57,43,0.08);
        }

        .button-group {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 20px;
        }

        .btn {
            background: #C0392B;
            color: white;
            padding: 10px 24px;
            border: none;
            border-radius: 100px;
            font-weight: 700;
            font-size: 13px;
            cursor: pointer;
            transition: background .15s;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }
        .btn-back {
            background: #F5F3EF;
            color: #8A7D72;
        }
        .btn-back:hover { background: #EBE8E2; color: #1C1612; }
        .btn:hover { background: #a93226; }
        .btn:active { transform: scale(.98); }

        @media (max-width: 900px) {
            .page-wrapper {
                left: 0;
                flex-direction: column;
            }
            .panel-left { min-height: 280px; position: relative; }
            .panel-right { width: 100%; height: auto; flex: 1; justify-content: flex-start; }
            .caption { font-size: 1.2rem; padding: 6px 18px; }
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
            <div class="caption">📞 We're Here to Help</div>
            <div class="sub-caption">Fast support • WhatsApp enabled • Emergency contact</div>
        </div>
    </div>

    <!-- RIGHT PANEL: Contact & Support Form -->
    <div class="panel-right">
        <div class="step-badge">● STEP 5 OF 6</div>
        <div class="step-dots">
            <span class="on"></span>
            <span class="on"></span>
            <span class="on"></span>
            <span class="on"></span>
            <span class="on"></span>
            <span></span>
        </div>

        <h1>Contact & Support</h1>
        <p class="subtitle">Additional contact details for better support and communication.</p>

        <?php if ($error): ?>
            <div class="error-box">⚠️ <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" id="contactForm">
            <div class="form-group">
                <label>Alternative Phone (optional)</label>
                <input type="tel" name="alt_phone_raw" id="altPhoneInput"
                       value="<?= htmlspecialchars($seller['alt_phone'] ?? '') ?>"
                       placeholder="e.g., +977 9812345678">
                <input type="hidden" name="alt_phone" id="altPhoneHidden">
                <small style="display:block; font-size:10px; color:#8A7D72; margin-top:4px;">
                    International format with country code
                </small>
            </div>

            <div class="form-group">
                <label>WhatsApp Number (optional)</label>
                <input type="tel" name="whatsapp_raw" id="whatsappInput"
                       value="<?= htmlspecialchars($seller['whatsapp'] ?? '') ?>"
                       placeholder="e.g., +977 9812345678">
                <input type="hidden" name="whatsapp" id="whatsappHidden">
                <small style="display:block; font-size:10px; color:#8A7D72; margin-top:4px;">
                    For instant chat support
                </small>
            </div>

            <div class="form-group">
                <label>Emergency Contact (optional)</label>
                <input type="text" name="emergency_contact" 
                       value="<?= htmlspecialchars($seller['emergency_contact'] ?? '') ?>" 
                       placeholder="Name — Phone Number / Relationship">
                <small style="display:block; font-size:10px; color:#8A7D72; margin-top:4px;">
                    In case we need to reach you urgently
                </small>
            </div>

            <div class="button-group">
                <a href="business_info.php" class="btn btn-back">← Back</a>
                <button type="submit" class="btn">Review Application →</button>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/intl-tel-input@24.0.0/build/js/intlTelInput.min.js"></script>
<script>
    // Initialize intl-tel-input for Alternative Phone
    const altInput = document.querySelector("#altPhoneInput");
    let altIti;

    // Initialize for WhatsApp
    const waInput = document.querySelector("#whatsappInput");
    let waIti;

    function setupIti(input, initialCountry = "np") {
        return window.intlTelInput(input, {
            initialCountry: initialCountry,
            separateDialCode: true,
            utilsScript: "https://cdn.jsdelivr.net/npm/intl-tel-input@24.0.0/build/js/utils.js",
            preferredCountries: ["np", "in", "us", "gb", "au", "ae", "my", "ca", "sg", "sa"],
            nationalMode: false,
            autoPlaceholder: "polite",
            formatOnDisplay: true
        });
    }

    document.addEventListener("DOMContentLoaded", function() {
        altIti = setupIti(altInput);
        waIti = setupIti(waInput);

        // Set existing numbers if any
        let existingAlt = "<?= htmlspecialchars($seller['alt_phone'] ?? '') ?>";
        if (existingAlt && existingAlt.trim() !== "") {
            altIti.setNumber(existingAlt);
        }
        let existingWa = "<?= htmlspecialchars($seller['whatsapp'] ?? '') ?>";
        if (existingWa && existingWa.trim() !== "") {
            waIti.setNumber(existingWa);
        }

        const form = document.getElementById("contactForm");
        const altHidden = document.getElementById("altPhoneHidden");
        const waHidden = document.getElementById("whatsappHidden");

        form.addEventListener("submit", function(e) {
            let valid = true;
            let errorMsg = "";

            // Validate Alternative Phone if not empty
            const altRaw = altInput.value.trim();
            if (altRaw !== "") {
                if (!altIti.isValidNumber()) {
                    valid = false;
                    errorMsg = "Alternative phone: Please enter a complete, valid international number.";
                } else {
                    altHidden.value = altIti.getNumber();
                }
            } else {
                altHidden.value = "";
            }

            // Validate WhatsApp if not empty
            const waRaw = waInput.value.trim();
            if (waRaw !== "") {
                if (!waIti.isValidNumber()) {
                    valid = false;
                    errorMsg = "WhatsApp: Please enter a complete, valid international number.";
                } else {
                    waHidden.value = waIti.getNumber();
                }
            } else {
                waHidden.value = "";
            }

            if (!valid) {
                e.preventDefault();
                // Show error inline (create or update error box)
                let errorDiv = document.querySelector(".error-box");
                if (!errorDiv) {
                    errorDiv = document.createElement("div");
                    errorDiv.className = "error-box";
                    document.querySelector(".panel-right form").insertBefore(errorDiv, document.querySelector(".panel-right form").firstChild);
                }
                errorDiv.innerHTML = "⚠️ " + errorMsg;
                errorDiv.style.display = "block";
                return false;
            }
            return true;
        });
    });
</script>
</body>
</html>