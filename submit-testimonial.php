<?php
session_start();
require_once 'includes/connection.php';
require_once 'includes/public_site.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!rdv_csrf_verify() || !rdv_rate_limit('testimonial', 4, 600)) {
        $_SESSION['testimonial_error'] = 'Please refresh the page and try again.';
        header('Location: ./#testimonials');
        exit;
    }
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $rating = (int)$_POST['rating'];
    $review = trim($_POST['review']);
    $user_id = $_SESSION['user_id'] ?? null;
    
    $errors = [];
    if (empty($name)) $errors[] = "Name is required.";
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Valid email is required.";
    if ($rating < 1 || $rating > 5) $rating = 5;
    if (empty($review)) $errors[] = "Review text is required.";
    
    if (empty($errors)) {
        $stmt = $conn->prepare("INSERT INTO testimonials (user_id, name, email, rating, review, status) VALUES (?, ?, ?, ?, ?, 'pending')");
        $stmt->bind_param("issis", $user_id, $name, $email, $rating, $review);
        if ($stmt->execute()) {
            $_SESSION['testimonial_message'] = "Thank you! Your review has been submitted and is pending approval.";
        } else {
            $_SESSION['testimonial_error'] = "Submission failed: " . $conn->error;
        }
        $stmt->close();
    } else {
        $_SESSION['testimonial_error'] = implode(" ", $errors);
    }
    header("Location: ./#testimonials");
    exit();
}
?>