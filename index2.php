<?php

require_once "config.php";

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


/* =========================================================
   CHECK DRIVER STATUS
   ========================================================= */

$driver_logged_in = false;
$driver_verified = false;

if (isset($_SESSION["driver_id"])) {

    $driver_id = (int) $_SESSION["driver_id"];

    $driver_status_sql = "
        SELECT status
        FROM drivers
        WHERE id = ?
        LIMIT 1
    ";

    $driver_status_stmt = mysqli_prepare(
        $conn,
        $driver_status_sql
    );

    if ($driver_status_stmt) {

        mysqli_stmt_bind_param(
            $driver_status_stmt,
            "i",
            $driver_id
        );

        mysqli_stmt_execute(
            $driver_status_stmt
        );

        $driver_status_result =
            mysqli_stmt_get_result(
                $driver_status_stmt
            );

        $driver_data =
            mysqli_fetch_assoc(
                $driver_status_result
            );

        mysqli_stmt_close(
            $driver_status_stmt
        );

        if ($driver_data) {

            $driver_logged_in = true;

            if ($driver_data["status"] === "Verified") {
                $driver_verified = true;
            }
        }
    }
}


/* =========================================================
   AVAILABLE TAXIS
   ========================================================= */

$sql = "
    SELECT
        id,
        brand,
        model,
        registration_number,
        rent
    FROM taxis
    WHERE status = 'Available'
    ORDER BY id DESC
";

$result = mysqli_query($conn, $sql);

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
        Taxi Management System
    </title>

    <link
        rel="stylesheet"
        href="css/style.css"
    >

    <style>

        /* ==============================
           HOME PAGE
        ============================== */

        .hero {
            text-align: center;
            padding: 40px 20px;
        }

        .hero h2 {
            margin-bottom: 10px;
        }

        .hero p {
            margin-bottom: 20px;
        }


        /* ==============================
           ACCESS CARDS
        ============================== */

        .access-container {
            display: grid;
            grid-template-columns:
                repeat(3, 1fr);
            gap: 20px;
        }

        .access-card {
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 20px;
            background: #fff;
        }

        .access-card h3 {
            margin-top: 0;
        }

        .access-card a {
            display: inline-block;
            margin-top: 10px;
            padding: 8px 14px;
            text-decoration: none;
        }


        /* ==============================
           TAXI CARDS
        ============================== */

        .taxi-container {
            display: grid;
            grid-template-columns:
                repeat(3, 1fr);
            gap: 20px;
        }

        .taxi-card {
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 20px;
            background: #fff;
        }

        .taxi-card h3 {
            margin-top: 0;
        }

        .rent {
            font-weight: bold;
        }


        /* ==============================
           REQUEST BUTTON
        ============================== */

        .request-button {
            display: inline-block;

            margin-top: 12px;

            padding: 10px 16px;

            background: #15803d;

            color: #fff;

            text-decoration: none;

            border-radius: 6px;

            font-weight: bold;
        }

        .request-button:hover {
            background: #166534;
        }


        /* ==============================
           LOGIN BUTTON
        ============================== */

        .login-button {
            display: inline-block;

            margin-top: 12px;

            padding: 10px 16px;

            background: #fff;

            color: #222;

            text-decoration: none;

            border: 1px solid #333;

            border-radius: 6px;
        }


        /* ==============================
           STATUS BUTTON
        ============================== */

        .status-button {
            display: inline-block;

            margin-top: 12px;

            padding: 10px 16px;

            background: #f3f4f6;

            color: #555;

            border: 1px solid #ccc;

            border-radius: 6px;

            font-weight: bold;
        }


        .taxi-action-note {
            margin-top: 10px;

            font-size: 14px;

            color: #666;
        }


        /* ==============================
           MOBILE
        ============================== */

        @media (max-width: 800px) {

            .access-container,
            .taxi-container {
                grid-template-columns: 1fr;
            }

        }

    </style>

</head>


<body>


<!-- ============================== -->
<!-- HEADER -->
<!-- ============================== -->

