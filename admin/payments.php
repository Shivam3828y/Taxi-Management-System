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

    if ($agreement_id > 0) {

        $sql = "
            UPDATE agreements
            SET
                accepted = 1,
                accepted_at = NOW()
            WHERE id = ?
            AND status = 'Active'
            AND accepted = 0
        ";

        $stmt = mysqli_prepare($conn, $sql);

        if ($stmt) {

            mysqli_stmt_bind_param(
                $stmt,
                "i",
                $agreement_id
            );

            mysqli_stmt_execute($stmt);

            if (
                mysqli_stmt_affected_rows($stmt) > 0
            ) {

                $message =
                    "Agreement accepted successfully.";

            } else {

                $error =
                    "Agreement could not be accepted.";

            }

            mysqli_stmt_close($stmt);

        } else {

            $error =
                "Database error: "
                . mysqli_error($conn);

        }
    }
}


// =====================================================
// GET DRIVER
// =====================================================

$sql = "
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

$stmt = mysqli_prepare($conn, $sql);

if (!$stmt) {
    die("Database error: " . mysqli_error($conn));
}

mysqli_stmt_bind_param(
    $stmt,
    "i",
    $driver_id
);

mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);

$driver = mysqli_fetch_assoc($result);

mysqli_stmt_close($stmt);


if (!$driver) {

    session_destroy();

    header("Location: login.php");
    exit;
}


// =====================================================
// GET ACTIVE ASSIGNMENT + TAXI
// =====================================================

$assignment = null;

$sql = "
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

$stmt = mysqli_prepare($conn, $sql);

if ($stmt) {

    mysqli_stmt_bind_param(
        $stmt,
        "i",
        $driver_id
    );

    mysqli_stmt_execute($stmt);

    $result = mysqli_stmt_get_result($stmt);

    $assignment = mysqli_fetch_assoc($result);

    mysqli_stmt_close($stmt);
}


// =====================================================
// GET ACTIVE AGREEMENT
// =====================================================

$agreement = null;

