<?php
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
ini_set('upload_max_filesize', '20M');
ini_set('post_max_size', '20M');

if (!isset($_SESSION['user_id']) && !(isset($_SESSION['client_id']) && $_SESSION['user_type'] === 'client')) {
    $_SESSION['errors'] = ["You must be logged in to upload photos."];
    error_log("Unauthorized access attempt in save_inspection_photos.php");
    header("Location: index.html");
    exit();
}

require '../db_connect.php';
require 'config.php';

// Check database connection
if (!$conn) {
    $_SESSION['errors'] = ["Database connection failed."];
    error_log("Database connection failed in save_inspection_photos.php");
    $redirect = (isset($_SESSION['client_id']) && $_SESSION['user_type'] === 'client') ? "client_details.php" : "index.html";
    header("Location: $redirect");
    exit();
}

// Check for quote_id
$quote_id = $_POST['quote_id'] ?? null;
if (!$quote_id || !is_numeric($quote_id)) {
    $_SESSION['errors'] = ["Invalid or missing quote ID."];
    error_log("Invalid quote_id: $quote_id");
    header("Location: inspection_photos.php?quote_id=$quote_id");
    exit();
}

// Verify quote ownership
if (isset($_SESSION['client_id']) && $_SESSION['user_type'] === 'client') {
    $stmt = $conn->prepare("SELECT q.quote_id, q.client_id FROM quotes q WHERE q.quote_id = ? AND q.client_id = ?");
    $stmt->bind_param("is", $quote_id, $_SESSION['client_id']);
} else {
    $stmt = $conn->prepare("SELECT q.quote_id, q.client_id FROM quotes q WHERE q.quote_id = ? AND q.user_id = ?");
    $stmt->bind_param("ii", $quote_id, $_SESSION['user_id']);
}
$stmt->execute();
$quote_result = $stmt->get_result();
if ($quote_result->num_rows === 0) {
    $_SESSION['errors'] = ["No quote found for this quote ID or you lack permission."];
    error_log("No quote found for quote_id: $quote_id");
    header("Location: inspection_photos.php?quote_id=$quote_id");
    exit();
}
$quote_data = $quote_result->fetch_assoc();
$client_id = $quote_data['client_id'];
$stmt->close();

// Validate vehicles array
$vehicles = $_POST['vehicles'] ?? [];
if (empty($vehicles) || !is_array($vehicles)) {
    $_SESSION['errors'] = ["No vehicle data provided."];
    error_log("No vehicle data for quote_id: $quote_id");
    header("Location: inspection_photos.php?quote_id=$quote_id");
    exit();
}

// Function to get Microsoft Graph API access token
function getGraphAccessToken() {
    $url = "https://login.microsoftonline.com/" . AZURE_TENANT_ID . "/oauth2/v2.0/token";
    $data = [
        'client_id' => AZURE_CLIENT_ID,
        'scope' => 'https://graph.microsoft.com/.default',
        'client_secret' => AZURE_CLIENT_SECRET,
        'grant_type' => 'client_credentials'
    ];
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);
    
    if ($http_code !== 200) {
        error_log("Failed to get Graph API token: HTTP $http_code, Response: $response, Curl Error: $curl_error");
        return false;
    }
    
    $token_data = json_decode($response, true);
    if (!isset($token_data['access_token'])) {
        error_log("No access token in response: " . json_encode($token_data));
        return false;
    }
    
    error_log("Access token retrieved successfully for OneDrive");
    return $token_data['access_token'];
}

// Function to upload file to OneDrive
function uploadToOneDrive($file_path, $file_name, $access_token) {
    $url = "https://graph.microsoft.com/v1.0/drives/" . ONEDRIVE_DRIVE_ID . "/root:/Inspections/$file_name:/content";
    $file_content = file_get_contents($file_path);
    if ($file_content === false) {
        error_log("Failed to read file content: $file_path");
        return false;
    }
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer $access_token",
        "Content-Type: application/octet-stream",
        "Content-Length: " . strlen($file_content)
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $file_content);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);
    
    if ($http_code !== 201 && $http_code !== 200) {
        error_log("Failed to upload to OneDrive: HTTP $http_code, Response: $response, Curl Error: $curl_error");
        return false;
    }
    
    $response_data = json_decode($response, true);
    if (!isset($response_data['webUrl'])) {
        error_log("No webUrl in OneDrive response: " . json_encode($response_data));
        return false;
    }
    
    error_log("Uploaded file to OneDrive: $file_name, webUrl: " . $response_data['webUrl']);
    return $response_data['webUrl'];
}

// Initialize errors and track successful uploads
$errors = [];
$valid_photo_types = ['left_side', 'right_side', 'front', 'back', 'bonnet_open', 'license_disc', 'odometer'];
$successful_uploads = 0;

