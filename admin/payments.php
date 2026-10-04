<?php

require_once "../config.php";

session_start();

if (!isset($_SESSION["admin_id"])) {
    header("Location: login.php");
    exit;
}

$filter_driver_id = (int) ($_GET["driver_id"] ?? 0);
$filter_status = trim($_GET["status"] ?? "");
$filter_method = trim($_GET["payment_method"] ?? "");

$allowed_status = ["Paid", "Pending", "Failed"];
$allowed_methods = ["UPI", "Cash", "Bank Transfer", "Card"];

if (!in_array($filter_status, $allowed_status, true)) {
    $filter_status = "";
}

if (!in_array($filter_method, $allowed_methods, true)) {
    $filter_method = "";
}


/* =====================================================
   SUMMARY
===================================================== */

$summary = [
    "total_collected" => 0,
    "total_pending" => 0,
    "total_failed" => 0,
    "total_count" => 0,
    "paid_count" => 0,
    "pending_count" => 0
];

$summary_sql = "
    SELECT
        COALESCE(
            SUM(
                CASE
                    WHEN status = 'Paid' THEN amount
                    ELSE 0
                END
            ),
            0
        ) AS total_collected,

        COALESCE(
            SUM(
                CASE
                    WHEN status = 'Pending' THEN amount
                    ELSE 0
                END
            ),
            0
        ) AS total_pending,

        COALESCE(
            SUM(
                CASE
                    WHEN status = 'Failed' THEN amount
                    ELSE 0
                END
            ),
            0
        ) AS total_failed,

        COUNT(*) AS total_count,

        SUM(
            CASE
                WHEN status = 'Paid' THEN 1
                ELSE 0
            END
        ) AS paid_count,

        SUM(
            CASE
                WHEN status = 'Pending' THEN 1
                ELSE 0
            END
        ) AS pending_count

    FROM payments
";

$summary_result = mysqli_query($conn, $summary_sql);

if ($summary_result) {
    $row = mysqli_fetch_assoc($summary_result);

    if ($row) {
        $summary = array_merge($summary, $row);
    }
}


/* =====================================================
   DRIVER LIST
===================================================== */

$drivers = [];

$driver_result = mysqli_query(
    $conn,
    "
        SELECT id, name
        FROM drivers
        ORDER BY name ASC
    "
);

if ($driver_result) {
    while ($row = mysqli_fetch_assoc($driver_result)) {
        $drivers[] = $row;
    }
}


/* =====================================================
   PAYMENT HISTORY
===================================================== */

$payments = [];

$sql = "
    SELECT
        payments.id,
        payments.driver_id,
        payments.assignment_id,
        payments.amount,
        payments.payment_date,
        payments.payment_method,
        payments.status,
        payments.notes,

        drivers.name AS driver_name,
        drivers.phone AS driver_phone,

        taxis.brand,
        taxis.model,
        taxis.registration_number

    FROM payments

    INNER JOIN drivers
        ON payments.driver_id = drivers.id

    LEFT JOIN assignments
        ON payments.assignment_id = assignments.id

    LEFT JOIN taxis
        ON assignments.taxi_id = taxis.id

    WHERE 1 = 1
";

$types = "";
$params = [];

if ($filter_driver_id > 0) {
    $sql .= " AND payments.driver_id = ?";
    $types .= "i";
    $params[] = $filter_driver_id;
}

if ($filter_status !== "") {
    $sql .= " AND payments.status = ?";
    $types .= "s";
    $params[] = $filter_status;
}

if ($filter_method !== "") {
    $sql .= " AND payments.payment_method = ?";
    $types .= "s";
    $params[] = $filter_method;
}

$sql .= "
    ORDER BY
        payments.payment_date DESC,
        payments.id DESC
";

$stmt = mysqli_prepare($conn, $sql);

if ($stmt) {

    if ($types !== "") {
        mysqli_stmt_bind_param(
            $stmt,
            $types,
            ...$params
        );
    }

    mysqli_stmt_execute($stmt);

    $result = mysqli_stmt_get_result($stmt);

    while ($row = mysqli_fetch_assoc($result)) {
        $payments[] = $row;
    }

    mysqli_stmt_close($stmt);
}

?>

