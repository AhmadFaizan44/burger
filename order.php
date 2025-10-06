<?php
// order.php — server-side order processing
// Recalculate totals server-side to prevent tampering.

// 1) PRICE BOOK
$PRICE_BOOK = [
  "burger_classic" => ["name" => "Classic Burger", "price" => 6.99],
  "burger_cheese"  => ["name" => "Cheese Burger",  "price" => 7.99],
  "burger_chicken" => ["name" => "Chicken Burger", "price" => 7.49],
  "fries_small"    => ["name" => "Fries (Small)",  "price" => 2.49],
  "fries_medium"   => ["name" => "Fries (Medium)", "price" => 3.49],
  "fries_large"    => ["name" => "Fries (Large)",  "price" => 4.49],
  "soda_small"     => ["name" => "Soda (Small)",   "price" => 1.49],
  "soda_large"     => ["name" => "Soda (Large)",   "price" => 2.49],
];

// 2) INPUTS
$customer_name    = trim($_POST["customer_name"] ?? "");
$customer_phone   = trim($_POST["customer_phone"] ?? "");
$customer_address = trim($_POST["customer_address"] ?? "");
$deal_code        = strtoupper(trim($_POST["deal_code"] ?? ""));
$cart_json        = $_POST["cart_json"] ?? "";

$errors = [];
if ($customer_name === "")    $errors[] = "Name is required.";
if ($customer_phone === "")   $errors[] = "Phone is required.";
if ($customer_address === "") $errors[] = "Address is required.";
if ($cart_json === "")        $errors[] = "Cart is empty or missing.";

$payload = json_decode($cart_json, true);
if ($payload === null) $errors[] = "Invalid cart JSON.";

// Basic cart normalization
$items = [];
if (isset($payload["items"]) && is_array($payload["items"])) {
  foreach ($payload["items"] as $it) {
    $id = $it["id"] ?? "";
    $qty = intval($it["qty"] ?? 0);
    if (!isset($PRICE_BOOK[$id])) continue;
    if ($qty <= 0) continue;
    $qty = min(99, max(0, $qty));
    $items[$id] = ($items[$id] ?? 0) + $qty;
  }
}
if (empty($items)) $errors[] = "No valid items in cart.";

// 3) SERVER-SIDE DEALS LOGIC
$freeSodaAdded = false;
$friesUpgraded = false;
$discountRate = 0.0;

if ($deal_code === "COMBO10") {
  $discountRate = 0.10;
} elseif ($deal_code === "FREESODA") {
  // Add a free small soda if not already in cart (cap one free soda)
  if (!isset($items["soda_small"])) {
    $items["soda_small"] = 1;
    $freeSodaAdded = true;
  }
} elseif ($deal_code === "FRIESUP") {
  // If there is at least one fries_small or fries_medium, upgrade one to fries_large but keep medium price
  if (isset($items["fries_medium"]) && $items["fries_medium"] > 0) {
    // We'll mark an upgrade by adjusting pricing later
    $friesUpgraded = true;
  } elseif (isset($items["fries_small"]) && $items["fries_small"] > 0) {
    // upgrade small->large at medium price too
    $friesUpgraded = true;
  }
}

// 4) TOTALS
$subtotal = 0.0;
$lines = [];

foreach ($items as $id => $qty) {
  $name = $PRICE_BOOK[$id]["name"];
  $unit = $PRICE_BOOK[$id]["price"];

  // Apply FRIESUP: one large fries at medium price if we can
  if ($friesUpgraded && ($id === "fries_large")) {
    // Only if customer actually has a large fries in the cart; otherwise no-op.
    $unit_medium = $PRICE_BOOK["fries_medium"]["price"];
    // We'll reduce price of ONE unit to medium price if that's cheaper.
    $discountPerUnit = max(0.0, $unit - $unit_medium);
    $countWithUpgrade = 1; // one upgrade
    $countNormal = $qty - $countWithUpgrade;
    if ($countNormal < 0) { $countWithUpgrade = $qty; $countNormal = 0; }
    $lineTotal = ($countWithUpgrade * ($unit - $discountPerUnit)) + ($countNormal * $unit);
  } else {
    $lineTotal = $qty * $unit;
  }

  $lines[] = ["id"=>$id, "name"=>$name, "qty"=>$qty, "unit"=>$unit, "total"=>$lineTotal];
  $subtotal += $lineTotal;
}

