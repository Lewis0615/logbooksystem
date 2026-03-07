<?php
/**
 * System Settings Module
 * St. Dominic Savio College - Visitor Management System
 */

require_once '../config/config.php';
require_once '../config/auth.php';
require_once '../config/settings.php';

// Require admin or supervisor role
$auth->requireLogin('login.php');
if (!$auth->hasRole(ROLE_ADMIN) && !$auth->hasRole(ROLE_SUPERVISOR)) {
    header('Location: dashboard.php');
    exit();
}

$error_message = '';
$success_message = '';

// Handle general settings update
if ($_POST && isset($_POST['update_settings'])) {
    $csrf_token_post = $_POST['csrf_token'] ?? '';
    if (!validateCSRFToken($csrf_token_post)) {
        $error_message = 'Security token validation failed.';
    } else {
        try {
            saveSystemSetting('default_duration',  (int)($_POST['default_duration']  ?? 480));
            saveSystemSetting('require_photo',     isset($_POST['require_photo'])     ? '1' : '0');
            saveSystemSetting('require_id_upload', isset($_POST['require_id_upload']) ? '1' : '0');
            // Group / campus capacity
            $max_group = $_POST['max_group_size'] ?? '0';
            saveSystemSetting('max_group_size', $max_group === '0' ? '0' : max(1, (int)$max_group));
            $success_message = 'Settings updated successfully!';
        } catch (Exception $e) {
            $error_message = 'Failed to save settings: ' . $e->getMessage();
        }
    }
}

// Handle dress code items update
if ($_POST && isset($_POST['update_dress_code_items'])) {
    $csrf_token_post = $_POST['csrf_token'] ?? '';
    if (!validateCSRFToken($csrf_token_post)) {
        $error_message = 'Security token validation failed.';
    } else {
        try {
            // Process any uploaded images first
            $upload_dir   = dirname(__DIR__) . '/assets/images/dress-code/';
            $uploaded_map = [];
            if (!empty($_FILES['dc_image_files']['name'])) {
                $allowed_exts  = ['jpg','jpeg','png'];
                $allowed_mimes = ['image/jpeg','image/png'];
                foreach ($_FILES['dc_image_files']['name'] as $idx => $fname) {
                    if ($_FILES['dc_image_files']['error'][$idx] !== UPLOAD_ERR_OK || empty($fname)) continue;
                    $tmp  = $_FILES['dc_image_files']['tmp_name'][$idx];
                    $ext  = strtolower(pathinfo($fname, PATHINFO_EXTENSION));
                    if (!in_array($ext, $allowed_exts)) continue;
                    $mime = mime_content_type($tmp);
                    if (!in_array($mime, $allowed_mimes)) continue;
                    $safe = 'dc_' . time() . '_' . (int)$idx . '.' . $ext;
                    if (move_uploaded_file($tmp, $upload_dir . $safe)) {
                        $uploaded_map[(int)$idx] = $safe;
                    }
                }
            }

            $list = json_decode($_POST['dress_code_items_json'] ?? '[]', true);
            if (is_array($list)) {
                // Sanitise each item; resolve __upload_N__ placeholders to actual filenames
                $clean = array_values(array_filter(array_map(function($item) use ($uploaded_map) {
                    $title   = trim(strip_tags($item['title'] ?? ''));
                    $raw_img = trim($item['image'] ?? '');
                    if (preg_match('/^__upload_(\d+)__$/', $raw_img, $m) && isset($uploaded_map[(int)$m[1]])) {
                        $image = $uploaded_map[(int)$m[1]];
                    } else {
                        $image = basename($raw_img);
                    }
                    $status = in_array($item['status'] ?? '', ['allowed','not_allowed']) ? $item['status'] : 'allowed';
                    return $title ? compact('title','status','image') : null;
                }, $list)));
                saveSystemSetting('dress_code_items', json_encode($clean, JSON_UNESCAPED_UNICODE));
                $success_message = 'Dress code items updated successfully!';
            } else {
                $error_message = 'Invalid data submitted.';
            }
        } catch (Exception $e) {
            $error_message = 'Failed to save dress code items: ' . $e->getMessage();
        }
    }
}

// Handle visit purposes list update
if ($_POST && isset($_POST['update_visit_purposes'])) {
    $csrf_token_post = $_POST['csrf_token'] ?? '';
    if (!validateCSRFToken($csrf_token_post)) {
        $error_message = 'Security token validation failed.';
    } else {
        try {
            $list = json_decode($_POST['visit_purposes_json'] ?? '[]', true);
            if (is_array($list)) {
                saveSettingList('visit_purposes_list', $list);
                $success_message = 'Visit purposes updated successfully!';
            } else {
                $error_message = 'Invalid data submitted.';
            }
        } catch (Exception $e) {
            $error_message = 'Failed to save visit purposes: ' . $e->getMessage();
        }
    }
}

// Handle offices list update
if ($_POST && isset($_POST['update_offices'])) {
    $csrf_token_post = $_POST['csrf_token'] ?? '';
    if (!validateCSRFToken($csrf_token_post)) {
        $error_message = 'Security token validation failed.';
    } else {
        try {
            $list = json_decode($_POST['offices_json'] ?? '[]', true);
            if (is_array($list)) {
                saveSettingList('offices_list', $list);
                $success_message = 'Offices updated successfully!';
            } else {
                $error_message = 'Invalid data submitted.';
            }
        } catch (Exception $e) {
            $error_message = 'Failed to save offices: ' . $e->getMessage();
        }
    }
}

