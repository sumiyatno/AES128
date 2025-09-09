<?php
// filepath: d:\website\AES128\test.php

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/models/File.php';

try {
    echo "=== DEBUGGING FILE MODEL ===\n";
    
    // Check table structure
    $stmt = $pdo->query("DESCRIBE files");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Table 'files' columns:\n";
    foreach ($columns as $col) {
        echo "  - {$col['Field']} ({$col['Type']})\n";
    }
    
    // Check if file_description column exists
    $hasDescriptionCol = false;
    foreach ($columns as $col) {
        if ($col['Field'] === 'file_description') {
            $hasDescriptionCol = true;
            break;
        }
    }
    
    echo "\nfile_description column exists: " . ($hasDescriptionCol ? 'YES' : 'NO') . "\n";
    
    if (!$hasDescriptionCol) {
        echo "\n❌ MISSING file_description COLUMN!\n";
        echo "Run this SQL to fix:\n";
        echo "ALTER TABLE files ADD COLUMN file_description TEXT NULL AFTER original_filename;\n";
        exit(1); // Exit with error code
    } else {
        echo "\n✅ file_description column found\n";
        
        // Test FileModel instantiation - FIXED: Use correct class name
        echo "\nTesting FileModel instantiation...\n";
        $fileModel = new FileModel($pdo);
        echo "✅ FileModel created successfully\n";
        
        $testData = [
            'filename' => 'test_debug_' . time(),
            'original_filename' => base64_encode('test_debug.txt'),
            'file_description' => 'Test description for debugging',
            'mime_type' => base64_encode('text/plain'),
            'file_data' => 'test content data for debugging',
            'label_id' => 1,
            'access_level_id' => 1,
            'encryption_iv' => '',
            'restricted_password_hash' => null,
            'uploaded_by' => 1
        ];
        
        echo "\nTest data prepared:\n";
        echo "  - filename: " . $testData['filename'] . "\n";
        echo "  - original_filename (decoded): " . base64_decode($testData['original_filename']) . "\n";
        echo "  - file_description: " . $testData['file_description'] . "\n";
        echo "  - file_data length: " . strlen($testData['file_data']) . " bytes\n";
        
        echo "\nTesting FileModel save method...\n";
        $result = $fileModel->save($testData);
        
        if ($result) {
            $insertId = $pdo->lastInsertId();
            echo "✅ FileModel save test PASSED\n";
            echo "Last insert ID: " . $insertId . "\n";
            
            // Verify data was saved correctly
            echo "\nVerifying saved data...\n";
            $stmt = $pdo->prepare("SELECT * FROM files WHERE id = ?");
            $stmt->execute([$insertId]);
            $savedFile = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($savedFile) {
                echo "✅ File saved and retrieved successfully\n";
                echo "  - ID: " . $savedFile['id'] . "\n";
                echo "  - Filename: " . $savedFile['filename'] . "\n";
                echo "  - Original filename (decoded): " . base64_decode($savedFile['original_filename']) . "\n";
                echo "  - File description: " . ($savedFile['file_description'] ?? 'NULL') . "\n";
                echo "  - File data length: " . strlen($savedFile['file_data']) . " bytes\n";
                echo "  - Uploaded at: " . $savedFile['uploaded_at'] . "\n";
                echo "  - Uploaded by: " . $savedFile['uploaded_by'] . "\n";
            } else {
                echo "❌ Could not retrieve saved file\n";
            }
            
        } else {
            echo "❌ FileModel save test FAILED\n";
            
            // Check PDO error info
            $errorInfo = $pdo->errorInfo();
            if ($errorInfo[0] !== '00000') {
                echo "SQL Error: " . $errorInfo[2] . "\n";
                echo "SQL State: " . $errorInfo[0] . "\n";
                echo "Error Code: " . $errorInfo[1] . "\n";
            }
            exit(1); // Exit with error code
        }
    }
    
    echo "\n✅ ALL TESTS PASSED - file_description functionality working correctly\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1); // Exit with error code
}

echo "\n=== DEBUG COMPLETE ===\n";
exit(0); // Exit successfully