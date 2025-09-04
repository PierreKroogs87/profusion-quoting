<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();
if (!isset($_SESSION['user_id']) && !(isset($_SESSION['client_id']) && $_SESSION['user_type'] === 'client')) {
    $_SESSION['errors'] = ["You must be logged in to access this page."];
    error_log("Unauthorized access attempt in inspection_photos.php");
    header("Location: index.html");
    exit();
}
require '../db_connect.php';

// Initialize $vehicles to avoid undefined variable
$vehicles = [];

// Check for quote_id
$quote_id = $_GET['quote_id'] ?? null;
if (!$quote_id || !is_numeric($quote_id)) {
    $_SESSION['errors'] = ["Invalid or missing quote ID."];
    error_log("Invalid quote_id: " . ($quote_id ?? 'null') . " in inspection_photos.php");
    $redirect = (isset($_SESSION['client_id']) && $_SESSION['user_type'] === 'client') ? "client_details.php" : "index.html";
    header("Location: $redirect");
    exit();
}

// Verify quote ownership
if (isset($_SESSION['client_id']) && $_SESSION['user_type'] === 'client') {
    $stmt = $conn->prepare("
        SELECT q.*, b.brokerage_name 
        FROM quotes q 
        LEFT JOIN brokerages b ON q.brokerage_id = b.brokerage_id 
        WHERE q.quote_id = ? AND q.client_id = ?
    ");
    $stmt->bind_param("is", $quote_id, $_SESSION['client_id']);
} else {
    $stmt = $conn->prepare("
        SELECT q.*, b.brokerage_name 
        FROM quotes q 
        LEFT JOIN brokerages b ON q.brokerage_id = b.brokerage_id 
        WHERE q.quote_id = ? AND q.user_id = ?
    ");
    $stmt->bind_param("ii", $quote_id, $_SESSION['user_id']);
}
if (!$stmt->execute()) {
    $_SESSION['errors'] = ["Failed to verify quote ownership."];
    error_log("Quote query failed for quote_id: $quote_id, error: " . $stmt->error);
    $redirect = (isset($_SESSION['client_id']) && $_SESSION['user_type'] === 'client') ? "client_details.php" : "index.html";
    header("Location: $redirect");
    exit();
}
$quote_result = $stmt->get_result();
if ($quote_result->num_rows === 0) {
    $_SESSION['errors'] = ["No quote found for this quote ID or you lack permission."];
    error_log("No quote found for quote_id: $quote_id");
    $redirect = (isset($_SESSION['client_id']) && $_SESSION['user_type'] === 'client') ? "client_details.php" : "index.html";
    header("Location: $redirect");
    exit();
}
$quote_data = $quote_result->fetch_assoc();
$stmt->close();

// Fetch vehicles for the quote
$stmt = $conn->prepare("SELECT vehicle_id, vehicle_year, vehicle_make, vehicle_model FROM quote_vehicles WHERE quote_id = ? AND deleted_at IS NULL");
$stmt->bind_param("i", $quote_id);
if (!$stmt->execute()) {
    $_SESSION['errors'] = ["Failed to fetch vehicles for this quote."];
    error_log("Vehicle query failed for quote_id: $quote_id, error: " . $stmt->error);
    $redirect = (isset($_SESSION['client_id']) && $_SESSION['user_type'] === 'client') ? "client_details.php" : "index.html";
    header("Location: $redirect");
    exit();
}
$vehicle_result = $stmt->get_result();
while ($vehicle = $vehicle_result->fetch_assoc()) {
    $vehicles[] = $vehicle;
}
$stmt->close();

if (empty($vehicles)) {
    $_SESSION['errors'] = ["No vehicles found for this quote."];
    error_log("No vehicles found for quote_id: $quote_id");
    $redirect = (isset($_SESSION['client_id']) && $_SESSION['user_type'] === 'client') ? "client_details.php" : "index.html";
    header("Location: $redirect");
    exit();
}

mysqli_close($conn);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Complete Inspection - Insurance App</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --purple: #6A0DAD;
            --white: #fff;
            --font-scale: 1;
            --base-font: 14px;
            --base-padding: calc(0.375rem * var(--font-scale));
        }
        body {
            font-size: var(--base-font);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        main {
            flex: 1 0 auto;
        }
        footer {
            background-color: var(--white);
            color: var(--purple);
            font-size: var(--base-font);
            padding: calc(1rem * var(--font-scale)) 0;
            text-align: center;
            width: 100%;
            flex-shrink: 0;
        }
        header {
            padding: calc(1rem * var(--font-scale)) 0;
            text-align: left;
        }
        header img {
            height: calc(110px * var(--font-scale));
        }
        .navbar {
            background-color: var(--white);
            padding: calc(0.5rem * var(--font-scale)) 1rem;
            justify-content: flex-start;
        }
        .navbtn-purple {
            background-color: var(--purple);
            border-color: var(--purple);
            color: var(--white);
            font-size: 14px;
            padding: calc(0.5rem * var(--font-scale));
            text-decoration: none;
        }
        .navbtn-purple:hover {
            background-color: #4B0082;
            border-color: #4B0082;
            color: var(--white);
        }
        .btn-purple {
            background-color: var(--purple);
            border-color: var(--purple);
            color: var(--white);
            font-size: var(--base-font);
            padding: var(--base-padding) calc(0.75rem * var(--font-scale));
        }
        .btn-purple:hover {
            background-color: #4B0082;
            border-color: #4B0082;
            color: var(--white);
        }
        .photo-preview {
            max-width: 100%;
            height: auto;
            margin-top: 10px;
        }
        .vehicle-section {
            border: 1px solid #ccc;
            padding: 15px;
            margin-bottom: 15px;
        }
        .section-heading {
            font-size: 16px;
            font-weight: bold;
            color: var(--purple);
            margin-bottom: 10px;
        }
        .guidance-text {
            font-size: var(--base-font);
            color: #333;
            margin-bottom: 10px;
        }
        /* Adjust popover image size for mobile */
        @media (max-width: 576px) {
            .popover-body img {
                max-width: 150px !important; /* Smaller size for mobile */
                height: auto;
            }
            .popover {
                max-width: 200px; /* Limit popover width on mobile */
            }
        }
    </style>
</head>
<body>
    <main>
        <div class="container mt-3">
            <header>
                <?php
                $logo_href = (isset($_SESSION['client_id']) && $_SESSION['user_type'] === 'client') ? "client_details.php" : "index.html";
                ?>
                <a href="<?php echo htmlspecialchars($logo_href); ?>"><img src="./images/logo.png" alt="Profusion Insurance Logo"></a>
            </header>
            <nav class="navbar navbar-expand-lg">
                <div class="container-fluid">
                    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                        <span class="navbar-toggler-icon"></span>
                    </button>
                    <div class="collapse navbar-collapse" id="navbarNav">
                        <ul class="navbar-nav">
                            <?php
                            $nav_href = (isset($_SESSION['client_id']) && $_SESSION['user_type'] === 'client') ? "client_details.php" : "index.html";
                            ?>
                            <li class="nav-item"><a class="nav-link btn navbtn-purple" href="<?php echo htmlspecialchars($nav_href); ?>">Dashboard</a></li>
                            <li class="nav-item"><a class="nav-link btn navbtn-purple" href="../login_management/logout.php">Logout</a></li>
                        </ul>
                    </div>
                </div>
            </nav>

            <div class="container mt-4">
                <h2 class="mb-4">Complete Inspection (Quote ID: <?php echo htmlspecialchars($quote_id); ?>)</h2>
                <?php if (isset($_SESSION['errors'])) { ?>
                    <div class="alert alert-danger">
                        <?php foreach ($_SESSION['errors'] as $error) { ?>
                            <p><?php echo htmlspecialchars($error); ?></p>
                        <?php } ?>
                        <?php unset($_SESSION['errors']); ?>
                    </div>
                <?php } ?>
                <?php if (isset($_SESSION['success'])) { ?>
                    <div class="alert alert-success">
                        <?php echo htmlspecialchars($_SESSION['success']); ?>
                    </div>
                    <?php unset($_SESSION['success']); ?>
                <?php } ?>

                <div class="row">
                    <div class="col-md-8">
                        <p class="guidance-text">Please take clear photos of each vehicle as instructed below. Ensure good lighting and capture all required angles for the inspection certificate.</p>
                        <?php if (!empty($vehicles)) { ?>
                        <form id="inspectionForm" method="post" enctype="multipart/form-data" action="save_inspection_photos.php">
                            <input type="hidden" name="quote_id" value="<?php echo htmlspecialchars($quote_id); ?>">
                            <?php foreach ($vehicles as $index => $vehicle) { ?>
                            <div class="vehicle-section">
                                <div class="section-heading">Vehicle <?php echo $index + 1; ?>: <?php echo htmlspecialchars("{$vehicle['vehicle_year']} {$vehicle['vehicle_make']} {$vehicle['vehicle_model']}"); ?></div>
                                <input type="hidden" name="vehicles[<?php echo $index; ?>][vehicle_id]" value="<?php echo htmlspecialchars($vehicle['vehicle_id']); ?>">
                                <div class="mb-3">
                                    <label class="form-label">Left Side
                                        <span class="bi bi-question-circle text-primary ms-1" 
                                              data-bs-toggle="popover" 
                                              data-bs-trigger="focus" 
                                              data-bs-html="true" 
                                              data-bs-content="Test Popover"
                                              title="Left Side Example"></span>
                                    </label>
                                    <p class="guidance-text">Take a photo of the left side of the vehicle, showing the entire vehicle, including the wheels.</p>
                                    <button type="button" class="btn btn-purple capture-photo" data-vehicle-index="<?php echo $index; ?>" data-photo-type="left_side">Capture Photo</button>
                                    <input type="file" name="vehicles[<?php echo $index; ?>][left_side]" accept="image/*" capture="environment" style="display: none;" class="photo-input">
                                    <img class="photo-preview" id="preview-left_side-<?php echo $index; ?>" style="display: none;">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Right Side
                                        <span class="bi bi-question-circle text-primary ms-1" 
                                              data-bs-toggle="popover" 
                                              data-bs-trigger="focus" 
                                              data-bs-html="true" 
                                              data-bs-content="<img src='./images/examples/right_side_example.jpg' alt='Right Side Example' style='max-width: 200px; height: auto;' onerror='console.error(\"Failed to load Right Side image\")'>"
                                              title="Right Side Example"></span>
                                    </label>
                                    <p class="guidance-text">Take a photo of the right side of the vehicle, showing the entire vehicle, including the wheels.</p>
                                    <button type="button" class="btn btn-purple capture-photo" data-vehicle-index="<?php echo $index; ?>" data-photo-type="right_side">Capture Photo</button>
                                    <input type="file" name="vehicles[<?php echo $index; ?>][right_side]" accept="image/*" capture="environment" style="display: none;" class="photo-input">
                                    <img class="photo-preview" id="preview-right_side-<?php echo $index; ?>" style="display: none;">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Front
                                        <span class="bi bi-question-circle text-primary ms-1" 
                                              data-bs-toggle="popover" 
                                              data-bs-trigger="focus" 
                                              data-bs-html="true" 
                                              data-bs-content="<img src='./images/examples/front_example.jpg' alt='Front Example' style='max-width: 200px; height: auto;' onerror='console.error(\"Failed to load Front image\")'>"
                                              title="Front Example"></span>
                                    </label>
                                    <p class="guidance-text">Take a photo of the front of the vehicle, showing the entire vehicle, including the wheels and roof.</p>
                                    <button type="button" class="btn btn-purple capture-photo" data-vehicle-index="<?php echo $index; ?>" data-photo-type="front">Capture Photo</button>
                                    <input type="file" name="vehicles[<?php echo $index; ?>][front]" accept="image/*" capture="environment" style="display: none;" class="photo-input">
                                    <img class="photo-preview" id="preview-front-<?php echo $index; ?>" style="display: none;">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Back
                                        <span class="bi bi-question-circle text-primary ms-1" 
                                              data-bs-toggle="popover" 
                                              data-bs-trigger="focus" 
                                              data-bs-html="true" 
                                              data-bs-content="<img src='./images/examples/back_example.jpg' alt='Back Example' style='max-width: 200px; height: auto;' onerror='console.error(\"Failed to load Back image\")'>"
                                              title="Back Example"></span>
                                    </label>
                                    <p class="guidance-text">Take a photo of the back of the vehicle, showing the entire vehicle, including the wheels and roof.</p>
                                    <button type="button" class="btn btn-purple capture-photo" data-vehicle-index="<?php echo $index; ?>" data-photo-type="back">Capture Photo</button>
                                    <input type="file" name="vehicles[<?php echo $index; ?>][back]" accept="image/*" capture="environment" style="display: none;" class="photo-input">
                                    <img class="photo-preview" id="preview-back-<?php echo $index; ?>" style="display: none;">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Front with Bonnet Open
                                        <span class="bi bi-question-circle text-primary ms-1" 
                                              data-bs-toggle="popover" 
                                              data-bs-trigger="focus" 
                                              data-bs-html="true" 
                                              data-bs-content="<img src='./images/examples/bonnet_open_example.jpg' alt='Bonnet Open Example' style='max-width: 200px; height: auto;' onerror='console.error(\"Failed to load Bonnet Open image\")'>"
                                              title="Bonnet Open Example"></span>
                                    </label>
                                    <p class="guidance-text">Take a photo of the front of the vehicle with the bonnet open, showing the engine and number plate.</p>
                                    <button type="button" class="btn btn-purple capture-photo" data-vehicle-index="<?php echo $index; ?>" data-photo-type="bonnet_open">Capture Photo</button>
                                    <input type="file" name="vehicles[<?php echo $index; ?>][bonnet_open]" accept="image/*" capture="environment" style="display: none;" class="photo-input">
                                    <img class="photo-preview" id="preview-bonnet_open-<?php echo $index; ?>" style="display: none;">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">License Disc
                                        <span class="bi bi-question-circle text-primary ms-1" 
                                              data-bs-toggle="popover" 
                                              data-bs-trigger="focus" 
                                              data-bs-html="true" 
                                              data-bs-content="<img src='./images/examples/license_disc_example.jpg' alt='License Disc Example' style='max-width: 200px; height: auto;' onerror='console.error(\"Failed to load License Disc image\")'>"
                                              title="License Disc Example"></span>
                                    </label>
                                    <p class="guidance-text">Take a photo of the license disc currently on the vehicle. Send a new photo when the new disc is fitted.</p>
                                    <button type="button" class="btn btn-purple capture-photo" data-vehicle-index="<?php echo $index; ?>" data-photo-type="license_disc">Capture Photo</button>
                                    <input type="file" name="vehicles[<?php echo $index; ?>][license_disc]" accept="image/*" capture="environment" style="display: none;" class="photo-input">
                                    <img class="photo-preview" id="preview-license_disc-<?php echo $index; ?>" style="display: none;">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Odometer
                                        <span class="bi bi-question-circle text-primary ms-1" 
                                              data-bs-toggle="popover" 
                                              data-bs-trigger="focus" 
                                              data-bs-html="true" 
                                              data-bs-content="<img src='./images/examples/odometer_example.jpg' alt='Odometer Example' style='max-width: 200px; height: auto;' onerror='console.error(\"Failed to load Odometer image\")'>"
                                              title="Odometer Example"></span>
                                    </label>
                                    <p class="guidance-text">Take a photo of the odometer with the vehicle turned on and dashlights illuminated.</p>
                                    <button type="button" class="btn btn-purple capture-photo" data-vehicle-index="<?php echo $index; ?>" data-photo-type="odometer">Capture Photo</button>
                                    <input type="file" name="vehicles[<?php echo $index; ?>][odometer]" accept="image/*" capture="environment" style="display: none;" class="photo-input">
                                    <img class="photo-preview" id="preview-odometer-<?php echo $index; ?>" style="display: none;">
                                </div>
                            </div>
                            <?php } ?>
                            <div class="mb-3">
                                <button type="submit" class="btn btn-purple">Save Photos</button>
                                <?php
                                $back_href = (isset($_SESSION['client_id']) && $_SESSION['user_type'] === 'client') ? "client_details.php" : "index.html";
                                ?>
                                <a href="<?php echo htmlspecialchars($back_href); ?>" class="btn btn-link ms-3">Back to Dashboard</a>
                            </div>
                        </form>
                        <?php } else { ?>
                            <div class="alert alert-danger">No vehicles available to display.</div>
                        <?php } ?>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <footer class="text-center py-3 mt-4">
        <p>© 2025 Profusion Insurance</p>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Initialize Bootstrap Popovers with error logging
        try {
            document.querySelectorAll('[data-bs-toggle="popover"]').forEach(element => {
                console.log('Initializing popover for:', element);
                new bootstrap.Popover(element, {
                    trigger: 'focus', // Show on click, close on outside click
                    html: true, // Allow HTML content (for images)
                    placement: 'auto', // Automatically position popover
                    boundary: 'viewport' // Keep popover within viewport
                });
            });
        } catch (error) {
            console.error('Error initializing popovers:', error);
        }

        document.querySelectorAll('.capture-photo').forEach(button => {
            button.addEventListener('click', function() {
                console.log('Capture Photo button clicked:', { button: this });
                try {
                    const vehicleIndex = this.getAttribute('data-vehicle-index');
                    const photoType = this.getAttribute('data-photo-type');
                    console.log('Vehicle Index:', vehicleIndex, 'Photo Type:', photoType);
                    const fileInput = document.querySelector(`input[name="vehicles[${vehicleIndex}][${photoType}]"]`);
                    
                    if (!fileInput) {
                        console.error('File input not found for selector:', `input[name="vehicles[${vehicleIndex}][${photoType}]"]`);
                        alert('Error: Photo input field not found. Please try again or contact support.');
                        return;
                    }
                    
                    if (navigator.mediaDevices && navigator.mediaDevices.getUserMedia) {
                        console.log('Requesting camera access...');
                        navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } })
                            .then(() => {
                                console.log('Camera access granted, triggering file input');
                                fileInput.click();
                            })
                            .catch(err => {
                                console.error('Camera access error:', err);
                                let message = 'Camera access was denied. Please allow camera access in your browser settings or select a photo manually.';
                                if (err.name === 'NotAllowedError') {
                                    message = 'Camera permission was denied. Please go to your browser or device settings to allow camera access, then try again. Alternatively, select a photo manually.';
                                } else if (err.name === 'NotFoundError') {
                                    message = 'No camera found on your device. Please select a photo manually.';
                                }
                                alert(message);
                                fileInput.click();
                            });
                    } else {
                        console.warn('MediaDevices API not supported');
                        alert('Your browser does not support camera access. Please select a photo manually.');
                        fileInput.click();
                    }
                } catch (error) {
                    console.error('Error in capture-photo event listener:', error);
                    alert('An error occurred while trying to capture the photo. Please try again or contact support.');
                }
            });
        });

        document.querySelectorAll('.photo-input').forEach(input => {
            input.addEventListener('change', function() {
                const vehicleIndex = this.name.match(/vehicles\[(\d+)\]/)[1];
                const photoType = this.name.match(/\[(\w+)\]$/)[1];
                const preview = document.getElementById(`preview-${photoType}-${vehicleIndex}`);
                if (this.files && this.files[0]) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        preview.src = e.target.result;
                        preview.style.display = 'block';
                    };
                    reader.readAsDataURL(this.files[0]);
                } else {
                    preview.style.display = 'none';
                    preview.src = '';
                }
            });
        });

        document.getElementById('inspectionForm').addEventListener('submit', function(e) {
            if (!confirm('Are you sure you want to save these photos?')) {
                e.preventDefault();
            }
        });

        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('sw.js')
                    .then(reg => console.log('Service Worker registered'))
                    .catch(err => console.error('Service Worker registration failed:', err));
            });
        }
    </script>
</body>
</html>