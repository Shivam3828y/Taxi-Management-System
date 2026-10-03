<?php

session_start();

if (!isset($_SESSION["admin_id"])) {
    header("Location: login.php");
    exit;
}

require_once "../config.php";

$message = "";
$error = "";


// =====================================================
// RECORD NEW PAYMENT
// =====================================================

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["add_payment"])) {

    csrf_verify();

    $driver_id      = (int) ($_POST["driver_id"] ?? 0);
    $assignment_id  = (int) ($_POST["assignment_id"] ?? 0);
    $amount         = (float) ($_POST["amount"] ?? 0);
    $payment_date   = trim($_POST["payment_date"] ?? "");
    $payment_method = trim($_POST["payment_method"] ?? "");
    $status         = trim($_POST["status"] ?? "Paid");
    $notes          = trim($_POST["notes"] ?? "");

    $allowed_status = ["Paid", "Pending", "Failed"];

    if ($driver_id <= 0 || $assignment_id <= 0) {
        $error = "Please select a driver / assignment.";
    } elseif ($amount <= 0) {
        $error = "Please enter a valid amount.";
    } elseif ($payment_date === "") {
        $error = "Please enter a payment date.";
    } elseif (!in_array($status, $allowed_status, true)) {
        $error = "Invalid payment status.";
    } else {

        $sql = "
            INSERT INTO payments
            (driver_id, assignment_id, amount, payment_date, payment_method, status, notes)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ";

        $stmt = mysqli_prepare($conn, $sql);

        if (!$stmt) {
            $error = "Database error: " . mysqli_error($conn);
        } else {
            mysqli_stmt_bind_param(
                $stmt,
                "iidsss",
                $driver_id,
                $assignment_id,
                $amount,
                $payment_date,
                $payment_method,
                $status,
                $notes
            );

            if (mysqli_stmt_execute($stmt)) {
                $message = "Payment recorded successfully.";
            } else {
                $error = "Failed to record payment: " . mysqli_error($conn);
            }

            mysqli_stmt_close($stmt);
        }
    }
}


// =====================================================
// UPDATE PAYMENT STATUS (mark pending/failed as paid, etc.)
// =====================================================

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["update_status"])) {

    csrf_verify();

    $payment_id = (int) ($_POST["payment_id"] ?? 0);
    $new_status = trim($_POST["new_status"] ?? "");

    $allowed_status = ["Paid", "Pending", "Failed"];

    if ($payment_id > 0 && in_array($new_status, $allowed_status, true)) {

        $sql = "UPDATE payments SET status = ? WHERE id = ?";
        $stmt = mysqli_prepare($conn, $sql);

        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "si", $new_status, $payment_id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            $message = "Payment status updated.";
        }
    }
}


// =====================================================
// ACTIVE ASSIGNMENTS (for the "record payment" dropdown)
// =====================================================

$assignments = [];

$sql = "
    SELECT
        assignments.id AS assignment_id,
        assignments.driver_id,
        drivers.name AS driver_name,
        taxis.brand,
        taxis.model,
        taxis.registration_number
    FROM assignments
    INNER JOIN drivers ON assignments.driver_id = drivers.id
    INNER JOIN taxis ON assignments.taxi_id = taxis.id
    WHERE assignments.status = 'Active'
    ORDER BY drivers.name
";

$result = mysqli_query($conn, $sql);

if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $assignments[] = $row;
    }
}


// =====================================================
// SUMMARY TOTALS
// =====================================================

$summary_sql = "
    SELECT
        COALESCE(SUM(CASE WHEN status = 'Paid' THEN amount ELSE 0 END), 0) AS total_collected,
        COALESCE(SUM(CASE WHEN status = 'Pending' THEN amount ELSE 0 END), 0) AS total_pending,
        COUNT(*) AS total_count
    FROM payments
";

$summary = mysqli_fetch_assoc(mysqli_query($conn, $summary_sql));


// =====================================================
// PAYMENT HISTORY (all drivers)
// =====================================================

$payments = [];

$sql = "
    SELECT
        payments.id,
        payments.amount,
        payments.payment_date,
        payments.payment_method,
        payments.status,
        payments.notes,
        drivers.id AS driver_id,
        drivers.name AS driver_name,
        taxis.brand,
        taxis.model,
        taxis.registration_number
    FROM payments
    INNER JOIN drivers ON payments.driver_id = drivers.id
    INNER JOIN assignments ON payments.assignment_id = assignments.id
    INNER JOIN taxis ON assignments.taxi_id = taxis.id
    ORDER BY payments.payment_date DESC, payments.id DESC
";

$result = mysqli_query($conn, $sql);

if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $payments[] = $row;
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payments - Taxi Management System</title>

    <link rel="stylesheet" href="../css/style.css">

</head>

<body>

