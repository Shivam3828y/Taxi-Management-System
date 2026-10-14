<?php

require_once "../config.php";

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* =========================================================
   DRIVER LOGIN CHECK
   ========================================================= */

if (!isset($_SESSION["driver_id"])) {
    header("Location: login.php");
    exit;
}

$driver_id = (int) $_SESSION["driver_id"];

$message = "";
$error = "";

$request_id = (int) (
    $_GET["request_id"]
    ?? $_POST["request_id"]
    ?? 0
);


/* =========================================================
   LOAD DRIVER
   ========================================================= */

$stmt = mysqli_prepare(
    $conn,
    "SELECT id, name, phone, email, status
     FROM drivers
     WHERE id = ?
     LIMIT 1"
);

if (!$stmt) {
    die("Database error: " . mysqli_error($conn));
}

mysqli_stmt_bind_param($stmt, "i", $driver_id);
mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);
$driver = mysqli_fetch_assoc($result);

mysqli_stmt_close($stmt);

if (!$driver) {
    $_SESSION = [];
    session_destroy();

    header("Location: login.php");
    exit;
}


/* =========================================================
   DRIVER VERIFICATION
   ========================================================= */

if ($driver["status"] !== "Verified") {
    $error = "Your driver account is not verified.";
}


/* =========================================================
   LOAD TAXI REQUEST
   ========================================================= */

$taxi_request = null;

if ($request_id > 0 && $error === "") {

    $request_sql = "
        SELECT
            taxi_requests.id,
            taxi_requests.driver_id,
            taxi_requests.taxi_id,
            taxi_requests.amount,
            taxi_requests.payment_status,
            taxi_requests.request_status,
            taxi_requests.requested_at,
            taxi_requests.approved_at,

            taxis.brand,
            taxis.model,
            taxis.registration_number,
            taxis.rent,
            taxis.status AS taxi_status

        FROM taxi_requests

        INNER JOIN taxis
            ON taxi_requests.taxi_id = taxis.id

        WHERE taxi_requests.id = ?
        AND taxi_requests.driver_id = ?

        LIMIT 1
    ";

    $stmt = mysqli_prepare($conn, $request_sql);

    if (!$stmt) {

        $error = "Unable to load taxi request.";

    } else {

        mysqli_stmt_bind_param(
            $stmt,
            "ii",
            $request_id,
            $driver_id
        );

        mysqli_stmt_execute($stmt);

        $result = mysqli_stmt_get_result($stmt);

        $taxi_request = mysqli_fetch_assoc($result);

        mysqli_stmt_close($stmt);

        if (!$taxi_request) {
            $error = "Taxi request not found.";
        }
    }
}


/* =========================================================
   PROCESS REQUEST PAYMENT CONFIRMATION
   ========================================================= */

