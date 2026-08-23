<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();


// =====================================================
// ADMIN AUTHENTICATION
// =====================================================

if (!isset($_SESSION["admin_id"])) {

    header("Location: login.php");
    exit;
}


require_once "../config.php";


// =====================================================
// ADMIN NAME
// =====================================================

$admin_name = $_SESSION["admin_name"] ?? "Admin";


// =====================================================
// DASHBOARD STATISTICS
// =====================================================

$stats = [
    "drivers" => 0,
    "pending_drivers" => 0,
    "verified_drivers" => 0,
    "taxis" => 0,
    "available_taxis" => 0,
    "assigned_taxis" => 0,
    "assignments" => 0,
    "agreements" => 0,
    "pending_agreements" => 0,
    "payments" => 0
];


// =====================================================
// DRIVER COUNT
// =====================================================

$sql = "
    SELECT COUNT(*) AS total
    FROM drivers
";

$result = mysqli_query($conn, $sql);

if ($result) {

    $row = mysqli_fetch_assoc($result);

    $stats["drivers"] = (int) $row["total"];
}


// =====================================================
// PENDING DRIVERS
// =====================================================

$sql = "
    SELECT COUNT(*) AS total
    FROM drivers
    WHERE status = 'Pending'
";

$result = mysqli_query($conn, $sql);

if ($result) {

    $row = mysqli_fetch_assoc($result);

    $stats["pending_drivers"] = (int) $row["total"];
}


// =====================================================
// VERIFIED DRIVERS
// =====================================================

$sql = "
    SELECT COUNT(*) AS total
    FROM drivers
    WHERE status = 'Verified'
";

$result = mysqli_query($conn, $sql);

if ($result) {

    $row = mysqli_fetch_assoc($result);

    $stats["verified_drivers"] = (int) $row["total"];
}


// =====================================================
// TOTAL TAXIS
// =====================================================

$sql = "
    SELECT COUNT(*) AS total
    FROM taxis
";

$result = mysqli_query($conn, $sql);

if ($result) {

    $row = mysqli_fetch_assoc($result);

    $stats["taxis"] = (int) $row["total"];
}


// =====================================================
// AVAILABLE TAXIS
// =====================================================

$sql = "
    SELECT COUNT(*) AS total
    FROM taxis
    WHERE status = 'Available'
";

$result = mysqli_query($conn, $sql);

if ($result) {

    $row = mysqli_fetch_assoc($result);

    $stats["available_taxis"] = (int) $row["total"];
}


// =====================================================
// ASSIGNED TAXIS
// =====================================================

$sql = "
    SELECT COUNT(*) AS total
    FROM taxis
    WHERE status = 'Assigned'
";

$result = mysqli_query($conn, $sql);

if ($result) {

    $row = mysqli_fetch_assoc($result);

    $stats["assigned_taxis"] = (int) $row["total"];
}


// =====================================================
// ACTIVE ASSIGNMENTS
// =====================================================

$sql = "
    SELECT COUNT(*) AS total
    FROM assignments
    WHERE status = 'Active'
";

$result = mysqli_query($conn, $sql);

if ($result) {

    $row = mysqli_fetch_assoc($result);

    $stats["assignments"] = (int) $row["total"];
}


// =====================================================
// ACTIVE AGREEMENTS
// =====================================================

$sql = "
    SELECT COUNT(*) AS total
    FROM agreements
    WHERE status = 'Active'
";

$result = mysqli_query($conn, $sql);

if ($result) {

    $row = mysqli_fetch_assoc($result);

    $stats["agreements"] = (int) $row["total"];
}


// =====================================================
// PENDING AGREEMENTS
// =====================================================

$sql = "
    SELECT COUNT(*) AS total
    FROM agreements
    WHERE status = 'Active'
    AND accepted = 0
";

$result = mysqli_query($conn, $sql);

if ($result) {

    $row = mysqli_fetch_assoc($result);

    $stats["pending_agreements"] = (int) $row["total"];
}


// =====================================================
// PAYMENT COUNT
// =====================================================

$sql = "
    SELECT COUNT(*) AS total
    FROM payments
";

$result = mysqli_query($conn, $sql);

