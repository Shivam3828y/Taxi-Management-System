<?php

session_start();

if (!isset($_SESSION["admin_id"])) {
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

    <title>Admin Dashboard - Taxi Management System</title>

    <link
        rel="stylesheet"
        href="../css/style.css"
    >

    <style>

        .dashboard-intro {
            margin-bottom: 25px;
        }

        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
        }

        .dashboard-card {
            border: 1px solid #ddd;
            padding: 20px;
            border-radius: 8px;
            background: #fff;
        }

        .dashboard-card h3 {
            margin-top: 0;
        }

        .dashboard-card p {
            margin-bottom: 15px;
        }

        .dashboard-card a {
            display: inline-block;
            padding: 8px 14px;
            text-decoration: none;
            border-radius: 5px;
            background: #333;
            color: #fff;
        }

        .dashboard-card a:hover {
            opacity: 0.85;
        }

        .workflow {
            margin-top: 30px;
        }

        .workflow ol {
            padding-left: 25px;
        }

        .workflow li {
            margin-bottom: 10px;
        }

        .coming-soon {
            color: #777;
            font-style: italic;
        }

        @media (max-width: 900px) {

            .dashboard-grid {
                grid-template-columns: repeat(2, 1fr);
            }

        }

        @media (max-width: 600px) {

            .dashboard-grid {
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

        <a href="dashboard.php">
            Dashboard
        </a>

        <a href="drivers.php">
            Drivers
        </a>

        <a href="taxis.php">
            Taxis
        </a>

        <a href="assignments.php">
            Assignments
        </a>

        <a href="agreements.php">
            Agreements
        </a>

        <a href="payments.php">
            Payments
        </a>

        <a href="../index2.php">
            Public Portal
        </a>

        <a href="logout.php">
            Logout
        </a>

    </nav>

</header>


<!-- ================================================= -->
<!-- MAIN -->
<!-- ================================================= -->

<main>


    <!-- ================================================= -->
    <!-- WELCOME -->
    <!-- ================================================= -->

    <section class="dashboard-intro">

        <h2>

            Welcome,
            <?php
            echo htmlspecialchars(
                $_SESSION["admin_name"]
            );
            ?>

        </h2>


        <p>
            Manage drivers, taxis, assignments,
            agreements and other taxi operations
            from the admin panel.
        </p>

    </section>


    <!-- ================================================= -->
    <!-- MANAGEMENT OPTIONS -->
    <!-- ================================================= -->

    <section>

        <h2>
            Management
        </h2>


        <div class="dashboard-grid">


            <!-- DRIVER -->

            <div class="dashboard-card">

                <h3>
                    Driver Management
                </h3>

                <p>
                    Register, verify and manage
                    taxi drivers.
                </p>

                <a href="drivers.php">
                    Manage Drivers
                </a>

            </div>


            <!-- TAXI -->

            <div class="dashboard-card">

                <h3>
                    Taxi Management
                </h3>

                <p>
                    Add taxis and manage their
                    status and fixed rent.
                </p>

                <a href="taxis.php">
                    Manage Taxis
                </a>

            </div>


            <!-- ASSIGNMENT -->

            <div class="dashboard-card">

                <h3>
                    Taxi Assignments
                </h3>

                <p>
                    Assign an available taxi
                    to a verified driver.
                </p>

                <a href="assignments.php">
                    Manage Assignments
                </a>

            </div>


            <!-- AGREEMENT -->

            <div class="dashboard-card">

                <h3>
                    Agreements
                </h3>

                <p>
                    Create and manage driver
                    taxi rental agreements.
                </p>

                <a href="agreements.php">
                    Manage Agreements
                </a>

            </div>


            <!-- MAINTENANCE -->

            <div class="dashboard-card">

                <h3>
                    Maintenance
                </h3>

                <p>
                    Track taxi servicing,
                    repairs and maintenance.
                </p>

                <p class="coming-soon">
                    Coming soon
                </p>

            </div>


            <!-- FINES -->

            <div class="dashboard-card">

                <h3>
                    Fines
                </h3>

                <p>
                    Record traffic fines and
                    assign responsibility.
                </p>

                <p class="coming-soon">
                    Coming soon
                </p>

            </div>


            <!-- PAYMENTS -->

            <div class="dashboard-card">

                <h3>
                    Payments
                </h3>

                <p>
                    Manage rent payments and
                    payment records.
                </p>

                <p class="coming-soon">
                    Coming soon
                </p>

            </div>


        </div>

    </section>


   
<footer>

    <p>
        &copy; 2026 Taxi Management System
    </p>

</footer>


</body>

</html>