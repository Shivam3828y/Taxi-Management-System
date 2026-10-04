<?php

require_once "../config.php";

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION["driver_id"])) {
    header("Location: login.php");
    exit;
}

$driver_id = (int) $_SESSION["driver_id"];

$message = "";
$error = "";

/* =====================================================
   ACCEPT AGREEMENT
   ===================================================== */

if (
    $_SERVER["REQUEST_METHOD"] === "POST"
    && isset($_POST["accept_agreement"])
) {

    csrf_verify();

    $agreement_id = (int) ($_POST["agreement_id"] ?? 0);

    if ($agreement_id > 0) {

        $accept_sql = "
            UPDATE agreements
            SET
                accepted = 1,
                accepted_at = NOW()
            WHERE id = ?
            AND status = 'Active'
            AND accepted = 0
            AND assignment_id IN (
                SELECT id
                FROM assignments
                WHERE driver_id = ?
                AND status = 'Active'
            )
        ";

        $accept_stmt = mysqli_prepare($conn, $accept_sql);

        if ($accept_stmt) {

            mysqli_stmt_bind_param(
                $accept_stmt,
                "ii",
                $agreement_id,
                $driver_id
            );

            mysqli_stmt_execute($accept_stmt);

            if (mysqli_stmt_affected_rows($accept_stmt) > 0) {
                $message = "Agreement accepted successfully.";
            } else {
                $error = "Agreement could not be accepted.";
            }

            mysqli_stmt_close($accept_stmt);

        } else {

            $error = "Database error: " . mysqli_error($conn);
        }
    }
}


/* =====================================================
   DRIVER INFORMATION
   ===================================================== */

$driver_sql = "
    SELECT
        id,
        name,
        phone,
        email,
        address,
        driving_license,
        status
    FROM drivers
    WHERE id = ?
    LIMIT 1
";

$driver_stmt = mysqli_prepare($conn, $driver_sql);

if (!$driver_stmt) {
    die("Database error: " . mysqli_error($conn));
}

mysqli_stmt_bind_param(
    $driver_stmt,
    "i",
    $driver_id
);

mysqli_stmt_execute($driver_stmt);

$driver_result = mysqli_stmt_get_result($driver_stmt);

$driver = mysqli_fetch_assoc($driver_result);

mysqli_stmt_close($driver_stmt);

if (!$driver) {

    $_SESSION = [];
    session_destroy();

    header("Location: login.php");
    exit;
}


/* =====================================================
   ACTIVE ASSIGNMENT
   ===================================================== */

$assignment_sql = "
    SELECT
        assignments.id AS assignment_id,
        assignments.assigned_at,
        assignments.status AS assignment_status,

        taxis.id AS taxi_id,
        taxis.brand,
        taxis.model,
        taxis.registration_number,
        taxis.rent,
        taxis.status AS taxi_status

    FROM assignments

    INNER JOIN taxis
        ON assignments.taxi_id = taxis.id

    WHERE assignments.driver_id = ?
    AND assignments.status = 'Active'

    ORDER BY assignments.id DESC

    LIMIT 1
";

$assignment_stmt = mysqli_prepare($conn, $assignment_sql);

if (!$assignment_stmt) {
    die("Database error: " . mysqli_error($conn));
}

mysqli_stmt_bind_param(
    $assignment_stmt,
    "i",
    $driver_id
);

mysqli_stmt_execute($assignment_stmt);

$assignment_result = mysqli_stmt_get_result($assignment_stmt);

$assignment = mysqli_fetch_assoc($assignment_result);

mysqli_stmt_close($assignment_stmt);


/* =====================================================
   ACTIVE AGREEMENT
   ===================================================== */

$agreement = null;

if ($assignment) {

    $agreement_sql = "
        SELECT
            id,
            start_date,
            end_date,
            rent,
            status,
            accepted,
            accepted_at
        FROM agreements
        WHERE assignment_id = ?
        AND status = 'Active'
        ORDER BY id DESC
        LIMIT 1
    ";

    $agreement_stmt = mysqli_prepare($conn, $agreement_sql);

    if ($agreement_stmt) {

        mysqli_stmt_bind_param(
            $agreement_stmt,
            "i",
            $assignment["assignment_id"]
        );

        mysqli_stmt_execute($agreement_stmt);

        $agreement_result =
            mysqli_stmt_get_result($agreement_stmt);

        $agreement =
            mysqli_fetch_assoc($agreement_result);

        mysqli_stmt_close($agreement_stmt);
    }
}


