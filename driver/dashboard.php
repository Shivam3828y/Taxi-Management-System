<?php

session_start();

if (!isset($_SESSION["driver_id"])) {
    header("Location: login.php");
    exit;
}

require_once "../config.php";

$driver_id = (int) $_SESSION["driver_id"];

$message = "";
$error = "";


// =====================================================
// ACCEPT AGREEMENT
// =====================================================

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

        $accept_stmt = mysqli_prepare(
            $conn,
            $accept_sql
        );

        if ($accept_stmt) {

            mysqli_stmt_bind_param(
                $accept_stmt,
                "ii",
                $agreement_id,
                $driver_id
            );

            mysqli_stmt_execute(
                $accept_stmt
            );

            if (
                mysqli_stmt_affected_rows(
                    $accept_stmt
                ) > 0
            ) {

                $message =
                    "Agreement accepted successfully.";

            } else {

                $error =
                    "Agreement could not be accepted.";

            }

            mysqli_stmt_close(
                $accept_stmt
            );

        } else {

            $error =
                "Database error: "
                . mysqli_error($conn);
        }
    }
}


// =====================================================
// DRIVER INFORMATION
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

mysqli_stmt_bind_param(
    $driver_stmt,
    "i",
    $driver_id
);

mysqli_stmt_execute(
    $driver_stmt
);

$driver_result =
    mysqli_stmt_get_result(
        $driver_stmt
    );

$driver = mysqli_fetch_assoc(
    $driver_result
);

mysqli_stmt_close(
    $driver_stmt
);


if (!$driver) {

    session_destroy();

    header("Location: login.php");

    exit;
}


// =====================================================
// ACTIVE ASSIGNMENT
// =====================================================

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


// =====================================================
// ACTIVE AGREEMENT
// =====================================================

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

    $agreement_stmt = mysqli_prepare(
        $conn,
        $agreement_sql
    );

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

            padding: 10px 0;

            border-bottom: 1px solid #ddd;

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

        .error-message {
            color: #b91c1c;
            margin-bottom: 15px;
        }

        .success-message {
            color: #15803d;
            margin-bottom: 15px;
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

        .agreement-box {

            border: 2px solid #444;

            border-radius: 10px;

            padding: 20px;

            background: #fafafa;

        }

        .agreement-warning {

            padding: 12px;

            background: #fff3cd;

            border-radius: 6px;

            margin-top: 15px;

        }

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
    <!-- MESSAGES -->
    <!-- ================================================= -->

    <?php if ($message !== ""): ?>

        <p class="success-message">

            <?php
            echo htmlspecialchars($message);
            ?>

        </p>

    <?php endif; ?>


    <?php if ($error !== ""): ?>

        <p class="error-message">

            <?php
            echo htmlspecialchars($error);
            ?>

        </p>

    <?php endif; ?>


    <!-- ================================================= -->
    <!-- WELCOME -->
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

            <span class="status
                <?php

                if (
                    $driver["status"] === "Verified"
                ) {

                    echo "verified";

                } elseif (
                    $driver["status"] === "Inactive"
                ) {

                    echo "inactive";

                } else {

                    echo "pending";
                }

                ?>"
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
    <!-- DRIVER PASS / ASSIGNED TAXI -->
    <!-- ================================================= -->

    <?php if ($assignment): ?>

        <section>

            <div class="pass-card">

                <div class="pass-title">
                    🚕 Driver Assignment Pass
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

        </section>


    <?php else: ?>

        <section>

            <div class="dashboard-card">

                <h2>
                    No Taxi Assigned
                </h2>

                <p>
                    You currently do not have a taxi assigned.
                </p>

                <p>
                    Once the admin verifies your registration
                    and assigns a taxi, the assignment will
                    appear here.
                </p>

            </div>

        </section>

    <?php endif; ?>


    <!-- ================================================= -->
    <!-- AGREEMENT -->
    <!-- ================================================= -->

    <?php if ($agreement): ?>

        <section>

            <h2>
                Taxi Agreement
            </h2>

            <div class="agreement-box">

                <h3>
                    Agreement Details
                </h3>


                <div class="pass-row">

                    <strong>
                        Agreement ID
                    </strong>

                    <span>
                        #<?php
                        echo (int)
                            $agreement["id"];
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

                    </span>

                </div>


                <div class="pass-row">

                    <strong>
                        Agreement Status
                    </strong>

                    <span>

                        <?php if (
                            $agreement["accepted"] == 1
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
                    $agreement["accepted"] == 0
                ): ?>

                    <div class="agreement-warning">

                        <strong>
                            Action Required
                        </strong>

                        <p>
                            Please review the agreement
                            details above and accept the
                            agreement to continue.
                        </p>


                        <form method="POST">

                            <?php csrf_field(); ?>

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
                        ✓ Agreement accepted

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


            <!-- RENT -->

            <div class="dashboard-card">

                <h3>
                    Rent Payment
                </h3>

                <p>
                    View your taxi rent and payment
                    records.
                </p>

                <a
                    class="dashboard-button"
                    href="payments.php"
                >
                    Rent Payments
                </a>

            </div>


            <!-- TAXI AVAILABILITY -->

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


            <!-- PROFILE -->

            <div class="dashboard-card">

                <h3>
                    My Profile
                </h3>

                <p>
                    View your registered driver
                    information.
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