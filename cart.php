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

// Handle Add to Cart Logic
if (isset($_POST['add_to_cart'])) {
    $product_code = mysqli_real_escape_string($connection, $_POST['product_code']);
    $product_name = mysqli_real_escape_string($connection, $_POST['product_name']);
    $product_price = (float)$_POST['product_price'];
    $user_id = (int)$_SESSION['user-session'];

    // Check if a cart exists for the user
    $cart_query = mysqli_query($connection, "SELECT * FROM cart WHERE users_id = $user_id AND flag_checkout = 0");
    if (mysqli_num_rows($cart_query) === 0) {
        // Create a new cart
        mysqli_query($connection, "INSERT INTO cart (users_id, address, contact_number, payment_method, total_amount, total_quantity, transaction_date, flag_checkout, flag_shipped) VALUES ($user_id, '', '', '', 0, 0, NULL, 0, 0)");
        $cart_id = mysqli_insert_id($connection);
    } else {
        $cart = mysqli_fetch_assoc($cart_query);
        $cart_id = $cart['id'];
    }

    // Check if item already exists in cart_items for this cart
    $existing_cart_item_query = mysqli_query($connection, "SELECT * FROM cart_items WHERE cart_id = $cart_id AND item_code = '$product_code'");
    if (mysqli_num_rows($existing_cart_item_query) > 0) {
        // If item already exists, update quantity
        $existing_cart_item = mysqli_fetch_assoc($existing_cart_item_query);
        $new_quantity = $existing_cart_item['quantity'] + 1;
        mysqli_query($connection, "UPDATE cart_items SET quantity = $new_quantity WHERE item_code = '$product_code' AND cart_id = $cart_id");
    } else {
        // If item does not exist, insert new item
        mysqli_query($connection, "INSERT INTO cart_items (users_id, cart_id, item_code, item_description, price, quantity) VALUES ($user_id, $cart_id, '$product_code', '$product_name', $product_price, 1)");
    }

    // Capture the current scroll position
    $scroll_position = $_POST['scroll_position'] ?? 0;

    // Redirect to the same page with scroll position
    header("Location: " . $_SERVER['PHP_SELF'] . "?scroll_position=" . $scroll_position);
    exit;
}

// Remove from cart logic
if (isset($_POST['remove_from_cart'])) {
    $product_name = mysqli_real_escape_string($connection, $_POST['product_name']);
    $user_id = (int)$_SESSION['user-session'];

    // Get the cart id for this user
    $cart_query = mysqli_query($connection, "SELECT id FROM cart WHERE users_id = $user_id AND flag_checkout = 0");
    $cart = mysqli_fetch_assoc($cart_query);
    $cart_id = $cart['id'];

    // Remove item from cart_items
    mysqli_query($connection, "DELETE FROM cart_items WHERE item_description = '$product_name' AND cart_id = $cart_id LIMIT 1");

    // Redirect to prevent form resubmission on page refresh
    header("Location: cart.php");
    exit;
}

// Clear cart logic
if (isset($_POST['clear_cart'])) {
    $user_id = (int)$_SESSION['user-session'];

    // Get the cart id for this user
    $cart_query = mysqli_query($connection, "SELECT id FROM cart WHERE users_id = $user_id AND flag_checkout = 0");
    $cart = mysqli_fetch_assoc($cart_query);
    $cart_id = $cart['id'];

    // Remove all items from cart_items
    mysqli_query($connection, "DELETE FROM cart_items WHERE cart_id = $cart_id");

    // Redirect to prevent form resubmission on page refresh
    header("Location: cart.php");
    exit;
}

// Confirm Payment Logic
if (isset($_POST['confirm_payment'])) {
    // Redirect to payment processing
    header("Location: process_payment.php");
    exit;
}

// Fetch products from the database
$result = mysqli_query($connection, "SELECT * FROM items");

// Fetch items in cart
$user_id = (int)$_SESSION['user-session'];
$cart_query = mysqli_query($connection, "SELECT id FROM cart WHERE users_id = $user_id AND flag_checkout = 0");
$cart = mysqli_fetch_assoc($cart_query);
$cart_id = $cart['id'] ?? 0;
$cart_result = mysqli_query($connection, "SELECT * FROM cart_items WHERE cart_id = $cart_id");

// Calculate total amount in cart
$total_amount = 0;
$total_quantity = 0;
$db_items = [];
if ($cart_result && mysqli_num_rows($cart_result) > 0) {
    while ($record = mysqli_fetch_assoc($cart_result)) {
        array_push($db_items, $record);
        $item_total = $record['price'] * $record['quantity'];
        $total_amount += $item_total;
        $total_quantity += $record['quantity'];
    }
}