$discount = $subtotal * $discountRate;
$taxRate = 0.08;
$tax = ($subtotal - $discount) * $taxRate;
$total = max(0.0, $subtotal - $discount + $tax);

$order_id = "BB-" . date("Ymd-His") . "-" . substr(md5(uniqid("", true)), 0, 6);

// 5) PERSIST
$dir = __DIR__ . "/data";
if (!is_dir($dir)) { mkdir($dir, 0777, true); }
$csv = $dir . "/orders.csv";
$fp = fopen($csv, "a");
if ($fp) {
  fputcsv($fp, [
    $order_id,
    date("c"),
    $customer_name,
    $customer_phone,
    $customer_address,
    $deal_code,
    json_encode($items),
    number_format($subtotal, 2, ".", ""),
    number_format($discount, 2, ".", ""),
    number_format($tax, 2, ".", ""),
    number_format($total, 2, ".", ""),
  ]);
  fclose($fp);
}

function h($s) { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Order Confirmation — Burger Bistro</title>
  <link rel="stylesheet" href="styles.css" />
</head>
<body>
  <header class="site-header">
    <div class="container flex between center-y">
      <h1 class="brand">🍔 Burger Bistro</h1>
      <nav>
        <a href="index.html">Back to Menu</a>
      </nav>
    </div>
  </header>
  <main class="container section">
    <div class="card">
      <h2>Order Confirmation</h2>
      <?php if (!empty($errors)): ?>
        <div class="card" style="background:#3b0d0d;border-color:#7a1a1a;">
          <h3>We couldn't place your order</h3>
          <ul>
            <?php foreach ($errors as $e): ?>
              <li><?= h($e) ?></li>
            <?php endforeach; ?>
          </ul>
          <p><a class="btn" href="index.html#cart">Go back to cart</a></p>
        </div>
      <?php else: ?>
        <p>Thanks, <strong><?= h($customer_name) ?></strong>! Your order ID is <strong><?= h($order_id) ?></strong>.</p>
        <h3>Order Summary</h3>
        <div class="cart-items">
          <?php foreach ($lines as $ln): ?>
            <div class="cart-item">
              <div><strong><?= h($ln["name"]) ?></strong></div>
              <div>Qty: <?= h($ln["qty"]) ?></div>
              <div>@ $<?= number_format($ln["unit"],2) ?></div>
              <div><strong>$<?= number_format($ln["total"],2) ?></strong></div>
            </div>
          <?php endforeach; ?>
        </div>
        <div class="cart-summary">
          <div class="row"><span>Subtotal</span><strong>$<?= number_format($subtotal,2) ?></strong></div>
          <div class="row"><span>Discount<?= $discountRate>0 ? " (".h($deal_code).")" : "" ?></span><strong>-$<?= number_format($discount,2) ?></strong></div>
          <div class="row"><span>Tax (8%)</span><strong>$<?= number_format($tax,2) ?></strong></div>
          <div class="row total"><span>Total</span><strong>$<?= number_format($total,2) ?></strong></div>
        </div>

        <h3>Delivery To</h3>
        <p>
          <?= nl2br(h($customer_name . "\n" . $customer_phone . "\n" . $customer_address)) ?>
        </p>

        <?php if ($deal_code === "FREESODA" && $freeSodaAdded): ?>
          <p class="muted">✅ A free small soda was added via <strong>FREESODA</strong>.</p>
        <?php endif; ?>
        <?php if ($deal_code === "FRIESUP" && $friesUpgraded): ?>
          <p class="muted">✅ One fries upgraded to Large at Medium price via <strong>FRIESUP</strong>.</p>
        <?php endif; ?>

        <p><a class="btn primary" href="index.html">Order again</a></p>
      <?php endif; ?>
    </div>
  </main>
  <footer class="site-footer">
    <div class="container">
      <p>© <span id="year"></span> Burger Bistro.</p>
    </div>
  </footer>
  <script>
    document.getElementById('year').textContent = new Date().getFullYear();
  </script>
</body>
</html>