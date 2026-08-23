<?php
/**
 * Email verification codes for public signup (session-based, pre-account).
 */
if (!function_exists('rdv_reg_verification_ttl')) {
    function rdv_reg_verification_ttl() {
        return 900; // 15 minutes to enter the code
    }
}

if (!function_exists('rdv_reg_verified_ttl')) {
    function rdv_reg_verified_ttl() {
        return 1800; // 30 minutes to finish signup after verify
    }
}

if (!function_exists('rdv_reg_clear_verification')) {
    function rdv_reg_clear_verification() {
        unset(
            $_SESSION['reg_pending_email'],
            $_SESSION['reg_code_hash'],
            $_SESSION['reg_code_expires'],
            $_SESSION['reg_code_sent_at'],
            $_SESSION['reg_verified_email'],
            $_SESSION['reg_verified_until']
        );
    }
}

if (!function_exists('rdv_reg_verified_email')) {
    function rdv_reg_verified_email() {
        $email = strtolower(trim((string) ($_SESSION['reg_verified_email'] ?? '')));
        $until = (int) ($_SESSION['reg_verified_until'] ?? 0);
        if ($email === '' || $until < time()) {
            return '';
        }
        return $email;
    }
}

if (!function_exists('rdv_reg_is_email_verified')) {
    function rdv_reg_is_email_verified($email) {
        $verified = rdv_reg_verified_email();
        return $verified !== '' && strcasecmp($verified, trim((string) $email)) === 0;
    }
}

if (!function_exists('rdv_reg_email_taken')) {
    function rdv_reg_email_taken(mysqli $conn, $email) {
        $stmt = $conn->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $exists = (bool) $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $exists;
    }
}

if (!function_exists('rdv_reg_send_verification_code')) {
    function rdv_reg_send_verification_code(mysqli $conn, $email) {
        $email = strtolower(trim((string) $email));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'message' => 'Enter a valid email address.'];
        }
        if (rdv_reg_email_taken($conn, $email)) {
            return ['ok' => false, 'message' => 'That email is already registered. Try logging in.'];
        }

        $sentAt = (int) ($_SESSION['reg_code_sent_at'] ?? 0);
        if ($sentAt > 0 && (time() - $sentAt) < 60) {
            $wait = 60 - (time() - $sentAt);
            return ['ok' => false, 'message' => 'Please wait ' . $wait . ' seconds before requesting another code.'];
        }

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $_SESSION['reg_pending_email'] = $email;
        $_SESSION['reg_code_hash'] = hash('sha256', $code . '|' . $email);
        $_SESSION['reg_code_expires'] = time() + rdv_reg_verification_ttl();
        $_SESSION['reg_code_sent_at'] = time();
        unset($_SESSION['reg_verified_email'], $_SESSION['reg_verified_until']);

        if (!function_exists('sendSignupVerificationCode')) {
            require_once dirname(__DIR__) . '/helpers/email_functions.php';
        }
        if (!function_exists('sendSignupVerificationCode') || !sendSignupVerificationCode($email, $code)) {
            rdv_reg_clear_verification();
            return ['ok' => false, 'message' => 'Could not send the verification email. Check SMTP settings or try again shortly.'];
        }

        return ['ok' => true, 'message' => 'We sent a 6-digit code to ' . $email . '. Check your inbox and spam folder.'];
    }
}

if (!function_exists('rdv_reg_verify_code')) {
    function rdv_reg_verify_code($email, $code) {
        $email = strtolower(trim((string) $email));
        $code = preg_replace('/\D/', '', (string) $code);
        $pending = strtolower(trim((string) ($_SESSION['reg_pending_email'] ?? '')));
        $hash = (string) ($_SESSION['reg_code_hash'] ?? '');
        $expires = (int) ($_SESSION['reg_code_expires'] ?? 0);

        if ($pending === '' || $hash === '') {
            return ['ok' => false, 'message' => 'Request a verification code first.'];
        }
        if (strcasecmp($email, $pending) !== 0) {
            return ['ok' => false, 'message' => 'Use the same email address you requested the code for.'];
        }
        if ($expires < time()) {
            return ['ok' => false, 'message' => 'That code expired. Request a new one.'];
        }
        if (strlen($code) !== 6) {
            return ['ok' => false, 'message' => 'Enter the 6-digit code from your email.'];
        }

        $expected = hash('sha256', $code . '|' . $email);
        if (!hash_equals($hash, $expected)) {
            return ['ok' => false, 'message' => 'Incorrect code. Try again or request a new one.'];
        }

        $_SESSION['reg_verified_email'] = $email;
        $_SESSION['reg_verified_until'] = time() + rdv_reg_verified_ttl();
        unset($_SESSION['reg_code_hash'], $_SESSION['reg_code_expires']);

        return ['ok' => true, 'message' => 'Email verified. Complete your account details below.'];
    }
}
