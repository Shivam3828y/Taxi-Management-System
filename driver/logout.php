<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

require_once "../config.php";


// =====================================================
// CHECK DRIVER LOGIN
// =====================================================

if (!isset($_SESSION["driver_id"])) {
    header("Location: login.php");
    exit;
}

$driver_id = (int) $_SESSION["driver_id"];

$message = "";
$error = "";


// =====================================================
// ACCEPT AGREEMENT
// =====================================================

if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    isset($_POST["accept_agreement"])
) {

    $agreement_id = (int) ($_POST["agreement_id"] ?? 0);

    if ($agreement_id <= 0) {

        $error = "Invalid agreement.";

    } else {

        /*
         * Agreement can only be accepted if:
         *
         * 1. Agreement belongs to driver's active assignment
         * 2. Agreement is Active
         * 3. Agreement is not already accepted
         */

        $sql = "
            UPDATE agreements a

            INNER JOIN assignments ass
                ON a.assignment_id = ass.id

            SET
                a.accepted = 1,
                a.accepted_at = NOW()

            WHERE a.id = ?
            AND a.status = 'Active'
            AND a.accepted = 0
            AND ass.driver_id = ?
            AND ass.status = 'Active'
        ";

        $stmt = mysqli_prepare($conn, $sql);

        if (!$stmt) {

            $error =
                "Database error: " .
                mysqli_error($conn);

        } else {

            mysqli_stmt_bind_param(
                $stmt,
                "ii",
                $agreement_id,
                $driver_id
            );

            mysqli_stmt_execute($stmt);

            if (mysqli_stmt_affected_rows($stmt) > 0) {

                $message =
                    "Agreement accepted successfully.";

            } else {

                $error =
                    "Agreement could not be accepted. "
                    . "It may already be accepted or inactive.";
            }

            mysqli_stmt_close($stmt);
        }
    }
}


// =====================================================
// GET DRIVER INFORMATION
// =====================================================

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

$driver_stmt = mysqli_prepare(
    $conn,
    $driver_sql
);

if (!$driver_stmt) {

    die(
        "Database error: " .
        mysqli_error($conn)
    );
}

mysqli_stmt_bind_param(
    $driver_stmt,
    "i",
    $driver_id
);

mysqli_stmt_execute($driver_stmt);

$driver_result =
    mysqli_stmt_get_result($driver_stmt);

$driver =
    mysqli_fetch_assoc($driver_result);

mysqli_stmt_close($driver_stmt);


// =====================================================
// DRIVER NOT FOUND
// =====================================================

if (!$driver) {

    session_unset();
    session_destroy();

    header("Location: login.php");
    exit;
}


// =====================================================
// ACTIVE ASSIGNMENT
// =====================================================

$assignment = null;

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
// ACTIVE AGREEMENT
// =====================================================

$agreement = null;

