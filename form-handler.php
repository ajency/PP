<?php
/**
 * Form Handler for Parikh Power Website
 * Handles contact forms and newsletter subscriptions
 */

// Configuration
$config = [
    'recipient_email' => 'anuj@ajency.in',
    'site_name' => 'Parikh Power',
    'recaptcha_secret' => '', // Add your reCAPTCHA secret key here
];

// Enable error reporting for debugging (remove in production)
// error_reporting(E_ALL);
// ini_set('display_errors', 1);

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /');
    exit;
}

// Get the referer page for redirect
$referer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '/';

// Function to sanitize input
function sanitize($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

// Function to validate email
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

// Function to verify reCAPTCHA (optional - uncomment if using)
function verifyRecaptcha($response, $secret) {
    if (empty($secret) || empty($response)) {
        return true; // Skip verification if not configured
    }

    $url = 'https://www.google.com/recaptcha/api/siteverify';
    $data = [
        'secret' => $secret,
        'response' => $response
    ];

    $options = [
        'http' => [
            'header' => "Content-type: application/x-www-form-urlencoded\r\n",
            'method' => 'POST',
            'content' => http_build_query($data)
        ]
    ];

    $context = stream_context_create($options);
    $result = file_get_contents($url, false, $context);
    $resultJson = json_decode($result);

    return $resultJson->success;
}

// Determine form type and process accordingly
$formType = 'unknown';
$errors = [];
$emailData = [];

// Check if it's a newsletter subscription (only has email field)
if (isset($_POST['email']) && !isset($_POST['your-name']) && !isset($_POST['your-full-name'])) {
    $formType = 'newsletter';
    $email = sanitize($_POST['email']);

    if (empty($email)) {
        $errors[] = 'Email is required';
    } elseif (!isValidEmail($email)) {
        $errors[] = 'Please enter a valid email address';
    }

    if (empty($errors)) {
        $emailData = [
            'subject' => 'New Newsletter Subscription - Parikh Power',
            'body' => "New newsletter subscription:\n\nEmail: $email\n\nSubscribed on: " . date('Y-m-d H:i:s')
        ];
    }
}
// Footer contact form (your-name, phone, your-email, company, your-message)
elseif (isset($_POST['your-name'])) {
    $formType = 'footer-contact';

    $name = sanitize($_POST['your-name'] ?? '');
    $phone = sanitize($_POST['phone'] ?? '');
    $email = sanitize($_POST['your-email'] ?? '');
    $company = sanitize($_POST['company'] ?? '');
    $message = sanitize($_POST['your-message'] ?? '');

    // Validation
    if (empty($name)) $errors[] = 'Name is required';
    if (empty($phone)) $errors[] = 'Phone is required';
    if (empty($email)) $errors[] = 'Email is required';
    elseif (!isValidEmail($email)) $errors[] = 'Please enter a valid email address';

    if (empty($errors)) {
        $emailData = [
            'subject' => 'New Contact Form Submission - Parikh Power',
            'body' => "New contact form submission:\n\n" .
                      "Name: $name\n" .
                      "Phone: $phone\n" .
                      "Email: $email\n" .
                      "Company: $company\n" .
                      "Message: $message\n\n" .
                      "Submitted on: " . date('Y-m-d H:i:s')
        ];
    }
}
// Contact page form (your-full-name, your-phone, your-company, your-email, your-message)
elseif (isset($_POST['your-full-name'])) {
    $formType = 'contact-page';

    $name = sanitize($_POST['your-full-name'] ?? '');
    $phone = sanitize($_POST['your-phone'] ?? '');
    $email = sanitize($_POST['your-email'] ?? '');
    $company = sanitize($_POST['your-company'] ?? '');
    $message = sanitize($_POST['your-message'] ?? '');

    // Verify reCAPTCHA if configured
    $recaptchaResponse = $_POST['g-recaptcha-response'] ?? '';
    if (!empty($config['recaptcha_secret']) && !verifyRecaptcha($recaptchaResponse, $config['recaptcha_secret'])) {
        $errors[] = 'reCAPTCHA verification failed. Please try again.';
    }

    // Validation
    if (empty($name)) $errors[] = 'Full name is required';
    if (empty($phone)) $errors[] = 'Phone is required';
    if (empty($email)) $errors[] = 'Email is required';
    elseif (!isValidEmail($email)) $errors[] = 'Please enter a valid email address';
    if (empty($message)) $errors[] = 'Message is required';

    if (empty($errors)) {
        $emailData = [
            'subject' => 'New Contact Form Submission - Parikh Power',
            'body' => "New contact form submission from Contact Page:\n\n" .
                      "Full Name: $name\n" .
                      "Phone: $phone\n" .
                      "Email: $email\n" .
                      "Company: $company\n" .
                      "Message: $message\n\n" .
                      "Submitted on: " . date('Y-m-d H:i:s')
        ];
    }
}

// Process the form if no errors
if (empty($errors) && !empty($emailData)) {
    // Set email headers
    $headers = [
        'From: noreply@parikhpower.in',
        'Reply-To: ' . ($email ?? 'noreply@parikhpower.in'),
        'X-Mailer: PHP/' . phpversion(),
        'Content-Type: text/plain; charset=UTF-8'
    ];

    // Send email
    $mailSent = mail(
        $config['recipient_email'],
        $emailData['subject'],
        $emailData['body'],
        implode("\r\n", $headers)
    );

    if ($mailSent) {
        // Redirect with success message
        $successUrl = $referer . (strpos($referer, '?') !== false ? '&' : '?') . 'form_status=success';
        header('Location: ' . $successUrl);
        exit;
    } else {
        $errors[] = 'Failed to send message. Please try again later.';
    }
}

// If there were errors, redirect back with error message
if (!empty($errors)) {
    $errorUrl = $referer . (strpos($referer, '?') !== false ? '&' : '?') . 'form_status=error&message=' . urlencode(implode(', ', $errors));
    header('Location: ' . $errorUrl);
    exit;
}

// Fallback redirect
header('Location: /');
exit;
