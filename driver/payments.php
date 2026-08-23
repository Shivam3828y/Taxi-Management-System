<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

if (!isset($_SESSION["driver_id"])) {
    header("Location: login.php");
    exit;
}

require_once "../config.php";

$driver_id = (int) $_SESSION["driver_id"];


// =====================================================
// DRIVER INFORMATION
// =====================================================

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

$driver_stmt = mysqli_prepare(
    $conn,
    $driver_sql
);

if (!$driver_stmt) {
    die("Database error: " . mysqli_error($conn));
}

mysqli_stmt_bind_param(
    $driver_stmt,
    "i",
    $driver_id
);

mysqli_stmt_execute($driver_stmt);

$driver_result = mysqli_stmt_get_result(
    $driver_stmt
);

$driver = mysqli_fetch_assoc(
    $driver_result
);

mysqli_stmt_close($driver_stmt);


// =====================================================
// DRIVER NOT FOUND
// =====================================================

if (!$driver) {

    session_destroy();

    header("Location: login.php");

    exit;
}


// =====================================================
// CURRENT ACTIVE ASSIGNMENT
// =====================================================

$assignment = null;

$assignment_sql = "
    SELECT
        assignments.id AS assignment_id,
        assignments.assigned_at,

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


// =====================================================
// PAYMENT SUMMARY
// =====================================================

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


// =====================================================
// PAYMENT HISTORY
// =====================================================

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
        ON payments.assignment_id =
           assignments.id

    INNER JOIN taxis
        ON assignments.taxi_id =
           taxis.id

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
        $row =
        mysqli_fetch_assoc(
            $payments_result
        )
    ) {

        $payments[] = $row;
    }

    mysqli_stmt_close(
        $payments_stmt
    );
}


// =====================================================
// DEMO UPI PAYMENT
// =====================================================

$upi_id = "taxi@upi";

$qr_url = "";

$rent_amount = 0;