$scroll_position = isset($_GET['scroll_position']) ? intval($_GET['scroll_position']) : 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css" rel="stylesheet">
    <link rel="stylesheet" href="cart.css">
    <title>Shopping Page</title>
    <style>
      .btn {
          display: inline-block;
          padding: 10px 20px;
          background-color: #007bff;
          color: #fff;
          border: none;
          border-radius: 4px;
          cursor: pointer;
          transition: background-color 0.3s ease;
      }

      .btn:hover {
          background-color: #0056b3;
      }

      .btn.clear-cart {
          background-color: #dc3545;
      }

      .btn.clear-cart:hover {
          background-color: #c82333;
      }

      .form-container {
          margin-top: 20px;
          padding: 20px;
          background-color: #f9f9f9;
          border-radius: 8px;
          box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
      }

      .form-container label {
          display: block;
          margin-bottom: 8px;
          font-weight: bold;
      }

      .form-container input[type=text],
      .form-container textarea {
          width: 100%;
          padding: 10px;
          border: 1px solid #ccc;
          border-radius: 5px;
          box-sizing: border-box;
          font-size: 14px;
      }

      .form-container textarea {
          resize: vertical;
          min-height: 100px;
      }

      .form-container hr {
          margin: 20px 0;
          border: none;
          border-top: 1px solid #ccc;
      }

      .payment-method {
          margin-bottom: 20px;
      }

      .product {
          background: rgba(255, 255, 255, 0.8);
          border: 1px solid #ddd;
          border-radius: 8px;
          box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
          padding: 20px;
          text-align: center;
          transition: transform 0.2s;
      }

      .product:hover {
          transform: scale(1.05);
      }

      .product img {
          max-width: 100%;
          height: auto;
          border-radius: 4px;
      }

      .container {
          position: relative;
      }
    </style>
</head>
<body>
    <header class="header">
        <nav>
            <div class="nav__bar">
                <div class="logo nav__logo">
                    <h1 class="page-title">Yayaw Auto Repair Shop Shopping Cart</h1>
                </div>
                <div class="nav-links">
                    <a href="home.php">Home</a>
                    <a href="home.php">Contact Us</a>
                </div>
            </div>
        </nav>
    </header>

    <div class="container">
        <div class="product-list" id="product-list" style="position: relative;">
            <?php
            while ($row = mysqli_fetch_assoc($result)) {
                echo "
                <div class='product'>
                    <img src='{$row['imageurl']}' alt='{$row['description']}' class='products-images'>
                    <h3>{$row['description']}</h3>
                    <p>₱{$row['price']}</p>
                    <form method='POST'>
                        <input type='hidden' name='product_code' value='{$row['code']}'>
                        <input type='hidden' name='product_name' value='{$row['description']}'>
                        <input type='hidden' name='product_price' value='{$row['price']}'>
                        <input type='hidden' name='scroll_position' value='$scroll_position'>
                        <button class='btn add-to-cart' name='add_to_cart'>Add to Cart</button>
                    </form>
                </div>
                ";
            }
            ?>
        </div>

        <div class="cart">
            <h2>Your Cart</h2>
            <ul id="cart-list">
                <?php
                if ($cart_id && $cart_result && mysqli_num_rows($cart_result) > 0) {
                    foreach ($db_items as $value) {
                        $item_total = $value['price'] * $value['quantity'];
                        echo "<li>{$value['item_description']} - Quantity: {$value['quantity']}
                        - Price: ₱" . number_format($value['price'], 2) . " each - Total: ₱" . number_format($item_total, 2) . "</li> <br>";
                    }
                    echo "<li><strong>Total Amount: ₱" . number_format($total_amount, 2) . "</strong></li>";
                    echo "<li><form method='POST'><button class='btn clear-cart' name='clear_cart'>Clear Cart</button></form></li>";
                    echo "<li>
                        <div class='form-container'>
                            <form method='POST' action='process_payment.php'>
                                <label for='contact_number'>Contact Number:</label>
                                <input type='text' id='contact_number' name='contact_number' required>
                                <label for='address'>Delivery Address:</label>
                                <textarea id='address' name='address' required></textarea>
                                <input type='hidden' name='total_amount' value='$total_amount'>
                                <input type='hidden' name='total_quantity' value='$total_quantity'>
                                <button class='btn' name='confirm_payment'>Confirm Payment</button>
                            </form>
                        </div>
                    </li>";
                } else {
                    echo "<li>Your cart is empty.</li>";
                }
                ?>
            </ul>
        </div>
    </div>

    <footer class="footer">
        <div class="section__container footer__container">
            <div class="footer__col">
                <p class="section__description">Owner: Erick Maigting</p>
                <p class="section__description">Business start: 2014</p>
                <ul class="footer__socials">
                    <li><a href="https://www.facebook.com/profile.php?id=100094762587018"><i class="ri-facebook-fill"></i></a></li>
                    <li><a href="https://www.facebook.com/erick.maigting?mibextid=ZbWKwL"><i class="ri-user-fill"></i></a></li>
                    <li><a href="https://www.youtube.com/@yayawautorepairshop"><i class="ri-youtube-fill"></i></a></li>
                </ul>
            </div>
        </div>
    </footer>

    <!-- Script to maintain scroll position -->
    <script>
        window.addEventListener('load', function() {
            const scrollPosition = new URLSearchParams(window.location.search).get('scroll_position');
            if (scrollPosition) {
                window.scrollTo(0, parseInt(scrollPosition));
            }
        });

        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', () => {
                const scrollPosition = window.scrollY;
                form.querySelector('input[name="scroll_position"]').value = scrollPosition;
            });
        });
    </script>
</body>
</html>