if ($result) {

    $row = mysqli_fetch_assoc($result);

    $stats["payments"] = (int) $row["total"];
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
        Admin Dashboard - Taxi Management System
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


        .dashboard-intro {

            margin-bottom: 30px;

        }


        /* =================================================
           STATISTICS
        ================================================= */

        .stats-grid {

            display: grid;

            grid-template-columns:
                repeat(4, 1fr);

            gap: 20px;

            margin-bottom: 35px;

        }


        .stat-card {

            background: #fff;

            border: 1px solid #ddd;

            border-radius: 10px;

            padding: 20px;

        }


        .stat-title {

            font-size: 14px;

            color: #666;

            margin-bottom: 8px;

        }


        .stat-number {

            font-size: 30px;

            font-weight: bold;

        }


        .stat-description {

            margin-top: 8px;

            font-size: 13px;

            color: #777;

        }


        /* =================================================
           MANAGEMENT
        ================================================= */

        .dashboard-grid {

            display: grid;

            grid-template-columns:
                repeat(3, 1fr);

            gap: 20px;

        }


        .dashboard-card {

            border: 1px solid #ddd;

            padding: 20px;

            border-radius: 10px;

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

            padding: 9px 15px;

            text-decoration: none;

            border-radius: 5px;

            background: #333;

            color: #fff;

        }


        .dashboard-card a:hover {

            opacity: 0.85;

        }


        .coming-soon {

            color: #777;

            font-style: italic;

        }


        /* =================================================
           WORKFLOW
        ================================================= */

        .workflow {

            margin-top: 35px;

        }


        .workflow ol {

            padding-left: 25px;

        }


        .workflow li {

            margin-bottom: 10px;

        }


        /* =================================================
           MOBILE
        ================================================= */

        @media (max-width: 1000px) {

            .stats-grid {

                grid-template-columns:
                    repeat(2, 1fr);

            }


            .dashboard-grid {

                grid-template-columns:
                    repeat(2, 1fr);

            }

        }


        @media (max-width: 600px) {

            .stats-grid,

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

<main class="dashboard-container">


    <!-- =================================================
         WELCOME
    ================================================= -->

    <section class="dashboard-intro">

        <h2>

            Welcome,
            <?php
            echo htmlspecialchars(
                $admin_name
            );
            ?>

        </h2>


        <p>

            Manage drivers, taxis, assignments,
            agreements and payments from the
            admin panel.

        </p>

    </section>


    <!-- =================================================
         STATISTICS
    ================================================= -->

    <section>

        <h2>
            System Overview
        </h2>


        <div class="stats-grid">


            <!-- DRIVERS -->

            <div class="stat-card">

                <div class="stat-title">
                    Total Drivers
                </div>

                <div class="stat-number">

                    <?php
                    echo $stats["drivers"];
                    ?>

                </div>

                <div class="stat-description">

                    <?php
                    echo $stats["verified_drivers"];
                    ?>

                    verified drivers

                </div>

            </div>


            <!-- PENDING DRIVERS -->

            <div class="stat-card">

                <div class="stat-title">
                    Pending Drivers
                </div>

                <div class="stat-number">

                    <?php
                    echo $stats["pending_drivers"];
                    ?>

                </div>

                <div class="stat-description">

                    Applications waiting
                    for verification

                </div>

            </div>


            <!-- TAXIS -->

            <div class="stat-card">

                <div class="stat-title">
                    Total Taxis
                </div>

                <div class="stat-number">

                    <?php
                    echo $stats["taxis"];
                    ?>

                </div>

                <div class="stat-description">

                    <?php
                    echo $stats["available_taxis"];
                    ?>

                    available

                </div>

            </div>


            <!-- ASSIGNED -->

            <div class="stat-card">

                <div class="stat-title">
                    Assigned Taxis
                </div>

                <div class="stat-number">

                    <?php
                    echo $stats["assigned_taxis"];
                    ?>

                </div>

                <div class="stat-description">

                    <?php
                    echo $stats["assignments"];
                    ?>

                    active assignments

                </div>

            </div>


            <!-- AGREEMENTS -->

            <div class="stat-card">

                <div class="stat-title">
                    Active Agreements
                </div>

                <div class="stat-number">

                    <?php
                    echo $stats["agreements"];
                    ?>

                </div>

                <div class="stat-description">

                    Active taxi agreements

                </div>

            </div>


            <!-- PENDING AGREEMENTS -->

            <div class="stat-card">

                <div class="stat-title">
                    Pending Agreements
                </div>

                <div class="stat-number">

                    <?php
                    echo $stats["pending_agreements"];
                    ?>

                </div>

                <div class="stat-description">

                    Waiting for driver acceptance

                </div>

            </div>


            <!-- PAYMENTS -->

            <div class="stat-card">

                <div class="stat-title">
                    Payment Records
                </div>

                <div class="stat-number">

                    <?php
                    echo $stats["payments"];
                    ?>

                </div>

                <div class="stat-description">

                    Recorded payment transactions

                </div>

            </div>


            <!-- AVAILABLE TAXIS -->

            <div class="stat-card">

                <div class="stat-title">
                    Available Taxis
                </div>

                <div class="stat-number">

                    <?php
                    echo $stats["available_taxis"];
                    ?>

                </div>

                <div class="stat-description">

                    Ready for assignment

                </div>

            </div>


        </div>

    </section>


    <!-- =================================================
         MANAGEMENT OPTIONS
    ================================================= -->

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

                    Review driver applications,
                    verify drivers and manage
                    driver accounts.

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
                    availability, assignment and
                    maintenance status.

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

                    Assign available taxis to
                    verified drivers and manage
                    active assignments.

                </p>

                <a href="assignments.php">
                    Manage Assignments
                </a>

            </div>


            <!-- AGREEMENTS -->

            

            <!-- PAYMENTS -->

            <div class="dashboard-card">

                <h3>
                    Payments
                </h3>

                <p>

                    View and manage driver taxi
                    rent payment records.

                </p>

                <a href="payments.php">
                    Manage Payments
                </a>

            </div>


            <!-- MAINTENANCE -->

            <div class="dashboard-card">

                <h3>
                    Maintenance
                </h3>

                <p>

                    Track taxi servicing, repairs
                    and maintenance records.

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


        </div>

    </section>


    <!-- =================================================
         WORKFLOW
    ================================================= -->

    <section class="workflow">

        <h2>
            Driver & Taxi Workflow
        </h2>


        <ol>

            <li>
                Driver submits a registration
                application.
            </li>


            <li>
                Admin reviews and verifies
                the driver.
            </li>


            <li>
                Admin adds and manages taxis.
            </li>


            <li>
                Admin assigns an available taxi
                to a verified driver.
            </li>


            <li>
                Admin creates the rental agreement
                for the assignment.
            </li>


            <li>
                Driver logs into the driver portal
                and accepts the agreement.
            </li>


            <li>
                Driver can view the assigned taxi,
                rent and payment records.
            </li>


            <li>
                Payment records can be managed
                through the admin payment section.
            </li>


        </ol>

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