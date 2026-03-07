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
    <link rel="icon" type="image/png" href="/logbooksystem/assets/images/sdsclogo.png">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/index.css">
    
</head>
<body>

    <!-- ════════════════════════════════
         LEFT PANEL (unchanged)
    ════════════════════════════════ -->
    <div class="left-panel">
        <div class="left-panel-content">
            <div class="logo">
                <img src="/logbooksystem/assets/images/sdsclogo.png" alt="Logo" style="width:50px;height:50px;">
            </div>
            <h1 class="school-name">St. Dominic Savio College</h1>
            <div class="subtitle">Visitor Log Book Management System</div>
            <div class="divider"></div>
            <h2 class="welcome-heading">Welcome, Visitor!</h2>
            <p class="welcome-text">Register quickly and securely to access our campus. Your information is kept confidential and helps us maintain a safe environment.</p>

            <?php if ($enforce_dress_code): ?>
            <div class="dress-code-section">
                <div class="dress-code-header">
                    <h2 class="dress-code-title">Dress Code Policy</h2>
                    <p class="dress-code-subtitle">Please observe our dress code guidelines when visiting campus</p>
                </div>
                <div class="dress-code-grid">
                    <?php foreach ($dress_code_items_db as $dc_item):
                        $dc_cls    = ($dc_item['status'] ?? 'allowed') === 'allowed' ? 'allowed' : 'not-allowed';
                        $dc_badge  = $dc_cls === 'allowed'
                            ? '<div class="dress-code-status allowed-badge">✓ Allowed</div>'
                            : '<div class="dress-code-status notallowed-badge">✗ Not Allowed</div>';
                        $dc_img    = htmlspecialchars($dc_item['image']  ?? '');
                        $dc_title  = htmlspecialchars($dc_item['title']  ?? '');
                        $dc_status_js = ($dc_item['status'] ?? 'allowed') === 'allowed' ? 'allowed' : 'not-allowed';
                    ?>
                    <div class="dress-code-card <?php echo $dc_cls; ?>"
                         onclick="openDressCodeModal('<?php echo addslashes($dc_title); ?>','assets/images/dress-code/<?php echo $dc_img; ?>','<?php echo $dc_status_js; ?>')">
                        <div class="dress-code-image-wrapper">
                            <img src="assets/images/dress-code/<?php echo $dc_img; ?>" alt="<?php echo $dc_title; ?>" class="dress-code-image">
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

    <!-- ════════════════════════════════
         RIGHT PANEL — form + pass
    ════════════════════════════════ -->
    <div class="right-panel">

        <!-- ── FORM PANEL ── -->
        <div class="form-panel" id="form-panel">
            <div class="form-header">
                <h2 class="form-title">Visitor Registration</h2>
                <p class="form-subtitle">Fields marked * are required.</p>
            </div>

            <form class="registration-form" id="visitor-form">

                <!-- Full Name -->
                <div class="form-group">
                    <label class="form-label" for="fullName">
                        <svg viewBox="0 0 24 24"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
                        Full Name *
                    </label>
                    <input type="text" id="fullName" class="form-input" placeholder="Enter your full name" required>
                </div>

                <!-- Contact Number -->
                <div class="form-group">
                    <label class="form-label" for="contactNumber">
                        <svg viewBox="0 0 24 24"><path d="M6.62 10.79c1.44 2.83 3.76 5.14 6.59 6.59l2.2-2.2c.27-.27.67-.36 1.02-.24 1.12.37 2.33.57 3.57.57.55 0 1 .45 1 1V20c0 .55-.45 1-1 1-9.39 0-17-7.61-17-17 0-.55.45-1 1-1h3.5c.55 0 1 .45 1 1 0 1.25.2 2.45.57 3.57.11.35.03.74-.25 1.02l-2.2 2.2z"/></svg>
                        Contact Number *
                    </label>
                    <input type="tel" id="contactNumber" class="form-input" placeholder="09X XXX XXXXX" pattern="09[0-9]{9}" maxlength="12" required title="Please enter a valid Philippine mobile number">
                </div>

                <!-- Email -->
                <div class="form-group full-width">
                    <label class="form-label" for="emailAddress">
                        <svg viewBox="0 0 24 24"><path d="M20 4H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z"/></svg>
                        Email Address * <span style="font-size:.75rem;color:#6b7280;font-weight:400;">(Gmail only)</span>
                    </label>
                    <input type="email" id="emailAddress" class="form-input" placeholder="example@gmail.com" required
                        pattern="[a-zA-Z0-9._%+\-]+@gmail\.com"
                        title="Please enter a valid Gmail address">
                    <small id="emailError" style="color:#ef4444;font-size:.8rem;margin-top:.25rem;display:none;">Please enter a valid Gmail address ending in @gmail.com</small>
                </div>

                <!-- Address -->
                <div class="form-group full-width">
                    <label class="form-label" for="address">
                        <svg viewBox="0 0 24 24"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/></svg>
                        Address *
                    </label>
                    <input type="text" id="address" class="form-input" placeholder="Street Address, Barangay, City/Municipality, Province, ZIP Code" required>
                </div>

                <!-- Purpose -->
                <div class="form-group">
                    <label class="form-label" for="purpose">
                        <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8l-6-6zm4 18H6V4h7v5h5v11z"/></svg>
                        Purpose of Visit *
                    </label>
                    <select id="purpose" class="form-select" required>
                        <option value="">Select purpose</option>
                        <?php foreach ($visit_purposes as $vp): ?>
                        <option value="<?php echo htmlspecialchars($vp); ?>"><?php echo htmlspecialchars($vp); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Purpose Other -->
                <div class="form-group" id="purposeOtherContainer" style="display:none;">
                    <label class="form-label" for="purposeOther">
                        <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8l-6-6zm4 18H6V4h7v5h5v11z"/></svg>
                        Specify Purpose *
                    </label>
                    <input type="text" id="purposeOther" class="form-input" placeholder="Please specify your purpose" required>
                </div>

                <!-- Department -->
                <div class="form-group">
                    <label class="form-label" for="department">
                        <svg viewBox="0 0 24 24"><path d="M21 16V4a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2h14a2 2 0 002-2zm-11-4c0-1.11.89-2 2-2s2 .89 2 2-.89 2-2 2-2-.89-2-2zM9 18v-2a2 2 0 012-2h2a2 2 0 012 2v2H9z"/></svg>
                        Office / Department *
                    </label>
                    <select id="department" class="form-select" required>
                        <option value="">Select department</option>
                        <?php foreach ($offices as $office): ?>
                        <option value="<?php echo htmlspecialchars($office); ?>"><?php echo htmlspecialchars($office); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Department Other -->
                <div class="form-group" id="departmentOtherContainer" style="display:none;">
                    <label class="form-label" for="departmentOther">
                        <svg viewBox="0 0 24 24"><path d="M21 16V4a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2h14a2 2 0 002-2zm-11-4c0-1.11.89-2 2-2s2 .89 2 2-.89 2-2 2-2-.89-2-2zM9 18v-2a2 2 0 012-2h2a2 2 0 012 2v2H9z"/></svg>
                        Specify Department *
                    </label>
                    <input type="text" id="departmentOther" class="form-input" placeholder="Please specify the department">
                </div>

                <!-- Person to Visit -->
                <div class="form-group full-width">
                    <label class="form-label" for="personToVisit">
                        <svg viewBox="0 0 24 24"><path d="M16 4c4.42 0 8 3.58 8 8s-3.58 8-8 8c-1.1 0-2.14-.22-3.1-.62L9 21l1.9-3.9c-1.78-1.46-2.9-3.64-2.9-6.1 0-4.42 3.58-8 8-8z"/></svg>
                        Person to Visit (if applicable)
                    </label>
                    <input type="text" id="personToVisit" class="form-input" placeholder="Name of person you're visiting">
                </div>

                <!-- Group Visit -->
                <div class="form-group">
                    <label class="form-label">
                        <svg viewBox="0 0 24 24"><path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z"/></svg>
                        Are you visiting as a group?
                    </label>
                    <div style="display:flex;gap:1.5rem;margin-top:.5rem;">
                        <label style="display:flex;align-items:center;gap:.5rem;cursor:pointer;">
                            <input type="radio" name="groupVisit" id="groupVisitYes" value="yes" style="width:18px;height:18px;cursor:pointer;">
                            <span style="color:var(--green-dark);font-weight:500;">Yes</span>
                        </label>
                        <label style="display:flex;align-items:center;gap:.5rem;cursor:pointer;">
                            <input type="radio" name="groupVisit" id="groupVisitNo" value="no" checked style="width:18px;height:18px;cursor:pointer;">
                            <span style="color:var(--green-dark);font-weight:500;">No</span>
                        </label>
                    </div>
                </div>

                <!-- Group Size -->
                <div class="form-group" id="groupSizeContainer" style="display:none;">
                    <label class="form-label" for="groupSize">
                        <svg viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8zm.5-13H11v6l5.25 3.15.75-1.23-4.5-2.67z"/></svg>
                        Total Number of People *
                    </label>
                    <input type="number" id="groupSize" class="form-input"
                           placeholder="Enter total number of people" min="2"
                           max="<?php echo $max_group_size > 0 ? $max_group_size : 9999; ?>">
                    <?php if ($max_group_size > 0): ?>
                    <small style="color:#666;font-size:.8rem;margin-top:.25rem;">Maximum group size: <strong><?php echo $max_group_size; ?> people</strong></small>
                    <?php endif; ?>
                    <small id="groupSizeError" style="color:#ef4444;font-size:.8rem;margin-top:.25rem;display:none;"></small>
                </div>

                <!-- Group Members -->
                <div class="form-group full-width" id="groupMembersContainer" style="display:none;">
                    <label class="form-label">
                        <svg viewBox="0 0 24 24"><path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z"/></svg>
                        Group Members Full Names *
                    </label>
                    <div id="groupMembersFields" style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-top:.5rem;"></div>
                </div>

                <!-- Notes -->
                <div class="form-group full-width">
                    <label class="form-label" for="notes">
                        <svg viewBox="0 0 24 24"><path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04c.39-.39.39-1.02 0-1.41l-2.34-2.34c-.39-.39-1.02-.39-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/></svg>
                        Additional Notes
                    </label>
                    <textarea id="notes" class="form-textarea" placeholder="Any additional information or special requests"></textarea>
                </div>

                <!-- ID Type -->
                <div class="form-group full-width">
                    <label class="form-label" for="idType">
                        <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8l-6-6zm4 18H6V4h7v5h5v11z"/></svg>
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

                <!-- ID Type Other -->
                <div class="form-group" id="idTypeOtherContainer" style="display:none;">
                    <label class="form-label" for="idTypeOther">
                        <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8l-6-6zm4 18H6V4h7v5h5v11z"/></svg>
                        Specify ID Type
                    </label>
                    <input type="text" id="idTypeOther" class="form-input" placeholder="Please specify your ID type">
                </div>

                <!-- ID Photo Upload -->
                <div class="form-group full-width">
                    <label class="form-label">
                        <svg viewBox="0 0 24 24"><path d="M23 19V5c0-1.1-.9-2-2-2H3c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h18c1.1 0 2-.9 2-2zM8.5 13.5l2.5 3.01L14.5 12l4.5 6H5l3.5-4.5z"/><circle cx="9.5" cy="8.5" r="1.5"/></svg>
                        Upload/Capture Valid ID <?php echo $require_photo ? '<span style="color:red;">*</span>' : '(Optional)'; ?>
                    </label>
                    <div class="photo-upload-zone" id="photo-zone">
                        <div class="upload-icon">
                            <svg viewBox="0 0 24 24"><path d="M23 19V5c0-1.1-.9-2-2-2H3c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h18c1.1 0 2-.9 2-2zM8.5 13.5l2.5 3.01L14.5 12l4.5 6H5l3.5-4.5z"/><circle cx="9.5" cy="8.5" r="1.5"/></svg>
                        </div>
                        <div class="upload-text">
                            <h4>Capture ID Photo or Upload File</h4>
                            <p>Use camera to capture or choose a file</p>
                        </div>
                        <div class="camera-controls">
                            <button type="button" class="camera-btn" id="start-camera-btn">
                                <svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor"><path d="M17 10.5V7c0-.55-.45-1-1-1H4c-.55 0-1 .45-1 1v10c0 .55.45 1 1 1h12c.55 0 1-.45 1-1v-3.5l4 4v-11l-4 4z"/></svg>
                                Open Camera
                            </button>
                            <label for="validId" class="camera-btn secondary">
                                <svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8l-6-6zm-1 9V7.5L17.5 12H13z"/></svg>
                                Upload File
                                <input type="file" id="validId" accept="image/*,.pdf" style="position:absolute;opacity:0;pointer-events:none;">
                            </label>
                        </div>
                    </div>

                    <div class="camera-preview" id="camera-preview">
                        <video id="camera-video" autoplay playsinline></video>
                        <canvas id="photo-canvas"></canvas>
                        <div class="camera-controls">
                            <button type="button" class="camera-btn" id="capture-btn">
                                <svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor"><circle cx="12" cy="12" r="3.2"/><path d="M9 2L7.17 4H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2h-3.17L15 2H9zm3 15c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5z"/></svg>
                                Capture ID Photo
                            </button>
                            <button type="button" class="camera-btn secondary" id="close-camera-btn">
                                <svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor"><path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/></svg>
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

                <!-- Submit -->
                <div class="form-group full-width">
                    <button type="submit" class="submit-btn">
                        <svg viewBox="0 0 24 24"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41L9 16.17z"/></svg>
                        Complete Registration
                    </button>
                </div>

            </form>
        </div><!-- /#form-panel -->

        <!-- ── PASS PANEL — shown after successful registration ── -->
        <div class="pass-panel" id="pass-panel">
            <div class="pass-card" id="visitor-pass-card">

                <!-- Header -->
                <div class="pass-card-header">
                    <div class="pass-header-content">
                        <div class="pass-logo">
                            <img src="/logbooksystem/assets/images/sdsclogo.png" alt="SDSC Logo">
                        </div>
                        <div class="pass-school-name">St. Dominic Savio College</div>
                        <div class="pass-subtitle-text">Official Visitor Pass</div>
                    </div>
                </div>

                <!-- Body -->
                <div class="pass-body">
                    <div class="pass-success-icon">
                        <svg viewBox="0 0 24 24"><path d="M9 16.2L4.8 12l-1.4 1.4L9 19 21 7l-1.4-1.4L9 16.2z"/></svg>
                    </div>
                    <h3 class="pass-title">Registration Successful!</h3>
                    <p class="pass-message">Your visitor pass has been generated</p>

                    <div class="pass-code-section">
                        <div class="pass-code-label">Your Visitor Pass Number</div>
                        <div class="pass-code-display">
                            <div class="pass-code-number" id="visitor-pass-code">—</div>
                        </div>
                        <div class="pass-code-note">Please show this number at the entrance</div>
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
                        <button type="button" class="pass-btn close-btn" id="close-pass-btn">New Visit</button>
                    </div>
                </div>

            </div>
        </div><!-- /#pass-panel -->

    </div><!-- /.right-panel -->

    <!-- ════════════════════════════════
         DRESS CODE DETAIL MODAL (unchanged)
    ════════════════════════════════ -->
    <div class="dc-modal-overlay" id="dc-modal-overlay" role="dialog" aria-modal="true">
        <div class="dc-modal">
            <div class="dc-modal-header">
                <span class="dc-modal-header-title">Dress Code Detail</span>
                <button class="dc-modal-close" onclick="closeDressCodeModal()" aria-label="Close">&#x2715;</button>
            </div>
            <div class="dc-modal-body">
                <div class="dc-modal-image-wrap">
                    <img id="dc-modal-img" src="" alt="">
                </div>
                <div id="dc-modal-badge" class="dc-modal-badge"></div>
                <h3 id="dc-modal-title" class="dc-modal-item-title"></h3>
                <p id="dc-modal-note" class="dc-modal-note"></p>
                <div id="dc-modal-bar" class="dc-modal-allowed-bar"></div>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>

    <script>
        // ── Settings from PHP (unchanged) ──
        const REQUIRE_PHOTO     = <?php echo json_encode($require_photo); ?>;
        const REQUIRE_ID_UPLOAD = <?php echo json_encode($require_id_upload); ?>;
        const MAX_GROUP_SIZE    = <?php echo json_encode($max_group_size); ?>;

        // ── State ──
        let cameraStream      = null;
        let capturedPhotoBlob = null;
        let registrationData  = {};

        // ══════════════════════════════════════════
        // PANEL SWAP  — the only new UI behaviour
        // ══════════════════════════════════════════
        function showPassPanel() {
            document.getElementById('form-panel').style.display = 'none';
            document.getElementById('pass-panel').classList.add('visible');
        }

        function showFormPanel() {
            document.getElementById('pass-panel').classList.remove('visible');
            document.getElementById('form-panel').style.display = '';
        }

        // ══════════════════════════════════════════
        // DRESS CODE MODAL (unchanged)
        // ══════════════════════════════════════════
        function openDressCodeModal(title, imgSrc, status) {
            const isAllowed = status === 'allowed';
            document.getElementById('dc-modal-img').src  = imgSrc;
            document.getElementById('dc-modal-img').alt  = title;
            document.getElementById('dc-modal-title').textContent = title;

            const badge = document.getElementById('dc-modal-badge');
            badge.textContent = isAllowed ? '✓ Allowed' : '✗ Not Allowed';
            badge.className   = 'dc-modal-badge ' + (isAllowed ? 'allowed' : 'not-allowed');

            document.getElementById('dc-modal-note').textContent = isAllowed
                ? 'This attire or item is permitted on campus. Visitors wearing this are welcome.'
                : 'This attire or item is NOT permitted on campus. Visitors wearing this may be denied entry.';

            const bar = document.getElementById('dc-modal-bar');
            bar.textContent = isAllowed ? '✔ This is acceptable on campus' : '✘ This violates the dress code policy';
            bar.className   = 'dc-modal-allowed-bar ' + (isAllowed ? 'allowed' : 'not-allowed');

            document.getElementById('dc-modal-overlay').classList.add('open');
        }

        function closeDressCodeModal() {
            document.getElementById('dc-modal-overlay').classList.remove('open');
        }

        // ══════════════════════════════════════════
        // DATE / TIME helpers (unchanged)
        // ══════════════════════════════════════════
        function getCurrentDateTime() {
            const now = new Date();
            return {
                date: now.toLocaleDateString('en-US', { year:'numeric', month:'long', day:'numeric' }),
                time: now.toLocaleTimeString('en-US', { hour:'2-digit', minute:'2-digit', second:'2-digit', hour12:true }),
                timestamp: now.toISOString()
            };
        }

        // ══════════════════════════════════════════
        // SERVER SUBMISSION (unchanged logic)
        // ══════════════════════════════════════════
        function submitVisitorRegistration(formData) {
            const submitBtn   = document.querySelector('.submit-btn');
            const originalHTML = submitBtn.innerHTML;
            submitBtn.innerHTML = '<svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor" style="animation:spin 1s linear infinite"><path d="M12 4V1L8 5l4 4V6c3.31 0 6 2.69 6 6 0 1.01-.25 1.97-.7 2.8l1.46 1.46C19.54 15.03 20 13.57 20 12c0-4.42-3.58-8-8-8zm0 14c-3.31 0-6-2.69-6-6 0-1.01.25-1.97.7-2.8L5.24 7.74C4.46 8.97 4 10.43 4 12c0 4.42 3.58 8 8 8v3l4-4-4-4v3z"/></svg> Registering...';
            submitBtn.disabled = true;

            const payload = {
                fullName:        formData.fullName,
                contactNumber:   formData.contactNumber,
                email:           formData.email           || null,
                address:         formData.address         || null,
                purpose:         formData.purpose,
                purposeOther:    formData.purposeOther    || null,
                department:      formData.department      || null,
                departmentOther: formData.departmentOther || null,
                personToVisit:   formData.personToVisit,
                idType:          formData.idType          || null,
                idTypeOther:     formData.idTypeOther     || null,
                isGroupVisit:    formData.isGroupVisit,
                groupSize:       formData.groupSize       || 1,
                groupMembers:    formData.groupMembers ? formData.groupMembers.join(', ') : null,
                additionalNotes: formData.notes           || null
            };

            const fd = new FormData();
            Object.entries(payload).forEach(([k, v]) => { if (v !== null && v !== undefined) fd.append(k, v); });

            if (capturedPhotoBlob) {
                fd.append('id_photo', capturedPhotoBlob, 'id_photo.jpg');
            } else {
                const idFile = document.getElementById('validId');
                if (idFile && idFile.files.length > 0) fd.append('id_photo', idFile.files[0]);
            }

            fetch('ajax/visitor-registration.php', { method:'POST', body: fd })
                .then(r => {
                    if (!r.ok) throw new Error(`Server error (HTTP ${r.status}). Please try again.`);
                    return r.text().then(text => {
                        try { return JSON.parse(text); }
                        catch (e) { throw new Error('Server returned an unexpected response. Please try again.\n\nDetails: ' + text.substring(0, 200)); }
                    });
                })
                .then(data => {
                    if (data.success) {
                        showVisitorPassModal(data.data);
                    } else {
                        alert('Registration failed: ' + data.message);
                        submitBtn.innerHTML = originalHTML;
                        submitBtn.disabled  = false;
                    }
                })
                .catch(err => {
                    console.error('Registration error:', err);
                    alert(err.message || 'Registration failed. Please try again.');
                    submitBtn.innerHTML = originalHTML;
                    submitBtn.disabled  = false;
                });
        }

        // ══════════════════════════════════════════
        // SHOW PASS — swaps panels instead of modal
        // ══════════════════════════════════════════
        function showVisitorPassModal(serverData) {
            const dt = getCurrentDateTime();

            registrationData = {
                passCode:    serverData.visitor_pass,
                visitorName: serverData.visitor_name,
                purpose:     serverData.purpose,
                personToVisit: serverData.person_to_visit,
                registrationDate: dt.date,
                registrationTime: dt.time
            };

            document.getElementById('visitor-pass-code').textContent = serverData.visitor_pass;

            // ← key change: swap panels, not a modal
            showPassPanel();

            // Reset submit button
            const submitBtn = document.querySelector('.submit-btn');
            submitBtn.innerHTML = '<svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M9 16.2L4.8 12l-1.4 1.4L9 19 21 7l-1.4-1.4L9 16.2z"/></svg>Complete Registration';
            submitBtn.disabled = false;
        }

        // ══════════════════════════════════════════
        // DOWNLOAD PASS (unchanged)
        // ══════════════════════════════════════════
        async function downloadVisitorPass() {
            const btn = document.getElementById('download-pass-btn');
            const orig = btn.innerHTML;
            btn.innerHTML = '<svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor" style="animation:spin 1s linear infinite"><path d="M12 4V1L8 5l4 4V6c3.31 0 6 2.69 6 6 0 1.01-.25 1.97-.7 2.8l1.46 1.46C19.54 15.03 20 13.57 20 12c0-4.42-3.58-8-8-8zm0 14c-3.31 0-6-2.69-6-6 0-1.01.25-1.97.7-2.8L5.24 7.74C4.46 8.97 4 10.43 4 12c0 4.42 3.58 8 8 8v3l4-4-4-4v3z"/></svg> Generating...';
            btn.disabled = true;
            try {
                const canvas = await html2canvas(document.getElementById('visitor-pass-card'), {
                    scale: 2, backgroundColor: '#ffffff', logging: false, useCORS: true, allowTaint: true
                });
                canvas.toBlob(blob => {
                    const url  = URL.createObjectURL(blob);
                    const link = document.createElement('a');
                    link.download = `SDSC-Visitor-Pass-${registrationData.passCode || 'pass'}.png`;
                    link.href = url;
                    link.click();
                    URL.revokeObjectURL(url);
                    btn.innerHTML = orig;
                    btn.disabled  = false;
                }, 'image/png');
            } catch (e) {
                console.error(e);
                alert('Failed to download pass. Please try again.');
                btn.innerHTML = orig;
                btn.disabled  = false;
            }
        }

        // ══════════════════════════════════════════
        // CAMERA (unchanged)
        // ══════════════════════════════════════════
        async function startCamera() {
            try {
                cameraStream = await navigator.mediaDevices.getUserMedia({ video: { width:{ideal:640}, height:{ideal:480}, facingMode:'user' } });
                document.getElementById('camera-video').srcObject = cameraStream;
                document.getElementById('photo-zone').style.display    = 'none';
                document.getElementById('camera-preview').style.display = 'block';
                document.getElementById('photo-preview').style.display  = 'none';
            } catch (e) {
                console.error(e);
                alert('Unable to access camera. Please check permissions.');
            }
        }

        function stopCamera() {
            if (cameraStream) { cameraStream.getTracks().forEach(t => t.stop()); cameraStream = null; }
            document.getElementById('camera-preview').style.display = 'none';
            document.getElementById('photo-zone').style.display     = 'block';
        }

        function capturePhoto() {
            const video  = document.getElementById('camera-video');
            const canvas = document.getElementById('photo-canvas');
            canvas.width = video.videoWidth; canvas.height = video.videoHeight;
            canvas.getContext('2d').drawImage(video, 0, 0);
            canvas.toBlob(blob => {
                capturedPhotoBlob = blob;
                document.getElementById('photo-img').src = canvas.toDataURL('image/jpeg', 0.8);
                stopCamera();
                document.getElementById('photo-preview').style.display = 'block';
            }, 'image/jpeg', 0.8);
        }

        function retakePhoto() {
            capturedPhotoBlob = null;
            document.getElementById('validId').value          = '';
            document.getElementById('photo-preview').style.display = 'none';
            document.getElementById('photo-zone').style.display    = 'block';
        }

        // ══════════════════════════════════════════
        // DOM READY — event listeners (unchanged logic)
        // ══════════════════════════════════════════
        document.addEventListener('DOMContentLoaded', function () {

            // Dress code modal close
            document.getElementById('dc-modal-overlay').addEventListener('click', e => { if (e.target === e.currentTarget) closeDressCodeModal(); });
            document.addEventListener('keydown', e => { if (e.key === 'Escape') closeDressCodeModal(); });

            // Group visit toggle
            document.getElementById('groupVisitYes').addEventListener('change', function () {
                if (this.checked) {
                    document.getElementById('groupSizeContainer').style.display = 'flex';
                    document.getElementById('groupSize').required = true;
                }
            });
            document.getElementById('groupVisitNo').addEventListener('change', function () {
                if (this.checked) {
                    document.getElementById('groupSizeContainer').style.display   = 'none';
                    document.getElementById('groupMembersContainer').style.display = 'none';
                    document.getElementById('groupMembersFields').innerHTML        = '';
                    document.getElementById('groupSize').required = false;
                    document.getElementById('groupSize').value    = '';
                }
            });

            if (MAX_GROUP_SIZE > 0) document.getElementById('groupSize').max = MAX_GROUP_SIZE;

            document.getElementById('groupSize').addEventListener('input', function () {
                const n = parseInt(this.value);
                const errEl = document.getElementById('groupSizeError');
                if (MAX_GROUP_SIZE > 0 && n > MAX_GROUP_SIZE) {
                    errEl.textContent = `Campus limit is ${MAX_GROUP_SIZE} people (2–${MAX_GROUP_SIZE}).`;
                    errEl.style.display = 'block';
                    document.getElementById('groupMembersContainer').style.display = 'none';
                    document.getElementById('groupMembersFields').innerHTML = '';
                    return;
                }
                errEl.style.display = 'none';
                const max = MAX_GROUP_SIZE > 0 ? MAX_GROUP_SIZE : 9999;
                if (n >= 2 && n <= max) {
                    document.getElementById('groupMembersContainer').style.display = 'flex';
                    const fields = document.getElementById('groupMembersFields');
                    fields.innerHTML = '';
                    for (let i = 1; i <= n; i++) {
                        const wrap = document.createElement('div');
                        wrap.style.cssText = 'display:flex;flex-direction:column;';
                        wrap.innerHTML = `<label style="font-size:.85rem;color:var(--green-dark);margin-bottom:.5rem;font-weight:500;">Person ${i} Full Name *</label>
                            <input type="text" id="groupMember${i}" name="groupMember${i}" class="form-input" placeholder="Enter full name of person ${i}" required>`;
                        fields.appendChild(wrap);
                    }
                } else {
                    document.getElementById('groupMembersContainer').style.display = 'none';
                    document.getElementById('groupMembersFields').innerHTML = '';
                }
            });

            // Purpose "other"
            document.getElementById('purpose').addEventListener('change', function () {
                const show = this.value.toLowerCase() === 'other';
                document.getElementById('purposeOtherContainer').style.display = show ? 'flex' : 'none';
                document.getElementById('purposeOther').required = show;
                if (!show) document.getElementById('purposeOther').value = '';
            });

            // Department "other"
            document.getElementById('department').addEventListener('change', function () {
                const show = this.value.toLowerCase() === 'other';
                document.getElementById('departmentOtherContainer').style.display = show ? 'flex' : 'none';
                document.getElementById('departmentOther').required = show;
                if (!show) document.getElementById('departmentOther').value = '';
            });

            // ID type "other"
            document.getElementById('idType').addEventListener('change', function () {
                const show = this.value === 'other';
                document.getElementById('idTypeOtherContainer').style.display = show ? 'flex' : 'none';
                if (!show) document.getElementById('idTypeOther').value = '';
            });

            // Camera buttons
            document.getElementById('start-camera-btn').addEventListener('click', startCamera);
            document.getElementById('capture-btn').addEventListener('click', capturePhoto);
            document.getElementById('close-camera-btn').addEventListener('click', stopCamera);
            document.getElementById('retake-btn').addEventListener('click', retakePhoto);

            // Contact number — numeric only
            const contactInput = document.getElementById('contactNumber');
            contactInput.addEventListener('input', function () {
                this.value = this.value.replace(/[^0-9]/g, '').slice(0, 11);
            });
            contactInput.addEventListener('keypress', function (e) {
                if (e.key && !/[0-9]/.test(e.key) && !['Backspace','Delete','Tab','ArrowLeft','ArrowRight'].includes(e.key)) e.preventDefault();
            });

            // Email live validation
            const emailInput = document.getElementById('emailAddress');
            const emailErrEl = document.getElementById('emailError');
            const gmailRx    = /^[a-zA-Z0-9._%+\-]+@gmail\.com$/i;
            emailInput.addEventListener('input', function () {
                emailErrEl.style.display = (this.value && !gmailRx.test(this.value.trim())) ? 'block' : 'none';
            });
            emailInput.addEventListener('blur', function () {
                if (this.value && !gmailRx.test(this.value.trim())) {
                    emailErrEl.style.display = 'block';
                    this.style.borderColor = '#ef4444';
                } else if (this.value) {
                    emailErrEl.style.display = 'none';
                    this.style.borderColor = '';
                }
            });
            emailInput.addEventListener('focus', function () { this.style.borderColor = ''; });

            // File input preview
            document.getElementById('validId').addEventListener('change', function (e) {
                const file = e.target.files[0];
                if (file && file.type.startsWith('image/')) {
                    const reader = new FileReader();
                    reader.onload = ev => {
                        document.getElementById('photo-img').src = ev.target.result;
                        document.getElementById('photo-filename').textContent = file.name;
                        document.getElementById('photo-zone').style.display    = 'none';
                        document.getElementById('photo-preview').style.display = 'block';
                    };
                    reader.readAsDataURL(file);
                }
            });

            // Form submit
            document.getElementById('visitor-form').addEventListener('submit', function (e) {
                e.preventDefault();

                const isGroup  = document.getElementById('groupVisitYes').checked;
                const groupSize = isGroup ? parseInt(document.getElementById('groupSize').value) : null;
                const members  = [];
                if (isGroup && groupSize) {
                    for (let i = 1; i <= groupSize; i++) {
                        const el = document.getElementById(`groupMember${i}`);
                        if (el) members.push(el.value);
                    }
                }

                const fd = {
                    fullName:        document.getElementById('fullName').value,
                    contactNumber:   document.getElementById('contactNumber').value,
                    email:           document.getElementById('emailAddress').value.trim(),
                    address:         document.getElementById('address').value,
                    purpose:         document.getElementById('purpose').value,
                    purposeOther:    document.getElementById('purpose').value === 'other' ? document.getElementById('purposeOther').value : null,
                    department:      document.getElementById('department').value,
                    departmentOther: document.getElementById('department').value === 'other' ? document.getElementById('departmentOther').value : null,
                    idType:          document.getElementById('idType').value,
                    idTypeOther:     document.getElementById('idType').value === 'other' ? document.getElementById('idTypeOther').value : null,
                    personToVisit:   document.getElementById('personToVisit').value,
                    isGroupVisit:    isGroup,
                    groupSize:       groupSize,
                    groupMembers:    members,
                    notes:           document.getElementById('notes').value
                };

                if (!fd.fullName || !fd.contactNumber || !fd.purpose || !fd.personToVisit) {
                    alert('Please fill in all required fields marked with *'); return;
                }
                if (fd.purpose.toLowerCase() === 'other' && !fd.purposeOther) {
                    alert('Please specify your purpose of visit'); return;
                }
                if (!/^09[0-9]{9}$/.test(fd.contactNumber)) {
                    alert('Please enter a valid Philippine mobile number (e.g., 09123456789)'); return;
                }
                if (!fd.email || !gmailRx.test(fd.email)) {
                    emailErrEl.style.display = 'block';
                    document.getElementById('emailAddress').focus(); return;
                }
                emailErrEl.style.display = 'none';
                if (isGroup && (!groupSize || groupSize < 2)) {
                    alert('Please enter the total number of people (minimum 2)'); return;
                }
                if (MAX_GROUP_SIZE > 0 && isGroup && groupSize > MAX_GROUP_SIZE) {
                    alert(`Maximum group size is ${MAX_GROUP_SIZE} people.`);
                    document.getElementById('groupSize').focus(); return;
                }
                if (isGroup && members.length !== groupSize) {
                    alert('Please enter the full names of all group members'); return;
                }
                const hasMedia = capturedPhotoBlob !== null || document.getElementById('validId').files.length > 0;
                if (REQUIRE_PHOTO && !hasMedia) {
                    alert('A photo or ID upload is required.'); return;
                }
                if (REQUIRE_ID_UPLOAD && !fd.idType) {
                    alert('Please select your ID type.'); return;
                }

                submitVisitorRegistration(fd);
            });

            // Pass panel — "New Visit" resets to form
            document.getElementById('close-pass-btn').addEventListener('click', function () {
                showFormPanel();
                document.getElementById('visitor-form').reset();
                document.getElementById('photo-preview').style.display = 'none';
                document.getElementById('photo-zone').style.display    = 'block';
                capturedPhotoBlob = null;
                ['groupSizeContainer','groupMembersContainer','purposeOtherContainer',
                 'departmentOtherContainer','idTypeOtherContainer'].forEach(id => {
                    document.getElementById(id).style.display = 'none';
                });
                document.getElementById('groupMembersFields').innerHTML = '';
                const btn = document.querySelector('.submit-btn');
                btn.innerHTML = '<svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M9 16.2L4.8 12l-1.4 1.4L9 19 21 7l-1.4-1.4L9 16.2z"/></svg>Complete Registration';
                btn.disabled = false;
            });

            document.getElementById('download-pass-btn').addEventListener('click', downloadVisitorPass);
        });
    </script>
</body>
</html>
<?php
}
exit();
?>