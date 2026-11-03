<?php
/**
 * TechTalent CV Studio configuration.
 */

// Private storage for uploaded CV files. In production point this at a folder
// OUTSIDE the web root (e.g. C:/xampp/techtalent_private/cv_uploads).
define('CV_PRIVATE_STORAGE', __DIR__ . '/../storage/cv_uploads');

// Maximum accepted upload size in bytes (5 MB).
define('CV_MAX_UPLOAD_BYTES', 5 * 1024 * 1024);

// Optional absolute path to Poppler's pdftotext binary. When set, it is used as a
// fallback if the built-in PHP PDF extractor recovers too little text.
define('CV_PDFTOTEXT_BIN', '');

// Upper bound on entries per repeatable section.
define('CV_MAX_ENTRIES', 30);
