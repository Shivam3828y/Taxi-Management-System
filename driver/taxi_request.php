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

mysqli_stmt_bind_param($stmt, "i", $driver_id);
mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);
$driver = mysqli_fetch_assoc($result);

mysqli_stmt_close($stmt);

if (!$driver) {
    session_destroy();
    header("Location: login.php");
    exit;
}


/* =========================================================
   DRIVER VERIFICATION CHECK
========================================================= */

if ($driver["status"] !== "Verified") {
    ?>
    <!DOCTYPE html>
    <html lang="en">

    <head>
        <meta charset="UTF-8">
        <meta
            name="viewport"
            content="width=device-width, initial-scale=1.0"
        >
        <title>Verification Required</title>

        <style>

            * {
                box-sizing: border-box;
                margin: 0;
                padding: 0;
            }

            body {
                font-family: Arial, sans-serif;
                background: #f5f6fa;
                min-height: 100vh;
                display: flex;
                justify-content: center;
                align-items: center;
                padding: 20px;
            }

            .box {
                width: 100%;
                max-width: 500px;
                background: white;
                padding: 35px;
                border-radius: 14px;
                text-align: center;
                box-shadow: 0 10px 30px rgba(0,0,0,0.08);
            }

            h1 {
                margin-bottom: 15px;
                color: #222;
            }

            p {
                color: #666;
                line-height: 1.6;
                margin-bottom: 25px;
            }

            a {
                display: inline-block;
                padding: 12px 22px;
                background: #111827;
                color: white;
                text-decoration: none;
                border-radius: 8px;
            }

            a:hover {
                background: #000;
            }

        </style>
    </head>

    <body>

        <div class="box">

            <h1>
                Verification Required
            </h1>

            <p>
                Your driver account is currently
                <strong>
                    <?= htmlspecialchars($driver["status"]) ?>
                </strong>.
                You can request a taxi only after admin verification.
            </p>

            <a href="../index2.php">
                Back to Taxi Portal
            </a>

        </div>

    </body>

    </html>

    <?php
    exit;
}


/* =========================================================
   GET TAXI ID
========================================================= */

$taxi_id = isset($_GET["taxi_id"])
    ? (int) $_GET["taxi_id"]
    : (
        isset($_POST["taxi_id"])
            ? (int) $_POST["taxi_id"]
            : 0
    );


if ($taxi_id <= 0) {
    header("Location: ../index2.php");
    exit;
}


/* =========================================================
   LOAD TAXI
========================================================= */

$stmt = mysqli_prepare(
    $conn,
    "SELECT
        id,
        brand,
        model,
        registration_number,
        rent,
        status
     FROM taxis
     WHERE id = ?
     LIMIT 1"
);

mysqli_stmt_bind_param(
    $stmt,
    "i",
    $taxi_id
);

mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);
$taxi = mysqli_fetch_assoc($result);

mysqli_stmt_close($stmt);


if (!$taxi) {
    die("Taxi not found.");
}


/* =========================================================
   CHECK TAXI AVAILABILITY
========================================================= */

if ($taxi["status"] !== "Available") {
    ?>

    <!DOCTYPE html>
    <html lang="en">

    <head>

        <meta charset="UTF-8">

        <meta
            name="viewport"
            content="width=device-width, initial-scale=1.0"
        >

        <title>Taxi Not Available</title>

        <style>

            body {
                font-family: Arial, sans-serif;
                background: #f5f6fa;
                display: flex;
                justify-content: center;
                align-items: center;
                min-height: 100vh;
                padding: 20px;
            }

            .box {
                background: white;
                padding: 35px;
                max-width: 500px;
                width: 100%;
                text-align: center;
                border-radius: 14px;
                box-shadow: 0 10px 30px rgba(0,0,0,.08);
            }

            h1 {
                margin-bottom: 15px;
            }

            p {
                color: #666;
                margin-bottom: 25px;
            }

            a {
                display: inline-block;
                background: #111827;
                color: white;
                padding: 12px 22px;
                border-radius: 8px;
                text-decoration: none;
            }

        </style>

    </head>

    <body>

        <div class="box">

            <h1>
                Taxi Not Available
            </h1>

            <p>
                This taxi is no longer available.
                Please return to the taxi portal and select another taxi.
            </p>

            <a href="../index2.php">
                View Available Taxis
            </a>

        </div>

    </body>

    </html>

    <?php
    exit;
}


