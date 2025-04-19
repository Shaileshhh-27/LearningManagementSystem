<?php
require_once 'config/database.php';

function validateTableStructure($conn, $table, $requiredColumns) {
    $result = $conn->query("PRAGMA table_info($table)");
    $existingColumns = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $existingColumns[$row['name']] = $row['type'];
    }
    
    $missingColumns = array_diff(array_keys($requiredColumns), array_keys($existingColumns));
    $typeMismatches = [];
    
    foreach ($existingColumns as $column => $type) {
        if (isset($requiredColumns[$column]) && $requiredColumns[$column] !== $type) {
            $typeMismatches[$column] = [
                'expected' => $requiredColumns[$column],
                'found' => $type
            ];
        }
    }
    
    return ['missing' => $missingColumns, 'mismatches' => $typeMismatches];
}

$db = new Database();
$conn = $db->getConnection();

$tables = [
    'assignments' => [
        'id' => 'INTEGER',
        'teacher_id' => 'INTEGER',
        'course_id' => 'INTEGER'
    ],
    'enrollments' => [
        'id' => 'INTEGER',
        'status' => 'VARCHAR(20)',
        'course_id' => 'INTEGER'
    ],
    // Add other tables...
];

$issues = [];
foreach ($tables as $table => $columns) {
    $validation = validateTableStructure($conn, $table, $columns);
    if (!empty($validation['missing']) || !empty($validation['mismatches'])) {
        $issues[$table] = $validation;
    }
}

header('Content-Type: application/json');
echo json_encode(['issues' => $issues], JSON_PRETTY_PRINT);