if (
    $_SERVER["REQUEST_METHOD"] === "POST"
    && isset($_POST["confirm_request_payment"])
    && $error === ""
) {

    if (function_exists("csrf_verify")) {
        csrf_verify();
    }

    $confirm_request_id =
        (int) ($_POST["request_id"] ?? 0);

    if ($confirm_request_id <= 0) {

        $error = "Invalid taxi request.";

    } else {

        mysqli_begin_transaction($conn);

        try {

            /* -------------------------------------------------
               LOCK REQUEST
               ------------------------------------------------- */

            $sql = "
                SELECT
                    id,
                    driver_id,
                    taxi_id,
                    amount,
                    payment_status,
                    request_status

                FROM taxi_requests

                WHERE id = ?
                AND driver_id = ?

                LIMIT 1

                FOR UPDATE
            ";

            $stmt = mysqli_prepare($conn, $sql);

            if (!$stmt) {
                throw new Exception(
                    "Unable to verify taxi request."
                );
            }

            mysqli_stmt_bind_param(
                $stmt,
                "ii",
                $confirm_request_id,
                $driver_id
            );

            mysqli_stmt_execute($stmt);

            $result = mysqli_stmt_get_result($stmt);

            $request = mysqli_fetch_assoc($result);

            mysqli_stmt_close($stmt);

            if (!$request) {
                throw new Exception(
                    "Taxi request not found."
                );
            }


            /* -------------------------------------------------
               ALREADY PAID
               ------------------------------------------------- */

            if ($request["payment_status"] === "Paid") {
                throw new Exception(
                    "This taxi request is already marked as Paid."
                );
            }


            /* -------------------------------------------------
               REQUEST MUST STILL BE PENDING
               ------------------------------------------------- */

            if ($request["request_status"] !== "Pending") {
                throw new Exception(
                    "This taxi request is no longer pending."
                );
            }


            /* -------------------------------------------------
               UPDATE PAYMENT STATUS
               ------------------------------------------------- */

            $update_sql = "
                UPDATE taxi_requests

                SET payment_status = 'Paid'

                WHERE id = ?
                AND driver_id = ?
                AND payment_status = 'Pending'
                AND request_status = 'Pending'
            ";

            $update_stmt = mysqli_prepare(
                $conn,
                $update_sql
            );

            if (!$update_stmt) {
                throw new Exception(
                    "Unable to update payment status."
                );
            }

            mysqli_stmt_bind_param(
                $update_stmt,
                "ii",
                $confirm_request_id,
                $driver_id
            );

            if (!mysqli_stmt_execute($update_stmt)) {

                mysqli_stmt_close($update_stmt);

                throw new Exception(
                    "Payment status could not be updated."
                );
            }

            $affected_rows =
                mysqli_stmt_affected_rows(
                    $update_stmt
                );

            mysqli_stmt_close($update_stmt);

            if ($affected_rows <= 0) {
                throw new Exception(
                    "Payment status was not changed."
                );
            }

            mysqli_commit($conn);

            $message =
                "Payment confirmation submitted successfully. "
                . "The admin can now process your taxi request.";


            /* -------------------------------------------------
               RELOAD REQUEST
               ------------------------------------------------- */

            $stmt = mysqli_prepare(
                $conn,
                "
                SELECT
                    taxi_requests.id,
                    taxi_requests.driver_id,
                    taxi_requests.taxi_id,
                    taxi_requests.amount,
                    taxi_requests.payment_status,
                    taxi_requests.request_status,
                    taxi_requests.requested_at,

                    taxis.brand,
                    taxis.model,
                    taxis.registration_number,
                    taxis.rent

                FROM taxi_requests

                INNER JOIN taxis
                    ON taxi_requests.taxi_id = taxis.id

                WHERE taxi_requests.id = ?
                AND taxi_requests.driver_id = ?

                LIMIT 1
                "
            );

            if ($stmt) {

                mysqli_stmt_bind_param(
                    $stmt,
                    "ii",
                    $confirm_request_id,
                    $driver_id
                );

                mysqli_stmt_execute($stmt);

                $result =
                    mysqli_stmt_get_result($stmt);

                $taxi_request =
                    mysqli_fetch_assoc($result);

                mysqli_stmt_close($stmt);
            }

        } catch (Throwable $e) {

            mysqli_rollback($conn);

            $error = $e->getMessage();
        }
    }
}


/* =========================================================
   CURRENT ACTIVE ASSIGNMENT
   ========================================================= */

$assignment = null;

$assignment_sql = "
    SELECT

        assignments.id AS assignment_id,
        assignments.assigned_at,

        taxis.id AS taxi_id,
        taxis.brand,
        taxis.model,
        taxis.registration_number,
        taxis.rent

    FROM assignments

    INNER JOIN taxis
        ON assignments.taxi_id = taxis.id

    WHERE assignments.driver_id = ?

    AND assignments.status = 'Active'

    ORDER BY assignments.id DESC

    LIMIT 1
";

$assignment_stmt = mysqli_prepare(
    $conn,
    $assignment_sql
);

if ($assignment_stmt) {

    mysqli_stmt_bind_param(
        $assignment_stmt,
        "i",
        $driver_id
    );

    mysqli_stmt_execute(
        $assignment_stmt
    );

    $assignment_result =
        mysqli_stmt_get_result(
            $assignment_stmt
        );

    $assignment =
        mysqli_fetch_assoc(
            $assignment_result
        );

    mysqli_stmt_close(
        $assignment_stmt
    );
}