if ($assignment) {

    $rent_amount =
        (float) $assignment["rent"];

    $amount_for_upi =
        number_format(
            $rent_amount,
            2,
            ".",
            ""
        );


    /*
     * UPI PAYMENT LINK
     *
     * NOTE:
     * taxi@upi is a demo/example UPI ID.
     */

    $upi_link =
        "upi://pay"
        . "?pa="
        . urlencode($upi_id)
        . "&pn="
        . urlencode(
            "Taxi Management System"
        )
        . "&am="
        . urlencode(
            $amount_for_upi
        )
        . "&cu=INR";


    /*
     * QR CODE
     *
     * QRServer is used only to generate
     * the visual QR code.
     */

    $qr_url =
        "https://api.qrserver.com/v1/create-qr-code/"
        . "?size=250x250"
        . "&data="
        . urlencode($upi_link);
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

        /* ================================================= */
        /* MAIN */
        /* ================================================= */

        .payments-container {

            max-width: 1100px;

            margin: 30px auto;

        }


        /* ================================================= */
        /* PAYMENT GRID */
        /* ================================================= */

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


        /* ================================================= */
        /* STATUS */
        /* ================================================= */

        .paid {

            color: green;

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


        /* ================================================= */
        /* TAXI CARD */
        /* ================================================= */

        .taxi-card {

            border: 2px solid #222;

            border-radius: 10px;

            padding: 20px;

            margin-bottom: 25px;

            background: #fff;

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


        /* ================================================= */
        /* QR PAYMENT */
        /* ================================================= */

        .qr-payment-box {

            margin-top: 25px;

            padding: 30px;

            border: 2px solid #222;

            border-radius: 12px;

            background: #fff;

            text-align: center;

        }


        .qr-payment-box h2 {

            margin-top: 0;

        }


        .qr-code {

            width: 250px;

            height: 250px;

            display: block;

            margin: 20px auto;

            padding: 8px;

            background: #fff;

            border: 1px solid #ddd;

            border-radius: 8px;

        }


        .upi-id {

            font-size: 18px;

            font-weight: bold;

        }


        .qr-amount {

            font-size: 22px;

            font-weight: bold;

            margin: 15px 0;

        }


        .payment-instruction {

            color: #555;

        }


        .demo-warning {

            margin-top: 15px;

            padding: 12px;

            background: #fff3cd;

            border-radius: 6px;

            color: #856404;

        }


        /* ================================================= */
        /* TABLE */
        /* ================================================= */

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


        /* ================================================= */
        /* BACK BUTTON */
        /* ================================================= */

        .back-button {

            display: inline-block;

            margin-top: 20px;

            padding: 10px 15px;

            border: 1px solid #333;

            border-radius: 6px;

            text-decoration: none;

        }


        /* ================================================= */
        /* MOBILE */
        /* ================================================= */

        @media (max-width: 600px) {

            .payment-row {

                flex-direction: column;

                gap: 5px;

            }


            .qr-code {

                width: 200px;

                height: 200px;

            }

        }

    </style>

</head>


<body>


<!-- ================================================= -->
<!-- HEADER -->
<!-- ================================================= -->

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


<!-- ================================================= -->
<!-- MAIN -->
<!-- ================================================= -->

<main class="payments-container">


    <!-- ================================================= -->
    <!-- TITLE -->
    <!-- ================================================= -->

    <section>

        <h2>
            Rent Payments
        </h2>


        <p>

            Welcome,

            <?php

            echo htmlspecialchars(
                $driver["name"]
            );

            ?>

        </p>

    </section>


    <!-- ================================================= -->
    <!-- CURRENT TAXI -->
    <!-- ================================================= -->

    <?php if ($assignment): ?>

        <section>

            <h2>
                Current Taxi
            </h2>


            <div class="taxi-card">


                <!-- TAXI -->

                <div class="payment-row">

                    <strong>
                        Taxi
                    </strong>


                    <span>

                        <?php

                        echo htmlspecialchars(
                            $assignment["brand"]
                            . " "
                            . $assignment["model"]
                        );

                        ?>

                    </span>

                </div>


                <!-- REGISTRATION -->

                <div class="payment-row">

                    <strong>
                        Registration Number
                    </strong>


                    <span>

                        <?php

                        echo htmlspecialchars(
                            $assignment[
                                "registration_number"
                            ]
                        );

                        ?>

                    </span>

                </div>


                <!-- DAILY RENT -->

                <div class="payment-row">

                    <strong>
                        Daily Rent
                    </strong>


                    <span>

                        ₹<?php

                        echo number_format(
                            (float)
                            $assignment["rent"],
                            2
                        );

                        ?>

                        / day

                    </span>

                </div>


                <!-- ASSIGNED AT -->

                <div class="payment-row">

                    <strong>
                        Assigned At
                    </strong>


                    <span>

                        <?php

                        echo htmlspecialchars(
                            $assignment[
                                "assigned_at"
                            ]
                        );

                        ?>

                    </span>

                </div>


            </div>

        </section>


        <!-- ================================================= -->
        <!-- QR PAYMENT -->
        <!-- ================================================= -->

        <section>

            <div class="qr-payment-box">

                <h2>
                    💳 Pay Taxi Rent
                </h2>


                <p>
                    Scan the QR code below to pay
                    your taxi rent.
                </p>


                <!-- QR CODE -->

                <?php if ($qr_url !== ""): ?>

                    <img
                        src="<?php
                            echo htmlspecialchars(
                                $qr_url
                            );
                        ?>"
                        alt="Taxi Rent Payment QR Code"
                        class="qr-code"
                    >

                <?php endif; ?>


                <!-- AMOUNT -->

                <div class="qr-amount">

                    Amount:

                    ₹<?php

                    echo number_format(
                        $rent_amount,
                        2
                    );

                    ?>

                </div>


                <!-- UPI -->

                <p>

                    UPI ID:

                    <span class="upi-id">

                        <?php

                        echo htmlspecialchars(
                            $upi_id
                        );

                        ?>

                    </span>

                </p>


                <p class="payment-instruction">

                    Scan this QR code using
                    Google Pay, PhonePe,
                    Paytm or another UPI app.

                </p>


                <div class="demo-warning">

                    <strong>
                        Demo Payment
                    </strong>

                    <br>

                    This project currently uses
                    <strong>
                        taxi@upi
                    </strong>
                    as an example UPI ID.

                    Replace it with your real
                    UPI ID before using this system
                    for actual payments.

                </div>

            </div>

        </section>


    <?php else: ?>


        <section>

            <div class="payment-card">

                <h3>
                    No Taxi Assigned
                </h3>


                <p>

                    You do not currently have
                    an active taxi assignment.

                </p>


            </div>

        </section>

    <?php endif; ?>


    <!-- ================================================= -->
    <!-- PAYMENT SUMMARY -->
    <!-- ================================================= -->

    <section>

        <h2>
            Payment Summary
        </h2>


        <div class="payment-grid">


            <!-- TOTAL PAID -->

            <div class="payment-card">

                <h3>
                    Total Paid
                </h3>


                <div class="amount">

                    ₹<?php

                    echo number_format(
                        $total_paid,
                        2
                    );

                    ?>

                </div>

            </div>


            <!-- PAYMENT COUNT -->

            <div class="payment-card">

                <h3>
                    Payments Made
                </h3>


                <div class="amount">

                    <?php

                    echo $payment_count;

                    ?>

                </div>

            </div>


            <!-- ACCOUNT STATUS -->

            <div class="payment-card">

                <h3>
                    Account
                </h3>


                <div>

                    <?php

                    if (
                        $driver["status"]
                        === "Verified"
                    ) {

                        echo '<span class="paid">
                                Verified
                              </span>';

                    } elseif (
                        $driver["status"]
                        === "Pending"
                    ) {

                        echo '<span class="pending">
                                Pending
                              </span>';

                    } else {

                        echo '<span class="failed">
                                Inactive
                              </span>';

                    }

                    ?>

                </div>

            </div>


        </div>

    </section>


    <!-- ================================================= -->
    <!-- PAYMENT HISTORY -->
    <!-- ================================================= -->

    <section>

        <h2>
            Payment History
        </h2>


        <?php if (
            count($payments) > 0
        ): ?>


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
                        $payments
                        as $payment
                    ): ?>


                        <tr>


                            <!-- PAYMENT ID -->

                            <td>

                                #<?php

                                echo (int)
                                    $payment["id"];

                                ?>

                            </td>


                            <!-- DATE -->

                            <td>

                                <?php

                                echo htmlspecialchars(
                                    $payment[
                                        "payment_date"
                                    ]
                                );

                                ?>

                            </td>


                            <!-- TAXI -->

                            <td>

                                <?php

                                echo htmlspecialchars(
                                    $payment["brand"]
                                    . " "
                                    . $payment["model"]
                                );

                                ?>

                            </td>


                            <!-- REGISTRATION -->

                            <td>

                                <?php

                                echo htmlspecialchars(
                                    $payment[
                                        "registration_number"
                                    ]
                                );

                                ?>

                            </td>


                            <!-- AMOUNT -->

                            <td>

                                ₹<?php

                                echo number_format(
                                    (float)
                                    $payment["amount"],
                                    2
                                );

                                ?>

                            </td>


                            <!-- METHOD -->

                            <td>

                                <?php

                                echo htmlspecialchars(
                                    $payment[
                                        "payment_method"
                                    ]
                                );

                                ?>

                            </td>


                            <!-- STATUS -->

                            <td>

                                <?php

                                $status =
                                    $payment["status"];


                                if (
                                    $status === "Paid"
                                ) {

                                    echo '<span class="paid">
                                            Paid
                                          </span>';

                                } elseif (
                                    $status === "Pending"
                                ) {

                                    echo '<span class="pending">
                                            Pending
                                          </span>';

                                } else {

                                    echo '<span class="failed">'
                                        . htmlspecialchars(
                                            $status
                                        )
                                        . '</span>';

                                }

                                ?>

                            </td>


                            <!-- NOTES -->

                            <td>

                                <?php

                                echo !empty(
                                    $payment["notes"]
                                )

                                    ? htmlspecialchars(
                                        $payment["notes"]
                                    )

                                    : "-";

                                ?>

                            </td>


                        </tr>


                    <?php endforeach; ?>


                    </tbody>

                </table>

            </div>


        <?php else: ?>


            <div class="payment-card">

                <h3>
                    No Payment Records
                </h3>


                <p>

                    No rent payments have been
                    recorded for your account yet.

                </p>


                <p>

                    Payments will appear here
                    after the admin records them.

                </p>

            </div>


        <?php endif; ?>


    </section>


    <!-- ================================================= -->
    <!-- BACK -->
    <!-- ================================================= -->

    <a
        class="back-button"
        href="dashboard.php"
    >
        Back to Dashboard
    </a>


</main>


<!-- ================================================= -->
<!-- FOOTER -->
<!-- ================================================= -->

<footer>

    <p>
        &copy; 2026 Taxi Management System
    </p>

</footer>


</body>

</html>