<header>

    <h1>
        Taxi Management System
    </h1>


    <nav>

        <a href="index2.php">
            Home
        </a>

        <a href="#taxis">
            Taxis
        </a>

        <a href="admin/login.php">
            Admin
        </a>

        <a href="driver/login.php">
            Driver
        </a>

    </nav>

</header>


<!-- ============================== -->
<!-- MAIN -->
<!-- ============================== -->

<main>


<!-- ============================== -->
<!-- WELCOME -->
<!-- ============================== -->

<section class="hero">

    <h2>
        Welcome to the Taxi Management System
    </h2>


    <p>
        Manage taxis, drivers, assignments,
        agreements and other taxi operations
        in one place.
    </p>

</section>


<!-- ============================== -->
<!-- SYSTEM ACCESS -->
<!-- ============================== -->

<section>

    <h2>
        System Access
    </h2>


    <div class="access-container">


        <!-- ADMIN -->

        <article class="access-card">

            <h3>
                Admin
            </h3>


            <p>
                Manage drivers, taxis, assignments,
                agreements and payments.
            </p>


            <a href="admin/login.php">
                Admin Login
            </a>

        </article>


        <!-- DRIVER -->

        <article class="access-card">

            <h3>
                Driver
            </h3>


            <p>
                Login to view your assignment,
                agreement, taxi details and
                payment information.
            </p>


            <a href="driver/login.php">
                Driver Login
            </a>

        </article>


        <!-- TAXIS -->

        <article class="access-card">

            <h3>
                Available Taxis
            </h3>


            <p>
                View taxis that are currently
                available for assignment.
            </p>


            <a href="#taxis">
                View Taxis
            </a>

        </article>


    </div>

</section>


<!-- ============================== -->
<!-- AVAILABLE TAXIS -->
<!-- ============================== -->

<section id="taxis">

    <h2>
        Available Taxis
    </h2>


    <?php if (
        $result &&
        mysqli_num_rows($result) > 0
    ): ?>


        <div class="taxi-container">


            <?php while (
                $taxi =
                mysqli_fetch_assoc($result)
            ): ?>


                <article class="taxi-card">


                    <h3>

                        <?php

                        echo e(
                            $taxi["brand"]
                            . " "
                            . $taxi["model"]
                        );

                        ?>

                    </h3>


                    <p>

                        <strong>
                            Registration:
                        </strong>

                        <?php

                        echo e(
                            $taxi[
                                "registration_number"
                            ]
                        );

                        ?>

                    </p>


                    <p class="rent">

                        Rent:

                        ₹<?php

                        echo number_format(
                            (float) $taxi["rent"],
                            2
                        );

                        ?>

                        / day

                    </p>


                    <!-- ==============================
                         DRIVER ACTION
                    ============================== -->

                    <?php if ($driver_verified): ?>


                        <!-- VERIFIED DRIVER -->

                        <a
                            href="driver/taxi_request.php?taxi_id=<?= (int) $taxi["id"] ?>"
                            class="request-button"
                        >
                            Request This Taxi
                        </a>


                        <p class="taxi-action-note">

                            Your request will be sent to
                            admin for approval.

                        </p>


                    <?php elseif ($driver_logged_in): ?>


                        <!-- LOGGED IN BUT NOT VERIFIED -->

                        <span class="status-button">
                            Driver Verification Pending
                        </span>


                        <p class="taxi-action-note">

                            You can request a taxi after
                            admin verifies your driver account.

                        </p>


                    <?php else: ?>


                        <!-- NOT LOGGED IN -->

                        <a
                            href="driver/login.php"
                            class="login-button"
                        >
                            Login as Driver to Request
                        </a>


                        <p class="taxi-action-note">

                            Driver login is required
                            to request a taxi.

                        </p>


                    <?php endif; ?>


                </article>


            <?php endwhile; ?>


        </div>


    <?php else: ?>


        <p>
            No taxis are currently available.
        </p>


    <?php endif; ?>


</section>


</main>


<!-- ============================== -->
<!-- FOOTER -->
<!-- ============================== -->

<footer>

    <p>
        &copy; 2026 Taxi Management System
    </p>

</footer>


</body>

</html>