<?php
/**
 * Public Visitor Registration Handler
 * St. Dominic Savio College - Visitor Management System
 * 
 * This handles visitor registration from the public form (index.php)
 * No authentication required for this endpoint
 */

ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);

header('Content-Type: application/json');

try {
    require_once '../config/config.php';
    require_once '../config/settings.php';
} catch (Throwable $bootErr) {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Server configuration error: ' . $bootErr->getMessage(), 'data' => null]);
    exit();
}

$response = ['success' => false, 'message' => '', 'data' => null];

try {
    // Only allow POST requests
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Only POST requests are allowed');
    }

    // Accept multipart/form-data (FormData) — supports file uploads
    $data = $_POST;

    // Validate required fields
    $required_fields = ['fullName', 'contactNumber', 'purpose', 'personToVisit'];
    foreach ($required_fields as $field) {
        if (empty($data[$field])) {
            throw new Exception("Missing required field: $field");
        }
    }

    // Sanitize and validate input
    $fullName = sanitizeInput($data['fullName']);
    $contactNumber = sanitizeInput($data['contactNumber']);
    $email = !empty($data['email']) ? sanitizeInput($data['email']) : null;
    $address = !empty($data['address']) ? sanitizeInput($data['address']) : null;
    $purpose = sanitizeInput($data['purpose']);
    $purposeOther = !empty($data['purposeOther']) ? sanitizeInput($data['purposeOther']) : null;
    $department = !empty($data['department']) ? sanitizeInput($data['department']) : null;
    $departmentOther = !empty($data['departmentOther']) ? sanitizeInput($data['departmentOther']) : null;
    $personToVisit = sanitizeInput($data['personToVisit']);
    $idType = !empty($data['idType']) ? sanitizeInput($data['idType']) : null;
    $idTypeOther = !empty($data['idTypeOther']) ? sanitizeInput($data['idTypeOther']) : null;
    $isGroupVisitRaw = $data['isGroupVisit'] ?? '';
    $isGroupVisit = ($isGroupVisitRaw === 'true' || $isGroupVisitRaw === '1' || $isGroupVisitRaw === 1) ? 1 : 0;
    $groupSize = !empty($data['groupSize']) ? (int)$data['groupSize'] : 1;
    $groupMembers = !empty($data['groupMembers']) ? sanitizeInput($data['groupMembers']) : null;
    $additionalNotes = !empty($data['additionalNotes']) ? sanitizeInput($data['additionalNotes']) : null;

    // Handle ID photo upload (file upload or camera-captured blob)
    $idPhotoPath = null;
    if (!empty($_FILES['id_photo']) && $_FILES['id_photo']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = dirname(__DIR__) . '/assets/uploads/visitor-ids/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        $allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $fileMime = mime_content_type($_FILES['id_photo']['tmp_name']);
        if (!in_array($fileMime, $allowedMimes)) {
            throw new Exception('Invalid ID photo format. Please upload a JPG, PNG, GIF, or WebP image.');
        }
        if ($_FILES['id_photo']['size'] > 5 * 1024 * 1024) {
            throw new Exception('ID photo is too large. Maximum size is 5MB.');
        }
        $ext = match($fileMime) {
            'image/png'  => 'png',
            'image/gif'  => 'gif',
            'image/webp' => 'webp',
            default      => 'jpg',
        };
        $filename = 'id_' . time() . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
        if (!move_uploaded_file($_FILES['id_photo']['tmp_name'], $uploadDir . $filename)) {
            throw new Exception('Failed to save ID photo. Please try again.');
        }
        $idPhotoPath = 'assets/uploads/visitor-ids/' . $filename;
    }

    // Parse full name into first and last name
    $nameParts = explode(' ', $fullName, 2);
    $firstName = $nameParts[0];
    $lastName = isset($nameParts[1]) ? $nameParts[1] : '';

    // Validate phone number format (Philippine mobile)
    if (!preg_match('/^09[0-9]{9}$/', $contactNumber)) {
        throw new Exception('Invalid phone number format. Please use format: 09XXXXXXXXX');
    }

    // Validate email if provided
    if ($email && !isValidEmail($email)) {
        throw new Exception('Invalid email format');
    }

    // Handle "Other" values
    if (strtolower((string)$purpose) === 'other' && $purposeOther) {
        $purpose = $purposeOther;
    }
    if (strtolower((string)$department) === 'other' && $departmentOther) {
        $department = $departmentOther;
    }
    if (strtolower((string)$idType) === 'other' && $idTypeOther) {
        $idType = $idTypeOther;
    }

    // Validate group visit data
    if ($isGroupVisit) {
        if ($groupSize < 2) {
            throw new Exception('Group size must be at least 2 people');
        }
        if (empty($groupMembers)) {
            throw new Exception('Group members information is required for group visits');
        }
    }

    // Check if visitor already exists
    $existingVisitor = $db->fetch(
        "SELECT * FROM visitors WHERE phone = ?", 
        [$contactNumber]
    );

    $visitorId = null;

    if ($existingVisitor) {
        // Update existing visitor information
        $visitorId = $existingVisitor['id'];

        // Only overwrite stored photo if a new one was uploaded
        if ($idPhotoPath !== null) {
            $db->execute(
                "UPDATE visitors SET 
                    first_name = ?, 
                    last_name = ?, 
                    email = ?, 
                    address = ?, 
                    id_type = ?,
                    id_photo_path = ?,
                    updated_at = NOW()
                WHERE id = ?",
                [$firstName, $lastName, $email, $address, $idType, $idPhotoPath, $visitorId]
            );
        } else {
            $db->execute(
                "UPDATE visitors SET 
                    first_name = ?, 
                    last_name = ?, 
                    email = ?, 
                    address = ?, 
                    id_type = ?, 
                    updated_at = NOW()
                WHERE id = ?",
                [$firstName, $lastName, $email, $address, $idType, $visitorId]
            );
        }
    } else {
        // Create new visitor
        $db->execute(
            "INSERT INTO visitors (
                first_name, last_name, phone, email, address, id_type, id_photo_path, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())",
            [$firstName, $lastName, $contactNumber, $email, $address, $idType, $idPhotoPath]
        );
        
        $visitorId = $db->lastInsertId();
    }

    // Check if visitor is currently checked in
    $existingVisit = $db->fetch(
        "SELECT * FROM visits WHERE visitor_id = ? AND status = 'checked_in'",
        [$visitorId]
    );

    if ($existingVisit) {
        throw new Exception('This visitor is already checked in. Please check out first before registering a new visit.');
    }

    // Block re-entry after check-out on the same day
    $checkedOutToday = $db->fetch(
        "SELECT id FROM visits WHERE visitor_id = ? AND status = 'checked_out' AND DATE(check_in_time) = CURDATE()",
        [$visitorId]
    );
    if ($checkedOutToday) {
        throw new Exception('You have already checked out today and cannot register again until tomorrow.');
    }

    // Check blacklist
    $isBlacklisted = $db->fetch(
        "SELECT * FROM blacklist 
        WHERE (visitor_id = ? OR phone = ?) 
        AND status = 'active' 
        AND (is_permanent = 1 OR expiry_date >= CURDATE())",
        [$visitorId, $contactNumber]
    );

    if ($isBlacklisted) {
        throw new Exception('This visitor is blacklisted and cannot register for a visit.');
    }

    // Campus capacity check
    $maxCampusCapacity = (int)getSystemSetting('max_group_size', '0');
    if ($maxCampusCapacity > 0) {
        $occupancyRow = $db->fetch(
            "SELECT COALESCE(SUM(CASE WHEN is_group_visit = 1 THEN COALESCE(NULLIF(group_size, 0), 1) ELSE 1 END), 0) AS total_people
             FROM visits WHERE status = 'checked_in'"
        );
        $currentPeople = (int)($occupancyRow['total_people'] ?? 0);
        $incomingPeople = ($isGroupVisit && $groupSize > 1) ? $groupSize : 1;
        if (($currentPeople + $incomingPeople) > $maxCampusCapacity) {
            throw new Exception(
                "Campus capacity limit of {$maxCampusCapacity} " .
                ($maxCampusCapacity === 1 ? 'visitor' : 'visitors') .
                " has been reached. New check-ins are paused until someone checks out."
            );
        }
    }

    // Generate visitor pass
    $visitorPass = generateVisitorPass();
    
    // Ensure visitor pass is unique
    while ($db->fetch("SELECT id FROM visits WHERE visit_pass = ?", [$visitorPass])) {
        $visitorPass = generateVisitorPass();
    }

    // Calculate expected checkout time (default 8 hours)
    $expectedDuration = DEFAULT_VISIT_DURATION; // minutes
    $expectedCheckout = date('Y-m-d H:i:s', strtotime("+$expectedDuration minutes"));

    // For public registration, we'll use a system user ID (1) or create a special "self-registration" user
    // For now, let's use user ID 1 (admin) for checked_in_by
    $checkedInBy = 1; // System/Self-registration

    // Create visit record
    $db->execute(
        "INSERT INTO visits (
            visitor_id, person_to_visit, department, purpose, visit_pass,
            check_in_time, expected_checkout_time, expected_duration,
            status, is_group_visit, group_size, group_members, 
            additional_notes, checked_in_by, created_at
        ) VALUES (?, ?, ?, ?, ?, NOW(), ?, ?, 'checked_in', ?, ?, ?, ?, ?, NOW())",
        [
            $visitorId, $personToVisit, $department, $purpose, $visitorPass,
            $expectedCheckout, $expectedDuration, $isGroupVisit, $groupSize,
            $groupMembers, $additionalNotes, $checkedInBy
        ]
    );

    $visitId = $db->lastInsertId();

    // Prepare response data
    $responseData = [
        'visitor_id' => $visitorId,
        'visit_id' => $visitId,
        'visitor_pass' => $visitorPass,
        'visitor_name' => $fullName,
        'check_in_time' => date('Y-m-d H:i:s'),
        'expected_checkout' => $expectedCheckout,
        'purpose' => $purpose,
        'person_to_visit' => $personToVisit,
        'department' => $department,
        'is_group_visit' => $isGroupVisit,
        'group_size' => $groupSize,
        'id_photo_path' => $idPhotoPath,
    ];

    $response = [
        'success' => true,
        'message' => 'Visitor registration successful! Welcome to St. Dominic Savio College.',
        'data' => $responseData
    ];

} catch (Exception $e) {
    $response = [
        'success' => false,
        'message' => $e->getMessage(),
        'data' => null
    ];
    
    // Log the error
    error_log("Visitor Registration Error: " . $e->getMessage() . " - Data: " . print_r($data ?? [], true));
}

ob_end_clean();
echo json_encode($response);
exit();
?>