/* =========================================================
   CHECK ACTIVE ASSIGNMENT
========================================================= */

$stmt = mysqli_prepare(
    $conn,
    "SELECT id
     FROM assignments
     WHERE driver_id = ?
       AND status = 'Active'
     LIMIT 1"
);

mysqli_stmt_bind_param(
    $stmt,
    "i",
    $driver_id
);

mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);
$active_assignment = mysqli_fetch_assoc($result);

mysqli_stmt_close($stmt);


if ($active_assignment) {
    ?>

    <!DOCTYPE html>
    <html lang="en">

    <head>

        <meta charset="UTF-8">

        <meta
            name="viewport"
            content="width=device-width, initial-scale=1.0"
        >

        <title>Taxi Already Assigned</title>

        <style>

            body {
                font-family: Arial, sans-serif;
                background: #f5f6fa;
                display: flex;
                justify-content: center;
                align-items: center;
                min-height: 100vh;
                padding: 20px;
            }

            .box {
                background: white;
                padding: 35px;
                max-width: 500px;
                width: 100%;
                text-align: center;
                border-radius: 14px;
                box-shadow: 0 10px 30px rgba(0,0,0,.08);
            }

            h1 {
                margin-bottom: 15px;
            }

            p {
                color: #666;
                line-height: 1.6;
                margin-bottom: 25px;
            }

            a {
                display: inline-block;
                background: #111827;
                color: white;
                padding: 12px 22px;
                border-radius: 8px;
                text-decoration: none;
            }

        </style>

    </head>

    <body>

        <div class="box">

            <h1>
                Taxi Already Assigned
            </h1>

            <p>
                You already have an active taxi assignment.
                You cannot request another taxi until the current assignment ends.
            </p>

            <a href="taxi.php">
                View My Taxi
            </a>

        </div>

    </body>

    </html>

    <?php
    exit;
}


/* =========================================================
   CHECK EXISTING PENDING REQUEST
========================================================= */

$stmt = mysqli_prepare(
    $conn,
    "SELECT
        id,
        payment_status,
        request_status
     FROM taxi_requests
     WHERE driver_id = ?
       AND taxi_id = ?
       AND request_status = 'Pending'
     LIMIT 1"
);

mysqli_stmt_bind_param(
    $stmt,
    "ii",
    $driver_id,
    $taxi_id
);

mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);
$existing_request = mysqli_fetch_assoc($result);

mysqli_stmt_close($stmt);


/* =========================================================
   PROCESS REQUEST
========================================================= */

$message = "";
$error = "";


