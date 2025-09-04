<?php
session_start();
if (!isset($_SESSION['client_id']) || $_SESSION['user_type'] !== 'client') {
    header("Location: index.html");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Client Details - Insurance App</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="./styles.css" rel="stylesheet">
</head>
<body>
    <main>
        <div class="container mt-3">
            <header>
                <a href="client_details.php"><img src="./images/logo.png" alt="Profusion Insurance Logo"></a>
            </header>
            <nav class="navbar navbar-expand-lg">
                <div class="container-fluid">
                    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                        <span class="navbar-toggler-icon"></span>
                    </button>
                    <div class="collapse navbar-collapse" id="navbarNav">
                        <ul class="navbar-nav">
                            <li class="nav-item"><a class="nav-link btn navbtn-purple" href="client_details.php">Dashboard</a></li>
                            <li class="nav-item"><a class="nav-link btn navbtn-purple" href="../login_management/logout.php">Logout</a></li>
                        </ul>
                    </div>
                </div>
            </nav>

            <div class="container mt-4">
                <h2 class="mb-4">Client Details</h2>
                <div id="errorMessage" class="alert alert-danger d-none"></div>

                <div class="row">
                    <div class="col-md-8">
                        <div class="card mb-3">
                            <div class="card-header">Personal Information</div>
                            <div class="card-body">
                                <p><strong>Name:</strong> <span id="client_name">Loading...</span></p>
                                <p><strong>Client ID:</strong> <span id="client_id">Loading...</span></p>
                                <p><strong>Email:</strong> <span id="client_email">Loading...</span></p>
                                <p><strong>Cell Number:</strong> <span id="cell_number">Loading...</span></p>
                                <p><strong>SMS Consent:</strong> <span id="sms_consent">Loading...</span></p>
                                <p><strong>Physical Address:</strong> <span id="physical_address">Loading...</span></p>
                                <p><strong>Postal Address:</strong> <span id="postal_address">Loading...</span></p>
                            </div>
                        </div>

                        <div class="card mb-3">
                            <div class="card-header">Insured Vehicles</div>
                            <div class="card-body" id="vehicles_container">
                                <p>Loading...</p>
                            </div>
                        </div>

                        <div class="card mb-3">
                            <div class="card-header">Debit Order Details</div>
                            <div class="card-body">
                                <p><strong>Debit Order Date:</strong> <span id="debit_date">Loading...</span></p>
                                <p><strong>Debit Order Premium:</strong> <span id="debit_premium">Loading...</span></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <footer class="text-center py-3 mt-4">
        <p>© 2025 Profusion Insurance</p>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="app.js"></script>
</body>
</html>