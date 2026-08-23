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
// GET DRIVER PROFILE
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


// =====================================================
// DRIVER NOT FOUND
// =====================================================

if (!$driver) {

    session_destroy();

    header("Location: login.php");

    exit;
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
        My Profile - Taxi Management System
    </title>

    <link
        rel="stylesheet"
        href="../css/style.css"
    >

    <style>

        .profile-container {

            max-width: 800px;

            margin: 40px auto;

        }

        .profile-card {

            border: 1px solid #ddd;

            border-radius: 10px;

            padding: 25px;

            background: #fff;

        }

        .profile-row {

            display: flex;

            justify-content: space-between;

            gap: 20px;

            padding: 14px 0;

            border-bottom: 1px solid #ddd;

        }

        .profile-row:last-child {

            border-bottom: none;

        }

        .profile-label {

            font-weight: bold;

        }

        .verified {

            color: green;

            font-weight: bold;

        }

        .pending {

            color: #b45309;

            font-weight: bold;

        }

        .inactive {

            color: #b91c1c;

            font-weight: bold;

        }

        .dashboard-button {

            display: inline-block;

            margin-top: 20px;

            padding: 10px 15px;

            border: 1px solid #333;

            border-radius: 6px;

            text-decoration: none;

        }

        @media (max-width: 600px) {

            .profile-row {

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
<!-- PROFILE -->
<!-- ================================================= -->

<main class="profile-container">

    <section>

        <h2>
            My Profile
        </h2>

        <p>
            Your registered driver information.
        </p>


        <div class="profile-card">


            <!-- NAME -->

            <div class="profile-row">

                <span class="profile-label">
                    Full Name
                </span>

                <span>
                    <?php
                    echo htmlspecialchars(
                        $driver["name"]
                    );
                    ?>
                </span>

            </div>


            <!-- PHONE -->

            <div class="profile-row">

                <span class="profile-label">
                    Phone Number
                </span>

                <span>
                    <?php
                    echo htmlspecialchars(
                        $driver["phone"]
                    );
                    ?>
                </span>

            </div>


            <!-- EMAIL -->

            <div class="profile-row">

                <span class="profile-label">
                    Email
                </span>

                <span>

                    <?php

                    echo $driver["email"] !== ""
                        ? htmlspecialchars(
                            $driver["email"]
                        )
                        : "Not provided";

                    ?>

                </span>

            </div>


            <!-- ADDRESS -->

            <div class="profile-row">

                <span class="profile-label">
                    Address
                </span>

                <span>

                    <?php

                    echo $driver["address"] !== ""
                        ? htmlspecialchars(
                            $driver["address"]
                        )
                        : "Not provided";

                    ?>

                </span>

            </div>


            <!-- LICENSE -->

            <div class="profile-row">

                <span class="profile-label">
                    Driving License
                </span>

                <span>
                    <?php
                    echo htmlspecialchars(
                        $driver["driving_license"]
                    );
                    ?>
                </span>

            </div>


            <!-- STATUS -->

            <div class="profile-row">

                <span class="profile-label">
                    Account Status
                </span>

                <span>

                    <?php if (
                        $driver["status"] === "Verified"
                    ): ?>

                        <span class="verified">
                            Verified
                        </span>

                    <?php elseif (
                        $driver["status"] === "Pending"
                    ): ?>

                        <span class="pending">
                            Pending Verification
                        </span>

                    <?php else: ?>

                        <span class="inactive">
                            Inactive
                        </span>

                    <?php endif; ?>

                </span>

            </div>


        </div>


        <a
            class="dashboard-button"
            href="dashboard.php"
        >
            Back to Dashboard
        </a>


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