<?php
// Test Export CSV functionality
echo "=== TESTING EXPORT CSV FUNCTIONALITY ===\n\n";

// Set up session untuk testing
session_start();
$_SESSION['user_id'] = 2; // Set test user ID
$_SESSION['user_level'] = 2;

try {
    require_once __DIR__ . '/config/database.php';
    require_once __DIR__ . '/controllers/FileManagerController.php';
    
    $fileManagerController = new FileManagerController($pdo);
    
    echo "1. Testing exportMyFilesToCSV() function...\n";
    echo "User ID: " . $_SESSION['user_id'] . "\n\n";
    
    // Test 1: Export without deleted files
    echo "=== Test 1: Export active files only ===\n";
    try {
        $csvData = $fileManagerController->exportMyFilesToCSV(false);
        
        echo "✓ Export successful!\n";
        echo "CSV Length: " . strlen($csvData) . " characters\n";
        
        // Parse CSV to count rows
        $rows = explode("\n", trim($csvData));
        $headerRow = array_shift($rows);
        $dataRows = array_filter($rows); // Remove empty rows
        
        echo "Header: " . $headerRow . "\n";
        echo "Data rows: " . count($dataRows) . "\n";
        
        if (count($dataRows) > 0) {
            echo "Sample data row: " . $dataRows[0] . "\n";
        }
        
        // Save to file for inspection
        $filename = 'test_export_active_' . date('Y-m-d_H-i-s') . '.csv';
        file_put_contents($filename, $csvData);
        echo "✓ CSV saved to: $filename\n";
        
    } catch (Exception $e) {
        echo "✗ Export failed: " . $e->getMessage() . "\n";
    }
    
    echo "\n" . str_repeat("=", 50) . "\n\n";
    
    // Test 2: Export with deleted files
    echo "=== Test 2: Export including deleted files ===\n";
    try {
        $csvDataWithDeleted = $fileManagerController->exportMyFilesToCSV(true);
        
        echo "✓ Export with deleted files successful!\n";
        echo "CSV Length: " . strlen($csvDataWithDeleted) . " characters\n";
        
        // Parse CSV to count rows
        $rows = explode("\n", trim($csvDataWithDeleted));
        $headerRow = array_shift($rows);
        $dataRows = array_filter($rows);
        
        echo "Header: " . $headerRow . "\n";
        echo "Data rows: " . count($dataRows) . "\n";
        
        if (count($dataRows) > 0) {
            echo "Sample data row: " . $dataRows[0] . "\n";
        }
        
        // Save to file for inspection
        $filename = 'test_export_with_deleted_' . date('Y-m-d_H-i-s') . '.csv';
        file_put_contents($filename, $csvDataWithDeleted);
        echo "✓ CSV saved to: $filename\n";
        
    } catch (Exception $e) {
        echo "✗ Export with deleted files failed: " . $e->getMessage() . "\n";
    }
    
    echo "\n" . str_repeat("=", 50) . "\n\n";
    
    // Test 3: Direct file query to check data availability
    echo "=== Test 3: Check available files for user ===\n";
    try {
        $stmt = $pdo->prepare("
            SELECT f.*, l.name AS label_name, al.name AS file_access_level_name
            FROM files f
            LEFT JOIN labels l ON f.label_id = l.id
            LEFT JOIN access_levels al ON f.access_level_id = al.id
            WHERE f.uploaded_by = ?
            ORDER BY f.uploaded_at DESC
        ");
        $stmt->execute([$_SESSION['user_id']]);
        $files = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "Total files found in database: " . count($files) . "\n";
        
        if (count($files) > 0) {
            echo "\nFile details:\n";
            foreach ($files as $index => $file) {
                $filename = base64_decode($file['original_filename']);
                $status = $file['deleted_at'] ? 'DELETED' : 'ACTIVE';
                $size = strlen($file['file_data']);
                
                echo sprintf(
                    "%d. %s (%s) - %s - %d bytes - %s\n",
                    $index + 1,
                    $filename,
                    $file['label_name'] ?? 'No Label',
                    $status,
                    $size,
                    $file['uploaded_at']
                );
            }
        } else {
            echo "No files found for user ID: " . $_SESSION['user_id'] . "\n";
            echo "Try uploading some files first or change the user ID in this test script.\n";
        }
        
    } catch (Exception $e) {
        echo "✗ Database query failed: " . $e->getMessage() . "\n";
    }
    
    echo "\n" . str_repeat("=", 50) . "\n\n";
    
    // Test 4: Test CSV format validation
    echo "=== Test 4: CSV Format Validation ===\n";
    if (isset($csvData) && !empty($csvData)) {
        $lines = explode("\n", $csvData);
        $header = str_getcsv($lines[0]);
        
        echo "Expected CSV columns:\n";
        $expectedColumns = ['ID', 'Filename', 'Label', 'Access Level', 'File Size', 'Downloads', 'Uploaded At', 'Status'];
        foreach ($expectedColumns as $col) {
            $found = in_array($col, $header);
            echo "  " . ($found ? "✓" : "✗") . " $col\n";
        }
        
        echo "\nActual header columns:\n";
        foreach ($header as $col) {
            echo "  - $col\n";
        }
        
        // Test CSV parsing
        if (count($lines) > 1 && !empty(trim($lines[1]))) {
            $sampleData = str_getcsv($lines[1]);
            echo "\nSample data parsing:\n";
            foreach ($header as $index => $column) {
                $value = $sampleData[$index] ?? 'N/A';
                echo "  $column: $value\n";
            }
        }
    }
    
    echo "\n" . str_repeat("=", 50) . "\n\n";
    
    // Test 5: Test different user scenarios
    echo "=== Test 5: Test with different user IDs ===\n";
    $testUserIds = [1, 2, 3];
    
    foreach ($testUserIds as $testUserId) {
        echo "Testing user ID: $testUserId\n";
        $_SESSION['user_id'] = $testUserId;
        
        try {
            $testCsvData = $fileManagerController->exportMyFilesToCSV(false);
            $testRows = explode("\n", trim($testCsvData));
            $testDataRows = array_filter(array_slice($testRows, 1)); // Remove header and empty rows
            
            echo "  Files found: " . count($testDataRows) . "\n";
            
        } catch (Exception $e) {
            echo "  Error: " . $e->getMessage() . "\n";
        }
    }
    
    // Reset to original test user
    $_SESSION['user_id'] = 2;
    
    echo "\n=== EXPORT CSV TESTING COMPLETE ===\n";
    echo "Check the generated CSV files in the current directory for manual inspection.\n";
    
} catch (Exception $e) {
    echo "✗ Test setup failed: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
?>