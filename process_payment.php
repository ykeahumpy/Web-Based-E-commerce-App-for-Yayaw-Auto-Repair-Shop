<?php
session_start();

// Redirect to index.php if user session is not set
if (!isset($_SESSION['user-session'])) {
    header("Location: index.php");
    exit;
}

// Database configuration
$dbhost = 'localhost';
$dbusername = 'root';
$dbpassword = '';
$dbname = 'yayawautorepairshop';
$connection = mysqli_connect($dbhost, $dbusername, $dbpassword, $dbname);

// Check connection
if (mysqli_connect_error()) {
    die('Failed to connect to database');
}

// Check if required POST data is available
if (isset($_POST['total_amount'], $_POST['total_quantity'], $_POST['contact_number'], $_POST['address'])) {
    $total_amount = floatval($_POST['total_amount']);
    $total_quantity = intval($_POST['total_quantity']);
    $user_id = intval($_SESSION['user-session']);
    $contact_number = mysqli_real_escape_string($connection, $_POST['contact_number']);
    $address = mysqli_real_escape_string($connection, $_POST['address']);
    
    // Create transaction
    $payment_method = "Cash on Delivery";
    date_default_timezone_set('Asia/Manila');
    $transaction_date = date('Y-m-d H:i:s');

    // Begin transaction
    mysqli_begin_transaction($connection);

    try {
        // Insert transaction into cart
        $insert_query = "
            INSERT INTO cart (users_id, address, contact_number, payment_method, total_amount, total_quantity, transaction_date, flag_checkout, flag_shipped)
            VALUES (?, ?, ?, ?, ?, ?, ?, 1, 0)
        ";
        $stmt = mysqli_prepare($connection, $insert_query);
        mysqli_stmt_bind_param($stmt, 'isssdis', $user_id, $address, $contact_number, $payment_method, $total_amount, $total_quantity, $transaction_date);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        // Get the last inserted cart ID
        $cart_id = mysqli_insert_id($connection);

        // Copy cart items to cart_sales
        $insert_cart_sales_query = "
            INSERT INTO cart_sales (cart_id, item_description, quantity, price, total_price, transaction_date, user_id)
            SELECT ?, item_description, quantity, price, (quantity * price) AS total_price, ?, users_id
            FROM cart_items
            WHERE cart_id = (
                SELECT id
                FROM cart
                WHERE users_id = ? AND flag_checkout = 0
            )
        ";
        $stmt = mysqli_prepare($connection, $insert_cart_sales_query);
        mysqli_stmt_bind_param($stmt, 'isi', $cart_id, $transaction_date, $user_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        // Delete previous cart items
        $delete_cart_query = "
            DELETE FROM cart_items
            WHERE cart_id = (
                SELECT id
                FROM cart
                WHERE users_id = ? AND flag_checkout = 0
            )
        ";
        $stmt = mysqli_prepare($connection, $delete_cart_query);
        mysqli_stmt_bind_param($stmt, 'i', $user_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        // Delete previous cart
        $delete_cart_query = "
            DELETE FROM cart
            WHERE users_id = ? AND flag_checkout = 0
        ";
        $stmt = mysqli_prepare($connection, $delete_cart_query);
        mysqli_stmt_bind_param($stmt, 'i', $user_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        // Commit transaction
        mysqli_commit($connection);

        // Redirect to thank you page
        header("Location: thankyou.php");
        exit;
    } catch (Exception $e) {
        // Rollback transaction in case of an error
        mysqli_rollback($connection);
        die('Failed to process payment: ' . $e->getMessage());
    } finally {
        // Close connection
        mysqli_close($connection);
    }
} else {
    // Redirect back to cart if required data is missing
    header("Location: cart.php");
    exit;
}
?>