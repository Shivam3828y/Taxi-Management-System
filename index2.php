<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once "config.php";


// =====================================================
// AVAILABLE TAXIS
// =====================================================

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

        /* =================================================
           HERO
        ================================================= */

        .hero {
            text-align: center;
            padding: 50px 20px;
        }

        .hero h2 {
            margin-bottom: 10px;
        }

        .hero p {
            max-width: 700px;
            margin: 0 auto;
        }


        /* =================================================
           BUTTON
        ================================================= */

        .button {
            display: inline-block;
            padding: 10px 18px;
            border: 1px solid #333;
            border-radius: 6px;
            text-decoration: none;
        }


        /* =================================================
           SECTION TITLE
        ================================================= */

        .section-title {
            margin-bottom: 20px;
        }


        /* =================================================
           ACCESS SECTION
        ================================================= */

        .access-container {

            display: grid;

            grid-template-columns:
                repeat(2, 1fr);

            gap: 20px;

        }


        .access-card {

            border: 1px solid #ddd;

            border-radius: 10px;

            padding: 25px;

            background: #fff;

        }


        .access-card h3 {
            margin-top: 0;
        }


        /* =================================================
           AVAILABLE TAXIS
        ================================================= */

        .taxi-container {

            display: grid;

            grid-template-columns:
                repeat(3, 1fr);

            gap: 20px;

        }


        .taxi-card {

            border: 1px solid #ddd;

            border-radius: 10px;

            padding: 20px;

            background: #fff;

        }


        .taxi-card h3 {
            margin-top: 0;
        }


        .rent {
            font-weight: bold;
        }


        /* =================================================
           RESPONSIVE
        ================================================= */

        @media (max-width: 900px) {

            .taxi-container {
                grid-template-columns: 1fr 1fr;
            }

        }


        @media (max-width: 600px) {

            .access-container,
            .taxi-container {
                grid-template-columns: 1fr;
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

        <a href="index2.php">
            Home
        </a>

        <a href="#taxis">
            Available Taxis
        </a>

        <a href="driver/register.php">
            Driver Registration
        </a>

        <a href="driver/login.php">
            Driver Login
        </a>

        <a href="admin/login.php">
            Admin Login
        </a>

    </nav>

</header>


<!-- ================================================= -->
<!-- MAIN -->
<!-- ================================================= -->

<main>


<!-- ================================================= -->
<!-- HERO -->
<!-- ================================================= -->

<section class="hero">

    <h2>
        Taxi Management System
    </h2>

    <p>
        A centralized system for managing drivers,
        taxis, assignments, agreements and rent
        payments.
    </p>

</section>


<!-- ================================================= -->
<!-- SYSTEM ACCESS -->
<!-- ================================================= -->

<section>

    <h2 class="section-title">
        Access
    </h2>


    <div class="access-container">


        <!-- DRIVER -->

        <article class="access-card">

            <h3>
                Driver Portal
            </h3>

            <p>
                Existing drivers can login to
                view their assigned taxi,
                agreement and rent payments.
            </p>

            <a
                class="button"
                href="driver/login.php"
            >
                Driver Login
            </a>

            <a
                class="button"
                href="driver/register.php"
            >
                New Driver Registration
            </a>

        </article>


        <!-- ADMIN -->

        <article class="access-card">

            <h3>
                Admin Panel
            </h3>

            <p>
                Authorized administrators can
                manage drivers, taxis,
                assignments, agreements and
                payments.
            </p>

            <a
                class="button"
                href="admin/login.php"
            >
                Admin Login
            </a>

        </article>


    </div>

</section>


<!-- ================================================= -->
<!-- AVAILABLE TAXIS -->
<!-- ================================================= -->

<section id="taxis">

    <h2 class="section-title">
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

                        echo htmlspecialchars(
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

                        echo htmlspecialchars(
                            $taxi[
                                "registration_number"
                            ]
                        );

                        ?>

                    </p>


                    <p class="rent">

                        Daily Rent:

                        ₹<?php

                        echo number_format(
                            (float)
                            $taxi["rent"],
                            2
                        );

                        ?>

                    </p>


                    <p>

                        Status:

                        <strong>
                            Available
                        </strong>

                    </p>


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