/* =========================================================
   LATEST ASSIGNMENT PAYMENT
   ========================================================= */

$latest_payment = null;

if ($assignment) {

    $latest_payment_sql = "
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

    $latest_payment_stmt = mysqli_prepare(
        $conn,
        $latest_payment_sql
    );

    if ($latest_payment_stmt) {

        mysqli_stmt_bind_param(
            $latest_payment_stmt,
            "ii",
            $driver_id,
            $assignment["assignment_id"]
        );

        mysqli_stmt_execute(
            $latest_payment_stmt
        );

        $latest_payment_result =
            mysqli_stmt_get_result(
                $latest_payment_stmt
            );

        $latest_payment =
            mysqli_fetch_assoc(
                $latest_payment_result
            );

        mysqli_stmt_close(
            $latest_payment_stmt
        );
    }
}


/* =========================================================
   PAYMENT SUMMARY
   ========================================================= */

$total_paid = 0;
$payment_count = 0;

$summary_sql = "
    SELECT
        COALESCE(SUM(amount), 0) AS total_paid,
        COUNT(*) AS payment_count

    FROM payments

    WHERE driver_id = ?
    AND status = 'Paid'
";

$summary_stmt = mysqli_prepare(
    $conn,
    $summary_sql
);

if ($summary_stmt) {

    mysqli_stmt_bind_param(
        $summary_stmt,
        "i",
        $driver_id
    );

    mysqli_stmt_execute(
        $summary_stmt
    );

    $summary_result =
        mysqli_stmt_get_result(
            $summary_stmt
        );

    $summary =
        mysqli_fetch_assoc(
            $summary_result
        );

    if ($summary) {

        $total_paid =
            (float) $summary["total_paid"];

        $payment_count =
            (int) $summary["payment_count"];
    }

    mysqli_stmt_close(
        $summary_stmt
    );
}


/* =========================================================
   PAYMENT HISTORY
   ========================================================= */

$payments = [];

$payments_sql = "
    SELECT

        payments.id,
        payments.amount,
        payments.payment_date,
        payments.payment_method,
        payments.status,
        payments.notes,

        taxis.brand,
        taxis.model,
        taxis.registration_number

    FROM payments

    INNER JOIN assignments
        ON payments.assignment_id = assignments.id

    INNER JOIN taxis
        ON assignments.taxi_id = taxis.id

    WHERE payments.driver_id = ?

    ORDER BY
        payments.payment_date DESC,
        payments.id DESC
";

$payments_stmt = mysqli_prepare(
    $conn,
    $payments_sql
);

if ($payments_stmt) {

    mysqli_stmt_bind_param(
        $payments_stmt,
        "i",
        $driver_id
    );

    mysqli_stmt_execute(
        $payments_stmt
    );

    $payments_result =
        mysqli_stmt_get_result(
            $payments_stmt
        );

    while (
        $row = mysqli_fetch_assoc(
            $payments_result
        )
    ) {

        $payments[] = $row;
    }

    mysqli_stmt_close(
        $payments_stmt
    );
}


/* =========================================================
   UPI SETTINGS
   ========================================================= */

$upi_id = "shivam3828y@okhdfcbank";

$upi_name = "Taxi Management System";


/* =========================================================
   REQUEST PAYMENT UPI
   ========================================================= */

$request_payment_amount = 0;

$request_upi_uri = "";

$request_qr_url = "";

if ($taxi_request) {

    $request_payment_amount =
        (float) $taxi_request["amount"];

    if (
        $request_payment_amount > 0
        && $upi_id !== ""
    ) {

        $request_upi_uri =
            "upi://pay"
            . "?pa="
            . rawurlencode($upi_id)

            . "&pn="
            . rawurlencode($upi_name)

            . "&am="
            . rawurlencode(
                number_format(
                    $request_payment_amount,
                    2,
                    ".",
                    ""
                )
            )

            . "&cu=INR"

            . "&tn="
            . rawurlencode(
                "Taxi Request #"
                . (int) $taxi_request["id"]
                . " - Driver "
                . $driver_id
            );

        $request_qr_url =
            "https://api.qrserver.com/v1/create-qr-code/"
            . "?size=280x280"
            . "&data="
            . rawurlencode(
                $request_upi_uri
            );
    }
}


