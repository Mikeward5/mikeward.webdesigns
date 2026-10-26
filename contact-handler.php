<?php

declare(strict_types=1);

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    exit('Method not allowed.');
}
// Load PHPMailer and private SMTP configuration.
require __DIR__ . '/vendor/autoload.php';

$config = require dirname(__DIR__) . '/mail-config.php';

// Helper function for displaying a safe response.
function respond(int $status, string $message): void
{
    http_response_code($status);
    header('Content-Type: text/html; charset=UTF-8');
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');

    $safeMessage = htmlspecialchars(
        $message,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );

    echo '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Contact | MikeWard WebDesigns</title>
    <style>
        body {
            margin: 0;
            padding: 24px;
            min-height: 100vh;
            box-sizing: border-box;
            display: grid;
            place-items: center;
            background: #f7f7f7;
            color: #222;
            font-family: Arial, sans-serif;
        }

        main {
            width: 100%;
            max-width: 480px;
            padding: 32px;
            box-sizing: border-box;
            background: white;
            border: 1px solid #e5e5e5;
            border-radius: 10px;
            text-align: center;
        }

        h1 {
            font-size: 24px;
        }

        p {
            line-height: 1.6;
        }

        a {
            display: inline-block;
            margin-top: 16px;
            padding: 12px 24px;
            background: #176da5;
            color: white;
            border-radius: 25px;
            text-decoration: none;
        }

        a:focus-visible {
            outline: 3px solid #176da5;
            outline-offset: 4px;
        }
    </style>
</head>
<body>
    <main>
        <h1>MikeWard WebDesigns</h1>
        <p>' . $safeMessage . '</p>
        <a href="./contact.html">Back to contact page</a>
    </main>
</body>
</html>';

    exit;
}

// Read only scalar form values.
function formValue(string $key): string
{
    $value = $_POST[$key] ?? '';

    if (!is_string($value)) {
        respond(400, 'Please check your form details and try again.');
    }

    return trim($value);
}

// Honeypot: genuine visitors should leave this field empty.
if (formValue('website') !== '') {
    respond(200, 'Thank you for your message.');
}

// Collect submitted fields.
$name = formValue('name');
$email = formValue('email');
$enquiryType = formValue('enquiry_type');
$message = formValue('message');

// Validate name.
if (
    $name === '' ||
    mb_strlen($name, 'UTF-8') > 100 ||
    preg_match('/[\x00-\x1F\x7F]/u', $name)
) {
    respond(400, 'Please enter a valid name.');
}

// Validate email.
if (
    mb_strlen($email, 'UTF-8') > 254 ||
    !filter_var($email, FILTER_VALIDATE_EMAIL)
) {
    respond(400, 'Please enter a valid email address.');
}

// Validate enquiry type.
$allowedEnquiryTypes = [
    '' => 'Not specified',
    'general' => 'General enquiry',
    'website-design' => 'Website design question',
    'future-project' => 'Future project',
    'other' => 'Other'
];

if (!array_key_exists($enquiryType, $allowedEnquiryTypes)) {
    respond(400, 'Please select a valid enquiry type.');
}

// Validate message.
$messageLength = mb_strlen($message, 'UTF-8');

if (
    $messageLength < 10 ||
    $messageLength > 2000 ||
    str_contains($message, "\0")
) {
    respond(400, 'Your message must be between 10 and 2000 characters.');
}

// Basic per-IP rate limiting.
// Uses a private directory outside public_html.
$rateLimitDirectory = dirname(__DIR__) . '/contact-rate-limits';
$clientIP = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$rateLimitKey = hash('sha256', $clientIP);
$rateLimitFile = $rateLimitDirectory . '/' . $rateLimitKey . '.txt';

if (!is_dir($rateLimitDirectory)) {
    if (
        !mkdir($rateLimitDirectory, 0700, true) &&
        !is_dir($rateLimitDirectory)
    ) {
        error_log('Contact form: rate-limit directory unavailable.');
        respond(503, 'The contact form is temporarily unavailable. Please try again later.');
    }
}

$rateLimitHandle = fopen($rateLimitFile, 'c+');

if ($rateLimitHandle === false) {
    error_log('Contact form: rate-limit file unavailable.');
    respond(503, 'The contact form is temporarily unavailable. Please try again later.');
}

if (!flock($rateLimitHandle, LOCK_EX)) {
    fclose($rateLimitHandle);
    respond(503, 'The contact form is temporarily unavailable. Please try again later.');
}

$lastSubmission = (int) trim(stream_get_contents($rateLimitHandle) ?: '0');
$currentTime = time();

// Allow one accepted submission attempt every 60 seconds per IP.
if ($currentTime - $lastSubmission < 60) {
    flock($rateLimitHandle, LOCK_UN);
    fclose($rateLimitHandle);

    respond(429, 'Please wait a minute before sending another message.');
}

rewind($rateLimitHandle);
ftruncate($rateLimitHandle, 0);
fwrite($rateLimitHandle, (string) $currentTime);
fflush($rateLimitHandle);

flock($rateLimitHandle, LOCK_UN);
fclose($rateLimitHandle);

// Prepare PHPMailer.
$mail = new PHPMailer(true);

try {
    // SMTP settings.
    $mail->isSMTP();
    $mail->Host = $config['smtp_host'];
    $mail->SMTPAuth = true;
    $mail->Username = $config['smtp_username'];
    $mail->Password = $config['smtp_password'];
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    $mail->Port = (int) $config['smtp_port'];
    $mail->CharSet = 'UTF-8';
    $mail->Timeout = 15;

    // Sender and recipient.
    $mail->setFrom(
        $config['smtp_from'],
        'MikeWard WebDesigns'
    );

    $mail->addAddress($config['recipient']);

    // Reply directly to the person who submitted the form.
    $mail->addReplyTo($email, $name);

    // Email content.
    $mail->isHTML(false);
    $mail->Subject = 'New website enquiry - MikeWard WebDesigns';

    $mail->Body =
        "New contact form enquiry\n\n" .
        "Name: {$name}\n" .
        "Email: {$email}\n" .
        "Enquiry type: {$allowedEnquiryTypes[$enquiryType]}\n\n" .
        "Message:\n{$message}\n";

    // Send the email.
    $mail->send();

    respond(
        200,
        "Thank you for getting in touch! Your message has been sent successfully. I'll aim to reply within 2–3 working days."
    );

} catch (Exception $e) {
    // Log the error without exposing SMTP details to visitors.
    error_log('Contact form SMTP error: ' . $mail->ErrorInfo);

    respond(
        500,
        'Sorry, your message could not be sent. Please try again later or email me directly.'
    );
}