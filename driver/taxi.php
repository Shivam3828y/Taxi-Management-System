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


/* =====================================================
   DRIVER INFORMATION
   ===================================================== */

$driver_sql = "
    SELECT
        id,
        name,
        phone,
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
   ASSIGNED TAXI
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

$assignment_stmt = mysqli_prepare(
    $conn,
    $assignment_sql
);

if (!$assignment_stmt) {
    die("Database error: " . mysqli_error($conn));
}

mysqli_stmt_bind_param(
    $assignment_stmt,
    "i",
    $driver_id
);

mysqli_stmt_execute($assignment_stmt);

$assignment_result =
    mysqli_stmt_get_result($assignment_stmt);

$assignment =
    mysqli_fetch_assoc($assignment_result);

mysqli_stmt_close($assignment_stmt);


/* =====================================================
   ACTIVE AGREEMENT
   ===================================================== */

$agreement = null;

if ($assignment) {

    $agreement_sql = "
        SELECT
            id,
            rent,
            start_date,
            end_date,
            status,
            accepted,
            accepted_at
        FROM agreements
        WHERE assignment_id = ?
        AND status = 'Active'
        ORDER BY id DESC
        LIMIT 1
    ";

    $agreement_stmt = mysqli_prepare(
        $conn,
        $agreement_sql
    );

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
   LATEST PAYMENT
   ===================================================== */

$payment = null;

if ($assignment) {

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

    $payment_stmt = mysqli_prepare(
        $conn,
        $payment_sql
    );

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
   RENT AMOUNT
   ===================================================== */

$rent_amount = 0;

if ($agreement) {
    $rent_amount = (float) $agreement["rent"];
} elseif ($assignment) {
    $rent_amount = (float) $assignment["rent"];
}


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
        My Taxi - Taxi Management System
    </title>

    <link
        rel="stylesheet"
        href="../css/style.css"
    >

    <style>

        .taxi-container {
            max-width: 1000px;
            margin: 30px auto;
            padding: 0 15px;
        }

        .page-title {
            margin-bottom: 25px;
        }

        .taxi-card {
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 25px;
        }

        .taxi-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
            margin-bottom: 20px;
        }

        .taxi-title {
            margin: 0;
            font-size: 25px;
        }

        .status-badge {
            display: inline-block;
            padding: 7px 13px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: bold;
        }

        .active {
            background: #dcfce7;
            color: #166534;
        }

        .pending {
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

        .taxi-details {
            display: grid;
            grid-template-columns:
                repeat(
                    auto-fit,
                    minmax(220px, 1fr)
                );
            gap: 15px;
        }

        .detail-box {
            border: 1px solid #eee;
            border-radius: 8px;
            padding: 15px;
            background: #fafafa;
        }

        .detail-label {
            display: block;
            color: #666;
            font-size: 14px;
            margin-bottom: 5px;
        }

        .detail-value {
            font-size: 17px;
            font-weight: 600;
        }

        .payment-section {
            margin-top: 25px;
            padding-top: 25px;
            border-top: 1px solid #ddd;
        }

        .payment-title {
            margin-top: 0;
        }

        .payment-box {
            display: grid;
            grid-template-columns:
                repeat(
                    auto-fit,
                    minmax(220px, 1fr)
                );
            gap: 15px;
        }

        .payment-info {
            border: 1px solid #eee;
            border-radius: 8px;
            padding: 15px;
            background: #fafafa;
        }

        .pay-button {
            display: inline-block;
            margin-top: 20px;
            padding: 12px 22px;
            background: #15803d;
            color: #fff;
            text-decoration: none;
            border-radius: 7px;
            font-weight: bold;
        }

        .pay-button:hover {
            background: #166534;
        }

        .history-button {
            display: inline-block;
            margin-top: 20px;
            margin-left: 10px;
            padding: 12px 22px;
            background: #fff;
            color: #222;
            text-decoration: none;
            border: 1px solid #333;
            border-radius: 7px;
        }

        .empty-box {
            text-align: center;
            padding: 45px 25px;
            border: 1px dashed #aaa;
            border-radius: 12px;
            background: #fafafa;
        }

        .empty-box h2 {
            margin-top: 0;
        }

        .back-button {
            display: inline-block;
            margin-bottom: 20px;
            text-decoration: none;
            color: #333;
        }

        @media (max-width: 600px) {

            .taxi-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .history-button {
                margin-left: 0;
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

        <a href="taxi.php">
            My Taxi
        </a>

        <a href="payments.php">
            Payments
        </a>

        <a href="profile.php">
            Profile
        </a>

        <a href="logout.php">
            Logout
        </a>

    </nav>

</header>


<main class="taxi-container">

    <a
        href="dashboard.php"
        class="back-button"
    >
        ← Back to Dashboard
    </a>


    <h1 class="page-title">
        My Assigned Taxi
    </h1>


    <?php if ($assignment): ?>

        <div class="taxi-card">

            <div class="taxi-header">

                <h2 class="taxi-title">

                    <?= e($assignment["brand"]) ?>

                    <?= e($assignment["model"]) ?>

                </h2>

                <span class="status-badge active">

                    <?= e(
                        $assignment["assignment_status"]
                    ) ?>

                </span>

            </div>


            <!-- TAXI DETAILS -->

            <div class="taxi-details">

                <div class="detail-box">

                    <span class="detail-label">
                        Taxi ID
                    </span>

                    <span class="detail-value">
                        <?= e($assignment["taxi_id"]) ?>
                    </span>

                </div>


                <div class="detail-box">

                    <span class="detail-label">
                        Registration Number
                    </span>

                    <span class="detail-value">
                        <?= e(
                            $assignment["registration_number"]
                        ) ?>
                    </span>

                </div>


                <div class="detail-box">

                    <span class="detail-label">
                        Brand
                    </span>

                    <span class="detail-value">
                        <?= e($assignment["brand"]) ?>
                    </span>

                </div>


                <div class="detail-box">

                    <span class="detail-label">
                        Model
                    </span>

                    <span class="detail-value">
                        <?= e($assignment["model"]) ?>
                    </span>

                </div>


                <div class="detail-box">

                    <span class="detail-label">
                        Rent
                    </span>

                    <span class="detail-value">
                        ₹<?= number_format(
                            $rent_amount,
                            2
                        ) ?>
                    </span>

                </div>


                <div class="detail-box">

                    <span class="detail-label">
                        Taxi Status
                    </span>

                    <span class="detail-value">
                        <?= e(
                            $assignment["taxi_status"]
                        ) ?>
                    </span>

                </div>


                <div class="detail-box">

                    <span class="detail-label">
                        Assigned Date
                    </span>

                    <span class="detail-value">
                        <?= e(
                            $assignment["assigned_at"]
                        ) ?>
                    </span>

                </div>


                <div class="detail-box">

                    <span class="detail-label">
                        Driver
                    </span>

                    <span class="detail-value">
                        <?= e($driver["name"]) ?>
                    </span>

                </div>

            </div>


            <!-- PAYMENT -->

            <div class="payment-section">

                <h2 class="payment-title">
                    Rent Payment
                </h2>


                <div class="payment-box">

                    <div class="payment-info">

                        <span class="detail-label">
                            Rent Amount
                        </span>

                        <span class="detail-value">
                            ₹<?= number_format(
                                $rent_amount,
                                2
                            ) ?>
                        </span>

                    </div>


                    <div class="payment-info">

                        <span class="detail-label">
                            Payment Status
                        </span>

                        <span
                            class="<?= e(
                                $payment_status_class
                            ) ?>"
                        >
                            <?= e($payment_status) ?>
                        </span>

                    </div>


                    <div class="payment-info">

                        <span class="detail-label">
                            Last Payment
                        </span>

                        <span class="detail-value">

                            <?php if ($payment): ?>

                                <?= e(
                                    $payment["payment_date"]
                                ) ?>

                            <?php else: ?>

                                No payment recorded

                            <?php endif; ?>

                        </span>

                    </div>

                </div>


                <?php if ($payment_status !== "Paid"): ?>

                    <a
                        href="payments.php"
                        class="pay-button"
                    >
                        Pay Rent
                    </a>

                <?php else: ?>

                    <span
                        class="status-badge active"
                        style="margin-top:20px;"
                    >
                        Rent Paid
                    </span>

                <?php endif; ?>


                <a
                    href="payments.php"
                    class="history-button"
                >
                    Payment History
                </a>

            </div>

        </div>


    <?php else: ?>

        <div class="empty-box">

            <h2>
                No Taxi Assigned
            </h2>

            <p>
                You currently do not have an active taxi
                assigned to you.
            </p>

            <p>
                Please contact the administrator for
                taxi assignment.
            </p>

        </div>

    <?php endif; ?>

</main>


<footer>

    <p>
        Taxi Management System
    </p>

</footer>


</body>

</html>