/* =========================================================
   ASSIGNED TAXI PAYMENT UPI
   ========================================================= */

$payment_amount = 0;

$upi_uri = "";

$qr_url = "";

$upi_note = "";

if ($assignment) {

    $payment_amount =
        (float) $assignment["rent"];

    $upi_note =
        "Taxi Rent - Driver ID "
        . $driver_id;

    if (
        $payment_amount > 0
        && $upi_id !== ""
    ) {

        $upi_uri =
            "upi://pay"

            . "?pa="
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
            . "?size=260x260"
            . "&data="
            . rawurlencode(
                $upi_uri
            );
    }
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
        Rent Payments - Taxi Management System
    </title>

    <link
        rel="stylesheet"
        href="../css/style.css"
    >

    <style>

        * {
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
            background: #f5f6fa;
            color: #222;
            margin: 0;
        }

        header {
            background: #111827;
            color: white;
            padding: 20px;
        }

        header h1 {
            margin: 0 0 15px 0;
        }

        nav {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }

        nav a {
            color: white;
            text-decoration: none;
        }

        .payments-container {
            max-width: 1100px;
            margin: 30px auto;
            padding: 0 20px;
        }

        .page-header {
            margin-bottom: 25px;
        }

        .card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 5px 20px rgba(0,0,0,.06);
        }

        .request-payment-card {
            border: 2px solid #16a34a;
        }

        .request-payment-card h2 {
            margin-top: 0;
        }

        .payment-grid {
            display: grid;
            grid-template-columns:
                repeat(
                    auto-fit,
                    minmax(220px, 1fr)
                );
            gap: 20px;
            margin: 20px 0;
        }

        .payment-card {
            border: 1px solid #ddd;
            border-radius: 10px;
            padding: 20px;
            background: #fff;
        }

        .payment-card h3 {
            margin-top: 0;
        }

        .amount {
            font-size: 24px;
            font-weight: bold;
        }

        .paid {
            color: #166534;
            font-weight: bold;
        }

        .pending {
            color: #b45309;
            font-weight: bold;
        }

        .failed {
            color: #b91c1c;
            font-weight: bold;
        }

        .taxi-payment-card {
            border: 2px solid #222;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 30px;
            background: #fff;
        }

        .taxi-details {
            display: grid;
            grid-template-columns:
                repeat(
                    auto-fit,
                    minmax(220px, 1fr)
                );
            gap: 12px;
            margin-bottom: 25px;
        }

        .detail-box {
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 15px;
            background: #fafafa;
        }

        .detail-label {
            display: block;
            color: #666;
            font-size: 13px;
            margin-bottom: 5px;
        }

        .detail-value {
            font-size: 17px;
            font-weight: 600;
        }

        .upi-section {
            border-top: 1px solid #ddd;
            padding-top: 25px;
            text-align: center;
        }

        .qr-box {
            margin: 20px auto;
            width: 300px;
            min-height: 300px;
            border: 1px solid #ddd;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #fff;
        }

        .qr-box img {
            width: 280px;
            height: 280px;
            object-fit: contain;
        }

        .upi-placeholder {
            padding: 30px;
            color: #666;
        }

        .button {
            display: inline-block;
            border: none;
            padding: 13px 22px;
            border-radius: 8px;
            font-size: 15px;
            cursor: pointer;
            text-decoration: none;
            margin: 5px;
        }

        .primary {
            background: #111827;
            color: white;
        }

        .green {
            background: #16a34a;
            color: white;
            font-weight: bold;
        }

        .green:hover {
            background: #15803d;
        }

        .secondary {
            background: #e5e7eb;
            color: #111827;
        }

        .blue {
            background: #2563eb;
            color: white;
        }

        .message {
            background: #dcfce7;
            color: #166534;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .error {
            background: #fee2e2;
            color: #991b1b;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .notice {
            margin-top: 15px;
            padding: 15px;
            border-radius: 8px;
            background: #fff7ed;
            color: #9a3412;
            font-size: 14px;
            line-height: 1.5;
        }

        .success-box {
            margin-top: 20px;
            padding: 18px;
            border-radius: 8px;
            background: #dcfce7;
            color: #166534;
        }

        .payment-status {
            margin-top: 20px;
            padding: 15px;
            border-radius: 8px;
            background: #f5f5f5;
        }

        .payment-row {
            display: flex;
            justify-content: space-between;
            gap: 20px;
            padding: 10px 0;
            border-bottom: 1px solid #ddd;
        }

        .payment-row:last-child {
            border-bottom: none;
        }

        .table-container {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            background: #fff;
        }

        th,
        td {
            border: 1px solid #ddd;
            padding: 12px;
            text-align: left;
        }

        th {
            background: #f3f3f3;
        }

        .status-badge {
            display: inline-block;
            padding: 5px 9px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: bold;
        }

        .status-paid {
            background: #dcfce7;
            color: #166534;
        }

        .status-pending {
            background: #fef3c7;
            color: #92400e;
        }

        .status-failed {
            background: #fee2e2;
            color: #991b1b;
        }

        .empty-card {
            padding: 30px;
            text-align: center;
            border: 1px solid #ddd;
            border-radius: 10px;
            background: #fff;
        }

        @media (max-width: 600px) {

            .payments-container {
                padding: 0 12px;
            }

            .payment-row {
                flex-direction: column;
                gap: 5px;
            }

            .qr-box {
                width: 240px;
                min-height: 240px;
            }

            .qr-box img {
                width: 220px;
                height: 220px;
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

        <a href="payments.php">
            Rent Payments
        </a>

        <a href="../index2.php#taxis">
            Taxi Availability
        </a>

        <a href="logout.php">
            Logout
        </a>

    </nav>

</header>


<main class="payments-container">


    <?php if ($message): ?>

        <div class="message">
            <?= htmlspecialchars($message) ?>
        </div>

    <?php endif; ?>


    <?php if ($error): ?>

        <div class="error">
            <?= htmlspecialchars($error) ?>
        </div>

    <?php endif; ?>


    <section class="page-header">

        <h2>
            Rent Payments
        </h2>

        <p>
            Welcome,
            <strong>
                <?= htmlspecialchars($driver["name"]) ?>
            </strong>
        </p>

    </section>


    <!-- =====================================================
         TAXI REQUEST PAYMENT
    ====================================================== -->

    <?php if ($taxi_request): ?>

        <section class="card request-payment-card">

            <h2>
                Taxi Request Payment
            </h2>

            <p>
                Complete the payment for your taxi request.
            </p>


            <div class="taxi-details">

                <div class="detail-box">

                    <span class="detail-label">
                        Request ID
                    </span>

                    <span class="detail-value">
                        #<?= (int) $taxi_request["id"] ?>
                    </span>

                </div>


                <div class="detail-box">

                    <span class="detail-label">
                        Taxi
                    </span>

                    <span class="detail-value">

                        <?= htmlspecialchars(
                            $taxi_request["brand"]
                            . " "
                            . $taxi_request["model"]
                        ) ?>

                    </span>

                </div>


                <div class="detail-box">

                    <span class="detail-label">
                        Registration Number
                    </span>

                    <span class="detail-value">

                        <?= htmlspecialchars(
                            $taxi_request[
                                "registration_number"
                            ]
                        ) ?>

                    </span>

                </div>


                <div class="detail-box">

                    <span class="detail-label">
                        Amount
                    </span>

                    <span class="detail-value">

                        ₹<?= number_format(
                            $request_payment_amount,
                            2
                        ) ?>

                    </span>

                </div>


                <div class="detail-box">

                    <span class="detail-label">
                        Payment Status
                    </span>

                    <span class="detail-value">

                        <?php if (
                            $taxi_request["payment_status"]
                            === "Paid"
                        ): ?>

                            <span class="paid">
                                PAID
                            </span>

                        <?php else: ?>

                            <span class="pending">
                                PENDING
                            </span>

                        <?php endif; ?>

                    </span>

                </div>


                <div class="detail-box">

                    <span class="detail-label">
                        Request Status
                    </span>

                    <span class="detail-value">

                        <?= htmlspecialchars(
                            $taxi_request["request_status"]
                        ) ?>

                    </span>

                </div>

            </div>


            <?php if (
                $taxi_request["payment_status"]
                === "Paid"
            ): ?>

                <div class="success-box">

                    <strong>
                        Payment Completed
                    </strong>

                    <br><br>

                    Your payment confirmation has been recorded.

                    <br><br>

                    The admin can now process your taxi request.

                </div>


            <?php elseif (
                $taxi_request["request_status"]
                === "Pending"
            ): ?>


                <div class="upi-section">

                    <h3>
                        Pay Using UPI
                    </h3>

                    <p>
                        Scan the QR code using your UPI application.
                    </p>


                    <?php if ($request_qr_url !== ""): ?>

                        <div class="qr-box">

                            <img
                                src="<?= htmlspecialchars(
                                    $request_qr_url
                                ) ?>"
                                alt="UPI Payment QR Code"
                            >

                        </div>


                        <div class="amount">

                            ₹<?= number_format(
                                $request_payment_amount,
                                2
                            ) ?>

                        </div>


                        <p>
                            UPI ID:
                            <strong>
                                <?= htmlspecialchars($upi_id) ?>
                            </strong>
                        </p>


                        <a
                            href="<?= htmlspecialchars(
                                $request_upi_uri
                            ) ?>"
                            class="button green"
                        >
                            Pay via UPI App
                        </a>


                    <?php else: ?>

                        <div class="qr-box">

                            <div class="upi-placeholder">

                                <strong>
                                    UPI QR could not be generated.
                                </strong>

                                <br><br>

                                UPI ID:
                                <?= htmlspecialchars($upi_id) ?>

                            </div>

                        </div>

                    <?php endif; ?>


                    <div class="notice">

                        After you actually complete the UPI payment,
                        use the button below.

                        <br><br>

                        This button records your payment confirmation
                        in the taxi request. It does not independently
                        verify the bank/UPI transaction.

                    </div>


                    <form
                        method="POST"
                        style="margin-top: 20px;"
                    >

                        <input
                            type="hidden"
                            name="request_id"
                            value="<?= (int) $taxi_request["id"] ?>"
                        >


                        <?php if (
                            function_exists("csrf_token")
                        ): ?>

                            <input
                                type="hidden"
                                name="csrf_token"
                                value="<?= htmlspecialchars(
                                    csrf_token()
                                ) ?>"
                            >

                        <?php endif; ?>


                        <button
                            type="submit"
                            name="confirm_request_payment"
                            class="button blue"
                            onclick="
                                return confirm(
                                    'Have you actually completed the UPI payment?'
                                );
                            "
                        >
                            I Have Completed Payment
                        </button>

                    </form>

                </div>

            <?php endif; ?>


            <div style="margin-top:20px;">

                <a
                    href="dashboard.php"
                    class="button primary"
                >
                    Dashboard
                </a>

                <a
                    href="../index2.php"
                    class="button secondary"
                >
                    Taxi Portal
                </a>

            </div>

        </section>

    <?php endif; ?>


    <!-- =====================================================
         CURRENT ASSIGNED TAXI
    ====================================================== -->

    <?php if ($assignment): ?>

        <section class="taxi-payment-card">

            <div>

                <h2>
                    Assigned Taxi
                </h2>

                <p>
                    Pay the rent for your currently assigned taxi.
                </p>

            </div>


            <div class="taxi-details">

                <div class="detail-box">

                    <span class="detail-label">
                        Taxi
                    </span>

                    <span class="detail-value">

                        <?= htmlspecialchars(
                            $assignment["brand"]
                            . " "
                            . $assignment["model"]
                        ) ?>

                    </span>

                </div>


                <div class="detail-box">

                    <span class="detail-label">
                        Registration Number
                    </span>

                    <span class="detail-value">

                        <?= htmlspecialchars(
                            $assignment[
                                "registration_number"
                            ]
                        ) ?>

                    </span>

                </div>


                <div class="detail-box">

                    <span class="detail-label">
                        Rent
                    </span>

                    <span class="detail-value">

                        ₹<?= number_format(
                            $payment_amount,
                            2
                        ) ?>

                    </span>

                </div>


                <div class="detail-box">

                    <span class="detail-label">
                        Assignment ID
                    </span>

                    <span class="detail-value">

                        #<?= (int)
                            $assignment[
                                "assignment_id"
                            ] ?>

                    </span>

                </div>

            </div>


            <div class="upi-section">

                <h3>
                    Pay Rent Using UPI
                </h3>


                <?php if ($qr_url !== ""): ?>

                    <div class="qr-box">

                        <img
                            src="<?= htmlspecialchars(
                                $qr_url
                            ) ?>"
                            alt="UPI Payment QR Code"
                        >

                    </div>


                    <div class="amount">

                        ₹<?= number_format(
                            $payment_amount,
                            2
                        ) ?>

                    </div>


                    <p>
                        UPI ID:
                        <strong>
                            <?= htmlspecialchars($upi_id) ?>
                        </strong>
                    </p>


                    <a
                        href="<?= htmlspecialchars(
                            $upi_uri
                        ) ?>"
                        class="button green"
                    >
                        Pay via UPI App
                    </a>


                <?php else: ?>

                    <div class="qr-box">

                        <div class="upi-placeholder">

                            <strong>
                                UPI QR could not be generated.
                            </strong>

                            <br><br>

                            UPI ID:
                            <?= htmlspecialchars($upi_id) ?>

                        </div>

                    </div>

                <?php endif; ?>

            </div>


            <div class="payment-status">

                <strong>
                    Payment Status:
                </strong>


                <?php if ($latest_payment): ?>

                    <?php if (
                        $latest_payment["status"]
                        === "Paid"
                    ): ?>

                        <span class="paid">
                            PAID
                        </span>

                    <?php elseif (
                        $latest_payment["status"]
                        === "Pending"
                    ): ?>

                        <span class="pending">
                            PENDING
                        </span>

                    <?php else: ?>

                        <span class="failed">

                            <?= htmlspecialchars(
                                strtoupper(
                                    $latest_payment["status"]
                                )
                            ) ?>

                        </span>

                    <?php endif; ?>

                <?php else: ?>

                    <span class="pending">
                        PENDING
                    </span>

                <?php endif; ?>


                <?php if ($latest_payment): ?>

                    <div class="payment-row">

                        <span>
                            Last Payment
                        </span>

                        <strong>

                            ₹<?= number_format(
                                (float)
                                $latest_payment["amount"],
                                2
                            ) ?>

                        </strong>

                    </div>


                    <div class="payment-row">

                        <span>
                            Payment Date
                        </span>

                        <strong>

                            <?= htmlspecialchars(
                                $latest_payment[
                                    "payment_date"
                                ]
                            ) ?>

                        </strong>

                    </div>


                    <div class="payment-row">

                        <span>
                            Payment Method
                        </span>

                        <strong>

                            <?= htmlspecialchars(
                                $latest_payment[
                                    "payment_method"
                                ] ?? "—"
                            ) ?>

                        </strong>

                    </div>

                <?php endif; ?>

            </div>

        </section>


    <?php elseif (!$taxi_request): ?>

        <section class="empty-card">

            <h2>
                No Taxi Assigned
            </h2>

            <p>
                You currently do not have an active taxi
                assignment and there is no payment request.
            </p>

        </section>

    <?php endif; ?>


    <!-- =====================================================
         PAYMENT SUMMARY
    ====================================================== -->

    <section>

        <h2>
            Payment Summary
        </h2>


        <div class="payment-grid">

            <div class="payment-card">

                <h3>
                    Total Paid
                </h3>

                <div class="amount">

                    ₹<?= number_format(
                        $total_paid,
                        2
                    ) ?>

                </div>

            </div>


            <div class="payment-card">

                <h3>
                    Payments Made
                </h3>

                <div class="amount">

                    <?= $payment_count ?>

                </div>

            </div>


            <div class="payment-card">

                <h3>
                    Account Status
                </h3>

                <div>

                    <?php if (
                        $driver["status"]
                        === "Verified"
                    ): ?>

                        <span class="paid">
                            Verified
                        </span>

                    <?php elseif (
                        $driver["status"]
                        === "Pending"
                    ): ?>

                        <span class="pending">
                            Pending
                        </span>

                    <?php else: ?>

                        <span class="failed">
                            Inactive
                        </span>

                    <?php endif; ?>

                </div>

            </div>

        </div>

    </section>


    <!-- =====================================================
         PAYMENT HISTORY
    ====================================================== -->

    <section class="card">

        <h2>
            Payment History
        </h2>


        <?php if (!empty($payments)): ?>

            <div class="table-container">

                <table>

                    <thead>

                        <tr>

                            <th>
                                Payment ID
                            </th>

                            <th>
                                Date
                            </th>

                            <th>
                                Taxi
                            </th>

                            <th>
                                Registration
                            </th>

                            <th>
                                Amount
                            </th>

                            <th>
                                Method
                            </th>

                            <th>
                                Status
                            </th>

                            <th>
                                Notes
                            </th>

                        </tr>

                    </thead>


                    <tbody>

                        <?php foreach (
                            $payments as $payment
                        ): ?>

                            <tr>

                                <td>
                                    #<?= (int)
                                        $payment["id"] ?>
                                </td>


                                <td>

                                    <?= htmlspecialchars(
                                        $payment[
                                            "payment_date"
                                        ]
                                    ) ?>

                                </td>


                                <td>

                                    <?= htmlspecialchars(
                                        $payment["brand"]
                                        . " "
                                        . $payment["model"]
                                    ) ?>

                                </td>


                                <td>

                                    <?= htmlspecialchars(
                                        $payment[
                                            "registration_number"
                                        ]
                                    ) ?>

                                </td>


                                <td>

                                    ₹<?= number_format(
                                        (float)
                                        $payment["amount"],
                                        2
                                    ) ?>

                                </td>


                                <td>

                                    <?= htmlspecialchars(
                                        $payment[
                                            "payment_method"
                                        ]
                                    ) ?>

                                </td>


                                <td>

                                    <?php
                                    $status =
                                        $payment["status"];
                                    ?>


                                    <?php if (
                                        $status === "Paid"
                                    ): ?>

                                        <span
                                            class="status-badge status-paid"
                                        >
                                            Paid
                                        </span>

                                    <?php elseif (
                                        $status === "Pending"
                                    ): ?>

                                        <span
                                            class="status-badge status-pending"
                                        >
                                            Pending
                                        </span>

                                    <?php else: ?>

                                        <span
                                            class="status-badge status-failed"
                                        >

                                            <?= htmlspecialchars(
                                                $status
                                            ) ?>

                                        </span>

                                    <?php endif; ?>

                                </td>


                                <td>

                                    <?php if (
                                        !empty(
                                            $payment["notes"]
                                        )
                                    ): ?>

                                        <?= htmlspecialchars(
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


        <?php else: ?>

            <div class="empty-card">

                <h3>
                    No Payment Records
                </h3>

                <p>
                    No completed rent payments have been
                    recorded for your account yet.
                </p>

            </div>

        <?php endif; ?>

    </section>


    <a
        class="button secondary"
        href="dashboard.php"
    >
        Back to Dashboard
    </a>


</main>


<footer
    style="text-align:center;padding:25px;"
>

    <p>
        &copy; 2026 Taxi Management System
    </p>

</footer>


</body>

</html>