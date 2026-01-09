<?php
require "config.php";

// Initialize variables to prevent undefined variable warnings
$statusMessage = "";
$successNotification = "";
$captchaResponse = null;
$emailInput = "";
$passwordInput = "";
$newUserId = null;
$verificationCode = "";
$emailSubject = "";
$emailBody = "";
$userData = null;
$emailSent = false;
$activeForm = 'login';

$lang = $_SESSION['lang'] ?? 'en';
// Check for success flag in URL
if (isset($_GET['signup_success'])) {
    $successNotification = msg('signup_success_notif');
} elseif (isset($_GET['info']) && $_GET['info'] == 'login_required') {
    $statusMessage = msg('msg_login_checkout');
    $activeForm = 'register';
}else {
    // No success message to display
}

// Registration Logic
if (isset($_POST['register'])) { 
    $activeForm = 'register';
    $captchaResponse = $_POST['cf-turnstile-response'] ?? null;

    // Verify Captcha
    if (!Security::verifyCaptcha($captchaResponse)) {
        $statusMessage = msg('error_captcha');
    } else {
        if ($_POST['password'] !== $_POST['confirm_password']) {
             $statusMessage = ($_SESSION['lang'] == 'fr') ? "Les mots de passe ne correspondent pas." : "Passwords do not match.";
        } elseif (!Security::isPasswordStrong($_POST['password'])) {
            // Captcha is valid, check password strength
            $statusMessage = msg('error_pwd_weak');
        } else {
            // Password is strong, proceed with user creation
            $usernameInput = str_pad(random_int(0, 99999999), 8, '0', STR_PAD_LEFT);  //create a random number for the folder name of the user
            $emailInput = $_POST['email'];
            $passwordInput = $_POST['password'];

            $profileData = [
                'firstname' => $_POST['firstname'] ?? '',
                'lastname'  => $_POST['lastname'] ?? '',
                'phone'     => $_POST['phone'] ?? '',
                'address'   => $_POST['address'] ?? '',
                'zipcode'   => $_POST['zipcode'] ?? '',
                'city'      => $_POST['city'] ?? '',
                'country'   => $_POST['country'] ?? ''
            ];

            // Attempt to register the user
            $newUserId = $userMgr->register($usernameInput, $emailInput, $passwordInput);

            // Check if registration returned a valid ID
            if ($newUserId) {

                // Generate 2FA Token
                $verificationCode = $tokenMgr->generate2FACode($newUserId);

                // Prepare Email
                $emailSubject = msg('email_subj_reg');

                if($_SESSION['lang'] == 'fr'){
                    $emailBody = "Votre code de validation : <b>$verificationCode</b>";
                }else{
                    $emailBody = "Verification code : <b>$verificationCode</b>";
                }
                
                
                // Attempt to send email
                $emailSent = $userMgr->sendEmail($emailInput, $emailSubject, $emailBody);

                if ($emailSent) {
                    // Set session for verification step
                    $_SESSION['auth_mode'] = 'REGISTER'; 
                    $_SESSION['pending_user_id'] = $newUserId;
                    
                    header("Location: verification.php");
                    exit;
                } else {
                    // Email failed to send
                    // Ideally initiate a rollback or delete user here
                    $statusMessage = msg('error_email_send');
                }
            } else {
                // Registration failed likely due to duplicate email or username
                $statusMessage = msg('error_duplicate');
            }
        }
    }
} else {
    // Not a registration request
}

// Login Logic
if (isset($_POST['login'])) {
    $activeForm = 'login';
    $captchaResponse = $_POST['cf-turnstile-response'] ?? null;

    // Verify Captcha
    if (!Security::verifyCaptcha($captchaResponse)) {
        $statusMessage = msg('error_captcha');
    } else {
        // Captcha is valid, verify credentials
        $emailInput = $_POST['email'];
        $passwordInput = $_POST['password'];

        $userData = $userMgr->login($emailInput, $passwordInput);
        
        // Verify if user data returned is a valid array and not empty
        if (is_array($userData) && !empty($userData)) {
            
            // Generate 2FA Token using the ID from the user array
            $verificationCode = $tokenMgr->generate2FACode($userData['id']);
            
            // Prepare Email
            $emailSubject = msg('email_subj_login');
            $emailBody = "Code : <b>$verificationCode</b>";
            
            // Attempt to send email
            $emailSent = $userMgr->sendEmail($userData['email'], $emailSubject, $emailBody);

            if ($emailSent) {
                // Set session for verification step
                $_SESSION['auth_mode'] = 'LOGIN';
                $_SESSION['pending_user_id'] = $userData['id'];
                
                header("Location: verification.php");
                exit;
            } else {
                // Email failed to send
                $statusMessage = msg('error_email_send');
            }
        } else {
            // Login failed, invalid credentials
            $statusMessage = msg('error_login_fail');
        }
    }
} else {
}
?>

