<?php
/**
 * TechTalent Solutions - SMTP / e-mail settings (single place to edit).
 *
 * Every e-mail the platform sends (password-reset OTP, admin replies to contact
 * messages, acknowledgements, admin alerts) reads its credentials from here.
 *
 * IMPORTANT
 *  - 'smtp_password' must be a 16-character Google "App Password"
 *    (Google Account > Security > 2-Step Verification > App passwords),
 *    NOT your normal Gmail password. Paste it without spaces.
 *  - Do NOT commit this file with a real password to Git (see /.gitignore).
 *  - Alternatively set the environment variable TECHTALENT_SMTP_PASSWORD
 *    (and TECHTALENT_SMTP_USERNAME, etc.) and leave the value below empty.
 */
return [
    'smtp_host'       => 'smtp.gmail.com',
    'smtp_port'       => 587,
    'smtp_encryption' => 'tls',                         // 'tls' (port 587) or 'ssl' (port 465)
    'smtp_username'   => 'your-account@gmail.com',
    'smtp_password'   => 'YOUR_16_CHAR_APP_PASSWORD',

    'from_email'      => '',                            // empty = use smtp_username
    'from_name'       => 'TechTalent Solutions',
    'reply_to_email'  => '',                            // empty = use from address

    // Receive an e-mail alert when someone submits the Contact Us form.
    // Leave empty to disable.
    'admin_notify_email' => '',

    // Send the visitor an automatic "we received your message" e-mail.
    'send_acknowledgement' => true,

    // Local WAMP/XAMPP often lacks CA certificates, so verification is OFF by default.
    // Set to true on a live server.
    'verify_ssl'      => false,

    'timeout'         => 10,                            // seconds before SMTP gives up
];
