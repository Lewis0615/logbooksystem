<?php
/**
 * Main Entry Point
 * St. Dominic Savio College - Visitor Management System
 */

require_once 'config/config.php';
require_once 'config/auth.php';

// Check if user is logged in
if ($auth->isLoggedIn()) {
    // Check session timeout
    if (!$auth->checkSessionTimeout()) {
        header('Location: login.php?message=session_expired');
        exit();
    }
    
    // Redirect based on user role
    $user_role = $_SESSION['user_role'] ?? '';
    
    switch ($user_role) {
        case ROLE_ADMIN:
        case ROLE_SUPERVISOR:
            header('Location: modules/admin/dashboard.php');
            break;
        case ROLE_GUARD:
            header('Location: modules/visitor/checkin.php');
            break;
        default:
            // Unknown role, logout and redirect to login
            $auth->logout();
            header('Location: login.php?message=invalid_role');
            break;
    }
} else {
    // User not logged in, show homepage with options
    // Load system settings for visitor form
    require_once 'config/settings.php';
    $sys               = getAllSystemSettings();
    $enforce_dress_code = ($sys['enforce_dress_code'] ?? '1') == '1';
    $require_photo      = ($sys['require_photo']      ?? '0') == '1';
    $require_id_upload  = ($sys['require_id_upload']  ?? '0') == '1';
    // Load visit purposes and offices from DB, falling back to config.php defaults
    $visit_purposes = getSettingList('visit_purposes_list', $visit_purposes);
    $offices        = getSettingList('offices_list', $offices);
    // Load dress code items from DB
    $_dc_default = [
        ['title'=>'Proper Attire',         'status'=>'allowed',     'image'=>'proper-attire.svg'],
        ['title'=>'Closed Footwear',        'status'=>'allowed',     'image'=>'closed-footwear.svg'],
        ['title'=>'Sleeveless / Tank Tops', 'status'=>'not_allowed', 'image'=>'sleeveless.svg'],
        ['title'=>'Short Skirts/Shorts',    'status'=>'not_allowed', 'image'=>'short-skirts.svg'],
        ['title'=>'Slippers / Flip-flops',  'status'=>'not_allowed', 'image'=>'slippers.svg'],
        ['title'=>'Offensive Clothing',     'status'=>'not_allowed', 'image'=>'offensive-clothing.svg'],
    ];
    $dress_code_items_db = getSettingList('dress_code_items', $_dc_default);
    $max_group_size = (int)($sys['max_group_size'] ?? 0); // 0 = infinite
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo APP_NAME; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --green-dark: #1a4d2e;
            --green-mid: #256340;
            --green-light: #e8f5ee;
            --green-accent: #2d7a4f;
            --gold: #c9a84c;
            --gold-light: #e8c96a;
            --border: #c2dece;
            --text-muted: #a8c9b5;
            --custom-color: #5853f0;
            --custom-color-accent: #050089; /* Example custom color variable */
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'DM Sans', sans-serif;
            background: var(--green-light);
            min-height: 100vh;
            display: flex;
            flex-direction: row;
        }

        /* LEFT PANEL */
        .left-panel {
            width: 30%;
            background: var(--green-dark);
            position: relative;
            overflow: hidden;
            padding: 3rem 2.5rem;
            display: flex;
            flex-direction: column;
            justify-content: flex-start;
            min-height: 100vh;
        }

        .left-panel::before {
            content: '';
            position: absolute;
            top: -50px;
            right: -50px;
            width: 200px;
            height: 200px;
            background: radial-gradient(circle, rgba(201, 168, 76, 0.15) 0%, transparent 70%);
            border-radius: 50%;
            z-index: 1;
        }

        .left-panel::after {
            content: '';
            position: absolute;
            bottom: -100px;
            left: -100px;
            width: 300px;
            height: 300px;
            background: radial-gradient(circle, rgba(201, 168, 76, 0.1) 0%, transparent 70%);
            border-radius: 50%;
            z-index: 1;
        }

        .left-panel-content {
            position: relative;
            z-index: 2;
        }

        .logo {
            width: 72px;
            height: 72px;
            border: 2px solid var(--gold);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1.5rem;
        }

        .logo svg {
            width: 32px;
            height: 32px;
            fill: var(--gold);
        }

        .school-name {
            font-family: 'Playfair Display', serif;
            font-size: 2rem;
            font-weight: 700;
            color: white;
            line-height: 1.2;
            margin-bottom: 0.75rem;
        }

        .subtitle {
            color: var(--gold);
            font-size: 0.65rem;
            text-transform: uppercase;
            letter-spacing: 0.18em;
            margin-bottom: 1.5rem;
            font-weight: 500;
        }

        .divider {
            width: 44px;
            height: 2px;
            background: linear-gradient(90deg, var(--gold) 0%, var(--gold-light) 100%);
            margin-bottom: 2rem;
        }

        .welcome-heading {
            font-family: 'Playfair Display', serif;
            font-size: 1.75rem;
            font-weight: 600;
            color: white;
            margin-bottom: 1rem;
        }

        .welcome-text {
            color: var(--text-muted);
            font-size: 0.95rem;
            line-height: 1.5;
            margin-bottom: 2.5rem;
        }

        .feature-cards {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .feature-card {
            background: rgba(45, 122, 79, 0.3);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 14px;
            padding: 1.25rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            transition: all 0.2s ease;
        }

        .feature-card:hover {
            background: rgba(45, 122, 79, 0.4);
        }

        .feature-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            flex-shrink: 0;
        }

        .feature-icon.gold {
            background: linear-gradient(135deg, var(--gold) 0%, var(--gold-light) 100%);
        }

        .feature-icon.teal {
            background: linear-gradient(135deg, #2dd4bf 0%, #14b8a6 100%);
        }

        .feature-info h4 {
            color: white;
            font-size: 0.9rem;
            font-weight: 600;
            margin-bottom: 0.25rem;
        }

        .feature-info p {
            color: var(--text-muted);
            font-size: 0.8rem;
            line-height: 1.3;
        }

        /* RIGHT PANEL */
        .right-panel {
            flex: 1;
            background: var(--green-light);
            padding: 3rem 2.5rem;
            overflow-y: auto;
        }

        .form-header {
            margin-bottom: 2rem;
        }

        .step-label {
            color: var(--green-accent);
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            font-weight: 600;
            margin-bottom: 0.75rem;
        }

        .form-title {
            font-family: 'Playfair Display', serif;
            font-size: 2.25rem;
            font-weight: 700;
            color: var(--green-dark);
            margin-bottom: 0.5rem;
        }

        .form-subtitle {
            color: #666;
            font-size: 0.9rem;
            margin-bottom: 1.5rem;
        }

        .progress-bar {
            display: flex;
            gap: 6px;
            margin-bottom: 2rem;
        }

        .progress-segment {
            height: 4px;
            flex: 1;
            border-radius: 99px;
        }

        .progress-segment.active-1 { background: var(--green-dark); }
        .progress-segment.active-2 { background: var(--green-accent); }
        .progress-segment.inactive { background: #e5e7eb; }

        /* FORM STYLES */
        .registration-form {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group.full-width {
            grid-column: 1 / -1;
        }

        .form-label {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: var(--green-dark);
            font-size: 0.9rem;
        }

        .form-label svg {
            width: 16px;
            height: 16px;
            fill: var(--green-accent);
        }

        .form-input,
        .form-select,
        .form-textarea {
            background: white;
            border: 1.5px solid var(--border);
            border-radius: 10px;
            padding: 13px 16px;
            font-size: 0.9rem;
            transition: all 0.2s ease;
            font-family: inherit;
        }

        .form-input:focus,
        .form-select:focus,
        .form-textarea:focus {
            outline: none;
            border-color: var(--green-accent);
            box-shadow: 0 0 0 3px rgba(45, 122, 79, 0.1);
        }

        .form-textarea {
            min-height: 100px;
            resize: vertical;
        }

        .form-select {
            appearance: none;
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3e%3c/svg%3e");
            background-position: right 12px center;
            background-repeat: no-repeat;
            background-size: 16px;
            padding-right: 40px;
        }

        /* PHOTO UPLOAD */
        .photo-upload-zone {
            border: 2px dashed var(--border);
            border-radius: 14px;
            background: rgba(255, 255, 255, 0.7);
            padding: 2rem;
            text-align: center;
            position: relative;
            transition: all 0.2s ease;
            cursor: pointer;
        }

        .photo-upload-zone:hover {
            border-color: var(--green-accent);
            background: rgba(232, 245, 238, 0.8);
        }

        .photo-upload-zone input[type="file"] {
            position: absolute;
            inset: 0;
            opacity: 0;
            cursor: pointer;
        }

        .upload-icon {
            width: 48px;
            height: 48px;
            border: 2px dashed var(--border);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
        }

        .upload-icon svg {
            width: 20px;
            height: 20px;
            fill: var(--green-accent);
        }

        .upload-text h4 {
            color: var(--green-dark);
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .upload-text p {
            color: #666;
            font-size: 0.85rem;
            margin-bottom: 1rem;
        }

        .camera-controls {
            display: flex;
            gap: 1rem;
            justify-content: center;
            margin-top: 1rem;
        }

        .camera-btn {
            background: var(--green-dark);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 500;
            font-size: 0.85rem;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .camera-btn:hover {
            background: var(--green-accent);
        }

        .camera-btn.secondary {
            background: #6b7280;
        }

        .camera-btn.secondary:hover {
            background: #4b5563;
        }

        .camera-preview {
            display: none;
            margin-top: 1rem;
        }

        .camera-preview video {
            width: 100%;
            max-width: 300px;
            border-radius: 8px;
            background: #000;
        }

        .camera-preview canvas {
            display: none;
        }

        .photo-preview {
            display: none;
            margin-top: 1rem;
            text-align: center;
        }

        .photo-preview img {
            max-width: 200px;
            max-height: 200px;
            border-radius: 8px;
            margin-bottom: 0.5rem;
        }

        .photo-preview .filename {
            font-size: 0.8rem;
            color: #666;
        }

        .retake-btn {
            background: #ef4444;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            font-size: 0.8rem;
            cursor: pointer;
            margin-top: 0.5rem;
        }

        .retake-btn:hover {
            background: #dc2626;
        }

        /* VISITOR PASS CONFIRMATION */
        .pass-confirmation {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.75);
            backdrop-filter: blur(5px);
            z-index: 1000;
            align-items: center;
            justify-content: center;
            padding: 2rem;
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes slideUp {
            from { 
                opacity: 0;
                transform: translateY(30px); 
            }
            to { 
                opacity: 1;
                transform: translateY(0); 
            }
        }

        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        .pass-card-wrapper {
            animation: slideUp 0.4s ease;
        }

        .pass-card {
            background: linear-gradient(135deg, #ffffff 0%, #f8fafb 100%);
            border-radius: 20px;
            padding: 0;
            max-width: 480px;
            width: 100%;
            text-align: center;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.2);
            overflow: hidden;
            border: 3px solid var(--green-accent);
        }

        .pass-card-header {
            background: linear-gradient(135deg, var(--green-dark) 0%, var(--green-accent) 100%);
            padding: 2rem 2rem 1.5rem;
            position: relative;
            overflow: hidden;
        }

        .pass-card-header::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -10%;
            width: 200px;
            height: 200px;
            background: radial-gradient(circle, rgba(255, 255, 255, 0.15) 0%, transparent 70%);
            border-radius: 50%;
        }

        .pass-card-header::after {
            content: '';
            position: absolute;
            bottom: -30%;
            left: -5%;
            width: 150px;
            height: 150px;
            background: radial-gradient(circle, rgba(255, 255, 255, 0.1) 0%, transparent 70%);
            border-radius: 50%;
        }

        .pass-header-content {
            position: relative;
            z-index: 2;
        }

        .pass-logo {
            width: 70px;
            height: 70px;
            background: white;
            border-radius: 50%;
            margin: 0 auto 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }

        .pass-logo img {
            width: 45px;
            height: 45px;
        }

        .pass-school-name {
            color: white;
            font-family: 'Playfair Display', serif;
            font-size: 1.3rem;
            font-weight: 700;
            margin-bottom: 0.25rem;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
        }

        .pass-subtitle {
            color: rgba(255, 255, 255, 0.9);
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.15em;
            font-weight: 500;
        }

        .pass-body {
            padding: 2rem;
        }

        .pass-success-icon {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            border-radius: 50%;
            margin: 0 auto 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
        }

        .pass-success-icon svg {
            width: 32px;
            height: 32px;
            fill: white;
        }

        .pass-title {
            color: var(--green-dark);
            font-family: 'Playfair Display', serif;
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .pass-message {
            color: #6b7280;
            font-size: 0.9rem;
            margin-bottom: 2rem;
        }

        .pass-code-section {
            background: linear-gradient(135deg, var(--green-light) 0%, #d1f0e1 100%);
            border: 3px solid var(--green-accent);
            border-radius: 16px;
            padding: 1.5rem;
            margin: 1.5rem 0;
            position: relative;
            overflow: hidden;
        }

        .pass-code-section::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -20%;
            width: 150px;
            height: 150px;
            background: radial-gradient(circle, rgba(45, 122, 79, 0.1) 0%, transparent 70%);
            border-radius: 50%;
        }

        .pass-code-label {
            font-size: 0.7rem;
            color: var(--green-accent);
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            margin-bottom: 0.75rem;
            position: relative;
        }

        .pass-code-display {
            background: white;
            border-radius: 12px;
            padding: 1.25rem;
            margin-bottom: 0.5rem;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            position: relative;
        }

        .pass-code-number {
            font-size: 3rem;
            font-weight: 900;
            color: var(--green-dark);
            font-family: 'Monaco', 'Courier New', monospace;
            letter-spacing: 0.05em;
            text-shadow: 2px 2px 0px rgba(45, 122, 79, 0.1);
        }

        .pass-code-note {
            font-size: 0.7rem;
            color: var(--green-accent);
            font-style: italic;
            position: relative;
        }

        .pass-details {
            text-align: left;
            margin: 1.5rem 0;
            background: #f9fafb;
            border-radius: 12px;
            padding: 1.25rem;
        }

        .pass-detail-row {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
            padding-bottom: 1rem;
            border-bottom: 1px dashed #e5e7eb;
        }

        .pass-detail-row:last-child {
            margin-bottom: 0;
            padding-bottom: 0;
            border-bottom: none;
        }

        .pass-detail-label {
            color: #6b7280;
            font-weight: 600;
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .pass-detail-label svg {
            width: 14px;
            height: 14px;
            fill: currentColor;
        }

        .pass-detail-value {
            color: var(--green-dark);
            font-weight: 700;
            font-size: 0.9rem;
            text-align: right;
            max-width: 60%;
        }

        .pass-footer {
            background: #f9fafb;
            padding: 1.5rem 2rem;
            border-top: 2px dashed #e5e7eb;
        }

        .pass-footer-note {
            font-size: 0.75rem;
            color: #6b7280;
            margin-bottom: 1.25rem;
            line-height: 1.5;
        }

        .pass-actions {
            display: flex;
            gap: 1rem;
        }

        .pass-btn {
            flex: 1;
            padding: 14px 24px;
            border: none;
            border-radius: 10px;
            font-weight: 700;
            font-size: 0.9rem;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }

        .pass-btn svg {
            width: 18px;
            height: 18px;
            fill: currentColor;
        }

        .pass-btn.download {
            background: linear-gradient(135deg, var(--green-dark) 0%, var(--green-accent) 100%);
            color: white;
            box-shadow: 0 4px 12px rgba(45, 122, 79, 0.3);
        }

        .pass-btn.download:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(45, 122, 79, 0.4);
        }

        .pass-btn.download:active {
            transform: translateY(0);
        }

        .pass-btn.close {
            background: #f3f4f6;
            color: #374151;
        }

        .pass-btn.close:hover {
            background: #e5e7eb;
        }

        /* FILE UPLOAD BUTTON */
        .file-upload-btn {
            background: var(--green-dark);
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 8px;
            font-weight: 500;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.2s ease;
            font-size: 0.9rem;
        }

        .file-upload-btn:hover {
            background: var(--green-accent);
        }

        .file-upload-btn svg {
            width: 16px;
            height: 16px;
            fill: currentColor;
        }

        .file-upload-btn input[type="file"] {
            position: absolute;
            opacity: 0;
            pointer-events: none;
        }

        .file-name {
            margin-top: 0.5rem;
            font-size: 0.85rem;
            color: #666;
        }

        /* SUBMIT BUTTON */
        .submit-btn {
            background: var(--custom-color);
            color: white;
            border: none;
            padding: 16px 24px;
            border-radius: 10px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.75rem;
            width: 100%;
        }

        .submit-btn:hover {
            background: var(--custom-color-accent);
        }

        .submit-btn:active {
            transform: scale(0.99);
        }

        .submit-btn svg {
            width: 18px;
            height: 18px;
            fill: currentColor;
        }

        /* DRESS CODE SECTION - LEFT PANEL */
        .dress-code-section {
            background: rgba(45, 122, 79, 0.2);
            border: 1px solid rgba(201, 168, 76, 0.3);
            border-radius: 14px;
            padding: 1.5rem;
            margin-top: 2rem;
        }

        .dress-code-header {
            margin-bottom: 1.5rem;
        }

        .dress-code-title {
            font-family: 'Playfair Display', serif;
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--gold-light);
            margin-bottom: 0.5rem;
        }

        .dress-code-subtitle {
            color: var(--text-muted);
            font-size: 0.8rem;
            line-height: 1.4;
        }

        .dress-code-grid {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
            margin-bottom: 1rem;
        }

        .dress-code-card {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            padding: 0.875rem;
            display: flex;
            align-items: center;
            gap: 0.875rem;
            transition: all 0.2s ease;
            position: relative;
        }

        .dress-code-card.allowed {
            border-left: 3px solid #10b981;
        }

        .dress-code-card.not-allowed {
            border-left: 3px solid #ef4444;
        }

        .dress-code-card:hover {
            background: rgba(255, 255, 255, 0.08);
            transform: translateX(4px);
        }

        .dress-code-image-wrapper {
            width: 60px;
            height: 60px;
            flex-shrink: 0;
            border-radius: 8px;
            overflow: hidden;
            background: rgba(255, 255, 255, 0.9);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .dress-code-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .dress-code-card:hover .dress-code-image {
            transform: scale(1.1);
        }

        .dress-code-content {
            flex: 1;
            min-width: 0;
        }

        .dress-code-status {
            display: inline-block;
            padding: 0.2rem 0.6rem;
            border-radius: 12px;
            font-size: 0.65rem;
            font-weight: 600;
            margin-bottom: 0.35rem;
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }

        .dress-code-status.allowed-badge {
            background: rgba(16, 185, 129, 0.2);
            color: #6ee7b7;
        }

        .dress-code-status.notallowed-badge {
            background: rgba(239, 68, 68, 0.2);
            color: #fca5a5;
        }

        .dress-code-item-title {
            font-size: 0.85rem;
            font-weight: 600;
            color: white;
            margin-bottom: 0.25rem;
            line-height: 1.3;
        }

        .dress-code-description {
            font-size: 0.72rem;
            color: var(--text-muted);
            line-height: 1.4;
            display: none;
        }

        .dress-code-footer {
            background: rgba(201, 168, 76, 0.15);
            border: 1px solid rgba(201, 168, 76, 0.3);
            border-radius: 8px;
            padding: 0.75rem;
            text-align: center;
            margin-top: 1rem;
        }

        .dress-code-footer p {
            color: var(--gold-light);
            font-size: 0.7rem;
            font-weight: 500;
            margin: 0;
            line-height: 1.4;
        }

        .dress-code-expand {
            color: var(--text-muted);
            font-size: 0.7rem;
            text-align: center;
            margin-top: 0.75rem;
            cursor: pointer;
            transition: color 0.2s ease;
        }

        .dress-code-expand:hover {
            color: var(--gold);
        }

        /* RESPONSIVE DESIGN */
        @media (max-width: 900px) {
            body {
                flex-direction: column;
            }

            .left-panel {
                width: 100%;
                min-height: auto;
                padding: 2rem 1.5rem;
            }

            .right-panel {
                padding: 2rem 1.5rem;
            }

            .registration-form {
                grid-template-columns: 1fr;
            }

            .form-group.full-width {
                grid-column: 1;
            }
            
            .dress-code-section {
                margin-top: 1.5rem;
                padding: 1.25rem;
            }
            
            .dress-code-image-wrapper {
                width: 55px;
                height: 55px;
            }
        }

        @media (max-width: 480px) {
            .right-panel {
                padding: 1.5rem 1rem;
            }

            .form-title {
                font-size: 1.8rem;
            }

            .left-panel {
                padding: 1.5rem 1rem;
            }

            .school-name {
                font-size: 1.6rem;
            }
            
            .dress-code-section {
                padding: 1rem;
                margin-top: 1.25rem;
            }
            
            .dress-code-image-wrapper {
                width: 50px;
                height: 50px;
            }
            
            .dress-code-title {
                font-size: 1.1rem;
            }
            
            .dress-code-card {
                padding: 0.75rem;
                gap: 0.75rem;
            }
        }
    </style>
</head>
<body>
    <!-- LEFT PANEL -->
    <div class="left-panel">
        <div class="left-panel-content">
            <!-- Logo -->
            <div class="logo">
                <img src="/logbooksystem/assets/images/sdsclogo.png" alt="Logo" class="logo-icon" style="width: 50px; height: 50px;">
            </div>

            <!-- School Info -->
            <h1 class="school-name">St. Dominic Savio College</h1>
            <div class="subtitle">Visitor Log Book Management System</div>
            <div class="divider"></div>

            <!-- Welcome Section -->
            <h2 class="welcome-heading">Welcome, Visitor!</h2>
            <p class="welcome-text">
                Register quickly and securely to access our campus. 
                Your information is kept confidential and helps us maintain a safe environment.
            </p>
            
            <?php if ($enforce_dress_code): ?>
            <!-- Dress Code Section -->
            <div class="dress-code-section">
                <div class="dress-code-header">
                    <h2 class="dress-code-title">Dress Code Policy</h2>
                    <p class="dress-code-subtitle">Please observe our dress code guidelines when visiting campus</p>
                </div>
                
                <div class="dress-code-grid">
                    <?php foreach ($dress_code_items_db as $dc_item):
                        $dc_cls   = ($dc_item['status'] ?? 'allowed') === 'allowed' ? 'allowed' : 'not-allowed';
                        $dc_badge = $dc_cls === 'allowed'
                            ? '<div class="dress-code-status allowed-badge">✓ Allowed</div>'
                            : '<div class="dress-code-status notallowed-badge">✗ Not Allowed</div>';
                        $dc_img   = htmlspecialchars($dc_item['image']  ?? '');
                        $dc_title = htmlspecialchars($dc_item['title']  ?? '');
                    ?>
                    <div class="dress-code-card <?php echo $dc_cls; ?>">
                        <div class="dress-code-image-wrapper">
                            <img src="assets/images/dress-code/<?php echo $dc_img; ?>"
                                 alt="<?php echo $dc_title; ?>" class="dress-code-image">
                        </div>
                        <div class="dress-code-content">
                            <?php echo $dc_badge; ?>
                            <h3 class="dress-code-item-title"><?php echo $dc_title; ?></h3>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <div class="dress-code-footer">
                    <p>⚠️ Visitors not following the dress code may be denied entry</p>
                </div>
            </div>
            <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- RIGHT PANEL -->
    <div class="right-panel">
        <div class="form-header">
            <h2 class="form-title">Visitor Registration</h2>
            <p class="form-subtitle">Fields marked * are required.</p>
        

        <!-- Registration Form -->
        <form class="registration-form" id="visitor-form">
            <!-- Full Name -->
            <div class="form-group">
                <label class="form-label" for="fullName">
                    <svg viewBox="0 0 24 24">
                        <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
                    </svg>
                    Full Name *
                </label>
                <input type="text" id="fullName" class="form-input" placeholder="Enter your full name" required>
            </div>

            <!-- Contact Number -->
            <div class="form-group">
                <label class="form-label" for="contactNumber">
                    <svg viewBox="0 0 24 24">
                        <path d="M6.62 10.79c1.44 2.83 3.76 5.14 6.59 6.59l2.2-2.2c.27-.27.67-.36 1.02-.24 1.12.37 2.33.57 3.57.57.55 0 1 .45 1 1V20c0 .55-.45 1-1 1-9.39 0-17-7.61-17-17 0-.55.45-1 1-1h3.5c.55 0 1 .45 1 1 0 1.25.2 2.45.57 3.57.11.35.03.74-.25 1.02l-2.2 2.2z"/>
                    </svg>
                    Contact Number *
                </label>
                <input type="tel" id="contactNumber" class="form-input" placeholder="09X XXX XXXXX" pattern="09[0-9]{9}" maxlength="12" required title="Please enter a valid Philippine mobile number (e.g., 09123456789)">
                <small style="color: #666; font-size: 0.8rem; margin-top: 0.25rem;"></small>    
            </div>

            <!-- Email Address -->
            <div class="form-group full-width">
                <label class="form-label" for="emailAddress">
                    <svg viewBox="0 0 24 24">
                        <path d="M20 4H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z"/>
                    </svg>
                    Email Address * <span style="font-size:.75rem;color:#6b7280;font-weight:400;">(Gmail only)</span>
                </label>
                <input type="email" id="emailAddress" class="form-input" placeholder="yourname@gmail.com" required
                    pattern="[a-zA-Z0-9._%+\-]+@gmail\.com"
                    title="Please enter a valid Gmail address (e.g., yourname@gmail.com)">
                <small id="emailError" style="color:#ef4444;font-size:.8rem;margin-top:.25rem;display:none;">Please enter a valid Gmail address ending in @gmail.com</small>
            </div>

            <!-- Address -->
            <div class="form-group full-width">
                <label class="form-label" for="address">
                    <svg viewBox="0 0 24 24">
                        <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/>
                    </svg>
                    Address
                </label>
                <input type="text" id="address" class="form-input" placeholder="Street Address, Barangay, City/Municipality, Province, ZIP Code">
            </div>

            <!-- Purpose of Visit -->
            <div class="form-group">
                <label class="form-label" for="purpose">
                    <svg viewBox="0 0 24 24">
                        <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8l-6-6zm4 18H6V4h7v5h5v11z"/>
                    </svg>
                    Purpose of Visit *
                </label>
                <select id="purpose" class="form-select" required>
                    <option value="">Select purpose</option>
                    <?php foreach ($visit_purposes as $vp): ?>
                    <option value="<?php echo htmlspecialchars($vp); ?>"><?php echo htmlspecialchars($vp); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <!-- Purpose Other Input -->
            <div class="form-group" id="purposeOtherContainer" style="display: none;">
                <label class="form-label" for="purposeOther">
                    <svg viewBox="0 0 24 24">
                        <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8l-6-6zm4 18H6V4h7v5h5v11z"/>
                    </svg>
                    Specify Purpose *
                </label>
                <input type="text" id="purposeOther" class="form-input" placeholder="Please specify your purpose">
            </div>

            <!-- Office/Department -->
            <div class="form-group">
                <label class="form-label" for="department">
                    <svg viewBox="0 0 24 24">
                        <path d="M21 16V4a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2h14a2 2 0 002-2zm-11-4c0-1.11.89-2 2-2s2 .89 2 2-.89 2-2 2-2-.89-2-2zM9 18v-2a2 2 0 012-2h2a2 2 0 012 2v2H9z"/>
                    </svg>
                    Office / Department
                </label>
                <select id="department" class="form-select">
                    <option value="">Select department</option>
                    <?php foreach ($offices as $office): ?>
                    <option value="<?php echo htmlspecialchars($office); ?>"><?php echo htmlspecialchars($office); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <!-- Department Other Input -->
            <div class="form-group" id="departmentOtherContainer" style="display: none;">
                <label class="form-label" for="departmentOther">
                    <svg viewBox="0 0 24 24">
                        <path d="M21 16V4a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2h14a2 2 0 002-2zm-11-4c0-1.11.89-2 2-2s2 .89 2 2-.89 2-2 2-2-.89-2-2zM9 18v-2a2 2 0 012-2h2a2 2 0 012 2v2H9z"/>
                    </svg>
                    Specify Department
                </label>
                <input type="text" id="departmentOther" class="form-input" placeholder="Please specify the department">
            </div>

            <!-- Person to Visit -->
            <div class="form-group full-width">
                <label class="form-label" for="personToVisit">
                    <svg viewBox="0 0 24 24">
                        <path d="M16 4c4.42 0 8 3.58 8 8s-3.58 8-8 8c-1.1 0-2.14-.22-3.1-.62L9 21l1.9-3.9c-1.78-1.46-2.9-3.64-2.9-6.1 0-4.42 3.58-8 8-8z"/>
                    </svg>
                    Person to Visit *
                </label>
                <input type="text" id="personToVisit" class="form-input" placeholder="Name of person you're visiting" required>
            </div>

            <!-- Group Visit -->
            <div class="form-group">
                <label class="form-label">
                    <svg viewBox="0 0 24 24">
                        <path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z"/>
                    </svg>
                    Are you visiting as a group?
                </label>
                <div style="display: flex; gap: 1.5rem; margin-top: 0.5rem;">
                    <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                        <input type="radio" name="groupVisit" id="groupVisitYes" value="yes" style="width: 18px; height: 18px; cursor: pointer;">
                        <span style="color: var(--green-dark); font-weight: 500;">Yes</span>
                    </label>
                    <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                        <input type="radio" name="groupVisit" id="groupVisitNo" value="no" checked style="width: 18px; height: 18px; cursor: pointer;">
                        <span style="color: var(--green-dark); font-weight: 500;">No</span>
                    </label>
                </div>
            </div>

            <!-- Number of People in Group -->
            <div class="form-group" id="groupSizeContainer" style="display: none;">
                <label class="form-label" for="groupSize">
                    <svg viewBox="0 0 24 24">
                        <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8zm.5-13H11v6l5.25 3.15.75-1.23-4.5-2.67z"/>
                    </svg>
                    Total Number of People *
                </label>
                <input type="number" id="groupSize" class="form-input"
                       placeholder="Enter total number of people"
                       min="2"
                       max="<?php echo $max_group_size > 0 ? $max_group_size : 9999; ?>">
                <?php if ($max_group_size > 0): ?>
                <small style="color:#666;font-size:.8rem;margin-top:.25rem;">
                    Maximum group size: <strong><?php echo $max_group_size; ?> people</strong> per registration
                </small>
                <?php endif; ?>
                <small id="groupSizeError" style="color:#ef4444;font-size:.8rem;margin-top:.25rem;display:none;"></small>
            </div>

            <!-- Group Members Names -->
            <div class="form-group full-width" id="groupMembersContainer" style="display: none;">
                <label class="form-label">
                    <svg viewBox="0 0 24 24">
                        <path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z"/>
                    </svg>
                    Group Members Full Names *
                </label>
                <div id="groupMembersFields" style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-top: 0.5rem;">
                    <!-- Dynamic name fields will be inserted here -->
                </div>
            </div>

            <!-- Additional Notes -->
            <div class="form-group full-width">
                <label class="form-label" for="notes">
                    <svg viewBox="0 0 24 24">
                        <path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04c.39-.39.39-1.02 0-1.41l-2.34-2.34c-.39-.39-1.02-.39-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/>
                    </svg>
                    Additional Notes
                </label>
                <textarea id="notes" class="form-textarea" placeholder="Any additional information or special requests"></textarea>
            </div>

            <!-- Valid ID Upload -->
            <div class="form-group full-width">
                <label class="form-label" for="idType">
                    <svg viewBox="0 0 24 24">
                        <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8l-6-6zm4 18H6V4h7v5h5v11z"/>
                    </svg>
                    Valid ID Type <?php echo $require_id_upload ? '<span style="color:red;">*</span>' : '(Optional)'; ?>
                </label>
                <select id="idType" class="form-select" <?php echo $require_id_upload ? 'required' : ''; ?>>
                    <option value="">Select ID type</option>
                    <option value="drivers_license">Driver's License</option>
                    <option value="passport">Passport</option>
                    <option value="national_id">National ID / PhilSys ID</option>
                    <option value="voters_id">Voter's ID</option>
                    <option value="prc_id">PRC ID</option>
                    <option value="postal_id">Postal ID</option>
                    <option value="sss_id">SSS ID</option>
                    <option value="gsis_id">GSIS ID</option>
                    <option value="tin_id">TIN ID</option>
                    <option value="school_id">School ID</option>
                    <option value="company_id">Company ID</option>
                    <option value="barangay_id">Barangay ID</option>
                    <option value="senior_citizen_id">Senior Citizen ID</option>
                    <option value="pwd_id">PWD ID</option>
                    <option value="other">Other</option>
                </select>
            </div>
            
            <!-- ID Type Other Input -->
            <div class="form-group" id="idTypeOtherContainer" style="display: none;">
                <label class="form-label" for="idTypeOther">
                    <svg viewBox="0 0 24 24">
                        <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8l-6-6zm4 18H6V4h7v5h5v11z"/>
                    </svg>
                    Specify ID Type
                </label>
                <input type="text" id="idTypeOther" class="form-input" placeholder="Please specify your ID type">
            </div>

            <!-- Valid ID Photo/Upload -->
            <div class="form-group full-width">
                <label class="form-label">
                    <svg viewBox="0 0 24 24">
                        <path d="M23 19V5c0-1.1-.9-2-2-2H3c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h18c1.1 0 2-.9 2-2zM8.5 13.5l2.5 3.01L14.5 12l4.5 6H5l3.5-4.5z"/>
                        <circle cx="9.5" cy="8.5" r="1.5"/>
                    </svg>
                    Upload/Capture Valid ID <?php echo $require_photo ? '<span style="color:red;">*</span>' : '(Optional)'; ?>
                </label>
                <div class="photo-upload-zone" id="photo-zone">
                    <div class="upload-icon">
                        <svg viewBox="0 0 24 24">
                            <path d="M23 19V5c0-1.1-.9-2-2-2H3c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h18c1.1 0 2-.9 2-2zM8.5 13.5l2.5 3.01L14.5 12l4.5 6H5l3.5-4.5z"/>
                            <circle cx="9.5" cy="8.5" r="1.5"/>
                        </svg>
                    </div>
                    <div class="upload-text">
                        <h4>Capture ID Photo or Upload File</h4>
                        <p>Use camera to capture or choose a file</p>
                    </div>
                    <div class="camera-controls">
                        <button type="button" class="camera-btn" id="start-camera-btn">
                            <svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor">
                                <path d="M17 10.5V7c0-.55-.45-1-1-1H4c-.55 0-1 .45-1 1v10c0 .55.45 1 1 1h12c.55 0 1-.45 1-1v-3.5l4 4v-11l-4 4z"/>
                            </svg>
                            Open Camera
                        </button>
                        <label for="validId" class="camera-btn secondary">
                            <svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor">
                                <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8l-6-6zm-1 9V7.5L17.5 12H13z"/>
                            </svg>
                            Upload File
                            <input type="file" id="validId" accept="image/*,.pdf" style="position: absolute; opacity: 0; pointer-events: none;">
                        </label>
                    </div>
                </div>
                
                <div class="camera-preview" id="camera-preview">
                    <video id="camera-video" autoplay playsinline></video>
                    <canvas id="photo-canvas"></canvas>
                    <div class="camera-controls">
                        <button type="button" class="camera-btn" id="capture-btn">
                            <svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor">
                                <circle cx="12" cy="12" r="3.2"/>
                                <path d="M9 2L7.17 4H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2h-3.17L15 2H9zm3 15c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5z"/>
                            </svg>
                            Capture ID Photo
                        </button>
                        <button type="button" class="camera-btn secondary" id="close-camera-btn">
                            <svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor">
                                <path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/>
                            </svg>
                            Cancel
                        </button>
                    </div>
                </div>
                
                <div class="photo-preview" id="photo-preview">
                    <img id="photo-img" src="" alt="Captured ID Photo">
                    <div class="filename" id="photo-filename">Captured ID Photo</div>
                    <button type="button" class="retake-btn" id="retake-btn">Retake Photo</button>
                </div>
            </div>

            <!-- Submit Button -->
            <div class="form-group full-width">
                <button type="submit" class="submit-btn">
                    <svg viewBox="0 0 24 24">
                        <path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41L9 16.17z"/>
                    </svg>
                    Complete Registration
                </button>
            </div>
        </form>
    </div>

    <!-- Visitor Pass Confirmation Modal -->
    <div class="pass-confirmation" id="pass-confirmation">
        <div class="pass-card-wrapper">
            <div class="pass-card" id="visitor-pass-card">
                <!-- Header -->
                <div class="pass-card-header">
                    <div class="pass-header-content">
                        <div class="pass-logo">
                            <img src="/logbooksystem/assets/images/sdsclogo.png" alt="SDSC Logo">
                        </div>
                        <div class="pass-school-name">St. Dominic Savio College</div>
                        <div class="pass-subtitle">Official Visitor Pass</div>
                    </div>
                </div>
                
                <!-- Body -->
                <div class="pass-body">
                    <div class="pass-success-icon">
                        <svg viewBox="0 0 24 24">
                            <path d="M9 16.2L4.8 12l-1.4 1.4L9 19 21 7l-1.4-1.4L9 16.2z"/>
                        </svg>
                    </div>
                    
                    <h3 class="pass-title">Registration Successful!</h3>
                    <p class="pass-message">Your visitor pass has been generated</p>
                    
                    <!-- Pass Code Section -->
                    <div class="pass-code-section">
                        <div class="pass-code-label">Your Visitor Pass Number</div>
                        <div class="pass-code-display">
                            <div class="pass-code-number" id="visitor-pass-code">0000</div>
                        </div>
                        <div class="pass-code-note">Please show this number at the entrance</div>
                    </div>
                    
                    <!-- Details -->
                    <div class="pass-details">
                        <div class="pass-detail-row">
                            <span class="pass-detail-label">
                                <svg viewBox="0 0 24 24"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
                                Visitor Name
                            </span>
                            <span class="pass-detail-value" id="pass-visitor-name">--</span>
                        </div>
                        <div class="pass-detail-row">
                            <span class="pass-detail-label">
                                <svg viewBox="0 0 24 24"><path d="M19 3h-1V1h-2v2H8V1H6v2H5c-1.11 0-1.99.9-1.99 2L3 19c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H5V8h14v11zM7 10h5v5H7z"/></svg>
                                Date
                            </span>
                            <span class="pass-detail-value" id="pass-date">--</span>
                        </div>
                        <div class="pass-detail-row">
                            <span class="pass-detail-label">
                                <svg viewBox="0 0 24 24"><path d="M11.99 2C6.47 2 2 6.48 2 12s4.47 10 9.99 10C17.52 22 22 17.52 22 12S17.52 2 11.99 2zM12 20c-4.42 0-8-3.58-8-8s3.58-8 8-8 8 3.58 8 8-3.58 8-8 8zm.5-13H11v6l5.25 3.15.75-1.23-4.5-2.67z"/></svg>
                                Time
                            </span>
                            <span class="pass-detail-value" id="pass-time">--</span>
                        </div>
                        <div class="pass-detail-row">
                            <span class="pass-detail-label">
                                <svg viewBox="0 0 24 24"><path d="M20 6h-2.18c.11-.31.18-.65.18-1 0-1.66-1.34-3-3-3-1.05 0-1.96.54-2.5 1.35l-.5.67-.5-.68C10.96 2.54 10.05 2 9 2 7.34 2 6 3.34 6 5c0 .35.07.69.18 1H4c-1.11 0-1.99.89-1.99 2L2 19c0 1.11.89 2 2 2h16c1.11 0 2-.89 2-2V8c0-1.11-.89-2-2-2zm-5-2c.55 0 1 .45 1 1s-.45 1-1 1-1-.45-1-1 .45-1 1-1zM9 4c.55 0 1 .45 1 1s-.45 1-1 1-1-.45-1-1 .45-1 1-1zm11 15H4v-2h16v2zm0-5H4V8h5.08L7 10.83 8.62 12 11 8.76l1-1.36 1 1.36L15.38 12 17 10.83 14.92 8H20v6z"/></svg>
                                Purpose
                            </span>
                            <span class="pass-detail-value" id="pass-purpose">--</span>
                        </div>
                        <div class="pass-detail-row">
                            <span class="pass-detail-label">
                                <svg viewBox="0 0 24 24"><path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z"/></svg>
                                To Visit
                            </span>
                            <span class="pass-detail-value" id="pass-person-to-visit">--</span>
                        </div>
                    </div>
                </div>
                
                <!-- Footer -->
                <div class="pass-footer">
                    <p class="pass-footer-note">📋 Please keep this pass number for check-in and check-out. You can download a copy for your records.</p>
                    <div class="pass-actions">
                        <button type="button" class="pass-btn download" id="download-pass-btn">
                            <svg viewBox="0 0 24 24"><path d="M19 12v7H5v-7H3v7c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2v-7h-2zm-6 .67l2.59-2.58L17 11.5l-5 5-5-5 1.41-1.41L11 12.67V3h2z"/></svg>
                            Download Pass
                        </button>
                        <button type="button" class="pass-btn close" id="close-pass-btn">Close</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- HTML2Canvas Library for converting HTML to image -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    
    <script>
        // Settings injected from PHP
        const REQUIRE_PHOTO     = <?php echo json_encode($require_photo); ?>;
        const REQUIRE_ID_UPLOAD = <?php echo json_encode($require_id_upload); ?>;
        const MAX_GROUP_SIZE    = <?php echo json_encode($max_group_size); ?>; // 0 = no limit

        let cameraStream = null;
        let capturedPhotoBlob = null;
        let registrationData = {};

        // Generate visitor pass code
        function generateVisitorPassCode() {
            const prefix = 'VP';
            const timestamp = Date.now().toString().slice(-6); // Last 6 digits of timestamp
            const random = Math.floor(Math.random() * 1000).toString().padStart(3, '0');
            return `${prefix}-${timestamp}-${random}`;
        }

        // Format current date and time
        function getCurrentDateTime() {
            const now = new Date();
            const dateOptions = { 
                year: 'numeric', 
                month: 'long', 
                day: 'numeric' 
            };
            const timeOptions = { 
                hour: '2-digit', 
                minute: '2-digit', 
                second: '2-digit',
                hour12: true 
            };
            
            return {
                date: now.toLocaleDateString('en-US', dateOptions),
                time: now.toLocaleTimeString('en-US', timeOptions),
                timestamp: now.toISOString()
            };
        }

        // Show visitor pass confirmation
        function showVisitorPass(formData) {
            const passCode = generateVisitorPassCode();
            const dateTime = getCurrentDateTime();
            
            // Store registration data
            registrationData = {
                passCode: passCode,
                visitorName: formData.fullName,
                contactNumber: formData.contactNumber,
                address: formData.address || 'Not specified',
                purpose: formData.purpose,
                purposeOther: formData.purposeOther || null,
                department: formData.department || 'Not specified',
                departmentOther: formData.departmentOther || null,
                idType: formData.idType || 'Not specified',
                idTypeOther: formData.idTypeOther || null,
                personToVisit: formData.personToVisit,
                isGroupVisit: formData.isGroupVisit,
                groupSize: formData.groupSize || 1,
                groupMembers: formData.groupMembers || [],
                notes: formData.notes || 'None',
                registrationDate: dateTime.date,
                registrationTime: dateTime.time,
                timestamp: dateTime.timestamp,
                hasPhoto: !!capturedPhotoBlob
            };

            // Submit registration to server
            submitVisitorRegistration(formData);
        }

        // Get purpose text from select value
        function getPurposeText(value, otherValue = null) {
            const purposes = {
                'academic': 'Academic Inquiry',
                'business': 'Business/Official',
                'personal': 'Personal Visit',
                'delivery': 'Delivery',
                'other': otherValue || 'Other'
            };
            return purposes[value] || value;
        }

        // Submit visitor registration to server
        function submitVisitorRegistration(formData) {
            // Show loading state
            const submitBtn = document.querySelector('.submit-btn');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Registering...';
            submitBtn.disabled = true;

            // Prepare data for submission
            const registrationData = {
                fullName: formData.fullName,
                contactNumber: formData.contactNumber,
                email: formData.email || null,
                address: formData.address || null,
                purpose: formData.purpose,
                purposeOther: formData.purposeOther || null,
                department: formData.department || null,
                departmentOther: formData.departmentOther || null,
                personToVisit: formData.personToVisit,
                idType: formData.idType || null,
                idTypeOther: formData.idTypeOther || null,
                isGroupVisit: formData.isGroupVisit,
                groupSize: formData.groupSize || 1,
                groupMembers: formData.groupMembers ? formData.groupMembers.join(', ') : null,
                additionalNotes: formData.notes || null
            };

            // Build FormData so the ID photo (blob or file) is included
            const fd = new FormData();
            Object.entries(registrationData).forEach(([key, val]) => {
                if (val !== null && val !== undefined) fd.append(key, val);
            });
            // Attach ID photo: prefer camera-captured blob, fall back to file input
            if (capturedPhotoBlob) {
                fd.append('id_photo', capturedPhotoBlob, 'id_photo.jpg');
            } else {
                const idFileInput = document.getElementById('validId');
                if (idFileInput && idFileInput.files.length > 0) {
                    fd.append('id_photo', idFileInput.files[0]);
                }
            }

        // Submit to server
            fetch('ajax/visitor-registration.php', {
                method: 'POST',
                // No Content-Type header — browser sets multipart/form-data with correct boundary
                body: fd
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    // Registration successful - show visitor pass with actual data from server
                    showVisitorPassModal(data.data);
                } else {
                    // Registration failed
                    alert('Registration failed: ' + data.message);
                    // Reset submit button
                    submitBtn.innerHTML = originalText;
                    submitBtn.disabled = false;
                }
            })
            .catch(error => {
                console.error('Registration error:', error);
                alert('Registration failed. Please check your internet connection and try again.');
                // Reset submit button
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            });
        }

        // Show visitor pass modal with server data
        function showVisitorPassModal(serverData) {
            const dateTime = getCurrentDateTime();
            
            // Store the server data globally for printing
            registrationData = {
                passCode: serverData.visitor_pass,
                visitorName: serverData.visitor_name,
                purpose: serverData.purpose,
                personToVisit: serverData.person_to_visit,
                registrationDate: dateTime.date,
                registrationTime: dateTime.time
            };
            
            // Update modal content with actual server data
            document.getElementById('visitor-pass-code').textContent = serverData.visitor_pass;
            document.getElementById('pass-visitor-name').textContent = serverData.visitor_name;
            document.getElementById('pass-date').textContent = dateTime.date;
            document.getElementById('pass-time').textContent = dateTime.time;
            document.getElementById('pass-purpose').textContent = serverData.purpose;
            document.getElementById('pass-person-to-visit').textContent = serverData.person_to_visit;
            
            // Show modal
            document.getElementById('pass-confirmation').style.display = 'flex';
            
            // Reset submit button
            const submitBtn = document.querySelector('.submit-btn');
            submitBtn.innerHTML = '<svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M9 16.2L4.8 12l-1.4 1.4L9 19 21 7l-1.4-1.4L9 16.2z"/></svg>Submit Registration';
            submitBtn.disabled = false;
            
            console.log('Visitor Registration Successful:', serverData);
        }

        // Download visitor pass as image
        async function downloadVisitorPass() {
            const downloadBtn = document.getElementById('download-pass-btn');
            const originalText = downloadBtn.innerHTML;
            
            try {
                // Show loading state
                downloadBtn.innerHTML = '<svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor" style="animation: spin 1s linear infinite;"><path d="M12 4V1L8 5l4 4V6c3.31 0 6 2.69 6 6 0 1.01-.25 1.97-.7 2.8l1.46 1.46C19.54 15.03 20 13.57 20 12c0-4.42-3.58-8-8-8zm0 14c-3.31 0-6-2.69-6-6 0-1.01.25-1.97.7-2.8L5.24 7.74C4.46 8.97 4 10.43 4 12c0 4.42 3.58 8 8 8v3l4-4-4-4v3z"/></svg> Generating...';
                downloadBtn.disabled = true;
                
                // Get the pass card element
                const passCard = document.getElementById('visitor-pass-card');
                
                // Use html2canvas to convert the card to canvas
                const canvas = await html2canvas(passCard, {
                    scale: 2, // Higher quality
                    backgroundColor: '#ffffff',
                    logging: false,
                    useCORS: true,
                    allowTaint: true
                });
                
                // Convert canvas to blob
                canvas.toBlob(function(blob) {
                    // Create download link
                    const url = URL.createObjectURL(blob);
                    const link = document.createElement('a');
                    const passNumber = registrationData.passCode || 'visitor-pass';
                    link.download = `SDSC-Visitor-Pass-${passNumber}.png`;
                    link.href = url;
                    link.click();
                    
                    // Clean up
                    URL.revokeObjectURL(url);
                    
                    // Reset button
                    downloadBtn.innerHTML = originalText;
                    downloadBtn.disabled = false;
                    
                    // Show success message
                    console.log('Visitor pass downloaded successfully');
                }, 'image/png');
                
            } catch (error) {
                console.error('Error downloading pass:', error);
                alert('Failed to download visitor pass. Please try again.');
                
                // Reset button
                downloadBtn.innerHTML = originalText;
                downloadBtn.disabled = false;
            }
        }

        // Camera functionality
        async function startCamera() {
            try {
                const constraints = {
                    video: {
                        width: { ideal: 640 },
                        height: { ideal: 480 },
                        facingMode: 'user' // Front-facing camera
                    }
                };
                
                cameraStream = await navigator.mediaDevices.getUserMedia(constraints);
                const video = document.getElementById('camera-video');
                video.srcObject = cameraStream;
                
                // Show camera preview
                document.getElementById('photo-zone').style.display = 'none';
                document.getElementById('camera-preview').style.display = 'block';
                document.getElementById('photo-preview').style.display = 'none';
                
            } catch (error) {
                console.error('Error accessing camera:', error);
                alert('Unable to access camera. Please check permissions and try again.');
            }
        }

        function stopCamera() {
            if (cameraStream) {
                cameraStream.getTracks().forEach(track => track.stop());
                cameraStream = null;
            }
            
            // Hide camera preview
            document.getElementById('camera-preview').style.display = 'none';
            document.getElementById('photo-zone').style.display = 'block';
        }

        function capturePhoto() {
            const video = document.getElementById('camera-video');
            const canvas = document.getElementById('photo-canvas');
            const context = canvas.getContext('2d');
            
            // Set canvas dimensions to match video
            canvas.width = video.videoWidth;
            canvas.height = video.videoHeight;
            
            // Draw current video frame to canvas
            context.drawImage(video, 0, 0);
            
            // Convert canvas to blob
            canvas.toBlob(function(blob) {
                capturedPhotoBlob = blob;
                
                // Create preview
                const img = document.getElementById('photo-img');
                img.src = canvas.toDataURL('image/jpeg', 0.8);
                
                // Show preview and hide camera
                stopCamera();
                document.getElementById('photo-preview').style.display = 'block';
            }, 'image/jpeg', 0.8);
        }

        function retakePhoto() {
            capturedPhotoBlob = null;
            // Clear file input
            document.getElementById('validId').value = '';
            document.getElementById('photo-preview').style.display = 'none';
            document.getElementById('photo-zone').style.display = 'block';
        }

        // Initialize event listeners
        document.addEventListener('DOMContentLoaded', function() {
            // Group visit toggle
            const groupVisitYes = document.getElementById('groupVisitYes');
            const groupVisitNo = document.getElementById('groupVisitNo');
            const groupSizeContainer = document.getElementById('groupSizeContainer');
            const groupSizeInput = document.getElementById('groupSize');
            
            groupVisitYes.addEventListener('change', function() {
                if (this.checked) {
                    groupSizeContainer.style.display = 'flex';
                    groupSizeInput.required = true;
                }
            });
            
            groupVisitNo.addEventListener('change', function() {
                if (this.checked) {
                    groupSizeContainer.style.display = 'none';
                    groupSizeInput.required = false;
                    groupSizeInput.value = '';
                    // Hide and clear group members fields
                    document.getElementById('groupMembersContainer').style.display = 'none';
                    document.getElementById('groupMembersFields').innerHTML = '';
                }
            });
            
            // Apply runtime max from settings
            if (MAX_GROUP_SIZE > 0) {
                groupSizeInput.max = MAX_GROUP_SIZE;
            }

            // Group size change - generate name fields
            groupSizeInput.addEventListener('input', function() {
                const numberOfPeople = parseInt(this.value);
                const groupMembersContainer = document.getElementById('groupMembersContainer');
                const groupMembersFields = document.getElementById('groupMembersFields');
                const errorEl = document.getElementById('groupSizeError');

                // Enforce campus limit
                if (MAX_GROUP_SIZE > 0 && numberOfPeople > MAX_GROUP_SIZE) {
                    errorEl.textContent = `Campus limit is ${MAX_GROUP_SIZE} people. Please enter a number between 2 and ${MAX_GROUP_SIZE}.`;
                    errorEl.style.display = 'block';
                    groupMembersContainer.style.display = 'none';
                    groupMembersFields.innerHTML = '';
                    return;
                } else {
                    errorEl.style.display = 'none';
                }

                if (numberOfPeople >= 2 && numberOfPeople <= (MAX_GROUP_SIZE > 0 ? MAX_GROUP_SIZE : 9999)) {
                    groupMembersContainer.style.display = 'flex';
                    
                    // Clear existing fields
                    groupMembersFields.innerHTML = '';
                    
                    // Generate name fields
                    for (let i = 1; i <= numberOfPeople; i++) {
                        const fieldDiv = document.createElement('div');
                        fieldDiv.style.cssText = 'display: flex; flex-direction: column;';
                        
                        const label = document.createElement('label');
                        label.style.cssText = 'font-size: 0.85rem; color: var(--green-dark); margin-bottom: 0.5rem; font-weight: 500;';
                        label.textContent = `Person ${i} Full Name *`;
                        
                        const input = document.createElement('input');
                        input.type = 'text';
                        input.className = 'form-input';
                        input.placeholder = `Enter full name of person ${i}`;
                        input.required = true;
                        input.id = `groupMember${i}`;
                        input.name = `groupMember${i}`;
                        
                        fieldDiv.appendChild(label);
                        fieldDiv.appendChild(input);
                        groupMembersFields.appendChild(fieldDiv);
                    }
                } else {
                    groupMembersContainer.style.display = 'none';
                    groupMembersFields.innerHTML = '';
                }
            });
            
            // Dropdown "Other" handling
            // Purpose of Visit
            const purposeSelect = document.getElementById('purpose');
            const purposeOtherContainer = document.getElementById('purposeOtherContainer');
            const purposeOtherInput = document.getElementById('purposeOther');
            
            purposeSelect.addEventListener('change', function() {
                if (this.value.toLowerCase() === 'other') {
                    purposeOtherContainer.style.display = 'flex';
                    purposeOtherInput.required = true;
                } else {
                    purposeOtherContainer.style.display = 'none';
                    purposeOtherInput.required = false;
                    purposeOtherInput.value = '';
                }
            });
            
            // Department
            const departmentSelect = document.getElementById('department');
            const departmentOtherContainer = document.getElementById('departmentOtherContainer');
            const departmentOtherInput = document.getElementById('departmentOther');
            
            departmentSelect.addEventListener('change', function() {
                if (this.value.toLowerCase() === 'other') {
                    departmentOtherContainer.style.display = 'flex';
                    departmentOtherInput.required = false;
                } else {
                    departmentOtherContainer.style.display = 'none';
                    departmentOtherInput.value = '';
                }
            });
            
            // ID Type
            const idTypeSelect = document.getElementById('idType');
            const idTypeOtherContainer = document.getElementById('idTypeOtherContainer');
            const idTypeOtherInput = document.getElementById('idTypeOther');
            
            idTypeSelect.addEventListener('change', function() {
                if (this.value === 'other') {
                    idTypeOtherContainer.style.display = 'flex';
                    idTypeOtherInput.required = false; // Not required as ID type itself is optional
                } else {
                    idTypeOtherContainer.style.display = 'none';
                    idTypeOtherInput.value = '';
                }
            });
            
            // Camera controls
            document.getElementById('start-camera-btn').addEventListener('click', startCamera);
            document.getElementById('capture-btn').addEventListener('click', capturePhoto);
            document.getElementById('close-camera-btn').addEventListener('click', stopCamera);
            document.getElementById('retake-btn').addEventListener('click', retakePhoto);

            // Contact number validation - only allow numbers
            const contactNumberInput = document.getElementById('contactNumber');
            contactNumberInput.addEventListener('input', function(e) {
                // Remove any non-numeric characters
                this.value = this.value.replace(/[^0-9]/g, '');
                
                // Limit to 11 digits
                if (this.value.length > 11) {
                    this.value = this.value.slice(0, 11);
                }
            });
            
            contactNumberInput.addEventListener('keypress', function(e) {
                // Only allow numeric keys
                if (e.key && !/[0-9]/.test(e.key) && e.key !== 'Backspace' && e.key !== 'Delete' && e.key !== 'Tab' && e.key !== 'ArrowLeft' && e.key !== 'ArrowRight') {
                    e.preventDefault();
                }
            });

            // Email live validation
            const emailInput = document.getElementById('emailAddress');
            const emailErrorEl = document.getElementById('emailError');
            const gmailPattern = /^[a-zA-Z0-9._%+\-]+@gmail\.com$/i;
            emailInput.addEventListener('input', function() {
                if (this.value && !gmailPattern.test(this.value.trim())) {
                    emailErrorEl.style.display = 'block';
                } else {
                    emailErrorEl.style.display = 'none';
                }
            });
            emailInput.addEventListener('blur', function() {
                if (this.value && !gmailPattern.test(this.value.trim())) {
                    emailErrorEl.style.display = 'block';
                    this.style.borderColor = '#ef4444';
                } else if (this.value) {
                    emailErrorEl.style.display = 'none';
                    this.style.borderColor = '';
                }
            });
            emailInput.addEventListener('focus', function() {
                this.style.borderColor = '';
            });
            const idInput = document.getElementById('validId');
            idInput.addEventListener('change', function(event) {
                const file = event.target.files[0];
                if (file) {
                    // Create preview for uploaded file
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        const img = document.getElementById('photo-img');
                        img.src = e.target.result;
                        document.getElementById('photo-filename').textContent = file.name;
                        
                        // Show preview and hide upload zone
                        document.getElementById('photo-zone').style.display = 'none';
                        document.getElementById('photo-preview').style.display = 'block';
                    };
                    if (file.type.startsWith('image/')) {
                        reader.readAsDataURL(file);
                    }
                }
            });

            // Form submission
            const form = document.getElementById('visitor-form');
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                
                // Collect form data
                const isGroupVisit = document.getElementById('groupVisitYes').checked;
                const groupSize = isGroupVisit ? parseInt(document.getElementById('groupSize').value) : null;
                
                // Collect group member names if it's a group visit
                let groupMembers = [];
                if (isGroupVisit && groupSize) {
                    for (let i = 1; i <= groupSize; i++) {
                        const memberInput = document.getElementById(`groupMember${i}`);
                        if (memberInput) {
                            groupMembers.push(memberInput.value);
                        }
                    }
                }
                
                const formData = {
                    fullName: document.getElementById('fullName').value,
                    contactNumber: document.getElementById('contactNumber').value,
                    email: document.getElementById('emailAddress').value.trim(),
                    address: document.getElementById('address').value,
                    purpose: document.getElementById('purpose').value,
                    purposeOther: document.getElementById('purpose').value === 'other' ? document.getElementById('purposeOther').value : null,
                    department: document.getElementById('department').value,
                    departmentOther: document.getElementById('department').value === 'other' ? document.getElementById('departmentOther').value : null,
                    idType: document.getElementById('idType').value,
                    idTypeOther: document.getElementById('idType').value === 'other' ? document.getElementById('idTypeOther').value : null,
                    personToVisit: document.getElementById('personToVisit').value,
                    isGroupVisit: isGroupVisit,
                    groupSize: groupSize,
                    groupMembers: groupMembers,
                    notes: document.getElementById('notes').value
                };
                
                // Validate required fields
                if (!formData.fullName || !formData.contactNumber || !formData.purpose || !formData.personToVisit) {
                    alert('Please fill in all required fields marked with *');
                    return;
                }
                
                // Validate "other" purpose specification
                if (formData.purpose.toLowerCase() === 'other' && !formData.purposeOther) {
                    alert('Please specify your purpose of visit');
                    return;
                }
                
                // Validate Philippine mobile number format
                const phonePattern = /^09[0-9]{9}$/;
                if (!phonePattern.test(formData.contactNumber)) {
                    alert('Please enter a valid Philippine mobile number (e.g., 09123456789)');
                    return;
                }

                // Validate Gmail address
                const emailPattern = /^[a-zA-Z0-9._%+\-]+@gmail\.com$/i;
                const emailErrorEl = document.getElementById('emailError');
                if (!formData.email || !emailPattern.test(formData.email)) {
                    emailErrorEl.style.display = 'block';
                    document.getElementById('emailAddress').focus();
                    return;
                }
                emailErrorEl.style.display = 'none';
                
                // Validate group size if group visit is selected
                if (formData.isGroupVisit && (!formData.groupSize || formData.groupSize < 2)) {
                    alert('Please enter the total number of people in your group (minimum 2)');
                    return;
                }

                // Enforce campus group size limit
                if (MAX_GROUP_SIZE > 0 && formData.isGroupVisit && formData.groupSize > MAX_GROUP_SIZE) {
                    alert(`The campus allows a maximum of ${MAX_GROUP_SIZE} people per group. Please reduce the number of people in your group.`);
                    document.getElementById('groupSize').focus();
                    return;
                }
                
                // Validate group member names
                if (formData.isGroupVisit && formData.groupMembers.length !== formData.groupSize) {
                    alert('Please enter the full names of all group members');
                    return;
                }

                // Validate photo / ID requirements based on system settings
                const hasPhotoOrId = capturedPhotoBlob !== null ||
                    document.getElementById('validId').files.length > 0;
                if (REQUIRE_PHOTO && !hasPhotoOrId) {
                    alert('A photo capture or ID photo upload is required for registration. Please use the camera or upload a file.');
                    return;
                }
                if (REQUIRE_ID_UPLOAD && !formData.idType) {
                    alert('Please select your ID type. Valid ID documentation is required for registration.');
                    return;
                }

                // Show visitor pass confirmation
                showVisitorPass(formData);
            });

            // Modal event listeners
            document.getElementById('close-pass-btn').addEventListener('click', function() {
                document.getElementById('pass-confirmation').style.display = 'none';
                // Reset form after successful registration
                document.getElementById('visitor-form').reset();
                // Reset photo preview
                const photoPreview = document.getElementById('photo-preview');
                const photoZone = document.getElementById('photo-zone');
                if (photoPreview) photoPreview.style.display = 'none';
                if (photoZone) photoZone.style.display = 'block';
                capturedPhotoBlob = null;
                
                // Reset group visit containers
                document.getElementById('groupSizeContainer').style.display = 'none';
                document.getElementById('groupMembersContainer').style.display = 'none';
                document.getElementById('purposeOtherContainer').style.display = 'none';
                document.getElementById('departmentOtherContainer').style.display = 'none';
                document.getElementById('idTypeOtherContainer').style.display = 'none';
                
                // Reset submit button
                const submitBtn = document.querySelector('.submit-btn');
                submitBtn.innerHTML = '<svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M9 16.2L4.8 12l-1.4 1.4L9 19 21 7l-1.4-1.4L9 16.2z"/></svg>Submit Registration';
                submitBtn.disabled = false;
            });

            document.getElementById('download-pass-btn').addEventListener('click', downloadVisitorPass);

            // Add some visual feedback on focus
            const inputs = document.querySelectorAll('.form-input, .form-select, .form-textarea');
            inputs.forEach(input => {
                input.addEventListener('focus', function() {
                    this.parentElement.classList.add('focused');
                });
                
                input.addEventListener('blur', function() {
                    this.parentElement.classList.remove('focused');
                });
            });

            // Close modal when clicking outside
            document.getElementById('pass-confirmation').addEventListener('click', function(e) {
                if (e.target === this) {
                    document.getElementById('close-pass-btn').click();
                }
            });
        });
    </script>
</body>
</html>
<?php
}
exit();
?>