<?php include 'header.php';?>
<head>
    <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
    <style>
    * { box-sizing: border-box; }
    body {
        font-family: 'Poppins', sans-serif;
        background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
        min-height: 100vh;
        display: flex; flex-direction: column; margin: 0; padding-top: 80px; 
    }

    .login-container {
        flex: 1; display: flex; align-items: center; justify-content: center; 
        width: 100%; padding: 20px; 
    }

    .wrapper {
        background: #fff;
        /* Hauteur automatique pour s'adapter au grand formulaire */
        min-height: 550px; 
        width: 500px; /* Un peu plus large pour les colonnes */
        max-width: 100%;
        border-radius: 20px;
        box-shadow: 0 15px 20px rgba(0,0,0,0.1);
        overflow: hidden; 
        position: relative;
        transition: height 0.3s ease;
    }

    .slide-container {
        width: 200%; display: flex;
        transition: transform 0.6s ease-in-out;
        align-items: flex-start; /* Aligner en haut pour éviter les vides */
    }
    .form-box { width: 50%; padding: 0 30px 30px 30px; box-sizing: border-box; }
    
    .title-text {
        font-size: 25px; font-weight: 600; text-align: center;
        margin-bottom: 20px; color: #333; margin-top: 20px;
    }
    
    /* Styles des champs */
    .field { height: 50px; width: 100%; margin-top: 15px; position: relative; }
    .field input {
        height: 100%; width: 100%; outline: none; padding-left: 15px;
        border-radius: 5px; border: 1px solid lightgrey; font-size: 15px;
        transition: all 0.3s ease;
    }
    .field input:focus { border-color: #4A90E2; box-shadow: 0 0 5px rgba(74, 144, 226, 0.3); }

    /* Grille pour mettre 2 champs sur la même ligne (Nom/Prénom, Ville/CP) */
    .row-group { display: flex; gap: 10px; margin-top: 15px; }
    .row-group .field { margin-top: 0; }
    
    button[type="submit"] {
        margin-top: 20px; width: 100%; height: 50px; border: none;
        border-radius: 5px; color: #fff; font-size: 18px; font-weight: 500;
        cursor: pointer; transition: all 0.3s ease; background: #4A90E2; 
    }
    button[type="submit"]:disabled { background: #ccc; cursor: not-allowed; }
    button[type="submit"]:hover:not(:disabled) { opacity: 0.9; }

    .link { text-align: center; margin-top: 20px; font-size: 14px; }
    .link a { color: #4A90E2; text-decoration: none; }
    .link a:hover { text-decoration: underline; }
    
    .pass-link { text-align: left; margin-top: 10px; font-size: 14px; }
    .pass-link a { color: #888; text-decoration: none; }
    
    .error { background: #ffebee; color: #c62828; padding: 10px; border-radius: 5px; margin: 10px; text-align: center; }
    .success { background: #e8f5e9; color: #2e7d32; padding: 10px; border-radius: 5px; margin: 10px; text-align: center; }
    
    .cf-turnstile { margin-top: 15px; display: flex; justify-content: center; }
    </style>
</head>

<div class="login-container">
    <div class="wrapper" id="mainWrapper">
        
        <?php if($statusMessage): ?>
            <div class="error"><?= htmlspecialchars($statusMessage) ?></div>
        <?php endif; ?>
        <?php if($successNotification): ?>
            <div class="success"><?= htmlspecialchars($successNotification) ?></div>
        <?php endif; ?>

        <div class="slide-container" id="slideContainer">
            
            <div class="form-box login">
                <div class="title-text"><?= msg('title_login') ?></div>
                <form method="post" action="#">
                    <div class="field">
                        <input type="email" name="email" placeholder="<?= msg('placeholder_email') ?>" required>
                    </div>
                    <div class="field">
                        <input type="password" name="password" placeholder="<?= msg('placeholder_password') ?>" required>
                    </div>
                    
                    <div class="pass-link">
                        <a href="forgot_password.php"><?= msg('link_forgot_pass') ?></a>
                    </div>

                    <div class="cf-turnstile" data-sitekey="" data-callback="onCaptchaSuccessLog"></div>
                    
                    <button type="submit" name="login" id="btn-log" disabled><?= msg('btn_login') ?></button>
                    
                    <div class="link">
                       <?= msg('ask_no_account') ?> <a href="#" onclick="switchToRegister(event)"><?= msg('action_signup') ?></a>
                    </div>
                </form>
            </div>

            <div class="form-box register">
                <div class="title-text"><?= msg('title_register') ?></div>
                <form method="post" action="#">
                    
                    <div class="row-group">
                        <div class="field">
                            <input type="text" name="firstname" placeholder="<?= msg('placeholder_firstname') ?>" required>
                        </div>
                        <div class="field">
                            <input type="text" name="lastname" placeholder="<?= msg('placeholder_lastname') ?>" required>
                        </div>
                    </div>

                    <div class="field">
                        <input type="email" name="email" placeholder="<?= msg('placeholder_email') ?>" required>
                    </div>

                    <div class="field">
                        <input type="tel" name="phone" placeholder="<?= msg('placeholder_phone') ?>" required>
                    </div>

                    <div class="field">
                        <input type="text" name="address" placeholder="<?= msg('placeholder_address') ?>" required>
                    </div>

                    <div class="row-group">
                        <div class="field">
                            <input type="text" name="zipcode" placeholder="<?= msg('placeholder_zipcode') ?>" required>
                        </div>
                        <div class="field">
                            <input type="text" name="city" placeholder="<?= msg('placeholder_city') ?>" required>
                        </div>
                    </div>

                    <div class="field">
                        <input type="text" name="country" placeholder="<?= msg('placeholder_country') ?>" required>
                    </div>

                    <div class="row-group">
                        <div class="field">
                            <input type="password" name="password" placeholder="<?= msg('placeholder_password') ?>" required>
                        </div>
                        <div class="field">
                            <input type="password" name="confirm_password" placeholder="Confirm" required>
                        </div>
                    </div>

                    <div class="cf-turnstile" data-sitekey="" data-callback="onCaptchaSuccessReg"></div>
                    
                    <button type="submit" name="register" id="btn-reg" disabled><?= msg('btn_register') ?></button>
                    
                    <div class="link">
                        <?= msg('ask_has_account') ?> <a href="#" onclick="switchToLogin(event)"><?= msg('action_login') ?></a>
                    </div>
                </form>
            </div>

        </div>
    </div>
</div>

<?php include "footer.php";?>

<script>
    const slideContainer = document.getElementById("slideContainer");
    const wrapper = document.getElementById("mainWrapper");
    
    // Ajuster la hauteur dynamiquement car le register est beaucoup plus grand
    function adjustHeight(formType) {
        if (formType === 'register') {
            wrapper.style.minHeight = "850px"; // Grand formulaire
        } else {
            wrapper.style.minHeight = "550px"; // Petit formulaire
        }
    }

    <?php if($activeForm === 'register'): ?>
        slideContainer.style.transform = "translateX(-50%)";
        adjustHeight('register');
    <?php else: ?>
        slideContainer.style.transform = "translateX(0%)";
        adjustHeight('login');
    <?php endif; ?>

    function switchToRegister(e) {
        e.preventDefault();
        slideContainer.style.transform = "translateX(-50%)";
        adjustHeight('register');
    }

    function switchToLogin(e) {
        e.preventDefault();
        slideContainer.style.transform = "translateX(0%)";
        adjustHeight('login');
    }

    function onCaptchaSuccessReg() { document.getElementById('btn-reg').disabled = false; }
    function onCaptchaSuccessLog() { document.getElementById('btn-log').disabled = false; }
</script>