<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        Payment Management - Taxi Management System
    </title>

    <link
        rel="stylesheet"
        href="../css/style.css"
    >

    <style>

        .payments-container {
            max-width: 1250px;
            margin: 30px auto;
            padding: 0 20px;
        }

        .page-intro {
            margin-bottom: 25px;
        }

        .page-intro p {
            color: #555;
        }

        .summary-grid {
            display: grid;
            grid-template-columns:
                repeat(
                    auto-fit,
                    minmax(190px, 1fr)
                );
            gap: 16px;
            margin-bottom: 25px;
        }

        .summary-card {
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 10px;
            padding: 18px;
        }

        .summary-card .label {
            color: #666;
            font-size: 14px;
        }

        .summary-card .value {
            margin-top: 7px;
            font-size: 24px;
            font-weight: bold;
        }

        .filters {
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 25px;
        }

        .filter-grid {
            display: grid;
            grid-template-columns:
                repeat(
                    auto-fit,
                    minmax(200px, 1fr)
                );
            gap: 15px;
            align-items: end;
        }

        .filter-grid label {
            display: block;
            margin-bottom: 6px;
            font-weight: 600;
        }

        .filter-grid select {
            width: 100%;
            box-sizing: border-box;
            padding: 10px;
        }

        .filter-actions {
            display: flex;
            gap: 10px;
        }

        .filter-actions button,
        .filter-actions a {
            padding: 10px 14px;
            border: 1px solid #333;
            border-radius: 6px;
            text-decoration: none;
            cursor: pointer;
        }

        .payment-table-wrapper {
            overflow-x: auto;
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 10px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 1050px;
        }

        th,
        td {
            border-bottom: 1px solid #ddd;
            padding: 12px;
            text-align: left;
            vertical-align: top;
        }

        th {
            background: #f5f5f5;
        }

        .amount {
            font-weight: bold;
            white-space: nowrap;
        }

        .badge {
            display: inline-block;
            padding: 5px 9px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
        }

        .badge-Paid {
            background: #dcfce7;
            color: #166534;
        }

        .badge-Pending {
            background: #fef3c7;
            color: #92400e;
        }

        .badge-Failed {
            background: #fee2e2;
            color: #991b1b;
        }

        .payment-note {
            color: #666;
            font-size: 13px;
            margin-top: 4px;
        }

        .empty-state {
            text-align: center;
            padding: 35px;
            color: #666;
        }

        .admin-note {
            background: #f8fafc;
            border-left: 4px solid #555;
            padding: 14px 16px;
            margin-bottom: 25px;
        }

        @media (max-width: 700px) {

            .payments-container {
                padding: 0 12px;
            }

        }

    </style>

</head>


<body>


<header>

    <h1>
        Payment Management
    </h1>

    <nav>

        <a href="dashboard.php">
            Dashboard
        </a>

        <a href="drivers.php">
            Drivers
        </a>

        <a href="taxis.php">
            Taxis
        </a>

        <a href="assignments.php">
            Assignments
        </a>

        <a href="agreements.php">
            Agreements
        </a>

        <a href="payments.php">
            Payments
        </a>

        <a href="../index2.php">
            Public Portal
        </a>

        <a href="logout.php">
            Logout
        </a>

    </nav>

</header>