if ($assignment) {

    $agreement_sql = "
        SELECT
            id,
            assignment_id,
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

        mysqli_stmt_execute(
            $agreement_stmt
        );

        $agreement_result =
            mysqli_stmt_get_result(
                $agreement_stmt
            );

        $agreement =
            mysqli_fetch_assoc(
                $agreement_result
            );

        mysqli_stmt_close(
            $agreement_stmt
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
        Driver Dashboard - Taxi Management System
    </title>

    <link
        rel="stylesheet"
        href="../css/style.css"
    >

    <style>

        /* =================================================
           DASHBOARD
        ================================================= */

        .dashboard-container {

            max-width: 1200px;

            margin: 30px auto;

            padding: 0 20px;

        }


        /* =================================================
           GRID
        ================================================= */

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


        /* =================================================
           CARD
        ================================================= */

        .dashboard-card {

            border: 1px solid #ddd;

            border-radius: 10px;

            padding: 20px;

            background: #fff;

        }


        /* =================================================
           ASSIGNMENT PASS
        ================================================= */

        .pass-card {

            border: 2px solid #222;

            border-radius: 12px;

            padding: 25px;

            background: #f8f8f8;

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

            padding: 12px 0;

            border-bottom: 1px solid #ddd;

        }


        .pass-row:last-child {

            border-bottom: none;

        }


        /* =================================================
           AGREEMENT
        ================================================= */

        .agreement-box {

            border: 2px solid #444;

            border-radius: 10px;

            padding: 25px;

            background: #fafafa;

        }


        .agreement-title {

            font-size: 22px;

            font-weight: bold;

            margin-bottom: 20px;

        }


        .agreement-warning {

            margin-top: 20px;

            padding: 15px;

            border-radius: 8px;

            background: #fff3cd;

            border: 1px solid #f0d98c;

        }


        /* =================================================
           STATUS
        ================================================= */

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


        /* =================================================
           MESSAGES
        ================================================= */

        .success-message {

            padding: 12px;

            margin-bottom: 20px;

            color: #166534;

            background: #f0fdf4;

            border: 1px solid #86efac;

            border-radius: 6px;

        }


        .error-message {

            padding: 12px;

            margin-bottom: 20px;

            color: #991b1b;

            background: #fef2f2;

            border: 1px solid #fca5a5;

            border-radius: 6px;

        }


        /* =================================================
           BUTTONS
        ================================================= */

        .dashboard-button {

            display: inline-block;

            margin-top: 10px;

            padding: 10px 15px;

            border: 1px solid #333;

            border-radius: 6px;

            text-decoration: none;

            cursor: pointer;

            background: #fff;

        }


        .accept-button {

            margin-top: 15px;

            padding: 11px 18px;

            border: none;

            border-radius: 6px;

            background: #15803d;

            color: #fff;

            cursor: pointer;

            font-size: 15px;

        }


        .accept-button:hover {

            opacity: 0.9;

        }


        /* =================================================
           MOBILE
        ================================================= */

        @media (max-width: 600px) {

            .pass-row {

                flex-direction: column;

                gap: 5px;

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

<main class="dashboard-container">


    <!-- ================================================= -->
    <!-- MESSAGE -->
    <!-- ================================================= -->

    <?php if ($message !== ""): ?>

        <div class="success-message">

            <?php
            echo htmlspecialchars($message);
            ?>

        </div>

    <?php endif; ?>


    <?php if ($error !== ""): ?>

        <div class="error-message">

            <?php
            echo htmlspecialchars($error);
            ?>

        </div>

    <?php endif; ?>


    <!-- ================================================= -->
    <!-- DRIVER INFORMATION -->
    <!-- ================================================= -->

    <section>

        <h2>

            Welcome,
            <?php
            echo htmlspecialchars(
                $driver["name"]
            );
            ?>

        </h2>


        <p>
            Driver Portal
        </p>


        <p>

            Account Status:

            <?php

            $status_class = "pending";

            if (
                $driver["status"] === "Verified"
            ) {

                $status_class = "verified";

            } elseif (
                $driver["status"] === "Inactive"
            ) {

                $status_class = "inactive";
            }

            ?>


            <span
                class="status <?php echo $status_class; ?>"
            >

                <?php
                echo htmlspecialchars(
                    $driver["status"]
                );
                ?>

            </span>

        </p>

    </section>


    <!-- ================================================= -->
    <!-- TAXI ASSIGNMENT -->
    <!-- ================================================= -->

    <section>

        <h2>
            Taxi Assignment
        </h2>


        <?php if ($assignment): ?>

            <div class="pass-card">


                <div class="pass-title">

                    🚕 Driver Assignment

                </div>


                <div class="pass-row">

                    <strong>
                        Driver
                    </strong>

                    <span>
                        <?php
                        echo htmlspecialchars(
                            $driver["name"]
                        );
                        ?>
                    </span>

                </div>


                <div class="pass-row">

                    <strong>
                        Phone
                    </strong>

                    <span>
                        <?php
                        echo htmlspecialchars(
                            $driver["phone"]
                        );
                        ?>
                    </span>

                </div>


                <div class="pass-row">

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


                <div class="pass-row">

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


                <div class="pass-row">

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


                <div class="pass-row">

                    <strong>
                        Assignment Status
                    </strong>

                    <span class="status verified">

                        <?php
                        echo htmlspecialchars(
                            $assignment[
                                "assignment_status"
                            ]
                        );
                        ?>

                    </span>

                </div>


                <div class="pass-row">

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


        <?php else: ?>


            <div class="dashboard-card">

                <h3>
                    No Taxi Assigned
                </h3>


                <p>
                    You currently do not have an active
                    taxi assignment.
                </p>


                <p>
                    After admin verification, an available
                    taxi can be assigned to you.
                </p>

            </div>


        <?php endif; ?>

    </section>


    <!-- ================================================= -->
    <!-- AGREEMENT -->
    <!-- ================================================= -->

    <?php if ($assignment): ?>

        <section>

            <h2>
                Taxi Agreement
            </h2>


            <?php if ($agreement): ?>


                <div class="agreement-box">


                    <div class="agreement-title">
                        Agreement Details
                    </div>


                    <div class="pass-row">

                        <strong>
                            Agreement ID
                        </strong>

                        <span>

                            #

                            <?php
                            echo (int)
                                $agreement["id"];
                            ?>

                        </span>

                    </div>


                    <div class="pass-row">

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


                    <div class="pass-row">

                        <strong>
                            Registration
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


                    <div class="pass-row">

                        <strong>
                            Start Date
                        </strong>

                        <span>

                            <?php
                            echo htmlspecialchars(
                                $agreement["start_date"]
                            );
                            ?>

                        </span>

                    </div>


                    <div class="pass-row">

                        <strong>
                            End Date
                        </strong>

                        <span>

                            <?php
                            echo htmlspecialchars(
                                $agreement["end_date"]
                            );
                            ?>

                        </span>

                    </div>


                    <div class="pass-row">

                        <strong>
                            Rent
                        </strong>

                        <span>

                            ₹<?php
                            echo number_format(
                                (float)
                                $agreement["rent"],
                                2
                            );
                            ?>

                            / day

                        </span>

                    </div>


                    <div class="pass-row">

                        <strong>
                            Agreement Status
                        </strong>

                        <span>

                            <?php if (
                                (int)
                                $agreement["accepted"]
                                === 1
                            ): ?>

                                <span class="accepted">
                                    Accepted
                                </span>

                            <?php else: ?>

                                <span class="waiting">
                                    Pending Acceptance
                                </span>

                            <?php endif; ?>

                        </span>

                    </div>


                    <?php if (
                        (int)
                        $agreement["accepted"]
                        === 0
                    ): ?>


                        <div class="agreement-warning">

                            <strong>
                                Action Required
                            </strong>


                            <p>

                                Please review the agreement
                                details and accept the
                                agreement.

                            </p>


                            <form
                                method="POST"
                                action=""
                            >

                                <input
                                    type="hidden"
                                    name="agreement_id"
                                    value="<?php
                                        echo (int)
                                            $agreement["id"];
                                    ?>"
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


                    <?php else: ?>


                        <p class="accepted">

                            ✓ Agreement Accepted

                            <?php if (
                                !empty(
                                    $agreement["accepted_at"]
                                )
                            ): ?>

                                on

                                <?php
                                echo htmlspecialchars(
                                    $agreement[
                                        "accepted_at"
                                    ]
                                );
                                ?>

                            <?php endif; ?>

                        </p>


                    <?php endif; ?>


                </div>


            <?php else: ?>


                <div class="dashboard-card">

                    <h3>
                        Agreement Not Created
                    </h3>


                    <p>
                        Your taxi has been assigned, but
                        the admin has not created an active
                        agreement yet.
                    </p>

                </div>


            <?php endif; ?>


        </section>

    <?php endif; ?>


    <!-- ================================================= -->
    <!-- DRIVER SERVICES -->
    <!-- ================================================= -->

    <section>

        <h2>
            Driver Services
        </h2>


        <div class="dashboard-grid">


            <div class="dashboard-card">

                <h3>
                    Rent Payments
                </h3>

                <p>
                    View your taxi rent and payment records.
                </p>

                <a
                    class="dashboard-button"
                    href="payments.php"
                >
                    Rent Payments
                </a>

            </div>


            <div class="dashboard-card">

                <h3>
                    Taxi Availability
                </h3>

                <p>
                    Check currently available taxis.
                </p>

                <a
                    class="dashboard-button"
                    href="../index2.php#taxis"
                >
                    View Available Taxis
                </a>

            </div>


            <div class="dashboard-card">

                <h3>
                    My Profile
                </h3>

                <p>
                    View your registered driver information.
                </p>

                <a
                    class="dashboard-button"
                    href="profile.php"
                >
                    View Profile
                </a>

            </div>


        </div>

    </section>


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