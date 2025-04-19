<?php
// Define base path
define('BASE_PATH', dirname(__DIR__) . DIRECTORY_SEPARATOR);

// Database file path
define('DB_PATH', BASE_PATH . 'database' . DIRECTORY_SEPARATOR . 'lms.db');

// Upload directories
define('UPLOAD_PATH', BASE_PATH . 'uploads' . DIRECTORY_SEPARATOR);
define('LECTURE_UPLOAD_PATH', UPLOAD_PATH . 'lectures' . DIRECTORY_SEPARATOR);
define('SUBMISSION_UPLOAD_PATH', UPLOAD_PATH . 'submissions' . DIRECTORY_SEPARATOR);
?> 