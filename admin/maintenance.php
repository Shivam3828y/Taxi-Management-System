<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

if (!isset($_SESSION["admin_id"])) {
    header("Location: login.php");
    exit;
}

require_once "../config.php";

$message = "";
$error = "";


// =====================================================
// UPDATE MAINTENANCE STATUS
// =====================================================

if (
    $_SERVER["REQUEST_METHOD"] === "POST"
    && isset($_POST["update_status"])
) {

    $maintenance_id =
        (int)($_POST["maintenance_id"] ?? 0);

    $status =
        $_POST["status"] ?? "";


    $allowed_statuses = [
        "Pending",
        "Under Maintenance",
        "Completed"
    ];


    if (
        $maintenance_id <= 0 ||
        !in_array($status, $allowed_statuses, true)
    ) {

        $error = "Invalid maintenance request.";

    } else {

        $sql = "
            UPDATE maintenance
            SET status = ?
            WHERE id = ?
        ";

        $stmt = mysqli_prepare($conn, $sql);

        if (!$stmt) {

            $error =
                "Database error: "
                . mysqli_error($conn);

        } else {

            mysqli_stmt_bind_param(
                $stmt,
                "si",
                $status,
                $maintenance_id
            );

            if (mysqli_stmt_execute($stmt)) {

                $message =
                    "Maintenance status updated successfully.";

            } else {

                $error =
                    "Failed to update maintenance status.";
            }

            mysqli_stmt_close($stmt);
        }
    }
}


// =====================================================
// GET ALL MAINTENANCE RECORDS
// =====================================================

$sql = "
    SELECT
        maintenance.id,
        maintenance.issue,
        maintenance.description,
        maintenance.status,
        maintenance.created_at,

        drivers.name AS driver_name,

        taxis.brand,
        taxis.model,
        taxis.registration_number

    FROM maintenance

    INNER JOIN drivers
        ON maintenance.driver_id = drivers.id

    INNER JOIN taxis
        ON maintenance.taxi_id = taxis.id

    ORDER BY maintenance.id DESC
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
        Maintenance Management
    </title>

    <link
        rel="stylesheet"
        href="../css/style.css"
    >

    <style>

        .maintenance-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        .maintenance-table th,
        .maintenance-table td {
            border: 1px solid #ddd;
            padding: 10px;
            text-align: left;
            vertical-align: top;
        }

        .maintenance-table th {
            background: #f5f5f5;
        }

        .status-form {
            display: flex;
            gap: 5px;
            align-items: center;
        }

        .status-form select {
            padding: 6px;
        }

        .status-form button {
            padding: 6px 10px;
        }

        .success-message {
            color: green;
            margin-bottom: 15px;
        }

        .error-message {
            color: red;
            margin-bottom: 15px;
        }

        .pending {
            color: #b45309;
            font-weight: bold;
        }

        .under-maintenance {
            color: #2563eb;
            font-weight: bold;
        }

        .completed {
            color: #15803d;
            font-weight: bold;
        }

        @media (max-width: 900px) {

            .maintenance-table {
                display: block;
                overflow-x: auto;
                white-space: nowrap;
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
        Maintenance Management
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

        <a href="logout.php">
            Logout
        </a>

    </nav>

</header>


<!-- ================================================= -->
<!-- MAIN -->
<!-- ================================================= -->

<main>

<section>

    <h2>
        Maintenance Records
    </h2>


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


    <?php if (
        $result &&
        mysqli_num_rows($result) > 0
    ): ?>


        <table class="maintenance-table">

            <thead>

                <tr>

                    <th>
                        ID
                    </th>

                    <th>
                        Driver
                    </th>

                    <th>
                        Taxi
                    </th>

                    <th>
                        Registration
                    </th>

                    <th>
                        Issue
                    </th>

                    <th>
                        Description
                    </th>

                    <th>
                        Date
                    </th>

                    <th>
                        Status
                    </th>

                    <th>
                        Update
                    </th>

                </tr>

            </thead>


            <tbody>


                <?php while (
                    $maintenance =
                    mysqli_fetch_assoc($result)
                ): ?>


                    <tr>


                        <!-- ID -->

                        <td>

                            <?php
                            echo $maintenance["id"];
                            ?>

                        </td>


                        <!-- DRIVER -->

                        <td>

                            <?php

                            echo htmlspecialchars(
                                $maintenance[
                                    "driver_name"
                                ]
                            );

                            ?>

                        </td>


                        <!-- TAXI -->

                        <td>

                            <?php

                            echo htmlspecialchars(
                                $maintenance["brand"]
                                . " "
                                . $maintenance["model"]
                            );

                            ?>

                        </td>


                        <!-- REGISTRATION -->

                        <td>

                            <?php

                            echo htmlspecialchars(
                                $maintenance[
                                    "registration_number"
                                ]
                            );

                            ?>

                        </td>


                        <!-- ISSUE -->

                        <td>

                            <?php

                            echo htmlspecialchars(
                                $maintenance["issue"]
                            );

                            ?>

                        </td>


                        <!-- DESCRIPTION -->

                        <td>

                            <?php

                            echo nl2br(
                                htmlspecialchars(
                                    $maintenance[
                                        "description"
                                    ]
                                )
                            );

                            ?>

                        </td>


                        <!-- DATE -->

                        <td>

                            <?php

                            echo htmlspecialchars(
                                $maintenance[
                                    "created_at"
                                ]
                            );

                            ?>

                        </td>


                        <!-- STATUS -->

                        <td>

                            <?php

                            $current_status =
                                $maintenance["status"];


                            if (
                                $current_status
                                === "Pending"
                            ) {

                                echo '<span class="pending">
                                    Pending
                                </span>';

                            } elseif (
                                $current_status
                                === "Under Maintenance"
                            ) {

                                echo '<span class="under-maintenance">
                                    Under Maintenance
                                </span>';

                            } else {

                                echo '<span class="completed">
                                    Completed
                                </span>';

                            }

                            ?>

                        </td>


                        <!-- UPDATE STATUS -->

                        <td>

                            <form
                                method="POST"
                                class="status-form"
                            >

                                <input
                                    type="hidden"
                                    name="update_status"
                                    value="1"
                                >

                                <input
                                    type="hidden"
                                    name="maintenance_id"
                                    value="<?php
                                        echo $maintenance["id"];
                                    ?>"
                                >


                                <select
                                    name="status"
                                    required
                                >

                                    <option
                                        value="Pending"
                                        <?php
                                        if (
                                            $current_status
                                            === "Pending"
                                        ) {
                                            echo "selected";
                                        }
                                        ?>
                                    >
                                        Pending
                                    </option>


                                    <option
                                        value="Under Maintenance"
                                        <?php
                                        if (
                                            $current_status
                                            === "Under Maintenance"
                                        ) {
                                            echo "selected";
                                        }
                                        ?>
                                    >
                                        Under Maintenance
                                    </option>


                                    <option
                                        value="Completed"
                                        <?php
                                        if (
                                            $current_status
                                            === "Completed"
                                        ) {
                                            echo "selected";
                                        }
                                        ?>
                                    >
                                        Completed
                                    </option>

                                </select>


                                <button type="submit">
                                    Update
                                </button>

                            </form>

                        </td>


                    </tr>


                <?php endwhile; ?>


            </tbody>

        </table>


    <?php else: ?>


        <p>
            No maintenance records found.
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