/* =====================================================
   PAYMENT INFORMATION
   ===================================================== */

$payment = null;
$payment_amount = 0;

if ($assignment) {

    if ($agreement) {
        $payment_amount = (float) $agreement["rent"];
    } else {
        $payment_amount = (float) $assignment["rent"];
    }

    $payment_sql = "
        SELECT
            id,
            amount,
            payment_date,
            payment_method,
            status,
            notes
        FROM payments
        WHERE driver_id = ?
        AND assignment_id = ?
        ORDER BY id DESC
        LIMIT 1
    ";

    $payment_stmt = mysqli_prepare($conn, $payment_sql);

    if ($payment_stmt) {

        mysqli_stmt_bind_param(
            $payment_stmt,
            "ii",
            $driver_id,
            $assignment["assignment_id"]
        );

        mysqli_stmt_execute($payment_stmt);

        $payment_result =
            mysqli_stmt_get_result($payment_stmt);

        $payment =
            mysqli_fetch_assoc($payment_result);

        mysqli_stmt_close($payment_stmt);
    }
}


/* =====================================================
   UPI QR
   ===================================================== */

$upi_id = "YOUR_UPI_ID@upi";
$upi_name = "Taxi Management System";

$upi_note =
    "Taxi Rent - Driver ID " . $driver_id;

$upi_uri =
    "upi://pay?pa="
    . rawurlencode($upi_id)
    . "&pn="
    . rawurlencode($upi_name)
    . "&am="
    . rawurlencode(
        number_format(
            $payment_amount,
            2,
            ".",
            ""
        )
    )
    . "&cu=INR"
    . "&tn="
    . rawurlencode($upi_note);

$qr_url =
    "https://api.qrserver.com/v1/create-qr-code/"
    . "?size=240x240&data="
    . rawurlencode($upi_uri);


/* =====================================================
   PAYMENT STATUS
   ===================================================== */

$payment_status = "Pending";

if ($payment) {
    $payment_status = $payment["status"];
}

$payment_status_class = "pending";