if ($_SERVER["REQUEST_METHOD"] === "POST") {

    if (function_exists("csrf_verify")) {
        csrf_verify();
    }


    /* ---------------------------------------------
       RE-CHECK DRIVER VERIFICATION
    --------------------------------------------- */

    if ($driver["status"] !== "Verified") {

        $error = "Your driver account is not verified.";

    }


    /* ---------------------------------------------
       RE-CHECK ACTIVE ASSIGNMENT
    --------------------------------------------- */

    if ($error === "") {

        $stmt = mysqli_prepare(
            $conn,
            "SELECT id
             FROM assignments
             WHERE driver_id = ?
               AND status = 'Active'
             LIMIT 1"
        );

        mysqli_stmt_bind_param(
            $stmt,
            "i",
            $driver_id
        );

        mysqli_stmt_execute($stmt);

        $result = mysqli_stmt_get_result($stmt);
        $active_assignment = mysqli_fetch_assoc($result);

        mysqli_stmt_close($stmt);


        if ($active_assignment) {

            $error =
                "You already have an active taxi assignment.";

        }

    }


    /* ---------------------------------------------
       RE-CHECK TAXI AVAILABILITY
    --------------------------------------------- */

    if ($error === "") {

        $stmt = mysqli_prepare(
            $conn,
            "SELECT
                id,
                rent,
                status
             FROM taxis
             WHERE id = ?
             LIMIT 1"
        );

        mysqli_stmt_bind_param(
            $stmt,
            "i",
            $taxi_id
        );

        mysqli_stmt_execute($stmt);

        $result = mysqli_stmt_get_result($stmt);
        $taxi_check = mysqli_fetch_assoc($result);

        mysqli_stmt_close($stmt);


        if (!$taxi_check) {

            $error = "Taxi not found.";

        } elseif ($taxi_check["status"] !== "Available") {

            $error =
                "This taxi is no longer available.";

        } else {

            $taxi["rent"] = $taxi_check["rent"];

        }

    }


    /* ---------------------------------------------
       CHECK DUPLICATE PENDING REQUEST
    --------------------------------------------- */

    if ($error === "") {

        $stmt = mysqli_prepare(
            $conn,
            "SELECT id
             FROM taxi_requests
             WHERE driver_id = ?
               AND taxi_id = ?
               AND request_status = 'Pending'
             LIMIT 1"
        );

        mysqli_stmt_bind_param(
            $stmt,
            "ii",
            $driver_id,
            $taxi_id
        );

        mysqli_stmt_execute($stmt);

        $result = mysqli_stmt_get_result($stmt);
        $duplicate_request = mysqli_fetch_assoc($result);

        mysqli_stmt_close($stmt);


        if ($duplicate_request) {

            $error =
                "You already have a pending request for this taxi.";

        }

    }


    /* ---------------------------------------------
       INSERT TAXI REQUEST
    --------------------------------------------- */

    if ($error === "") {

        mysqli_begin_transaction($conn);

        try {

            $amount = (float) $taxi["rent"];


            $stmt = mysqli_prepare(
                $conn,
                "INSERT INTO taxi_requests
                (
                    driver_id,
                    taxi_id,
                    amount,
                    payment_status,
                    request_status
                )
                VALUES
                (
                    ?,
                    ?,
                    ?,
                    'Pending',
                    'Pending'
                )"
            );


            mysqli_stmt_bind_param(
                $stmt,
                "iid",
                $driver_id,
                $taxi_id,
                $amount
            );


            if (!mysqli_stmt_execute($stmt)) {

                throw new Exception(
                    "Unable to create taxi request."
                );

            }


            $new_request_id =
                mysqli_insert_id($conn);


            mysqli_stmt_close($stmt);


            mysqli_commit($conn);


            $message =
                "Taxi request submitted successfully.";


            /* -----------------------------------------
               REFRESH REQUEST STATE
            ----------------------------------------- */

            $existing_request = [

                "id" => $new_request_id,

                "payment_status" => "Pending",

                "request_status" => "Pending"

            ];


        } catch (Throwable $e) {

            mysqli_rollback($conn);

            $error = $e->getMessage();

        }

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

    <title>Request Taxi</title>


    <style>

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }


        body {
            font-family: Arial, sans-serif;
            background: #f5f6fa;
            color: #222;
        }


        .container {
            max-width: 850px;
            margin: 50px auto;
            padding: 20px;
        }


        .card {
            background: white;
            border-radius: 16px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,.08);
        }


        h1 {
            margin-bottom: 10px;
        }


        .subtitle {
            color: #666;
            margin-bottom: 30px;
        }


        .taxi-details {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
            margin-bottom: 30px;
        }


        .detail {
            background: #f8f9fb;
            padding: 16px;
            border-radius: 10px;
        }


        .detail span {
            display: block;
            font-size: 13px;
            color: #777;
            margin-bottom: 6px;
        }


        .detail strong {
            font-size: 16px;
        }


        .rent {
            font-size: 24px;
            font-weight: bold;
        }


        .message {
            background: #e8f7ed;
            color: #166534;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }


        .error {
            background: #feecec;
            color: #991b1b;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }


        .pending {
            background: #fff7df;
            color: #92400e;
            padding: 20px;
            border-radius: 10px;
            margin-top: 20px;
        }


        .paid {
            background: #e8f7ed;
            color: #166534;
            padding: 20px;
            border-radius: 10px;
            margin-top: 20px;
        }


        .buttons {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin-top: 25px;
        }


        button,
        .button {
            border: none;
            padding: 13px 22px;
            border-radius: 8px;
            font-size: 15px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }


        .primary {
            background: #111827;
            color: white;
        }


        .primary:hover {
            background: #000;
        }


        .pay-button {
            background: #16a34a;
            color: white;
            font-weight: bold;
        }


        .pay-button:hover {
            background: #15803d;
        }


        .secondary {
            background: #e5e7eb;
            color: #111827;
        }


        .secondary:hover {
            background: #d1d5db;
        }


        .status-label {
            font-weight: bold;
        }


        @media (max-width: 650px) {

            .taxi-details {
                grid-template-columns: 1fr;
            }


            .container {
                margin: 20px auto;
            }


            .card {
                padding: 20px;
            }

        }

    </style>

</head>


<body>


<div class="container">


    <div class="card">


        <h1>
            Request This Taxi
        </h1>


        <p class="subtitle">
            Review the taxi details before sending your request to the admin.
        </p>


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


        <!-- =================================================
             TAXI DETAILS
        ================================================= -->

        <div class="taxi-details">


            <div class="detail">

                <span>
                    Brand
                </span>

                <strong>
                    <?= htmlspecialchars($taxi["brand"]) ?>
                </strong>

            </div>


            <div class="detail">

                <span>
                    Model
                </span>

                <strong>
                    <?= htmlspecialchars($taxi["model"]) ?>
                </strong>

            </div>


            <div class="detail">

                <span>
                    Registration Number
                </span>

                <strong>
                    <?= htmlspecialchars($taxi["registration_number"]) ?>
                </strong>

            </div>


            <div class="detail">

                <span>
                    Rent
                </span>

                <strong class="rent">

                    ₹<?= number_format(
                        (float) $taxi["rent"],
                        2
                    ) ?>

                </strong>

            </div>


        </div>


        <!-- =================================================
             EXISTING REQUEST
        ================================================= -->

        <?php if ($existing_request): ?>


            <?php if (
                $existing_request["payment_status"] === "Paid"
            ): ?>


                <!-- PAYMENT COMPLETED -->

                <div class="paid">

                    <strong>
                        Payment Completed
                    </strong>

                    <br><br>

                    Your payment for this taxi request has been
                    recorded successfully.

                    <br><br>

                    <strong>
                        Payment Status:
                    </strong>

                    Paid

                    <br>

                    <strong>
                        Request Status:
                    </strong>

                    <?= htmlspecialchars(
                        $existing_request["request_status"]
                    ) ?>

                </div>


                <div class="buttons">


                    <a
                        href="dashboard.php"
                        class="button primary"
                    >
                        Go to Dashboard
                    </a>


                    <a
                        href="../index2.php"
                        class="button secondary"
                    >
                        Back to Taxi Portal
                    </a>


                </div>


            <?php else: ?>


                <!-- PAYMENT PENDING -->

                <div class="pending">

                    <strong>
                        Request Already Submitted
                    </strong>

                    <br><br>

                    You already have a request for this taxi.

                    <br><br>

                    <span class="status-label">
                        Payment Status:
                    </span>

                    <?= htmlspecialchars(
                        $existing_request["payment_status"]
                    ) ?>

                    <br>

                    <span class="status-label">
                        Request Status:
                    </span>

                    <?= htmlspecialchars(
                        $existing_request["request_status"]
                    ) ?>

                </div>


                <div class="buttons">


                    <!-- PAY BUTTON -->

                    <a
                        href="payments.php?request_id=<?= (int) $existing_request["id"] ?>"
                        class="button pay-button"
                    >
                        Pay ₹<?= number_format(
                            (float) $taxi["rent"],
                            2
                        ) ?>
                    </a>


                    <a
                        href="dashboard.php"
                        class="button primary"
                    >
                        Go to Dashboard
                    </a>


                    <a
                        href="../index2.php"
                        class="button secondary"
                    >
                        Back to Taxi Portal
                    </a>


                </div>


            <?php endif; ?>


        <?php else: ?>


            <!-- =================================================
                 NEW REQUEST FORM
            ================================================= -->

            <form method="POST">


                <input
                    type="hidden"
                    name="taxi_id"
                    value="<?= (int) $taxi["id"] ?>"
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


                <div class="buttons">


                    <button
                        type="submit"
                        class="primary"
                    >
                        Send Request to Admin
                    </button>


                    <a
                        href="../index2.php"
                        class="button secondary"
                    >
                        Cancel
                    </a>


                </div>


            </form>


        <?php endif; ?>


    </div>


</div>


</body>

</html>