if ($assignment) {

    $sql = "
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

    $stmt = mysqli_prepare($conn, $sql);

    if ($stmt) {

        mysqli_stmt_bind_param(
            $stmt,
            "i",
            $assignment["assignment_id"]
        );

        mysqli_stmt_execute($stmt);

        $result = mysqli_stmt_get_result($stmt);

        $agreement =
            mysqli_fetch_assoc($result);

        mysqli_stmt_close($stmt);
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
        Driver Dashboard
    </title>

    <link
        rel="stylesheet"
        href="../css/style.css"
    >

    <style>

        .dashboard-container {

            max-width: 1200px;

            margin: 30px auto;

            padding: 0 20px;

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

            background: white;

        }

        .pass-card {

            border: 2px solid #222;

            border-radius: 12px;

            padding: 25px;

            background: #f8f8f8;

            margin-top: 15px;

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

        .message {

            padding: 12px;

            margin-bottom: 15px;

            border: 1px solid #86efac;

            background: #f0fdf4;

            color: #166534;

            border-radius: 6px;

        }

        .error {

            padding: 12px;

            margin-bottom: 15px;

            border: 1px solid #fca5a5;

            background: #fef2f2;

            color: #991b1b;

            border-radius: 6px;

        }

        .dashboard-button {

            display: inline-block;

            margin-top: 10px;

            padding: 10px 15px;

            border: 1px solid #333;

            border-radius: 6px;

            text-decoration: none;

            background: white;

            color: #111;

        }

        .dashboard-button:hover {

            background: #eee;

        }

        .accept-button {

            display: inline-block;

            margin-top: 15px;

            padding: 12px 18px;

            border: none;

            border-radius: 6px;

            background: #166534;

            color: white;

            cursor: pointer;

        }

        .accept-button:hover {

            background: #14532d;

        }

        .no-data {

            padding: 20px;

            border: 1px solid #ddd;

            border-radius: 10px;

            background: #fafafa;

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

        <div class="message">

            <?php
            echo htmlspecialchars($message);
            ?>

        </div>

    <?php endif; ?>


    <?php if ($error !== ""): ?>

        <div class="error">

            <?php
            echo htmlspecialchars($error);
            ?>

        </div>

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

            <?php if ($driver["status"] === "Verified"): ?>

                <span class="status verified">
                    Verified
                </span>

            <?php elseif ($driver["status"] === "Pending"): ?>

                <span class="status pending">
                    Pending Verification
                </span>

            <?php else: ?>

                <span class="status inactive">
                    <?php
                    echo htmlspecialchars(
                        $driver["status"]
                    );
                    ?>
                </span>

            <?php endif; ?>

        </p>

    </section>


    <!-- ================================================= -->
    <!-- DRIVER INFORMATION -->
    <!-- ================================================= -->

    <section>

        <h2>
            My Information
        </h2>

        <div class="dashboard-card">

            <p>
                <strong>Name:</strong>

                <?php
                echo htmlspecialchars(
                    $driver["name"]
                );
                ?>
            </p>

            <p>
                <strong>Phone:</strong>

                <?php
                echo htmlspecialchars(
                    $driver["phone"]
                );
                ?>
            </p>

            <p>
                <strong>Email:</strong>

                <?php
                echo htmlspecialchars(
                    $driver["email"]
                );
                ?>
            </p>

            <p>
                <strong>Driving License:</strong>

                <?php
                echo htmlspecialchars(
                    $driver["driving_license"]
                );
                ?>
            </p>

        </div>

    </section>


    <!-- ================================================= -->
    <!-- ASSIGNED TAXI PASS -->
    <!-- ================================================= -->

    <section>

        <h2>
            My Taxi
        </h2>


        <?php if ($assignment): ?>

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

                    </span>

                </div>


                <div class="pass-row">

                    <strong>
                        Assignment
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
                        Assigned On
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

            <div class="no-data">

                <h3>
                    No Taxi Assigned
                </h3>

                <p>
                    Admin has not assigned a taxi to you yet.
                </p>

                <p>
                    Once your registration is verified,
                    the admin can assign an available taxi.
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

                <div class="dashboard-card">

                    <p>

                        <strong>
                            Agreement Status:
                        </strong>


                        <?php if (
                            (int) $agreement["accepted"] === 1
                        ): ?>

                            <span class="accepted">
                                Accepted
                            </span>

                        <?php else: ?>

                            <span class="waiting">
                                Waiting for Driver Acceptance
                            </span>

                        <?php endif; ?>

                    </p>


                    <p>

                        <strong>
                            Start Date:
                        </strong>

                        <?php
                        echo htmlspecialchars(
                            $agreement["start_date"]
                        );
                        ?>

                    </p>


                    <p>

                        <strong>
                            End Date:
                        </strong>

                        <?php
                        echo htmlspecialchars(
                            $agreement["end_date"]
                        );
                        ?>

                    </p>


                    <p>

                        <strong>
                            Rent:
                        </strong>

                        ₹<?php

                        echo number_format(
                            (float)
                            $agreement["rent"],
                            2
                        );

                        ?>

                    </p>


                    <?php if (
                        (int) $agreement["accepted"] === 0
                    ): ?>

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

                    <?php else: ?>

                        <p>

                            Accepted At:

                            <?php
                            echo htmlspecialchars(
                                $agreement["accepted_at"]
                            );
                            ?>

                        </p>

                    <?php endif; ?>

                </div>


            <?php else: ?>

                <div class="no-data">

                    <h3>
                        Agreement Not Created
                    </h3>

                    <p>
                        Your taxi has been assigned,
                        but the admin has not created
                        the agreement yet.
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


            <!-- RENT -->

            <div class="dashboard-card">

                <h3>
                    Rent Payment
                </h3>

                <p>
                    View your taxi rent and payment records.
                </p>

                <a
                    class="dashboard-button"
                    href="payments.php"
                >
                    Rent & Payments
                </a>

            </div>


            <!-- TAXI AVAILABILITY -->

            <div class="dashboard-card">

                <h3>
                    Taxi Availability
                </h3>

                <p>
                    Check taxis currently available
                    for assignment.
                </p>

                <a
                    class="dashboard-button"
                    href="../index2.php#taxis"
                >
                    View Available Taxis
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