if ($payment_status === "Paid") {
    $payment_status_class = "paid";
} elseif ($payment_status === "Failed") {
    $payment_status_class = "failed";
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
        Driver Dashboard - Taxi Management System
    </title>

    <link
        rel="stylesheet"
        href="../css/style.css"
    >

    <style>

        .dashboard-container {
            max-width: 1200px;
            margin: 30px auto;
            padding: 0 15px;
        }

        .dashboard-grid {
            display: grid;
            grid-template-columns:
                repeat(
                    auto-fit,
                    minmax(250px, 1fr)
                );
            gap: 20px;
            margin-top: 20px;
        }

        .dashboard-card {
            border: 1px solid #ddd;
            border-radius: 10px;
            padding: 20px;
            background: #fff;
        }

        .dashboard-card h2 {
            margin-top: 0;
        }

        .pass-card {
            border: 2px solid #222;
            border-radius: 12px;
            padding: 25px;
            background: #f8f8f8;
            margin-top: 20px;
        }

        .pass-title {
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 20px;
        }

        .pass-row {
            display: flex;
            justify-content: space-between;
            gap: 20px;
            padding: 10px 0;
            border-bottom: 1px solid #ddd;
        }

        .pass-row:last-child {
            border-bottom: none;
        }

        .status {
            font-weight: bold;
        }

        .verified {
            color: green;
        }

        .pending {
            color: #b45309;
        }

        .inactive {
            color: #b91c1c;
        }

        .accepted {
            color: green;
            font-weight: bold;
        }

        .waiting {
            color: #b45309;
            font-weight: bold;
        }

        .paid {
            color: #15803d;
            font-weight: bold;
        }

        .failed {
            color: #b91c1c;
            font-weight: bold;
        }

        .error-message {
            color: #b91c1c;
            margin-bottom: 15px;
            padding: 10px;
            background: #fee2e2;
            border-radius: 6px;
        }

        .success-message {
            color: #15803d;
            margin-bottom: 15px;
            padding: 10px;
            background: #dcfce7;
            border-radius: 6px;
        }

        .dashboard-button {
            display: inline-block;
            margin-top: 10px;
            padding: 10px 15px;
            border: 1px solid #333;
            border-radius: 6px;
            text-decoration: none;
            cursor: pointer;
            background: #fff;
            color: #222;
        }

        .dashboard-button:hover {
            background: #f1f1f1;
        }

        .accept-button {
            background: #15803d;
            color: white;
            border: none;
            padding: 11px 18px;
            border-radius: 6px;
            cursor: pointer;
            margin-top: 15px;
        }

        .accept-button:hover {
            background: #166534;
        }

        .agreement-box {
            border: 2px solid #444;
            border-radius: 10px;
            padding: 20px;
            background: #fafafa;
            margin-top: 20px;
        }

        .agreement-warning {
            padding: 12px;
            background: #fff3cd;
            border-radius: 6px;
            margin-top: 15px;
        }

        .payment-card {
            border: 2px solid #ddd;
            border-radius: 12px;
            padding: 25px;
            background: #fff;
            margin-top: 20px;
        }

        .payment-layout {
            display: grid;
            grid-template-columns:
                minmax(250px, 1fr)
                280px;
            gap: 30px;
            align-items: center;
        }

        .payment-info {
            width: 100%;
        }

        .payment-row {
            display: flex;
            justify-content: space-between;
            gap: 20px;
            padding: 12px 0;
            border-bottom: 1px solid #eee;
        }

        .payment-row:last-child {
            border-bottom: none;
        }

        .qr-box {
            text-align: center;
            border: 1px solid #ddd;
            border-radius: 10px;
            padding: 20px;
            background: #fafafa;
        }

        .qr-box img {
            width: 220px;
            max-width: 100%;
            height: auto;
            border: 1px solid #ddd;
            padding: 8px;
            background: white;
        }

        .qr-title {
            font-weight: bold;
            font-size: 18px;
            margin-bottom: 12px;
        }

        .qr-amount {
            font-size: 22px;
            font-weight: bold;
            margin-top: 10px;
        }

        .payment-note {
            margin-top: 15px;
            padding: 12px;
            background: #fff7ed;
            border-radius: 7px;
            color: #7c2d12;
            font-size: 14px;
        }

        .service-grid {
            display: grid;
            grid-template-columns:
                repeat(
                    auto-fit,
                    minmax(220px, 1fr)
                );
            gap: 20px;
            margin-top: 20px;
        }

        .service-card {
            border: 1px solid #ddd;
            border-radius: 10px;
            padding: 20px;
            background: white;
        }

        .service-card h3 {
            margin-top: 0;
        }

        .empty-box {
            border: 1px dashed #aaa;
            border-radius: 10px;
            padding: 25px;
            text-align: center;
            background: #fafafa;
            margin-top: 20px;
        }

        @media (max-width: 700px) {

            .pass-row,
            .payment-row {
                flex-direction: column;
                gap: 5px;
            }

            .payment-layout {
                grid-template-columns: 1fr;
            }

            .qr-box {
                max-width: 300px;
                margin: 0 auto;
            }
        }

    </style>

</head>

<body>

<header>

    <h1>
        Taxi Management System
    </h1>

    <nav>

        <a href="dashboard.php">
            Dashboard
        </a>

        <a href="../index2.php#taxis">
            Taxi Availability
        </a>

        <a href="logout.php">
            Logout
        </a>

    </nav>

</header>


<main class="dashboard-container">

    <h1>
        Driver Dashboard
    </h1>


    <?php if ($message): ?>

        <div class="success-message">
            <?= e($message) ?>
        </div>

    <?php endif; ?>


    <?php if ($error): ?>

        <div class="error-message">
            <?= e($error) ?>
        </div>

    <?php endif; ?>


    <!-- =================================================
         DRIVER PROFILE
         ================================================= -->

    <div class="dashboard-card">

        <h2>
            Driver Information
        </h2>

        <div class="pass-row">

            <strong>
                Driver ID
            </strong>

            <span>
                <?= e($driver["id"]) ?>
            </span>

        </div>

        <div class="pass-row">

            <strong>
                Name
            </strong>

            <span>
                <?= e($driver["name"]) ?>
            </span>

        </div>

        <div class="pass-row">

            <strong>
                Phone
            </strong>

            <span>
                <?= e($driver["phone"]) ?>
            </span>

        </div>

        <div class="pass-row">

            <strong>
                Email
            </strong>

            <span>
                <?= e($driver["email"] ?: "Not provided") ?>
            </span>

        </div>

        <div class="pass-row">

            <strong>
                Address
            </strong>

            <span>
                <?= e($driver["address"]) ?>
            </span>

        </div>

        <div class="pass-row">

            <strong>
                Driving License
            </strong>

            <span>
                <?= e($driver["driving_license"]) ?>
            </span>

        </div>

        <div class="pass-row">

            <strong>
                Status
            </strong>

            <span
                class="status
                <?= strtolower(e($driver["status"])) ?>"
            >
                <?= e($driver["status"]) ?>
            </span>

        </div>

        <a
            href="profile.php"
            class="dashboard-button"
        >
            My Profile
        </a>

    </div>


    <!-- =================================================
         ASSIGNED TAXI
         ================================================= -->

    <?php if ($assignment): ?>

        <div class="pass-card">

            <div class="pass-title">
                Assigned Taxi
            </div>

            <div class="pass-row">

                <strong>
                    Driver
                </strong>

                <span>
                    <?= e($driver["name"]) ?>
                </span>

            </div>

            <div class="pass-row">

                <strong>
                    Phone
                </strong>

                <span>
                    <?= e($driver["phone"]) ?>
                </span>

            </div>

            <div class="pass-row">

                <strong>
                    Taxi
                </strong>

                <span>
                    <?= e($assignment["brand"]) ?>
                    <?= e($assignment["model"]) ?>
                </span>

            </div>

            <div class="pass-row">

                <strong>
                    Registration Number
                </strong>

                <span>
                    <?= e($assignment["registration_number"]) ?>
                </span>

            </div>

            <div class="pass-row">

                <strong>
                    Daily Rent
                </strong>

                <span>
                    ₹<?= number_format(
                        (float) $assignment["rent"],
                        2
                    ) ?>
                </span>

            </div>

            <?php if ($agreement): ?>

                <div class="pass-row">

                    <strong>
                        Agreement Rent
                    </strong>

                    <span>
                        ₹<?= number_format(
                            (float) $agreement["rent"],
                            2
                        ) ?>
                    </span>

                </div>

            <?php endif; ?>

            <div class="pass-row">

                <strong>
                    Assignment Status
                </strong>

                <span class="status accepted">
                    <?= e($assignment["assignment_status"]) ?>
                </span>

            </div>

            <div class="pass-row">

                <strong>
                    Assigned At
                </strong>

                <span>
                    <?= e($assignment["assigned_at"]) ?>
                </span>

            </div>

        </div>


        <!-- =================================================
             RENT PAYMENT / UPI
             ================================================= -->

        <div class="payment-card">

            <h2>
                Rent Payment
            </h2>

            <div class="payment-layout">

                <div class="payment-info">

                    <div class="payment-row">

                        <strong>
                            Taxi
                        </strong>

                        <span>
                            <?= e(
                                $assignment["registration_number"]
                            ) ?>
                        </span>

                    </div>

                    <div class="payment-row">

                        <strong>
                            Rent Amount
                        </strong>

                        <span>
                            ₹<?= number_format(
                                $payment_amount,
                                2
                            ) ?>
                        </span>

                    </div>

                    <div class="payment-row">

                        <strong>
                            Payment Method
                        </strong>

                        <span>
                            UPI
                        </span>

                    </div>

                    <div class="payment-row">

                        <strong>
                            Payment Status
                        </strong>

                        <span
                            class="<?= e(
                                $payment_status_class
                            ) ?>"
                        >
                            <?= e($payment_status) ?>
                        </span>

                    </div>

                    <?php if ($payment): ?>

                        <div class="payment-row">

                            <strong>
                                Last Payment
                            </strong>

                            <span>
                                <?= e(
                                    $payment["payment_date"]
                                ) ?>
                            </span>

                        </div>

                        <div class="payment-row">

                            <strong>
                                Last Payment Amount
                            </strong>

                            <span>
                                ₹<?= number_format(
                                    (float) $payment["amount"],
                                    2
                                ) ?>
                            </span>

                        </div>

                    <?php else: ?>

                        <div class="payment-row">

                            <strong>
                                Last Payment
                            </strong>

                            <span>
                                No payment recorded
                            </span>

                        </div>

                    <?php endif; ?>

                </div>


                <?php if ($payment_status !== "Paid"): ?>

                    <div class="qr-box">

                        <div class="qr-title">
                            Scan to Pay
                        </div>

                        <img
                            src="<?= e($qr_url) ?>"
                            alt="UPI Payment QR Code"
                        >

                        <div class="qr-amount">
                            ₹<?= number_format(
                                $payment_amount,
                                2
                            ) ?>
                        </div>

                        <p>
                            Pay using any supported UPI app.
                        </p>

                    </div>

                <?php else: ?>

                    <div class="qr-box">

                        <div class="qr-title paid">
                            Payment Completed
                        </div>

                        <p>
                            Your latest payment is marked
                            as Paid.
                        </p>

                    </div>

                <?php endif; ?>

            </div>


            <div class="payment-note">

                <strong>
                    Payment note:
                </strong>

                This QR is a UPI payment link.
                The current system does not automatically
                verify the UPI transaction. Payment status
                will only change when a payment record is
                received/updated in the system.

            </div>

        </div>

    <?php else: ?>

        <div class="empty-box">

            <h2>
                No Taxi Assigned
            </h2>

            <p>
                You currently do not have an active taxi
                assignment.
            </p>

        </div>

    <?php endif; ?>


    <!-- =================================================
         AGREEMENT
         ================================================= -->

    <?php if ($agreement): ?>

        <div class="agreement-box">

            <h2>
                Agreement
            </h2>

            <div class="pass-row">

                <strong>
                    Agreement ID
                </strong>

                <span>
                    <?= e($agreement["id"]) ?>
                </span>

            </div>

            <div class="pass-row">

                <strong>
                    Start Date
                </strong>

                <span>
                    <?= e($agreement["start_date"]) ?>
                </span>

            </div>

            <div class="pass-row">

                <strong>
                    End Date
                </strong>

                <span>
                    <?= e($agreement["end_date"]) ?>
                </span>

            </div>

            <div class="pass-row">

                <strong>
                    Rent
                </strong>

                <span>
                    ₹<?= number_format(
                        (float) $agreement["rent"],
                        2
                    ) ?>
                </span>

            </div>

            <div class="pass-row">

                <strong>
                    Agreement Status
                </strong>

                <span>
                    <?= e($agreement["status"]) ?>
                </span>

            </div>


            <?php if ((int) $agreement["accepted"] === 1): ?>

                <div class="pass-row">

                    <strong>
                        Acceptance
                    </strong>

                    <span class="accepted">
                        Accepted
                    </span>

                </div>

                <?php if ($agreement["accepted_at"]): ?>

                    <div class="pass-row">

                        <strong>
                            Accepted At
                        </strong>

                        <span>
                            <?= e(
                                $agreement["accepted_at"]
                            ) ?>
                        </span>

                    </div>

                <?php endif; ?>


            <?php else: ?>

                <div class="agreement-warning">

                    <strong>
                        Action Required
                    </strong>

                    <p>
                        Please review and accept your active
                        agreement.
                    </p>

                    <form
                        method="POST"
                        action=""
                    >

                        <?= csrf_field() ?>

                        <input
                            type="hidden"
                            name="agreement_id"
                            value="<?= e(
                                $agreement["id"]
                            ) ?>"
                        >

                        <button
                            type="submit"
                            name="accept_agreement"
                            class="accept-button"
                        >
                            Accept Agreement
                        </button>

                    </form>

                </div>

            <?php endif; ?>

        </div>

    <?php elseif ($assignment): ?>

        <div class="agreement-box">

            <h2>
                Agreement
            </h2>

            <p>
                No active agreement has been created for
                your current taxi assignment yet.
            </p>

        </div>

    <?php endif; ?>


    <!-- =================================================
         DRIVER SERVICES
         ================================================= -->

    <h2 style="margin-top:30px;">
        Driver Services
    </h2>

    <div class="service-grid">

        <div class="service-card">

            <h3>
                Rent Payment
            </h3>

            <p>
                View your current rent payment status
                and payment information.
            </p>

            <a
                href="payments.php"
                class="dashboard-button"
            >
                Payment History
            </a>

        </div>


        <div class="service-card">

            <h3>
                Taxi Availability
            </h3>

            <p>
                View currently available taxis.
            </p>

            <a
                href="../index2.php#taxis"
                class="dashboard-button"
            >
                View Taxis
            </a>

        </div>


        <div class="service-card">

            <h3>
                My Profile
            </h3>

            <p>
                View and manage your driver profile.
            </p>

            <a
                href="profile.php"
                class="dashboard-button"
            >
                Open Profile
            </a>

        </div>


        <div class="service-card">

            <h3>
                Documents
            </h3>

            <p>
                View your driver document information.
            </p>

            <a
                href="documents.php"
                class="dashboard-button"
            >
                My Documents
            </a>

        </div>

    </div>

</main>


<footer>

    <p>
        Taxi Management System
    </p>

</footer>

</body>

</html>