<?php
require "config.php";

// Start session if needed
if (session_status() === PHP_SESSION_NONE) session_start();

// Update cart if coming from results page
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $_SESSION['pending_cart'] = [
        'style' => $_POST['selected_style'] ?? 'bw',
        'size' => intval($_POST['selected_size'] ?? 48),
        'price' => floatval($_POST['selected_price'] ?? 35)
    ];
}

// Security check for image and cart existence
if (!isset($_SESSION['pending_cart']) || !isset($_SESSION['temp_image_data'])) {
    // Redirect to upload if data is missing
    header("Location: upload.php");
    exit;
}

// Redirects to checkout which now handles registration, login, address, payment, and final image generation
header("Location: checkout.php");
exit;
?>