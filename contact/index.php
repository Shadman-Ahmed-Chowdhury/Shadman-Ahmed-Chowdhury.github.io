<?php
/**
 * Contact form -> Cloudflare Turnstile verification -> forwards to Google Form.
 *
 * Served at /contact/ (this file is contact/index.php, so web servers serve
 * it as the directory index - the .php extension never appears in the URL).
 *
 * NOT for GitHub Pages: GitHub Pages serves static files only and cannot run
 * this script. Deploy the whole site (or at least this contact/ folder) to a
 * PHP-capable host that answers for the shadman.xyz domain so /contact/
 * resolves here.
 *
 * Fill in the TODO config values below before deploying.
 */

// Stopping the file execution for now.
exit;

// ---- Config: fill these in -------------------------------------------------
// Cloudflare Turnstile site key (public - safe to commit).
// TODO: replace with your real site key from the Cloudflare dashboard.
const TURNSTILE_SITE_KEY = 'XXXXXXXXXXXXXXXXXXXXXXXXXX';

// Cloudflare Turnstile secret key - keep this OUT of git. Set it as an
// environment variable on your host instead (e.g. in an .htaccess /
// php-fpm pool / hosting control panel), and read it here.
$turnstileSecretKey = getenv('TURNSTILE_SECRET_KEY') ?: '';

// Google Form details. Open your form, use "Get pre-filled link", fill in
// each field, copy the generated link, and read the entry.NNNNN IDs out of
// its query string.
// TODO: replace with your real Google Form ID and entry IDs.
const GOOGLE_FORM_ID = 'XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX';
const ENTRY_NAME = 'entry.111111111';
const ENTRY_EMAIL = 'entry.222222222';
const ENTRY_SUBJECT = 'entry.333333333';
const ENTRY_MESSAGE = 'entry.444444444';

// -----------------------------------------------------------------------------

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');
    $turnstileToken = $_POST['cf-turnstile-response'] ?? '';

    if ($name === '' || $message === '') {
        $errors[] = 'Name and message are required.';
    }
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please provide a valid email address.';
    }
    if ($turnstileToken === '') {
        $errors[] = 'Please complete the verification challenge.';
    }

    if (empty($errors)) {
        $verify = verifyTurnstile($turnstileToken, $turnstileSecretKey, $_SERVER['REMOTE_ADDR'] ?? '');
        if (!$verify) {
            $errors[] = 'Verification failed. Please try again.';
        }
    }

    if (empty($errors)) {
        $forwarded = submitToGoogleForm($name, $email, $subject, $message);
        if ($forwarded) {
            $success = true;
        } else {
            $errors[] = 'Could not send your message right now. Please try again later.';
        }
    }
}

/**
 * Verifies a Turnstile token with Cloudflare's siteverify endpoint.
 */
function verifyTurnstile(string $token, string $secretKey, string $remoteIp): bool
{
    if ($secretKey === '') {
        return false;
    }

    $ch = curl_init('https://challenges.cloudflare.com/turnstile/v0/siteverify');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query([
            'secret' => $secretKey,
            'response' => $token,
            'remoteip' => $remoteIp,
        ]),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
    ]);
    $response = curl_exec($ch);
    curl_close($ch);

    if ($response === false) {
        return false;
    }

    $result = json_decode($response, true);
    return !empty($result['success']);
}

/**
 * Posts the submitted fields to the Google Form's hidden formResponse endpoint.
 */
function submitToGoogleForm(string $name, string $email, string $subject, string $message): bool
{
    $url = 'https://docs.google.com/forms/d/e/' . GOOGLE_FORM_ID . '/formResponse';

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query([
            ENTRY_NAME => $name,
            ENTRY_EMAIL => $email,
            ENTRY_SUBJECT => $subject,
            ENTRY_MESSAGE => $message,
        ]),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
    ]);
    curl_exec($ch);
    $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // Google's formResponse endpoint returns 200 on a well-formed POST.
    return $statusCode >= 200 && $statusCode < 400;
}

$name = $_POST['name'] ?? '';
$email = $_POST['email'] ?? '';
$subject = $_POST['subject'] ?? '';
$message = $_POST['message'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Contact | Syed Shadman Ahmed Chowdhury</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="/styles.css">
    <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
    <style>
        .contact-page { max-width: 480px; margin: 0 auto; padding: 96px 24px 64px; }
        .contact-page .name { font-size: clamp(1.75rem, 4vw, 2.25rem); margin-bottom: 8px; }
        .contact-page .tagline { margin-bottom: 36px; }
        .contact-form label { display: block; font-weight: 500; font-size: 0.9375rem; margin: 18px 0 6px; color: var(--ink); }
        .contact-form input,
        .contact-form textarea {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid var(--line);
            border-radius: 6px;
            font-family: inherit;
            font-size: 0.9375rem;
            color: var(--ink);
            background: #fff;
        }
        .contact-form input:focus,
        .contact-form textarea:focus {
            outline: 2px solid var(--accent);
            outline-offset: 1px;
        }
        .contact-form textarea { min-height: 140px; resize: vertical; }
        .cf-turnstile { margin: 22px 0; }
        .form-errors { background: #fbeceb; color: #9a3b32; border-radius: 6px; padding: 12px 16px; margin: 20px 0; font-size: 0.9375rem; }
        .form-errors ul { margin: 0; padding-left: 18px; }
        .form-success p { font-size: 1.0625rem; line-height: 1.6; }
        .btn-send {
            background: var(--accent);
            color: #fff;
            border: none;
            border-radius: 6px;
            padding: 11px 22px;
            font-family: inherit;
            font-size: 0.9375rem;
            font-weight: 500;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-top: 22px;
            transition: background 0.2s ease;
        }
        .btn-send:hover { background: var(--accent-dark); }
    </style>
</head>
<body>
    <main class="contact-page">
        <h1 class="name">Contact</h1>

        <?php if ($success): ?>
            <p class="tagline">Thanks, <?= htmlspecialchars($name) ?> &mdash; your message is on its way.</p>
            <div class="form-success">
                <p><a href="/">&larr; Back to home</a></p>
            </div>
        <?php else: ?>

            <p class="tagline">Have a project in mind or just want to say hello? Send a message below.</p>

            <?php if (!empty($errors)): ?>
                <div class="form-errors">
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?= htmlspecialchars($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form class="contact-form" method="POST" action="">
                <label for="name">Name</label>
                <input type="text" id="name" name="name" value="<?= htmlspecialchars($name) ?>" required>

                <label for="email">Email</label>
                <input type="email" id="email" name="email" value="<?= htmlspecialchars($email) ?>" required>

                <label for="subject">Subject</label>
                <input type="text" id="subject" name="subject" value="<?= htmlspecialchars($subject) ?>">

                <label for="message">Message</label>
                <textarea id="message" name="message" required><?= htmlspecialchars($message) ?></textarea>

                <div class="cf-turnstile" data-sitekey="<?= htmlspecialchars(TURNSTILE_SITE_KEY) ?>"></div>

                <button type="submit" class="btn-send">
                    <i class="fas fa-paper-plane"></i>Send message
                </button>
            </form>

        <?php endif; ?>
    </main>
</body>
</html>
