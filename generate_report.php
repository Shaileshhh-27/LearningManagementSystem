<?php
// Start output buffering to catch any unwanted output
ob_start();

// Ensure PHP errors don't get output in the JSON response
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error_log.txt');

// Set timezone to avoid date warnings
date_default_timezone_set('UTC');

/**
 * Check database structure and log diagnostic information
 */
function checkDatabaseStructure($conn) {
    try {
        // Get list of all tables
        $tables = [];
        $result = $conn->query("SELECT name FROM sqlite_master WHERE type='table'");
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $tables[] = $row['name'];
        }
        
        debug_log("Database tables", $tables);
        
        // Check key tables
        $requiredTables = ['users', 'courses', 'enrollments', 'assignments', 'submissions'];
        $missingTables = [];
        foreach ($requiredTables as $table) {
            if (!in_array($table, $tables)) {
                $missingTables[] = $table;
            }
        }
        
        if (!empty($missingTables)) {
            debug_log("Missing tables", $missingTables);
        }
        
        // Check table structures for key tables
        foreach ($tables as $table) {
            $columns = [];
            $result = $conn->query("PRAGMA table_info($table)");
            while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                $columns[$row['name']] = $row['type'];
            }
            
            debug_log("Table structure for $table", $columns);
        }
        
        return true;
    } catch (Exception $e) {
        debug_log("Error checking database structure", [
            'message' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        return false;
    }
}

// Debug function to log errors
function debug_log($message, $data = null) {
    $log_file = __DIR__ . '/debug_log.txt';
    $timestamp = date('Y-m-d H:i:s');
    $log_message = "[$timestamp] $message";
    
    if ($data !== null) {
        $log_message .= ": " . print_r($data, true);
    }
    
    file_put_contents($log_file, $log_message . PHP_EOL, FILE_APPEND);
}

// Set up error handler
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    debug_log("PHP Error [$errno]: $errstr in $errfile on line $errline");
    return false; // Let PHP handle the error as well
});

// Set up exception handler
set_exception_handler(function($exception) {
    debug_log("Uncaught Exception: " . $exception->getMessage(), [
        'file' => $exception->getFile(),
        'line' => $exception->getLine(),
        'trace' => $exception->getTraceAsString()
    ]);
    
    // Clear any buffered output
    ob_end_clean();
    
    // Return error as JSON
    header('Content-Type: application/json');
    echo json_encode([
        'error' => 'Server error: ' . $exception->getMessage(),
        'headers' => [],
        'rows' => []
    ]);
    exit;
});