// Get access token
$access_token = getGraphAccessToken();
if (!$access_token) {
    $_SESSION['errors'] = ["Failed to authenticate with OneDrive."];
    error_log("OneDrive authentication failed for quote_id: $quote_id");
    header("Location: inspection_photos.php?quote_id=$quote_id");
    exit();
}

// Process uploaded files
foreach ($vehicles as $index => $vehicle_data) {
    $vehicle_id = isset($vehicle_data['vehicle_id']) && is_numeric($vehicle_data['vehicle_id']) ? (int)$vehicle_data['vehicle_id'] : null;
    
    // Verify vehicle belongs to the quote
    $stmt = $conn->prepare("SELECT vehicle_id, vehicle_year, vehicle_make, vehicle_model FROM quote_vehicles WHERE vehicle_id = ? AND quote_id = ? AND deleted_at IS NULL");
    $stmt->bind_param("ii", $vehicle_id, $quote_id);
    $stmt->execute();
    $vehicle_result = $stmt->get_result();
    if ($vehicle_result->num_rows === 0) {
        $errors[] = "Invalid vehicle ID for vehicle " . ($index + 1);
        error_log("Invalid vehicle_id: $vehicle_id for quote_id: $quote_id");
        continue;
    }
    $vehicle = $vehicle_result->fetch_assoc();
    $stmt->close();

    // Process each photo type
    foreach ($valid_photo_types as $photo_type) {
        if (isset($_FILES['vehicles']['name'][$index][$photo_type]) && $_FILES['vehicles']['error'][$index][$photo_type] === UPLOAD_ERR_OK) {
            $file_name = $_FILES['vehicles']['name'][$index][$photo_type];
            $file_tmp = $_FILES['vehicles']['tmp_name'][$index][$photo_type];
            $file_size = $_FILES['vehicles']['size'][$index][$photo_type];
            $file_type = $_FILES['vehicles']['type'][$index][$photo_type];

            // Validate file
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
            $max_size = 20 * 1024 * 1024; // 20MB
            if (!in_array($file_type, $allowed_types)) {
                $errors[] = "Invalid file type for $photo_type photo of vehicle " . ($index + 1) . ". Only JPEG, PNG, or GIF allowed.";
                error_log("Invalid file type: $file_type for $photo_type, vehicle " . ($index + 1));
                continue;
            }
            if ($file_size > $max_size) {
                $errors[] = "File size too large for $photo_type photo of vehicle " . ($index + 1) . ". Maximum size is 20MB.";
                error_log("File size too large: $file_size for $photo_type, vehicle " . ($index + 1));
                continue;
            }

            // Generate unique file name
            $ext = pathinfo($file_name, PATHINFO_EXTENSION);
            $new_file_name = "inspection_{$quote_id}_{$vehicle_id}_{$photo_type}_" . time() . ".$ext";

            // Upload to OneDrive
            $onedrive_url = uploadToOneDrive($file_tmp, $new_file_name, $access_token);
            if (!$onedrive_url) {
                $errors[] = "Failed to upload $photo_type photo for vehicle " . ($index + 1) . " to OneDrive.";
                error_log("OneDrive upload failed for $photo_type, vehicle_id: $vehicle_id");
                continue;
            }

            // Store in database
            $stmt = $conn->prepare("INSERT INTO inspection_photos (quote_id, vehicle_id, client_id, photo_type, file_path) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("iisss", $quote_id, $vehicle_id, $client_id, $photo_type, $onedrive_url);
            if (!$stmt->execute()) {
                $errors[] = "Failed to save $photo_type photo metadata for vehicle " . ($index + 1) . ".";
                error_log("Database insert failed for photo: $photo_type, vehicle_id: $vehicle_id, error: " . $stmt->error);
                continue;
            }
            $stmt->close();
            $successful_uploads++;
        } elseif ($_FILES['vehicles']['error'][$index][$photo_type] !== UPLOAD_ERR_NO_FILE) {
            $errors[] = "Error uploading $photo_type photo for vehicle " . ($index + 1) . ": Error code " . $_FILES['vehicles']['error'][$index][$photo_type];
            error_log("Upload error for $photo_type, vehicle " . ($index + 1) . ": Code " . $_FILES['vehicles']['error'][$index][$photo_type]);
        }
    }
}

// Check if at least one photo was uploaded successfully
if ($successful_uploads === 0) {
    $errors[] = "No photos were uploaded successfully.";
    error_log("No photos uploaded for quote_id: $quote_id");
}

if (!empty($errors)) {
    $_SESSION['errors'] = $errors;
    error_log("Photo upload errors for quote_id: $quote_id: " . implode(", ", $errors));
    header("Location: inspection_photos.php?quote_id=$quote_id");
    exit();
}

// Placeholder for admin notification
error_log("Inspection photos uploaded to OneDrive for quote_id: $quote_id, client_id: $client_id, uploads: $successful_uploads");
$_SESSION['success'] = "Inspection photos uploaded successfully!";
header("Location: inspection_photos.php?quote_id=$quote_id");
exit();

$conn->close();
?>