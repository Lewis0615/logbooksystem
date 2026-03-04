<?php
/**
 * System Settings Helper
 * St. Dominic Savio College - Visitor Management System
 *
 * Loads / saves key-value settings from the system_settings table.
 * Requires $db (Database instance) to already be available.
 */

/**
 * Return the value of a single setting, or $default if not found.
 */
function getSystemSetting(string $key, $default = null) {
    global $db;
    try {
        $row = $db->fetch(
            "SELECT setting_value FROM system_settings WHERE setting_key = ?",
            [$key]
        );
        return ($row !== false) ? $row['setting_value'] : $default;
    } catch (Exception $e) {
        return $default;
    }
}

/**
 * Return ALL settings as an associative array [ key => value ].
 */
function getAllSystemSettings(): array {
    global $db;
    try {
        $rows = $db->fetchAll("SELECT setting_key, setting_value FROM system_settings");
        $out  = [];
        foreach ($rows as $row) {
            $out[$row['setting_key']] = $row['setting_value'];
        }
        return $out;
    } catch (Exception $e) {
        return [];
    }
}

/**
 * Upsert a single setting.
 */
function saveSystemSetting(string $key, $value): void {
    global $db;
    $db->execute(
        "INSERT INTO system_settings (setting_key, setting_value)
         VALUES (?, ?)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()",
        [$key, (string)$value]
    );
}

/**
 * Return a JSON-encoded list setting as a PHP array.
 * Falls back to $default if the key is missing or invalid.
 */
function getSettingList(string $key, array $default = []): array {
    $val = getSystemSetting($key);
    if ($val === null) return $default;
    $decoded = json_decode($val, true);
    return (is_array($decoded) && count($decoded) > 0) ? $decoded : $default;
}

/**
 * Save a PHP array as a JSON-encoded list setting.
 * Trims items and removes empty strings.
 */
function saveSettingList(string $key, array $items): void {
    $clean = array_values(array_filter(array_map('trim', $items)));
    saveSystemSetting($key, json_encode($clean, JSON_UNESCAPED_UNICODE));
}