<main class="payments-container">


    <div class="page-intro">

        <h2>
            Rent Payment Records
        </h2>

        <p>
            View payment activity for all drivers,
            assigned taxis and rental agreements.
        </p>

    </div>


    <div class="admin-note">

        <strong>
            Automatic Payment Records
        </strong>

        <div>

            Payment status shown here is read from the
            <code>payments</code> table.

            When the UPI payment gateway and webhook are
            connected, successful payments will appear
            here automatically.

            Admin does not need to manually mark a
            successful online payment as Paid.

        </div>

    </div>


    <!-- =================================================
         SUMMARY
    ================================================= -->

    <section class="summary-grid">


        <div class="summary-card">

            <div class="label">
                Total Collected
            </div>

            <div class="value">

                ₹<?= number_format(
                    (float) $summary["total_collected"],
                    2
                ) ?>

            </div>

        </div>


        <div class="summary-card">

            <div class="label">
                Pending Amount
            </div>

            <div class="value">

                ₹<?= number_format(
                    (float) $summary["total_pending"],
                    2
                ) ?>

            </div>

        </div>


        <div class="summary-card">

            <div class="label">
                Failed Amount
            </div>

            <div class="value">

                ₹<?= number_format(
                    (float) $summary["total_failed"],
                    2
                ) ?>

            </div>

        </div>


        <div class="summary-card">

            <div class="label">
                Completed Payments
            </div>

            <div class="value">

                <?= (int) $summary["paid_count"] ?>

            </div>

        </div>


        <div class="summary-card">

            <div class="label">
                Pending Payments
            </div>

            <div class="value">

                <?= (int) $summary["pending_count"] ?>

            </div>

        </div>


    </section>


    <!-- =================================================
         FILTERS
    ================================================= -->

    <section class="filters">

        <h3>
            Payment Filters
        </h3>


        <form
            method="GET"
            action="payments.php"
        >

            <div class="filter-grid">


                <div>

                    <label for="driver_id">
                        Driver
                    </label>

                    <select
                        id="driver_id"
                        name="driver_id"
                    >

                        <option value="">
                            All Drivers
                        </option>


                        <?php foreach ($drivers as $driver): ?>

                            <option
                                value="<?= (int) $driver["id"] ?>"
                                <?= $filter_driver_id === (int) $driver["id"]
                                    ? "selected"
                                    : "" ?>
                            >

                                #<?= (int) $driver["id"] ?>
                                -
                                <?= e($driver["name"]) ?>

                            </option>

                        <?php endforeach; ?>


                    </select>

                </div>


                <div>

                    <label for="status">
                        Status
                    </label>

                    <select
                        id="status"
                        name="status"
                    >

                        <option value="">
                            All Status
                        </option>


                        <?php foreach ($allowed_status as $status): ?>

                            <option
                                value="<?= e($status) ?>"
                                <?= $filter_status === $status
                                    ? "selected"
                                    : "" ?>
                            >

                                <?= e($status) ?>

                            </option>

                        <?php endforeach; ?>


                    </select>

                </div>


                <div>

                    <label for="payment_method">
                        Payment Method
                    </label>

                    <select
                        id="payment_method"
                        name="payment_method"
                    >

                        <option value="">
                            All Methods
                        </option>


                        <?php foreach ($allowed_methods as $method): ?>

                            <option
                                value="<?= e($method) ?>"
                                <?= $filter_method === $method
                                    ? "selected"
                                    : "" ?>
                            >

                                <?= e($method) ?>

                            </option>

                        <?php endforeach; ?>


                    </select>

                </div>


                <div class="filter-actions">

                    <button type="submit">
                        Apply
                    </button>

                    <a href="payments.php">
                        Reset
                    </a>

                </div>


            </div>

        </form>

    </section>


    <!-- =================================================
         PAYMENT HISTORY
    ================================================= -->

    <section>

        <h2>
            Payment History
        </h2>


        <?php if (empty($payments)): ?>


            <div class="payment-table-wrapper">

                <div class="empty-state">

                    <h3>
                        No Payment Records Found
                    </h3>

                    <p>
                        Payment records will appear here
                        when a driver makes a payment.
                    </p>

                </div>

            </div>


        <?php else: ?>


            <div class="payment-table-wrapper">


                <table>


                    <thead>

                        <tr>

                            <th>
                                Payment ID
                            </th>

                            <th>
                                Driver
                            </th>

                            <th>
                                Driver ID
                            </th>

                            <th>
                                Taxi
                            </th>

                            <th>
                                Assignment ID
                            </th>

                            <th>
                                Amount
                            </th>

                            <th>
                                Payment Date
                            </th>

                            <th>
                                Method
                            </th>

                            <th>
                                Status
                            </th>

                            <th>
                                Details
                            </th>

                        </tr>

                    </thead>


                    <tbody>


                        <?php foreach ($payments as $payment): ?>


                            <tr>


                                <td>

                                    #<?= (int) $payment["id"] ?>

                                </td>


                                <td>

                                    <?= e(
                                        $payment["driver_name"]
                                    ) ?>


                                    <div class="payment-note">

                                        <?= e(
                                            $payment["driver_phone"]
                                        ) ?>

                                    </div>

                                </td>


                                <td>

                                    #<?= (int) $payment["driver_id"] ?>

                                </td>


                                <td>


                                    <?php if (
                                        !empty(
                                            $payment[
                                                "registration_number"
                                            ]
                                        )
                                    ): ?>


                                        <?= e(
                                            $payment["brand"]
                                            . " "
                                            . $payment["model"]
                                        ) ?>


                                        <div class="payment-note">

                                            <?= e(
                                                $payment[
                                                    "registration_number"
                                                ]
                                            ) ?>

                                        </div>


                                    <?php else: ?>

                                        —

                                    <?php endif; ?>


                                </td>


                                <td>

                                    #<?= (int) $payment["assignment_id"] ?>

                                </td>


                                <td class="amount">

                                    ₹<?= number_format(
                                        (float) $payment["amount"],
                                        2
                                    ) ?>

                                </td>


                                <td>

                                    <?= e(
                                        $payment["payment_date"]
                                    ) ?>

                                </td>


                                <td>

                                    <?= e(
                                        $payment[
                                            "payment_method"
                                        ] ?? "—"
                                    ) ?>

                                </td>


                                <td>


                                    <span
                                        class="badge badge-<?= e(
                                            $payment["status"]
                                        ) ?>"
                                    >

                                        <?= e(
                                            $payment["status"]
                                        ) ?>

                                    </span>


                                </td>


                                <td>


                                    <?php if (
                                        !empty(
                                            $payment["notes"]
                                        )
                                    ): ?>


                                        <?= e(
                                            $payment["notes"]
                                        ) ?>


                                    <?php else: ?>

                                        —

                                    <?php endif; ?>


                                </td>


                            </tr>


                        <?php endforeach; ?>


                    </tbody>


                </table>


            </div>


        <?php endif; ?>


    </section>


</main>


<footer>

    <p>
        &copy; 2026 Taxi Management System
    </p>

</footer>


</body>

</html>