try {
    require_once 'auth/Auth.php';
    require_once 'config/database.php';
    
    // Function to ensure clean JSON output
    function outputJSON($data) {
        // Clear any previous output
        ob_end_clean();
        
        // Set JSON headers
        header('Content-Type: application/json');
        header('Cache-Control: no-cache, must-revalidate');
        
        // Make sure data can be properly encoded
        if (isset($data['rows']) && is_array($data['rows'])) {
            foreach ($data['rows'] as $key => $row) {
                // Replace any NULL values with empty strings to avoid JSON encoding issues
                if (is_array($row)) {
                    foreach ($row as $field => $value) {
                        if ($value === NULL) {
                            $data['rows'][$key][$field] = '';
                        }
                    }
                }
            }
        }
        
        // Try to encode with error handling
        $json = json_encode($data);
        if ($json === false) {
            // Log the error
            debug_log('JSON encoding error: ' . json_last_error_msg(), $data);
            
            // Return a simplified error response
            echo json_encode([
                'error' => 'Failed to encode response data: ' . json_last_error_msg(),
                'headers' => [],
                'rows' => []
            ]);
        } else {
            // Output the JSON
            echo $json;
        }
        exit;
    }
    
    session_start();
    $auth = new Auth();
    
    // If not logged in or not an admin, redirect to login page
    if (!$auth->isLoggedIn() || $auth->getUserRole() !== 'admin') {
        if (isset($_GET['format']) || isset($_POST['format'])) {
            // If requesting a download, return error JSON
            outputJSON(['error' => 'Unauthorized access']);
        } else {
            header('Location: index.php');
            exit();
        }
    }
    
    $db = new Database();
    $conn = $db->getConnection();
    
    // Check database structure to assist with debugging
    checkDatabaseStructure($conn);
    
    // Get report type and format - check both GET and POST
    $reportType = $_GET['type'] ?? $_POST['type'] ?? '';
    $format = $_GET['format'] ?? $_POST['format'] ?? 'json';
    
    // Check if we have filtered data from POST
    $filteredData = null;
    if (isset($_POST['filtered_data'])) {
        try {
            $filteredData = json_decode($_POST['filtered_data'], true);
            // If it's not valid JSON or not an array, ignore it
            if (!is_array($filteredData)) {
                $filteredData = null;
            }
        } catch (Exception $e) {
            debug_log("Error parsing filtered data", [
                'error' => $e->getMessage(),
                'data' => $_POST['filtered_data']
            ]);
            $filteredData = null;
        }
    }
    
    debug_log("Starting report generation", [
        'type' => $reportType,
        'format' => $format,
        'parameters' => $_GET,
        'has_filtered_data' => !is_null($filteredData)
    ]);
    
    // Initialize response array
    $response = [
        'headers' => [],
        'rows' => [],
        'error' => null
    ];
    
    // If we have filtered data and we're not in JSON mode, skip database query
    if ($filteredData !== null && $format !== 'json') {
        // Use the headers from the first row if we have data
        if (count($filteredData) > 0) {
            $firstRow = $filteredData[0];
            $headers = [];
            
            // Create basic headers based on field names
            foreach (array_keys($firstRow) as $field) {
                $headers[$field] = ucfirst(str_replace('_', ' ', $field));
            }
            
            // Use serial for the first column
            if (isset($headers['serial'])) {
                $headers['serial'] = 'S.No';
            }
            
            $response['headers'] = $headers;
        }
        
        // Add the filtered rows
        $response['rows'] = $filteredData;
    } else {
        // Get report from database
        switch ($reportType) {
            case 'enrollment':
                generateEnrollmentReport($conn, $response);
                break;
            case 'performance':
                generatePerformanceReport($conn, $response);
                break;
            case 'teacher':
                generateTeacherReport($conn, $response);
                break;
            default:
                $response['error'] = 'Invalid report type';
        }
    }
    
    debug_log("Report generated successfully", [
        'type' => $reportType,
        'rowCount' => count($response['rows'])
    ]);
    
    // Return data based on requested format
    if ($format === 'json') {
        // Return as JSON for AJAX requests
        outputJSON($response);
    } else {
        // Download as file
        debug_log("Preparing file download", ['format' => $format]);
        
        // Convert data for download
        $data = [];
        
        // Add headers as first row
        $data[] = array_values($response['headers']);
        
        // Add data rows
        foreach ($response['rows'] as $row) {
            // Ensure row data is in same order as headers
            $orderedRow = [];
            foreach (array_keys($response['headers']) as $key) {
                $orderedRow[] = $row[$key] ?? '';
            }
            $data[] = $orderedRow;
        }
        
        // Set appropriate headers for download
        $filename = $reportType . '_report_' . date('Y-m-d') . '.' . ($format === 'excel' ? 'xlsx' : 'csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: max-age=0');
        
        if ($format === 'excel') {
            // Excel format (XLSX)
            require_once 'vendor/autoload.php';  // Make sure PHPSpreadsheet is installed
            
            try {
                $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
                $sheet = $spreadsheet->getActiveSheet();
                
                // Add data to sheet
                $sheet->fromArray($data, NULL, 'A1');
                
                // Auto-size columns
                foreach (range('A', $sheet->getHighestColumn()) as $col) {
                    $sheet->getColumnDimension($col)->setAutoSize(true);
                }
                
                // Style header row
                $headerStyle = [
                    'font' => [
                        'bold' => true,
                        'color' => ['rgb' => 'FFFFFF'],
                    ],
                    'fill' => [
                        'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                        'startColor' => ['rgb' => '4F81BD']
                    ]
                ];
                
                $sheet->getStyle('A1:' . $sheet->getHighestColumn() . '1')->applyFromArray($headerStyle);
                
                // Create writer and output
                $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
                
                // Send to browser
                header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
                $writer->save('php://output');
                
            } catch (Exception $e) {
                debug_log("Error creating Excel file", ['error' => $e->getMessage()]);
                // Fall back to CSV if Excel export fails
                outputCSV($data);
            }
            
        } else {
            // CSV format
            outputCSV($data);
        }
        
        exit();
    }
} catch (Exception $e) {
    debug_log("Exception in report generation", [
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
    
    $response = [
        'error' => 'Error generating report: ' . $e->getMessage(),
        'headers' => [],
        'rows' => []
    ];
    
    if (isset($_GET['format']) && $_GET['format'] !== 'json') {
        // For download formats, redirect back with error
        $error = urlencode('Error generating report: ' . $e->getMessage());
        header("Location: admin_dashboard.php?error=$error");
        exit;
    } else {
        // For AJAX, return JSON error
        outputJSON($response);
    }
}

/**
 * Generate enrollment report
 */
function generateEnrollmentReport($conn, &$response) {
    try {
        // Get filter parameters
        $courseId = $_GET['course_id'] ?? 'all';
        $dateFrom = $_GET['date_from'] ?? '';
        $dateTo = $_GET['date_to'] ?? '';
        
        debug_log("Generating enrollment report", [
            'courseId' => $courseId,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo
        ]);
        
        // Define headers
        $response['headers'] = [
            'serial' => 'S.No',
            'id' => 'ID',
            'student' => 'Student Name',
            'email' => 'Email',
            'course' => 'Course',
            'enrolled_date' => 'Enrolled Date',
            'status' => 'Status'
        ];
        
        // Check if the users table exists
        $checkUsersTable = $conn->query("SELECT name FROM sqlite_master WHERE type='table' AND name='users'");
        if (!$checkUsersTable->fetchArray()) {
            throw new Exception("Users table does not exist");
        }
        
        // Check if the enrollments table exists
        $hasEnrollments = $conn->query("SELECT name FROM sqlite_master WHERE type='table' AND name='enrollments'")->fetchArray();
        
        // Get structure of enrollments table if it exists
        $enrollmentColumns = [];
        if ($hasEnrollments) {
            $result = $conn->query("PRAGMA table_info(enrollments)");
            while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                $enrollmentColumns[$row['name']] = true;
            }
            
            debug_log("Enrollment table columns", array_keys($enrollmentColumns));
        }
        
        // Get structure of users table
        $userColumns = [];
        $result = $conn->query("PRAGMA table_info(users)");
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $userColumns[$row['name']] = true;
        }
        
        debug_log("Users table columns", array_keys($userColumns));
        
        // Check if courses table exists
        $hasCourses = $conn->query("SELECT name FROM sqlite_master WHERE type='table' AND name='courses'")->fetchArray();
        
        // Determine enrollment date field
        $enrollmentDateField = null;
        if ($hasEnrollments) {
            if (isset($enrollmentColumns['enrollment_date'])) {
                $enrollmentDateField = "e.enrollment_date";
            } else if (isset($enrollmentColumns['created_at'])) {
                $enrollmentDateField = "e.created_at";
            }
        }
        
        // Build select clause
        $selectClause = "SELECT u.id";
        $selectClause .= ", u.username as student";
        $selectClause .= isset($userColumns['email']) ? ", u.email" : ", '' as email";
        
        if ($hasEnrollments && $hasCourses) {
            $selectClause .= ", c.title as course";
            
            if ($enrollmentDateField) {
                $selectClause .= ", $enrollmentDateField as enrolled_date";
            } else {
                $selectClause .= ", '' as enrolled_date";
            }
            
            $selectClause .= ", CASE WHEN e.id IS NULL THEN 'Not Enrolled' ELSE 'Enrolled' END as status";
        } else {
            $selectClause .= ", '' as course, '' as enrolled_date, 'Unknown' as status";
        }
        
        // Build from clause
        $fromClause = " FROM users u";
        
        if ($hasEnrollments && $hasCourses) {
            $fromClause .= " LEFT JOIN enrollments e ON u.id = e.student_id";
    
    if ($courseId !== 'all') {
                $fromClause .= " AND e.course_id = :course_id";
            }
            
            $fromClause .= " LEFT JOIN courses c ON e.course_id = c.id";
        }
        
        // Build where clause
        $whereClause = " WHERE";
        
        // Filter to only include student users if role field exists
        if (isset($userColumns['role'])) {
            $whereClause .= " u.role = 'student'";
        } else {
            $whereClause .= " 1=1"; // No filter if role doesn't exist
        }
        
        // Add date filters if applicable
        if ($hasEnrollments && $enrollmentDateField && !empty($dateFrom)) {
            $whereClause .= " AND ($enrollmentDateField >= :date_from OR e.id IS NULL)";
        }
        
        if ($hasEnrollments && $enrollmentDateField && !empty($dateTo)) {
            $whereClause .= " AND ($enrollmentDateField <= :date_to OR e.id IS NULL)";
        }
        
        // Build order by clause
        $orderByClause = " ORDER BY";
        if ($hasEnrollments && $enrollmentDateField) {
            $orderByClause .= " CASE WHEN $enrollmentDateField IS NULL THEN 1 ELSE 0 END, $enrollmentDateField DESC, u.username ASC";
        } else {
            $orderByClause .= " u.username ASC";
        }
        
        // Combine all clauses
        $query = $selectClause . $fromClause . $whereClause . $orderByClause;
        
        debug_log("Enrollment report SQL", [
            'query' => $query,
            'params' => [
                'courseId' => $courseId,
                'dateFrom' => $dateFrom,
                'dateTo' => $dateTo
            ]
        ]);
        
        // Execute query
    $stmt = $conn->prepare($query);
        if (!$stmt) {
            throw new Exception("Failed to prepare enrollment query: " . $conn->lastErrorMsg());
        }
        
        // Bind parameters
        if ($hasEnrollments && $courseId !== 'all') {
            $stmt->bindValue(':course_id', $courseId, SQLITE3_INTEGER);
        }
        
        if ($hasEnrollments && $enrollmentDateField && !empty($dateFrom)) {
            $stmt->bindValue(':date_from', $dateFrom);
        }
        
        if ($hasEnrollments && $enrollmentDateField && !empty($dateTo)) {
            $stmt->bindValue(':date_to', $dateTo . ' 23:59:59');
    }
    
    $result = $stmt->execute();
        if (!$result) {
            throw new Exception("Failed to execute enrollment query: " . $conn->lastErrorMsg());
        }
    
        // Fetch results
        $response['rows'] = [];
        $serial = 1;
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            // Convert NULL values to empty strings
            foreach ($row as $key => $value) {
                if ($value === NULL) {
                    $row[$key] = '';
                }
            }
            // Add serial number
            $row['serial'] = $serial++;
            $response['rows'][] = $row;
        }
        
        debug_log("Enrollment report results", [
            'count' => count($response['rows'])
        ]);
    } catch (Exception $e) {
        debug_log("Error in enrollment report", [
            'message' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        throw $e; // Re-throw to be caught by main handler
    }
}

/**
 * Generate course performance report
 */
function generatePerformanceReport($conn, &$response) {
    try {
        // Get filter parameters
        $courseId = $_GET['course_id'] ?? 'all';
        $period = $_GET['period'] ?? 'all_time';
        
        debug_log("Generating performance report", [
            'courseId' => $courseId,
            'period' => $period
        ]);
        
        // Define headers
        $response['headers'] = [
            'serial' => 'S.No',
            'id' => 'ID',
            'course' => 'Course',
            'enrollments' => 'Enrollments',
            'active_students' => 'Active Students',
            'completion_count' => 'Completed Students',
            'completion_rate' => 'Completion Rate',
            'start_date' => 'Start Date',
            'end_date' => 'End Date',
            'status' => 'Status'
        ];
        
        // Check if courses table exists
        $checkCoursesTable = $conn->query("SELECT name FROM sqlite_master WHERE type='table' AND name='courses'");
        if (!$checkCoursesTable->fetchArray()) {
            throw new Exception("Courses table does not exist");
        }
        
        // Get course table structure
        $courseColumns = [];
        $result = $conn->query("PRAGMA table_info(courses)");
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $courseColumns[$row['name']] = true;
        }
        
        debug_log("Course table columns", array_keys($courseColumns));
        
        // Check for enrollments table
        $hasEnrollments = false;
        $checkEnrollmentsTable = $conn->query("SELECT name FROM sqlite_master WHERE type='table' AND name='enrollments'");
        if ($checkEnrollmentsTable->fetchArray()) {
            $hasEnrollments = true;
            
            // Check enrollments table structure to verify if status column exists
            $enrollmentColumns = [];
            $result = $conn->query("PRAGMA table_info(enrollments)");
            while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                $enrollmentColumns[$row['name']] = true;
            }
            
            debug_log("Enrollment table columns for performance report", array_keys($enrollmentColumns));
        }
        
        // Calculate date based on period
        $dateFilter = '';
        switch ($period) {
            case 'last_month':
                $dateFilter = "AND c.created_at >= date('now', '-1 month')";
                break;
            case 'last_quarter':
                $dateFilter = "AND c.created_at >= date('now', '-3 month')";
                break;
            case 'last_year':
                $dateFilter = "AND c.created_at >= date('now', '-1 year')";
                break;
        }
        
        // Build select clause
        $selectClause = "SELECT c.id, c.title as course";
        
        // Add enrollment counts if enrollments table exists
        if ($hasEnrollments) {
            $selectClause .= ",
                (SELECT COUNT(*) FROM enrollments e WHERE e.course_id = c.id) as enrollments";
                
            // Only use status fields if status column exists
            if (isset($enrollmentColumns['status'])) {
                $selectClause .= ",
                    (SELECT COUNT(*) FROM enrollments e WHERE e.course_id = c.id AND e.status = 'active') as active_students,
                    (SELECT COUNT(*) FROM enrollments e WHERE e.course_id = c.id AND e.status = 'completed') as completion_count,
                    CASE 
                        WHEN (SELECT COUNT(*) FROM enrollments e WHERE e.course_id = c.id) > 0 
                        THEN ROUND((SELECT COUNT(*) FROM enrollments e WHERE e.course_id = c.id AND e.status = 'completed') * 100.0 / 
                            (SELECT COUNT(*) FROM enrollments e WHERE e.course_id = c.id), 2) || '%'
                        ELSE '0%'
                    END as completion_rate";
            } else {
                // If no status column, just show total enrollments for all fields
                $selectClause .= ",
                    (SELECT COUNT(*) FROM enrollments e WHERE e.course_id = c.id) as active_students,
                    0 as completion_count,
                    '0%' as completion_rate";
            }
        } else {
            $selectClause .= ",
                0 as enrollments,
                0 as active_students,
                0 as completion_count,
                '0%' as completion_rate";
        }
        
        // Add date fields if they exist in the courses table
        if (isset($courseColumns['start_date'])) {
            $selectClause .= ", c.start_date";
        } else {
            $selectClause .= ", '' as start_date";
        }
        
        if (isset($courseColumns['end_date'])) {
            $selectClause .= ", c.end_date";
        } else {
            $selectClause .= ", '' as end_date";
        }
        
        // Add status field if it exists
        if (isset($courseColumns['status'])) {
            $selectClause .= ", c.status";
        } else {
            $selectClause .= ", 'Unknown' as status";
        }
            
        // Build the main query with FROM and WHERE clauses
        $query = $selectClause . " FROM courses c WHERE 1=1";
        
        // Add course filter
        if ($courseId !== 'all') {
            $query .= " AND c.id = :course_id";
        }
        
        // Add date filter
        $query .= " $dateFilter";
        
        // Add ORDER BY
        if (isset($courseColumns['created_at'])) {
            $query .= " ORDER BY c.created_at DESC, c.id DESC";
        } else {
            $query .= " ORDER BY c.id DESC";
        }
        
        debug_log("Performance report SQL", [
            'query' => $query,
            'courseId' => $courseId
        ]);
        
        // Execute query
    $stmt = $conn->prepare($query);
        if (!$stmt) {
            throw new Exception("Failed to prepare performance query: " . $conn->lastErrorMsg());
        }
    
        if ($courseId !== 'all') {
            $stmt->bindValue(':course_id', $courseId, SQLITE3_INTEGER);
    }
    
    $result = $stmt->execute();
        if (!$result) {
            throw new Exception("Failed to execute performance query: " . $conn->lastErrorMsg());
        }
    
        // Fetch results
        $response['rows'] = [];
        $serial = 1;
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            // Convert NULL values to empty strings
            foreach ($row as $key => $value) {
                if ($value === NULL) {
                    $row[$key] = '';
                }
                // Ensure first letter of status is capitalized (moved from SQL to PHP)
                if ($key === 'status' && $value !== '') {
                    $row[$key] = ucfirst($value);
                }
            }
            // Add serial number
            $row['serial'] = $serial++;
            $response['rows'][] = $row;
        }
        
        debug_log("Performance report results", [
            'count' => count($response['rows'])
        ]);
    } catch (Exception $e) {
        debug_log("Error in performance report", [
            'message' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        throw $e; // Re-throw to be caught by main handler
    }
}

/**
 * Generate teacher activity report
 */
function generateTeacherReport($conn, &$response) {
    try {
        // Get filter parameters
        $teacherId = $_GET['teacher_id'] ?? 'all';
        $dateFrom = $_GET['date_from'] ?? '';
        $dateTo = $_GET['date_to'] ?? '';
        
        debug_log("Generating teacher report", [
            'teacherId' => $teacherId,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo
        ]);
        
        // Define headers
        $response['headers'] = [
            'serial' => 'S.No',
            'id' => 'ID',
            'teacher' => 'Teacher Name',
            'email' => 'Email',
            'courses_created' => 'Courses Created',
            'active_courses' => 'Active Courses',
            'assignments_created' => 'Assignments Created',
            'students_count' => 'Total Students',
            'status' => 'Status'
        ];
        
        // Check if users table exists
        $checkUsersTable = $conn->query("SELECT name FROM sqlite_master WHERE type='table' AND name='users'");
        if (!$checkUsersTable->fetchArray()) {
            throw new Exception("Users table does not exist");
        }
        
        // Get structure of users table
        $usersColumns = [];
        $result = $conn->query("PRAGMA table_info(users)");
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $usersColumns[$row['name']] = true;
        }
        
        debug_log("Users table columns for teacher report", array_keys($usersColumns));
        
        // Check for courses and assignments tables
        $hasCourses = false;
        $hasAssignments = false;
        $hasEnrollments = false;
        
        // Check courses table
        $courseColumns = [];
        $checkCoursesTable = $conn->query("SELECT name FROM sqlite_master WHERE type='table' AND name='courses'");
        if ($checkCoursesTable->fetchArray()) {
            $hasCourses = true;
            
            // Check courses table structure
            $result = $conn->query("PRAGMA table_info(courses)");
            while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                $courseColumns[$row['name']] = true;
            }
            
            debug_log("Course table columns for teacher report", array_keys($courseColumns));
        }
        
        // Check assignments table
        $assignmentHasTeacherId = false;
        $checkAssignmentsTable = $conn->query("SELECT name FROM sqlite_master WHERE type='table' AND name='assignments'");
        if ($checkAssignmentsTable->fetchArray()) {
            $hasAssignments = true;
            
            // Check if assignments table has teacher_id column
            $result = $conn->query("PRAGMA table_info(assignments)");
            while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                if ($row['name'] === 'teacher_id') {
                    $assignmentHasTeacherId = true;
                    break;
                }
            }
            
            debug_log("Assignments table has teacher_id column", $assignmentHasTeacherId);
        }
        
        // Check if we have course_assignments table as alternative
        $hasCourseAssignments = false;
        $checkCourseAssignmentsTable = $conn->query("SELECT name FROM sqlite_master WHERE type='table' AND name='course_assignments'");
        if ($checkCourseAssignmentsTable->fetchArray()) {
            $hasCourseAssignments = true;
            debug_log("Course assignments table exists", true);
        }
        
        // Check enrollments table
        $checkEnrollmentsTable = $conn->query("SELECT name FROM sqlite_master WHERE type='table' AND name='enrollments'");
        if ($checkEnrollmentsTable->fetchArray()) {
            $hasEnrollments = true;
        }
        
        // Build select clause
        $selectClause = "SELECT u.id, u.username as teacher, u.email";
        
        // Add course counts if courses table exists
        if ($hasCourses) {
            if (!empty($dateFrom) || !empty($dateTo)) {
                $courseFilter = " WHERE teacher_id = u.id";
                if (!empty($dateFrom)) {
                    $courseFilter .= " AND created_at >= :date_from";
                }
                if (!empty($dateTo)) {
                    $courseFilter .= " AND created_at <= :date_to";
                }
                
                $selectClause .= ",
                    (SELECT COUNT(*) FROM courses c" . $courseFilter . ") as courses_created";
                
                // Only add active courses if status column exists
                if (isset($courseColumns['status'])) {
                    $selectClause .= ",
                    (SELECT COUNT(*) FROM courses c" . $courseFilter . " AND status = 'active') as active_courses";
                } else {
                    $selectClause .= ", 0 as active_courses";
                }
            } else {
                $selectClause .= ",
                    (SELECT COUNT(*) FROM courses c WHERE c.teacher_id = u.id) as courses_created";
                
                // Only add active courses if status column exists
                if (isset($courseColumns['status'])) {
                    $selectClause .= ",
                    (SELECT COUNT(*) FROM courses c WHERE c.teacher_id = u.id AND c.status = 'active') as active_courses";
                } else {
                    $selectClause .= ", 0 as active_courses";
                }
            }
        } else {
            $selectClause .= ",
                0 as courses_created,
                0 as active_courses";
        }
        
        // Add assignments count based on what tables/columns are available
        if ($hasAssignments && $assignmentHasTeacherId) {
            $selectClause .= ",
                (SELECT COUNT(*) FROM assignments a WHERE a.teacher_id = u.id) as assignments_created";
        } else if ($hasCourseAssignments && $hasCourses) {
            // Alternative: count assignments from course_assignments for courses taught by this teacher
            $selectClause .= ",
                (SELECT COUNT(*) 
                 FROM course_assignments ca 
                 JOIN courses c ON ca.course_id = c.id 
                 WHERE c.teacher_id = u.id) as assignments_created";
        } else {
            // If neither table has what we need, show zero
            $selectClause .= ", 0 as assignments_created";
        }
        
        // Add student count if enrollments and courses tables exist
        if ($hasEnrollments && $hasCourses) {
            $selectClause .= ",
                (SELECT COUNT(DISTINCT e.student_id) 
                 FROM enrollments e 
                 JOIN courses c ON e.course_id = c.id 
                 WHERE c.teacher_id = u.id) as students_count";
        } else {
            $selectClause .= ", 0 as students_count";
        }
        
        // Add status field if it exists
        if (isset($usersColumns['status'])) {
            $selectClause .= ", u.status";
        } else {
            $selectClause .= ", CASE 
                WHEN (SELECT COUNT(*) FROM courses c WHERE c.teacher_id = u.id) > 0 THEN 'Active' 
                ELSE 'Inactive' 
            END as status";
        }
        
        // Build FROM clause
        $fromClause = " FROM users u";
        
        // Build WHERE clause
        $whereClause = " WHERE";
        
        // Filter to only include teacher users if role field exists
        if (isset($usersColumns['role'])) {
            $whereClause .= " u.role = 'teacher'";
        } else {
            // If role doesn't exist, try to identify teachers heuristically
            $whereClause .= " EXISTS (SELECT 1 FROM courses c WHERE c.teacher_id = u.id)";
        }
        
        // Add teacher ID filter if applicable
        if ($teacherId !== 'all') {
            $whereClause .= " AND u.id = :teacher_id";
        }
        
        // Build ORDER BY clause
        $orderByClause = " ORDER BY";
        
        // Set ORDER BY based on available columns
        if (isset($usersColumns['created_at'])) {
            $orderByClause .= " u.created_at DESC, u.id DESC";
        } else {
            $orderByClause .= " u.id DESC";
        }
        
        // Combine all clauses into final query
        $query = $selectClause . $fromClause . $whereClause . $orderByClause;
        
        debug_log("Teacher report SQL", [
            'query' => $query,
            'teacherId' => $teacherId,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo
        ]);
        
        // Execute query
        $stmt = $conn->prepare($query);
        if (!$stmt) {
            throw new Exception("Failed to prepare teacher report query: " . $conn->lastErrorMsg());
        }
        
        // Bind parameters if applicable
        if ($teacherId !== 'all') {
            $stmt->bindValue(':teacher_id', $teacherId, SQLITE3_INTEGER);
        }
        
        if ($hasCourses && !empty($dateFrom)) {
            $stmt->bindValue(':date_from', $dateFrom);
        }
        
        if ($hasCourses && !empty($dateTo)) {
            $stmt->bindValue(':date_to', $dateTo . ' 23:59:59');
        }
        
        $result = $stmt->execute();
        if (!$result) {
            throw new Exception("Failed to execute teacher report query: " . $conn->lastErrorMsg());
        }
        
        // Fetch results
        $response['rows'] = [];
        $serial = 1;
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            // Convert NULL values to empty strings
            foreach ($row as $key => $value) {
                if ($value === NULL) {
                    $row[$key] = '';
                }
                // Ensure first letter of status is capitalized
                if ($key === 'status' && $value !== '') {
                    $row[$key] = ucfirst($value);
                }
            }
            // Add serial number
            $row['serial'] = $serial++;
            $response['rows'][] = $row;
        }
        
        debug_log("Teacher report results", [
            'count' => count($response['rows'])
        ]);
    } catch (Exception $e) {
        debug_log("Error in teacher report", [
            'message' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        throw $e; // Re-throw to be caught by main handler
    }
}

/**
 * Output data as CSV
 * 
 * @param array $data Array of rows to output
 */
function outputCSV($data) {
    header('Content-Type: text/csv; charset=utf-8');
    
    $output = fopen('php://output', 'w');
    
    // Add UTF-8 BOM to fix Excel encoding issues
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Write rows
    foreach ($data as $row) {
        fputcsv($output, $row);
    }
    
    fclose($output);
} 