// Load current settings from DB
$sys = getAllSystemSettings();
// Live campus occupancy (total people currently checked in, counting group_size for group visits)
try {
    $occupancyRow = $db->fetch(
        "SELECT COALESCE(SUM(CASE WHEN is_group_visit = 1 THEN COALESCE(NULLIF(group_size, 0), 1) ELSE 1 END), 0) AS total_people
         FROM visits WHERE status = 'checked_in'"
    );
    $current_campus_people = (int)($occupancyRow['total_people'] ?? 0);
} catch (Exception $e) {
    $current_campus_people = 0;
}
$csrf_token = generateCSRFToken();
$purposes_db = getSettingList('visit_purposes_list', $visit_purposes);
$offices_db  = getSettingList('offices_list', $offices);
$_dc_default = [
    ['title'=>'Proper Attire',         'status'=>'allowed',     'image'=>'proper-attire.svg'],
    ['title'=>'Closed Footwear',        'status'=>'allowed',     'image'=>'closed-footwear.svg'],
    ['title'=>'Sleeveless / Tank Tops', 'status'=>'not_allowed', 'image'=>'sleeveless.svg'],
    ['title'=>'Short Skirts/Shorts',    'status'=>'not_allowed', 'image'=>'short-skirts.svg'],
    ['title'=>'Slippers / Flip-flops',  'status'=>'not_allowed', 'image'=>'slippers.svg'],
    ['title'=>'Offensive Clothing',     'status'=>'not_allowed', 'image'=>'offensive-clothing.svg'],
];
$dress_code_items_db = getSettingList('dress_code_items', $_dc_default);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Settings</title>
    <link rel="icon" type="image/png" href="/logbooksystem/assets/images/sdsclogo.png">
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;500;600;700;800&family=DM+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="../assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/css/vendor/font-awesome.min.css" rel="stylesheet">
    <style>
        :root {
            /* Gray Scale */
            --gray-25: #fcfcfd;
            --gray-50: #f9fafb;
            --gray-100: #f3f4f6;
            --gray-200: #e5e7eb;
            --gray-300: #d1d5db;
            --gray-400: #9ca3af;
            --gray-500: #6b7280;
            --gray-600: #4b5563;
            --gray-700: #374151;
            --gray-800: #1f2937;
            --gray-900: #111827;
            --gray-950: #030712;
            
            /* Accent Colors */
            --accent-blue: #3b82f6;
            --accent-teal: #14b8a6;
            --accent-orange: #f97316;
            --accent-red: #ef4444;
            --green-500: #22c55e;
            
            /* Borders & Shadows */
            --border-lt: #e5e7eb;
            --shadow-xs: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow-sm: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px -1px rgba(0, 0, 0, 0.1);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -2px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -4px rgba(0, 0, 0, 0.1);
            
            /* Border Radius */
            --radius: 10px;
            --radius-sm: 6px;
            --radius-xs: 4px;
        }

        * {
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

        body {
            font-family: 'DM Sans', -apple-system, BlinkMacSystemFont, sans-serif;
            background: var(--gray-50);
            color: var(--gray-900);
            font-size: 14px;
            line-height: 1.6;
        }

        .main-content {
            margin-left: 260px;
            padding: 28px 30px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            min-height: 100vh;
            background: var(--gray-50);
        }

        .content-section {
            margin-bottom: 24px;
        }

        .page-header {
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 50%, #1d4ed8 100%);
            border-radius: 16px;
            box-shadow: var(--shadow-lg);
            padding: 32px 36px;
            margin-bottom: 24px;
            border: none;
            position: relative;
            overflow: hidden;
        }

        .page-header::before {
            content: '';
            position: absolute;
            top: -50px;
            right: -50px;
            width: 200px;
            height: 200px;
            background: radial-gradient(circle, rgba(255, 255, 255, 0.15) 0%, transparent 70%);
            border-radius: 50%;
        }

        .page-header::after {
            content: '';
            position: absolute;
            bottom: -30px;
            left: -30px;
            width: 150px;
            height: 150px;
            background: radial-gradient(circle, rgba(255, 255, 255, 0.1) 0%, transparent 70%);
            border-radius: 50%;
        }

        .page-header h2 {
            font-family: 'Work Sans', sans-serif;
            font-size: 1.8rem;
            font-weight: 800;
            color: white;
            margin-bottom: 6px;
            line-height: 1.2;
            letter-spacing: -0.02em;
            position: relative;
            z-index: 1;
        }

        .page-header .subtitle {
            color: rgba(255, 255, 255, 0.9);
            font-size: 0.9rem;
            font-weight: 400;
            margin-bottom: 0;
            position: relative;
            z-index: 1;
        }

        .content-card, .settings-card, .card {
            background: white;
            border: 1px solid var(--border-lt);
            border-radius: var(--radius);
            box-shadow: var(--shadow-xs);
            transition: all 0.3s ease;
        }

        .content-card:hover, .settings-card:hover, .card:hover {
            box-shadow: var(--shadow-sm);
        }

        .card-header {
            background: var(--gray-50);
            border-bottom: 1px solid var(--border-lt);
            padding: 20px 22px;
            border-radius: var(--radius) var(--radius) 0 0;
        }

        .card-header h5 {
            margin-bottom: 0;
            font-size: 1.125rem;
            font-weight: 700;
            color: var(--gray-900);
            font-family: 'Work Sans', sans-serif;
        }

        .card-body {
            padding: 20px 22px;
        }

        .form-label {
            color: var(--gray-700);
            font-weight: 600;
            font-size: 0.875rem;
            margin-bottom: 8px;
        }

        .form-control, .form-select {
            font-family: 'DM Sans', sans-serif;
            border-radius: var(--radius-sm);
            border: 1px solid var(--border-lt);
            padding: 9px 12px;
            transition: all 0.2s ease;
            font-size: 0.875rem;
            color: var(--gray-900);
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--accent-blue);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.12);
            outline: none;
        }

        .form-control:disabled, .form-control[readonly] {
            background-color: var(--gray-50);
            opacity: 1;
        }

        .form-check-input {
            border-radius: 4px;
            border: 1px solid var(--border-lt);
        }

        .form-check-input:checked {
            background-color: var(--accent-blue);
            border-color: var(--accent-blue);
        }

        .form-check-input:focus {
            border-color: var(--accent-blue);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.12);
        }

        .form-check-label {
            color: var(--gray-700);
            font-weight: 500;
            font-size: 0.875rem;
        }

        .btn {
            border-radius: var(--radius-sm);
            padding: 10px 20px;
            font-weight: 600;
            font-size: 0.875rem;
            transition: all 0.2s ease;
            border: none;
        }

        .btn-primary {
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            border: none;
            color: white;
            box-shadow: 0 2px 4px rgba(59, 130, 246, 0.2);
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
            color: white;
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(59, 130, 246, 0.3);
        }

        .btn-outline-warning {
            background: transparent;
            border: 1px solid var(--accent-orange);
            color: var(--accent-orange);
        }

        .btn-outline-warning:hover {
            background: var(--accent-orange);
            border-color: var(--accent-orange);
            color: white;
            transform: translateY(-1px);
            box-shadow: var(--shadow-sm);
        }

        .btn-outline-info {
            background: transparent;
            border: 1px solid var(--accent-blue);
            color: var(--accent-blue);
        }

        .btn-outline-info:hover {
            background: var(--accent-blue);
            border-color: var(--accent-blue);
            color: white;
            transform: translateY(-1px);
            box-shadow: var(--shadow-sm);
        }

        .btn i {
            flex-shrink: 0;
        }

        .badge {
            padding: 5px 11px;
            font-weight: 600;
            font-size: 0.75rem;
            border-radius: var(--radius-sm);
        }

        .alert {
            border-radius: var(--radius-sm);
            border: none;
            border-left: 4px solid;
            padding: 13px 18px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .alert-danger {
            background: rgba(239, 68, 68, 0.08);
            border-left-color: var(--accent-red);
            color: #991b1b;
        }

        .alert-success {
            background: rgba(34, 197, 94, 0.08);
            border-left-color: var(--green-500);
            color: #166534;
        }

        .alert i {
            flex-shrink: 0;
        }

        h5, h6 {
            font-family: 'Work Sans', sans-serif;
            font-weight: 700;
        }

        .badge-list {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
        }

        .section-header {
            margin-bottom: 1rem;
        }

        .section-header h6 {
            color: var(--gray-900);
            font-size: 1rem;
            margin-bottom: 0.25rem;
        }

        .section-header p {
            color: var(--gray-600);
            font-size: 0.875rem;
            margin-bottom: 0;
        }

        hr {
            border: 0;
            border-top: 1px solid var(--border-lt);
            margin: 1.5rem 0;
        }

        .settings-group {
            position: relative;
        }

        .settings-group-header h6 {
            font-size: 1.05rem;
            font-weight: 700;
            font-family: 'Work Sans', sans-serif;
        }

        .text-primary {
            color: var(--accent-blue) !important;
        }

        .form-check-card {
            background: var(--gray-50);
            border: 1px solid var(--border-lt);
            border-radius: var(--radius-sm);
            padding: 14px 16px;
            transition: all 0.2s ease;
        }

        .form-check-card:hover {
            background: white;
            border-color: var(--accent-blue);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.08);
        }

        .form-check-card .form-check-input {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }

        .form-check-card .form-check-label {
            cursor: pointer;
            margin-bottom: 4px;
            font-weight: 600;
        }

        .input-group-text {
            background: var(--gray-50);
            border: 1px solid var(--border-lt);
            color: var(--gray-600);
            font-size: 0.875rem;
            font-weight: 500;
            padding: 9px 12px;
        }

        .input-group .form-control {
            border-right: 0;
        }

        .input-group:focus-within .form-control,
        .input-group:focus-within .input-group-text {
            border-color: var(--accent-blue);
        }

        .input-group:focus-within .form-control {
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.12);
        }

        .fw-semibold {
            font-weight: 600;
        }

        .g-3 {
            --bs-gutter-x: 1rem;
            --bs-gutter-y: 1rem;
        }

        /* Enhanced Card Styling */
        .settings-card-compact {
            background: white;
            border: 1px solid var(--border-lt);
            border-radius: var(--radius);
            box-shadow: var(--shadow-xs);
            transition: all 0.3s ease;
            height: 100%;
        }

        .settings-card-compact:hover {
            box-shadow: var(--shadow-md);
            transform: translateY(-2px);
        }

        .settings-icon-box {
            width: 56px;
            height: 56px;
            border-radius: var(--radius);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin-bottom: 16px;
        }

        .icon-blue {
            background: rgba(59, 130, 246, 0.1);
            color: var(--accent-blue);
        }

        .icon-teal {
            background: rgba(20, 184, 166, 0.1);
            color: var(--accent-teal);
        }

        .icon-orange {
            background: rgba(249, 115, 22, 0.1);
            color: var(--accent-orange);
        }

        .icon-green {
            background: rgba(34, 197, 94, 0.1);
            color: var(--green-500);
        }

        .settings-quick-stat {
            font-size: 0.8rem;
            color: var(--gray-600);
            margin-top: 8px;
        }

        .badge-purpose {
            background: linear-gradient(135deg, var(--accent-blue) 0%, var(--accent-teal) 100%);
            color: white;
            padding: 8px 14px;
            font-size: 0.8rem;
            border-radius: var(--radius-sm);
            transition: all 0.2s ease;
        }

        .badge-purpose:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-sm);
        }

        .settings-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 24px;
        }

        .form-floating {
            position: relative;
        }

        .collapsible-section {
            border: 1px solid var(--border-lt);
            border-radius: var(--radius-sm);
            overflow: hidden;
            margin-bottom: 16px;
        }

        .collapsible-header {
            background: var(--gray-50);
            padding: 14px 18px;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: all 0.2s ease;
        }

        .collapsible-header:hover {
            background: var(--gray-100);
        }

        .collapsible-header h6 {
            margin: 0;
            font-size: 0.95rem;
        }

        .collapsible-body {
            padding: 18px;
            background: white;
        }

        .quick-action-card {
            background: linear-gradient(135deg, var(--gray-50) 0%, white 100%);
            border: 1px solid var(--border-lt);
            border-radius: var(--radius);
            padding: 20px;
            text-align: center;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .quick-action-card:hover {
            border-color: var(--accent-blue);
            background: white;
            box-shadow: var(--shadow-md);
            transform: translateY(-3px);
        }

        .quick-action-card i {
            font-size: 2rem;
            margin-bottom: 12px;
            display: block;
        }

        .setting-info-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: var(--gray-50);
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            color: var(--gray-700);
            border: 1px solid var(--border-lt);
        }

        /* ── Tag Editor ─────────────────────────────────────── */
        .tag-editor-container {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            min-height: 42px;
            padding: 8px;
            background: var(--gray-50);
            border: 1px solid var(--border-lt);
            border-radius: var(--radius-sm);
        }

        .editable-tag {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: linear-gradient(135deg, var(--accent-blue) 0%, var(--accent-teal) 100%);
            color: #fff;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.78rem;
            font-weight: 500;
            white-space: nowrap;
        }

        .tag-remove {
            background: none;
            border: none;
            color: rgba(255,255,255,.8);
            cursor: pointer;
            font-size: 1rem;
            line-height: 1;
            padding: 0;
            transition: color .15s;
        }
        .tag-remove:hover { color: #fff; }
        /* ─────────────────────────────────────────────────────── */

        /* ── Dress Code Item Editor ────────────────────────── */
        .dc-items-list { display: flex; flex-direction: column; gap: 10px; }
        .dc-item-row {
            display: flex;
            align-items: center;
            gap: 12px;
            background: var(--gray-50);
            border: 1px solid var(--border-lt);
            border-radius: var(--radius-sm);
            padding: 10px 12px;
            transition: border-color .2s, background .2s;
        }
        .dc-item-row:hover { background: #fff; border-color: var(--accent-blue); }
        .dc-preview-img {
            width: 54px; height: 54px;
            object-fit: contain;
            border-radius: var(--radius-xs);
            background: #fff;
            border: 1px solid var(--border-lt);
            padding: 4px;
            flex-shrink: 0;
        }
        .dc-fields { display: flex; gap: 10px; flex: 1; align-items: flex-end; flex-wrap: wrap; }
        .dc-field-group { display: flex; flex-direction: column; gap: 3px; }
        .dc-field-group label {
            font-size: .7rem; font-weight: 700; letter-spacing: .05em;
            text-transform: uppercase; color: var(--gray-400);
        }
        .dc-field-title  { flex: 2; min-width: 120px; }
        .dc-field-image  { flex: 2; min-width: 155px; }
        .dc-field-status { flex: 1.5; min-width: 130px; }
        .dc-remove-btn {
            background: none; border: none; color: var(--accent-red);
            font-size: 1rem; cursor: pointer; padding: 5px 7px;
            border-radius: var(--radius-xs); opacity: .65;
            transition: opacity .15s, background .15s; flex-shrink: 0;
        }
        .dc-remove-btn:hover { opacity: 1; background: rgba(239,68,68,.08); }
        .btn-add-dc-item {
            display: flex; align-items: center; justify-content: center; gap: 8px;
            width: 100%; padding: 9px 16px;
            background: var(--gray-50); border: 1.5px dashed var(--border-lt);
            border-radius: var(--radius-sm); color: var(--gray-500);
            font-size: .84rem; font-weight: 600; cursor: pointer;
            transition: all .2s; margin-top: 4px;
        }
        .btn-add-dc-item:hover { border-color: var(--accent-blue); color: var(--accent-blue); background: rgba(59,130,246,.04); }
        /* ─────────────────────────────────────────────────────── */

        .two-column-layout {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 24px;
        }

        @media (max-width: 992px) {
            .two-column-layout {
                grid-template-columns: 1fr;
            }

            .settings-grid {
                grid-template-columns: 1fr;
            }

            .right-column {
                order: -1;
            }

            .settings-grid[style*="grid-template-columns: 1fr"] {
                grid-template-columns: repeat(2, 1fr) !important;
            }
        }

        @media (max-width: 1200px) {
            .main-content {
                margin-left: 0;
                padding: 1rem;
            }
        }

        @media (max-width: 768px) {
            .page-header h2 {
                font-size: 1.5rem;
            }

            .btn {
                width: 100%;
                margin-bottom: 0.5rem;
            }

            .d-flex.justify-content-between {
                flex-direction: column;
                gap: 1rem;
            }

            .d-flex.justify-content-between > div {
                width: 100%;
            }

            .d-flex.justify-content-between .btn {
                width: 100%;
            }

            .d-flex.gap-2 {
                flex-direction: column;
                width: 100%;
            }

            .d-flex.gap-2 .btn {
                width: 100%;
            }

            .settings-grid[style*="grid-template-columns: 1fr"] {
                grid-template-columns: 1fr !important;
            }

            .collapsible-header h6 {
                font-size: 0.85rem;
            }
        }
    </style>
</head>
<body>
    <?php include '../includes/admin-sidebar.php'; ?>
    
    <div class="main-content">
        <div class="page-header">
            <div class="page-header-inner">
                <h2>
                    <i class="fas fa-cogs me-2"></i>System Settings
                </h2>
                <p class="subtitle">Configure system parameters and preferences</p>
            </div>
        </div>
        
        <div class="container-fluid">
        <?php if ($error_message): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-triangle"></i><?php echo $error_message; ?>
            </div>
        <?php endif; ?>
        
        <?php if ($success_message): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i><?php echo $success_message; ?>
            </div>
        <?php endif; ?>

        <!-- Two Column Layout -->
        <div class="two-column-layout">
            <!-- Left Column: Main Settings -->
            <div class="left-column">
                
        <!-- Settings Form -->
        <section class="content-section">
            <div class="card settings-card">
                <div class="card-header">
                    <h5><i class="fas fa-sliders-h me-2"></i>General Settings</h5>
                </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    
                    <!-- Application Configuration -->
                    <div class="settings-group mb-4">
                        <div class="settings-group-header mb-3">
                            <h6 class="text-primary mb-1">
                                <i class="fas fa-cog me-2"></i>Application Configuration
                            </h6>
                            <p class="text-muted small mb-0">Basic application settings and identification</p>
                        </div>
                        
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">
                                    <i class="fas fa-building me-2 text-muted"></i>Application Name
                                </label>
                                <input type="text" class="form-control" value="<?php echo APP_NAME; ?>" readonly>
                                <small class="text-muted">
                                    <i class="fas fa-info-circle me-1"></i>Defined in config.php
                                </small>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">
                                    <i class="fas fa-clock me-2 text-muted"></i>Default Visit Duration
                                </label>
                                <select class="form-select" name="default_duration">
                                    <option value="60"  <?php echo ($sys['default_duration'] ?? '480') == '60'  ? 'selected' : ''; ?>>1 Hour (60 minutes)</option>
                                    <option value="120" <?php echo ($sys['default_duration'] ?? '480') == '120' ? 'selected' : ''; ?>>2 Hours (120 minutes)</option>
                                    <option value="240" <?php echo ($sys['default_duration'] ?? '480') == '240' ? 'selected' : ''; ?>>4 Hours (240 minutes)</option>
                                    <option value="480" <?php echo ($sys['default_duration'] ?? '480') == '480' ? 'selected' : ''; ?>>8 Hours (480 minutes)</option>
                                </select>
                                <small class="text-muted">
                                    <i class="fas fa-info-circle me-1"></i>Default duration for new visitor check-ins
                                </small>
                            </div>
                        </div>
                    </div>
                    
                    <hr>
                    
                    <!-- Group Management -->
                    <div class="settings-group mb-4">
                        <div class="settings-group-header mb-3">
                            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                                <h6 class="text-primary mb-0">
                                    <i class="fas fa-users me-2"></i>Campus Capacity / Group Management
                                </h6>
                                <!-- Live occupancy badge -->
                                <span class="badge rounded-pill px-3 py-2"
                                      style="background:<?php echo ($current_campus_people > 0) ? 'rgba(59,130,246,.1)' : 'rgba(16,185,129,.1)'; ?>;
                                             color:<?php echo ($current_campus_people > 0) ? '#2563eb' : '#059669'; ?>;
                                             border:1px solid <?php echo ($current_campus_people > 0) ? 'rgba(59,130,246,.25)' : 'rgba(16,185,129,.25)'; ?>;
                                             font-size:.78rem;font-weight:600;">
                                    <i class="fas fa-circle me-1" style="font-size:.5rem;vertical-align:middle;"></i>
                                    <?php echo $current_campus_people; ?> <?php echo $current_campus_people === 1 ? 'visitor' : 'visitors'; ?> on campus now
                                </span>
                            </div>
                            <p class="text-muted small mb-0 mt-1">Set the maximum total number of people allowed on campus at the same time. Registrations that would exceed this limit will be blocked.</p>
                        </div>

                        <?php $max_group_size = $sys['max_group_size'] ?? '0'; ?>

                        <!-- Infinite / Limited toggle -->
                        <div class="row g-3 mb-3">
                            <div class="col-12">
                                <div class="d-flex gap-3">
                                    <div class="form-check-card flex-fill" id="groupLimitInfiniteCard"
                                         style="cursor:pointer;<?php echo $max_group_size == '0' ? 'border-color:var(--accent-blue);background:#fff;box-shadow:0 0 0 3px rgba(59,130,246,.1);' : ''; ?>">
                                        <div class="d-flex align-items-center gap-3">
                                            <input class="form-check-input" type="radio" name="group_limit_type"
                                                   id="limitInfinite" value="infinite"
                                                   <?php echo $max_group_size == '0' ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="limitInfinite" style="cursor:pointer;">
                                                <div class="d-flex align-items-center gap-2">
                                                    <span style="width:34px;height:34px;border-radius:8px;background:linear-gradient(135deg,#10b981,#059669);display:inline-flex;align-items:center;justify-content:center;flex-shrink:0;">
                                                        <i class="fas fa-infinity" style="color:#fff;font-size:.85rem;"></i>
                                                    </span>
                                                    <div>
                                                        <div class="fw-semibold" style="font-size:.875rem;">No Limit (Infinite)</div>
                                                        <div class="text-muted" style="font-size:.78rem;">Accept visitors without a campus headcount cap</div>
                                                    </div>
                                                </div>
                                            </label>
                                        </div>
                                    </div>
                                    <div class="form-check-card flex-fill" id="groupLimitCustomCard"
                                         style="cursor:pointer;<?php echo $max_group_size != '0' ? 'border-color:var(--accent-blue);background:#fff;box-shadow:0 0 0 3px rgba(59,130,246,.1);' : ''; ?>">
                                        <div class="d-flex align-items-center gap-3">
                                            <input class="form-check-input" type="radio" name="group_limit_type"
                                                   id="limitCustom" value="custom"
                                                   <?php echo $max_group_size != '0' ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="limitCustom" style="cursor:pointer;">
                                                <div class="d-flex align-items-center gap-2">
                                                    <span style="width:34px;height:34px;border-radius:8px;background:linear-gradient(135deg,#3b82f6,#2563eb);display:inline-flex;align-items:center;justify-content:center;flex-shrink:0;">
                                                        <i class="fas fa-user-check" style="color:#fff;font-size:.85rem;"></i>
                                                    </span>
                                                    <div>
                                                        <div class="fw-semibold" style="font-size:.875rem;">Set a Limit</div>
                                                        <div class="text-muted" style="font-size:.78rem;">Cap total concurrent visitors on campus</div>
                                                    </div>
                                                </div>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Number input (shown only when custom) -->
                        <div class="row g-3" id="groupLimitInputRow"
                             style="<?php echo $max_group_size == '0' ? 'display:none;' : ''; ?>">
                            <div class="col-md-6">
                                <label class="form-label">
                                    <i class="fas fa-hashtag me-2 text-muted"></i>Maximum Concurrent Campus Visitors
                                </label>
                                <div class="input-group">
                                    <input type="number" class="form-control" name="max_group_size"
                                           id="maxGroupSizeInput"
                                           min="1" max="9999"
                                           value="<?php echo $max_group_size != '0' ? (int)$max_group_size : ''; ?>"
                                           placeholder="e.g. 50">
                                    <span class="input-group-text bg-light">people</span>
                                </div>
                                <small class="text-muted">
                                    <i class="fas fa-info-circle me-1"></i>New check-ins will be blocked once this total is reached
                                </small>
                            </div>
                            <div class="col-md-6 d-flex align-items-end">
                                <div class="p-3 rounded" style="background:rgba(59,130,246,.06);border:1px solid rgba(59,130,246,.15);width:100%;">
                                    <div class="d-flex align-items-center gap-2 mb-1">
                                        <i class="fas fa-info-circle text-primary" style="font-size:.85rem;"></i>
                                        <span class="fw-semibold" style="font-size:.8rem;color:var(--gray-700);">How it works</span>
                                    </div>
                                    <p class="mb-0" style="font-size:.78rem;color:var(--gray-500);line-height:1.5;">
                                        The total number of people currently on campus (including group members) is counted in real time. When a new registration would push the total above this limit, the check-in is automatically blocked until someone checks out.
                                    </p>
                                    <?php if ($max_group_size != '0'): ?>
                                    <div class="mt-2 d-flex align-items-center gap-2" style="font-size:.78rem;">
                                        <span style="background:<?php echo $current_campus_people >= (int)$max_group_size ? '#fef2f2' : '#f0fdf4'; ?>;
                                                     color:<?php echo $current_campus_people >= (int)$max_group_size ? '#dc2626' : '#16a34a'; ?>;
                                                     border:1px solid <?php echo $current_campus_people >= (int)$max_group_size ? '#fecaca' : '#bbf7d0'; ?>;
                                                     border-radius:5px;padding:2px 8px;font-weight:600;">
                                            <?php echo $current_campus_people; ?> / <?php echo (int)$max_group_size; ?> slots used
                                        </span>
                                        <?php
                                            $remaining = (int)$max_group_size - $current_campus_people;
                                            if ($remaining > 0) {
                                                echo '<span style="color:var(--gray-500);">' . $remaining . ' slot' . ($remaining !== 1 ? 's' : '') . ' remaining</span>';
                                            } else {
                                                echo '<span style="color:#dc2626;font-weight:600;">Campus full — check-ins blocked</span>';
                                            }
                                        ?>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Hidden field for infinite -->
                        <input type="hidden" name="max_group_size" id="maxGroupSizeHidden"
                               value="0"
                               <?php echo $max_group_size != '0' ? 'disabled' : ''; ?>>
                    </div>

                    <hr>
                    
                    <!-- Visitor Requirements -->
                    <div class="settings-group mb-4">
                        <div class="settings-group-header mb-3">
                            <h6 class="text-primary mb-1">
                                <i class="fas fa-id-badge me-2"></i>Visitor Requirements
                            </h6>
                            <p class="text-muted small mb-0">Configure mandatory information for visitor check-in</p>
                        </div>
                        
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="form-check-card">
                                    <div class="d-flex align-items-start">
                                        <input class="form-check-input mt-1 me-3" type="checkbox" name="require_photo" id="require_photo" <?php echo ($sys['require_photo'] ?? '0') == '1' ? 'checked' : ''; ?>>
                                        <div class="flex-grow-1">
                                            <label class="form-check-label fw-semibold" for="require_photo">
                                                <i class="fas fa-camera me-2 text-muted"></i>Require Visitor Photo
                                            </label>
                                            <p class="text-muted small mb-0">Visitors must capture their photo during check-in</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-check-card">
                                    <div class="d-flex align-items-start">
                                        <input class="form-check-input mt-1 me-3" type="checkbox" name="require_id_upload" id="require_id_upload" <?php echo ($sys['require_id_upload'] ?? '0') == '1' ? 'checked' : ''; ?>>
                                        <div class="flex-grow-1">
                                            <label class="form-check-label fw-semibold" for="require_id_upload">
                                                <i class="fas fa-id-card me-2 text-muted"></i>Require ID Upload
                                            </label>
                                            <p class="text-muted small mb-0">Visitors must upload a valid identification document</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <hr class="my-4">
                    
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="text-muted small">
                            <i class="fas fa-info-circle me-2"></i>
                            Some settings require config.php modification
                        </div>
                        <button type="submit" name="update_settings" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Update Settings
                        </button>
                    </div>
                </form>
            </div>
        </div>
        </section>

            </div>
            <!-- End Left Column -->

            <!-- Right Column: Quick Info & Actions -->
            <div class="right-column">

                <!-- Visit Purposes Management -->
                <section class="content-section">
                    <div class="card settings-card">
                        <div class="card-header">
                            <h5><i class="fas fa-tags me-2"></i>Visit Purposes</h5>
                        </div>
                        <div class="card-body">
                            <p class="text-muted small mb-3">
                                <i class="fas fa-info-circle me-2"></i>
                                Manage predefined purposes for visitor check-ins
                            </p>
                            <form method="POST" id="purposesForm">
                                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                <input type="hidden" name="update_visit_purposes" value="1">
                                <input type="hidden" name="visit_purposes_json" id="visit_purposes_json"
                                       value="<?php echo htmlspecialchars(json_encode($purposes_db, JSON_UNESCAPED_UNICODE)); ?>">

                                <div id="purposeTagContainer" class="tag-editor-container mb-3">
                                    <?php foreach ($purposes_db as $item): ?>
                                    <span class="editable-tag">
                                        <?php echo htmlspecialchars($item); ?>
                                        <button type="button" class="tag-remove" data-list="purposes" title="Remove">&times;</button>
                                    </span>
                                    <?php endforeach; ?>
                                </div>

                                <div class="input-group input-group-sm mb-3">
                                    <input type="text" id="purposeNewItem" class="form-control"
                                           placeholder="Add new purpose…" maxlength="80">
                                    <button type="button" id="purposeAddBtn" class="btn btn-outline-primary">
                                        <i class="fas fa-plus me-1"></i>Add
                                    </button>
                                </div>

                                <button type="submit" class="btn btn-primary btn-sm w-100">
                                    <i class="fas fa-save me-2"></i>Save Visit Purposes
                                </button>
                            </form>
                        </div>
                    </div>
                </section>

                <!-- Offices Management -->
                <section class="content-section mt-3">
                    <div class="card settings-card">
                        <div class="card-header">
                            <h5><i class="fas fa-building me-2"></i>Offices / Departments</h5>
                        </div>
                        <div class="card-body">
                            <p class="text-muted small mb-3">
                                <i class="fas fa-info-circle me-2"></i>
                                Manage available offices and departments for visitor routing
                            </p>
                            <form method="POST" id="officesForm">
                                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                <input type="hidden" name="update_offices" value="1">
                                <input type="hidden" name="offices_json" id="offices_json"
                                       value="<?php echo htmlspecialchars(json_encode($offices_db, JSON_UNESCAPED_UNICODE)); ?>">

                                <div id="officeTagContainer" class="tag-editor-container mb-3">
                                    <?php foreach ($offices_db as $item): ?>
                                    <span class="editable-tag">
                                        <?php echo htmlspecialchars($item); ?>
                                        <button type="button" class="tag-remove" data-list="offices" title="Remove">&times;</button>
                                    </span>
                                    <?php endforeach; ?>
                                </div>

                                <div class="input-group input-group-sm mb-3">
                                    <input type="text" id="officeNewItem" class="form-control"
                                           placeholder="Add new office…" maxlength="80">
                                    <button type="button" id="officeAddBtn" class="btn btn-outline-primary">
                                        <i class="fas fa-plus me-1"></i>Add
                                    </button>
                                </div>

                                <button type="submit" class="btn btn-primary btn-sm w-100">
                                    <i class="fas fa-save me-2"></i>Save Offices
                                </button>
                            </form>
                        </div>
                    </div>
                </section>
            </div>
            <!-- End Right Column -->
        </div>
        <!-- End Two Column Layout -->

        <!-- Dress Code Policy Settings - Full Width -->
        <section class="content-section">
            <div class="card settings-card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-user-tie me-2"></i>Dress Code Policy Settings</h5>
                    <span class="badge badge-purpose">Enhanced Policy</span>
                </div>
                <div class="card-body">
                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                        
                        <!-- Dress Code Items Editor -->
                        <div class="collapsible-section">
                            <div class="collapsible-header">
                                <h6><i class="fas fa-list-check me-2 text-primary"></i>Dress Code Items</h6>
                                <i class="fas fa-chevron-down text-muted"></i>
                            </div>
                            <div class="collapsible-body">
                                <p class="text-muted small mb-3">Manage the dress code items displayed on the visitor registration page. Set each item's title, image, and whether it is allowed or not allowed.</p>

                                <input type="hidden" name="dress_code_items_json" id="dress_code_items_json"
                                       value="<?php echo htmlspecialchars(json_encode($dress_code_items_db, JSON_UNESCAPED_UNICODE)); ?>">

                                <div class="dc-items-list mb-3" id="dcItemsList">
                                <?php
                                $known_imgs = ['proper-attire.svg','closed-footwear.svg','sleeveless.svg','short-skirts.svg','slippers.svg','offensive-clothing.svg'];
                                foreach ($dress_code_items_db as $dc):
                                    $dc_img    = htmlspecialchars($dc['image']  ?? '');
                                    $dc_title  = htmlspecialchars($dc['title']  ?? '');
                                    $dc_status = $dc['status'] ?? 'allowed';
                                    $is_known  = in_array($dc['image'] ?? '', $known_imgs);
                                ?>
                                <div class="dc-item-row">
                                    <img src="../assets/images/dress-code/<?php echo $dc_img; ?>" alt="preview"
                                         class="dc-preview-img dc-preview" onerror="this.style.opacity='.25'">
                                    <div class="dc-fields">
                                        <div class="dc-field-group dc-field-title">
                                            <label>Title</label>
                                            <input type="text" class="form-control dc-title" value="<?php echo $dc_title; ?>" placeholder="e.g. Proper Attire">
                                        </div>
                                        <div class="dc-field-group dc-field-image">
                                            <label>Image File</label>
                                            <select class="form-select dc-image-select">
                                                <option value="proper-attire.svg"      <?php echo $dc_img==='proper-attire.svg'      ?'selected':''; ?>>proper-attire.svg</option>
                                                <option value="closed-footwear.svg"    <?php echo $dc_img==='closed-footwear.svg'    ?'selected':''; ?>>closed-footwear.svg</option>
                                                <option value="sleeveless.svg"         <?php echo $dc_img==='sleeveless.svg'         ?'selected':''; ?>>sleeveless.svg</option>
                                                <option value="short-skirts.svg"       <?php echo $dc_img==='short-skirts.svg'       ?'selected':''; ?>>short-skirts.svg</option>
                                                <option value="slippers.svg"           <?php echo $dc_img==='slippers.svg'           ?'selected':''; ?>>slippers.svg</option>
                                                <option value="offensive-clothing.svg" <?php echo $dc_img==='offensive-clothing.svg' ?'selected':''; ?>>offensive-clothing.svg</option>
                                                <?php if (!$is_known && $dc_img): ?>
                                                <option value="<?php echo $dc_img; ?>" selected><?php echo $dc_img; ?></option>
                                                <?php endif; ?>
                                                <option value="__custom__">Custom filename…</option>
                                                <option value="__upload__">📤 Upload image (JPG/PNG)…</option>
                                            </select>
                                            <input type="text" class="form-control dc-image-custom mt-1"
                                                   placeholder="e.g. my-image.svg"
                                                   value="<?php echo !$is_known ? $dc_img : ''; ?>"
                                                   style="display:<?php echo (!$is_known && $dc_img) ? 'block' : 'none'; ?>">
                                            <input type="file" class="form-control dc-image-upload mt-1"
                                                   accept=".jpg,.jpeg,.png"
                                                   style="display:none">
                                        </div>
                                        <div class="dc-field-group dc-field-status">
                                            <label>Status</label>
                                            <select class="form-select dc-status">
                                                <option value="allowed"     <?php echo $dc_status==='allowed'     ?'selected':''; ?>>✓ Allowed</option>
                                                <option value="not_allowed" <?php echo $dc_status==='not_allowed' ?'selected':''; ?>>✗ Not Allowed</option>
                                            </select>
                                        </div>
                                    </div>
                                    <button type="button" class="dc-remove-btn" title="Remove item"><i class="fas fa-times"></i></button>
                                </div>
                                <?php endforeach; ?>
                                </div>

                                <button type="button" class="btn-add-dc-item" id="addDcItemBtn">
                                    <i class="fas fa-plus-circle"></i> Add Dress Code Item
                                </button>
                            </div>
                        </div>
                        
                        <hr class="my-4">
                        
                        <div class="d-flex justify-content-between align-items-center">
                            <div class="text-muted small">
                                <i class="fas fa-info-circle me-2"></i>
                                Changes will apply to new visitor check-ins
                            </div>
                            <div class="d-flex gap-2">
                                <button type="button" class="btn btn-outline-info" data-bs-toggle="modal" data-bs-target="#dresscodeExampleModal">
                                    <i class="fas fa-images me-2"></i>View Examples
                                </button>
                                <button type="submit" name="update_dress_code_items" class="btn btn-primary">
                                    <i class="fas fa-save me-2"></i>Save Dress Code
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </section>
        </div>
        </div>
    </div>

    <!-- ── Dress Code Example Modal ──────────────────────────────── -->
    <div class="modal fade" id="dresscodeExampleModal" tabindex="-1" aria-labelledby="dresscodeExampleModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content" style="border:none; border-radius:16px; overflow:hidden; box-shadow:0 25px 60px rgba(0,0,0,.2);">

                <!-- Header -->
                <div class="modal-header" style="background:linear-gradient(135deg,#1e3a5f 0%,#2563eb 60%,#14b8a6 100%); padding:22px 28px; border:none;">
                    <div class="d-flex align-items-center gap-3">
                        <div style="width:44px;height:44px;background:rgba(255,255,255,.15);border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                            <i class="fas fa-user-tie" style="font-size:1.25rem;color:#fff;"></i>
                        </div>
                        <div>
                            <h5 class="modal-title mb-0" id="dresscodeExampleModalLabel"
                                style="font-family:'Work Sans',sans-serif;font-weight:800;color:#fff;font-size:1.2rem;letter-spacing:-.01em;">Dress Code Policy</h5>
                            <p class="mb-0" style="color:rgba(255,255,255,.75);font-size:.8rem;">Reference guide for visitor attire requirements</p>
                        </div>
                    </div>
                    <button type="button" class="btn-close btn-close-white ms-auto" data-bs-dismiss="modal" aria-label="Close"
                            style="opacity:.8;filter:brightness(2);"></button>
                </div>

                <!-- Body -->
                <div class="modal-body p-0" style="background:#f8fafc;">

                    <!-- Quick legend -->
                    <div class="d-flex align-items-center gap-4 px-4 py-3" style="background:#fff;border-bottom:1px solid #e5e7eb;">
                        <span style="display:inline-flex;align-items:center;gap:7px;font-size:.82rem;font-weight:600;color:#166534;">
                            <span style="width:22px;height:22px;background:#dcfce7;border:2px solid #22c55e;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;">
                                <i class="fas fa-check" style="font-size:.55rem;color:#16a34a;"></i>
                            </span>Allowed
                        </span>
                        <span style="display:inline-flex;align-items:center;gap:7px;font-size:.82rem;font-weight:600;color:#991b1b;">
                            <span style="width:22px;height:22px;background:#fee2e2;border:2px solid #ef4444;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;">
                                <i class="fas fa-times" style="font-size:.55rem;color:#dc2626;"></i>
                            </span>Not Allowed
                        </span>
                        <span class="ms-auto text-muted" style="font-size:.78rem;"><i class="fas fa-info-circle me-1"></i>Please consult employee handbook for full details</span>
                    </div>

                    <!-- Policy image -->
                    <div class="p-4" style="text-align:center;">
                        <img src="../assets/images/sample-dresscode.png"
                             id="dcPolicyImg"
                             alt="Dress Code Policy"
                             style="max-width:100%;height:auto;border-radius:12px;box-shadow:0 4px 24px rgba(0,0,0,.12);border:1px solid #e5e7eb;"
                             onerror="this.style.display='none';document.getElementById('dcNoPolicyImg').style.display='block';">
                        <div id="dcNoPolicyImg" style="display:none">
                            <div style="background:#f1f5f9;border:2px dashed #cbd5e1;border-radius:12px;padding:60px 40px;color:#64748b;">
                                <i class="fas fa-image" style="font-size:3rem;margin-bottom:16px;display:block;opacity:.4;"></i>
                                <p class="mb-1" style="font-weight:600;font-size:.95rem;">No policy image uploaded yet</p>
                                <p class="mb-0" style="font-size:.82rem;">Save the dress code infographic as<br><code style="background:#e2e8f0;padding:2px 7px;border-radius:4px;">assets/images/dress-code/dress-code-policy.jpg</code></p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Footer -->
                <div class="modal-footer" style="background:#fff;border-top:1px solid #e5e7eb;padding:16px 24px;">
                    <span class="text-muted me-auto" style="font-size:.8rem;">
                        <i class="fas fa-shield-alt me-1 text-primary"></i>
                        Consistent and professional appearance is key
                    </span>
                    <a href="../assets/images/sample-dresscode.png" download class="btn btn-outline-secondary btn-sm">
                        <i class="fas fa-download me-2"></i>Download
                    </a>
                    <button type="button" class="btn btn-primary btn-sm" data-bs-dismiss="modal">
                        <i class="fas fa-check me-2"></i>Got it
                    </button>
                </div>
            </div>
        </div>
    </div>
    <!-- ─────────────────────────────────────────────────────────── -->
    
    <script src="../assets/js/jquery.min.js"></script>
    <script src="../assets/js/bootstrap.bundle.min.js"></script>
    
    <script>
        /* ── Group Management Toggle ────────────────────────── */
        (function() {
            const radios       = document.querySelectorAll('input[name="group_limit_type"]');
            const inputRow     = document.getElementById('groupLimitInputRow');
            const numberInput  = document.getElementById('maxGroupSizeInput');
            const hiddenInput  = document.getElementById('maxGroupSizeHidden');
            const cardInfinite = document.getElementById('groupLimitInfiniteCard');
            const cardCustom   = document.getElementById('groupLimitCustomCard');

            function highlight(card, active) {
                if (active) {
                    card.style.borderColor = 'var(--accent-blue)';
                    card.style.background  = '#fff';
                    card.style.boxShadow   = '0 0 0 3px rgba(59,130,246,.1)';
                } else {
                    card.style.borderColor = '';
                    card.style.background  = '';
                    card.style.boxShadow   = '';
                }
            }

            function applyMode(mode) {
                if (mode === 'infinite') {
                    inputRow.style.display = 'none';
                    numberInput.disabled   = true;
                    hiddenInput.disabled   = false;
                    highlight(cardInfinite, true);
                    highlight(cardCustom,   false);
                } else {
                    inputRow.style.display = '';
                    numberInput.disabled   = false;
                    hiddenInput.disabled   = true;
                    highlight(cardCustom,   true);
                    highlight(cardInfinite, false);
                    if (!numberInput.value) numberInput.focus();
                }
            }

            radios.forEach(r => r.addEventListener('change', () => applyMode(r.value)));
            // Card click bubbles to radio change, but also bind label area
            [cardInfinite, cardCustom].forEach(card => {
                card.addEventListener('click', function(e) {
                    if (e.target.tagName === 'INPUT') return;
                    const radio = card.querySelector('input[type="radio"]');
                    radio.checked = true;
                    radio.dispatchEvent(new Event('change', { bubbles: true }));
                });
            });
        })();
        /* ─────────────────────────────────────────────────────── */

        // Collapsible section functionality
        document.querySelectorAll('.collapsible-header').forEach(header => {
            header.addEventListener('click', function() {
                const body = this.nextElementSibling;
                const icon = this.querySelector('.fa-chevron-down, .fa-chevron-up');
                
                if (body.style.display === 'none') {
                    body.style.display = 'block';
                    icon.classList.remove('fa-chevron-up');
                    icon.classList.add('fa-chevron-down');
                } else {
                    body.style.display = 'none';
                    icon.classList.remove('fa-chevron-down');
                    icon.classList.add('fa-chevron-up');
                }
            });
        });

        // Quick action card click handlers
        document.querySelectorAll('.quick-action-card').forEach(card => {
            card.addEventListener('click', function() {
                const heading = this.querySelector('h6').textContent;
                if (heading.includes('Export')) {
                    alert('Data export feature coming soon!');
                } else if (heading.includes('Cache')) {
                    alert('Cache clearing feature coming soon!');
                }
            });
        });

        /* ── Editable Tag Lists ─────────────────────────────── */
        function buildTagEditorList(containerId, hiddenId) {
            const container = document.getElementById(containerId);
            const hidden    = document.getElementById(hiddenId);
            if (!container || !hidden) return;

            function getItems() {
                return Array.from(container.querySelectorAll('.editable-tag'))
                    .map(el => el.childNodes[0].nodeValue.trim())
                    .filter(Boolean);
            }

            function syncHidden() { hidden.value = JSON.stringify(getItems()); }

            function addTag(text) {
                const val = text.trim();
                if (!val) return;
                const existing = getItems().map(s => s.toLowerCase());
                if (existing.includes(val.toLowerCase())) return;
                const span = document.createElement('span');
                span.className = 'editable-tag';
                span.innerHTML = val + ' <button type="button" class="tag-remove" title="Remove">&times;</button>';
                span.querySelector('.tag-remove').addEventListener('click', function() {
                    span.remove();
                    syncHidden();
                });
                container.appendChild(span);
                syncHidden();
            }

            // Bind existing remove buttons
            container.querySelectorAll('.tag-remove').forEach(btn => {
                btn.addEventListener('click', function() {
                    this.closest('.editable-tag').remove();
                    syncHidden();
                });
            });

            return addTag;
        }

        // Visit Purposes
        const addPurpose = buildTagEditorList('purposeTagContainer', 'visit_purposes_json');
        document.getElementById('purposeAddBtn')?.addEventListener('click', function() {
            const input = document.getElementById('purposeNewItem');
            addPurpose(input.value);
            input.value = '';
        });
        document.getElementById('purposeNewItem')?.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') { e.preventDefault(); document.getElementById('purposeAddBtn').click(); }
        });
        document.getElementById('purposesForm')?.addEventListener('submit', function() {
            // hidden already in sync, nothing extra needed
        });

        // Offices
        const addOffice = buildTagEditorList('officeTagContainer', 'offices_json');
        document.getElementById('officeAddBtn')?.addEventListener('click', function() {
            const input = document.getElementById('officeNewItem');
            addOffice(input.value);
            input.value = '';
        });
        document.getElementById('officeNewItem')?.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') { e.preventDefault(); document.getElementById('officeAddBtn').click(); }
        });
        /* ─────────────────────────────────────────────────────── */

        /* ── Dress Code Item Editor ─────────────────────────── */
        function dcSyncJson() {
            const rows = document.querySelectorAll('#dcItemsList .dc-item-row');
            const items = [];
            rows.forEach(function(row, idx) {
                const title      = row.querySelector('.dc-title').value.trim();
                const sel        = row.querySelector('.dc-image-select').value;
                const fileInput  = row.querySelector('.dc-image-upload');
                let image;
                if (sel === '__upload__' && fileInput && fileInput.files && fileInput.files.length > 0) {
                    fileInput.name = 'dc_image_files[' + idx + ']';
                    image = '__upload_' + idx + '__';
                } else if (sel === '__custom__') {
                    image = row.querySelector('.dc-image-custom').value.trim();
                    if (fileInput) fileInput.name = '';
                } else {
                    image = sel;
                    if (fileInput) fileInput.name = '';
                }
                const status = row.querySelector('.dc-status').value;
                if (title) items.push({ title, status, image });
            });
            document.getElementById('dress_code_items_json').value = JSON.stringify(items);
        }

        function dcBindRow(row) {
            const sel     = row.querySelector('.dc-image-select');
            const custom  = row.querySelector('.dc-image-custom');
            const upload  = row.querySelector('.dc-image-upload');
            const preview = row.querySelector('.dc-preview');

            function updatePreview(src) {
                if (!preview) return;
                preview.src = src;
                preview.style.opacity = src ? '' : '.25';
            }

            sel.addEventListener('change', function() {
                if (this.value === '__custom__') {
                    custom.style.display = 'block';
                    if (upload) upload.style.display = 'none';
                    custom.focus();
                    updatePreview('../assets/images/dress-code/' + custom.value.trim());
                } else if (this.value === '__upload__') {
                    custom.style.display = 'none';
                    if (upload) { upload.style.display = 'block'; upload.click(); }
                } else {
                    custom.style.display = 'none';
                    custom.value = '';
                    if (upload) upload.style.display = 'none';
                    updatePreview('../assets/images/dress-code/' + this.value);
                }
                dcSyncJson();
            });
            custom.addEventListener('input', function() {
                updatePreview('../assets/images/dress-code/' + this.value.trim());
                dcSyncJson();
            });
            if (upload) {
                upload.addEventListener('change', function() {
                    if (this.files && this.files[0]) {
                        const reader = new FileReader();
                        reader.onload = function(e) { updatePreview(e.target.result); };
                        reader.readAsDataURL(this.files[0]);
                    }
                    dcSyncJson();
                });
            }
            row.querySelector('.dc-title').addEventListener('input', dcSyncJson);
            row.querySelector('.dc-status').addEventListener('change', dcSyncJson);
            row.querySelector('.dc-remove-btn').addEventListener('click', function() {
                row.remove();
                dcSyncJson();
            });
        }

        document.querySelectorAll('#dcItemsList .dc-item-row').forEach(dcBindRow);

        // Ensure indices are assigned correctly before form submission
        document.querySelector('button[name="update_dress_code_items"]')?.closest('form')?.addEventListener('submit', function() {
            dcSyncJson();
        });

        document.getElementById('addDcItemBtn')?.addEventListener('click', function() {
            const row = document.createElement('div');
            row.className = 'dc-item-row';
            row.innerHTML = `
                <img src="../assets/images/dress-code/proper-attire.svg" alt="preview"
                     class="dc-preview-img dc-preview" onerror="this.style.opacity='.25'">
                <div class="dc-fields">
                    <div class="dc-field-group dc-field-title">
                        <label>Title</label>
                        <input type="text" class="form-control dc-title" value="" placeholder="e.g. Proper Attire">
                    </div>
                    <div class="dc-field-group dc-field-image">
                        <label>Image File</label>
                        <select class="form-select dc-image-select">
                            <option value="proper-attire.svg" selected>proper-attire.svg</option>
                            <option value="closed-footwear.svg">closed-footwear.svg</option>
                            <option value="sleeveless.svg">sleeveless.svg</option>
                            <option value="short-skirts.svg">short-skirts.svg</option>
                            <option value="slippers.svg">slippers.svg</option>
                            <option value="offensive-clothing.svg">offensive-clothing.svg</option>
                            <option value="__custom__">Custom filename\u2026</option>
                            <option value="__upload__">📤 Upload image (JPG/PNG)\u2026</option>
                        </select>
                        <input type="text" class="form-control dc-image-custom mt-1" placeholder="e.g. my-image.svg" style="display:none">
                        <input type="file" class="form-control dc-image-upload mt-1" accept=".jpg,.jpeg,.png" style="display:none">
                    </div>
                    <div class="dc-field-group dc-field-status">
                        <label>Status</label>
                        <select class="form-select dc-status">
                            <option value="allowed">✓ Allowed</option>
                            <option value="not_allowed">✗ Not Allowed</option>
                        </select>
                    </div>
                </div>
                <button type="button" class="dc-remove-btn" title="Remove item"><i class="fas fa-times"></i></button>`;
            document.getElementById('dcItemsList').appendChild(row);
            dcBindRow(row);
            dcSyncJson();
            row.querySelector('.dc-title').focus();
        });
        /* ─────────────────────────────────────────────────────── */
    </script>
    
    <?php include '../includes/chat-widget.php'; ?>
</body>
</html>