<header>

    <h1>Payment Management</h1>

    <nav>
        <a href="dashboard.php">Dashboard</a>
        <a href="drivers.php">Drivers</a>
        <a href="taxis.php">Taxis</a>
        <a href="assignments.php">Assignments</a>
        <a href="agreements.php">Agreements</a>
        <a href="payments.php">Payments</a>
        <a href="../index2.php">Public Portal</a>
        <a href="logout.php">Logout</a>
    </nav>

</header>

<main>

    <?php if ($message !== ""): ?>
        <p class="message"><?= e($message) ?></p>
    <?php endif; ?>

    <?php if ($error !== ""): ?>
        <p class="error"><?= e($error) ?></p>
    <?php endif; ?>

    <div class="summary-grid">
        <div class="summary-card">
            <div>Total Collected</div>
            <div class="value">₹<?= number_format((float) $summary["total_collected"], 2) ?></div>
        </div>
        <div class="summary-card">
            <div>Pending</div>
            <div class="value">₹<?= number_format((float) $summary["total_pending"], 2) ?></div>
        </div>
        <div class="summary-card">
            <div>Total Payments</div>
            <div class="value"><?= (int) $summary["total_count"] ?></div>
        </div>
    </div>

    <section class="form-card">

        <h2>Record a Payment</h2>

        <form method="POST" action="">

            <?php csrf_field(); ?>

            <div class="form-row">

                <div>
                    <label for="assignment_id">Driver / Taxi</label>
                    <select id="assignment_id" name="assignment_id" required
                        onchange="document.getElementById('driver_id').value = this.options[this.selectedIndex].getAttribute('data-driver')">
                        <option value="">-- Select active assignment --</option>
                        <?php foreach ($assignments as $a): ?>
                            <option
                                value="<?= (int) $a['assignment_id'] ?>"
                                data-driver="<?= (int) $a['driver_id'] ?>"
                            >
                                <?= e($a['driver_name']) ?> — <?= e($a['brand'] . ' ' . $a['model'] . ' (' . $a['registration_number'] . ')') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <input type="hidden" id="driver_id" name="driver_id" value="">

                <div>
                    <label for="amount">Amount (₹)</label>
                    <input type="number" step="0.01" min="0.01" id="amount" name="amount" required>
                </div>

                <div>
                    <label for="payment_date">Payment Date</label>
                    <input type="date" id="payment_date" name="payment_date" required>
                </div>

            </div>

            <div class="form-row">

                <div>
                    <label for="payment_method">Payment Method</label>
                    <select id="payment_method" name="payment_method">
                        <option value="Cash">Cash</option>
                        <option value="UPI">UPI</option>
                        <option value="Bank Transfer">Bank Transfer</option>
                        <option value="Card">Card</option>
                    </select>
                </div>

                <div>
                    <label for="status">Status</label>
                    <select id="status" name="status">
                        <option value="Paid">Paid</option>
                        <option value="Pending">Pending</option>
                        <option value="Failed">Failed</option>
                    </select>
                </div>

                <div>
                    <label for="notes">Notes</label>
                    <input type="text" id="notes" name="notes" maxlength="255">
                </div>

            </div>

            <button type="submit" name="add_payment">Record Payment</button>

        </form>

    </section>

    <section>

        <h2>Payment History</h2>

        <?php if (empty($payments)): ?>

            <p>No payments recorded yet.</p>

        <?php else: ?>

            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Driver</th>
                        <th>Taxi</th>
                        <th>Amount</th>
                        <th>Method</th>
                        <th>Status</th>
                        <th>Notes</th>
                        <th>Change Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($payments as $p): ?>
                        <tr>
                            <td><?= e($p['payment_date']) ?></td>
                            <td><?= e($p['driver_name']) ?></td>
                            <td><?= e($p['brand'] . ' ' . $p['model'] . ' (' . $p['registration_number'] . ')') ?></td>
                            <td>₹<?= number_format((float) $p['amount'], 2) ?></td>
                            <td><?= e($p['payment_method'] ?? '—') ?></td>
                            <td><span class="badge badge-<?= e($p['status']) ?>"><?= e($p['status']) ?></span></td>
                            <td><?= e($p['notes'] ?? '') ?></td>
                            <td>
                                <form method="POST" action="" style="display:flex; gap:5px;">
                                    <?php csrf_field(); ?>
                                    <input type="hidden" name="payment_id" value="<?= (int) $p['id'] ?>">
                                    <select name="new_status">
                                        <option value="Paid" <?= $p['status'] === 'Paid' ? 'selected' : '' ?>>Paid</option>
                                        <option value="Pending" <?= $p['status'] === 'Pending' ? 'selected' : '' ?>>Pending</option>
                                        <option value="Failed" <?= $p['status'] === 'Failed' ? 'selected' : '' ?>>Failed</option>
                                    </select>
                                    <button type="submit" name="update_status">Update</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

        <?php endif; ?>

    </section>

</main>

<footer>
    <p>&copy; 2026 Taxi Management System</p>
</footer>

</body>
</html>
