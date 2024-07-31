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

// Get user ID from session
$user_id = intval($_SESSION['user-session']);

// Fetch the latest transaction details
$query = "
    SELECT * 
    FROM cart
    WHERE users_id = ? AND flag_checkout = 1
    ORDER BY transaction_date DESC 
    LIMIT 1
";
$stmt = mysqli_prepare($connection, $query);
mysqli_stmt_bind_param($stmt, 'i', $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

// Initialize variables to store transaction details and cart items
$transaction = null;
$cart_sales_result = null;

if ($result && mysqli_num_rows($result) > 0) {
    $transaction = mysqli_fetch_assoc($result);

    // Fetch cart items related to the transaction
    $fetch_cart_sales_query = "
        SELECT item_description, quantity, price, total_price
        FROM cart_sales
        WHERE cart_id = ?
    ";
    $stmt_cart_sales = mysqli_prepare($connection, $fetch_cart_sales_query);
    mysqli_stmt_bind_param($stmt_cart_sales, 'i', $transaction['id']);
    mysqli_stmt_execute($stmt_cart_sales);
    $cart_sales_result = mysqli_stmt_get_result($stmt_cart_sales);

    // Close the prepared statement for fetching cart items
    mysqli_stmt_close($stmt_cart_sales);
} else {
    echo "No recent transactions found.";
}

// Close the prepared statement for fetching transaction details
mysqli_stmt_close($stmt);

// Close connection
mysqli_close($connection);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thank You for Your Purchase!</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f0f0f0;
            text-align: center;
        }
        .container {
            max-width: 600px;
            margin: 50px auto;
            background-color: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }
        h1 {
            color: #007bff;
        }
        p {
            font-size: 18px;
            line-height: 1.6;
        }
        .btn {
            display: inline-block;
            padding: 10px 20px;
            background-color: #007bff;
            color: #fff;
            text-decoration: none;
            border-radius: 4px;
            transition: background-color 0.3s ease;
            margin-top: 20px;
        }
        .btn:hover {
            background-color: #0056b3;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        table th, table td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        table th {
            background-color: #f2f2f2;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Thank You for Your Purchase!</h1>
        <?php if ($transaction) : ?>
            <p>Your payment has been successfully processed.</p>
            <p>Transaction Date: <?php echo htmlspecialchars($transaction['transaction_date']); ?></p>
            <p>Amount Paid: ₱<?php echo number_format($transaction['total_amount'], 2); ?></p>
            <p>Quantity: <?php echo htmlspecialchars($transaction['total_quantity']); ?></p>
            <p>Address: <?php echo htmlspecialchars($transaction['address']); ?></p>
            <p>Contact Number: <?php echo htmlspecialchars($transaction['contact_number']); ?></p>
            <p>Status: <?php echo $transaction['flag_checkout'] ? 'Checked Out' : 'Not Checked Out'; ?></p>
            <p>Payment Method: <?php echo htmlspecialchars($transaction['payment_method']); ?></p>
        <?php endif; ?>

        <!-- Cart Sales Items -->
        <h2>Cart Items</h2>
        <table>
            <thead>
                <tr>
                    <th>Item Description</th>
                    <th>Quantity</th>
                    <th>Price</th>
                    <th>Total Price</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($cart_sales_result) : ?>
                    <?php while ($item = mysqli_fetch_assoc($cart_sales_result)) : ?>
                        <tr>
                            <td><?php echo htmlspecialchars($item['item_description']); ?></td>
                            <td><?php echo htmlspecialchars($item['quantity']); ?></td>
                            <td>₱<?php echo number_format($item['price'], 2); ?></td>
                            <td>₱<?php echo number_format($item['total_price'], 2); ?></td>
                        </tr>
                    <?php endwhile; ?>
                <?php else : ?>
                    <tr>
                        <td colspan="4">No items found in cart.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <!-- Link to continue shopping -->
        <a href="home.php" class="btn">Continue Shopping</a>
    </div>
</body>
</html>
