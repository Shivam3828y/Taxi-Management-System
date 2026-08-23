<?php

session_start();

if (!isset($_SESSION["admin_id"])) {
    header("Location: login.php");
    exit;
}

require_once "../config.php";

$message = "";
$error = "";


// =====================================================
// ASSIGN TAXI
// =====================================================

if (
    $_SERVER["REQUEST_METHOD"] === "POST"
    && isset($_POST["assign_taxi"])
) {

    $driver_id = (int) ($_POST["driver_id"] ?? 0);
    $taxi_id = (int) ($_POST["taxi_id"] ?? 0);


    if ($driver_id <= 0 || $taxi_id <= 0) {

        $error = "Please select a driver and taxi.";

    } else {

        /*
         * Start transaction.
         *
         * Assignment creation and taxi status update
         * must succeed together.
         */

        mysqli_begin_transaction($conn);


        try {

            // =================================================
            // CHECK DRIVER
            // =================================================

            $driver_sql = "
                SELECT id, name
                FROM drivers
                WHERE id = ?
                AND status = 'Verified'
                LIMIT 1
            ";

            $driver_stmt = mysqli_prepare(
                $conn,
                $driver_sql
            );

            if (!$driver_stmt) {
                throw new Exception(
                    "Database error while checking driver."
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

            $driver = mysqli_fetch_assoc(
                $driver_result
            );

            mysqli_stmt_close($driver_stmt);


            if (!$driver) {

                throw new Exception(
                    "Driver must be verified before assigning a taxi."
                );
            }


            // =================================================
            // CHECK EXISTING ACTIVE ASSIGNMENT
            // =================================================

            $check_driver_sql = "
                SELECT id
                FROM assignments
                WHERE driver_id = ?
                AND status = 'Active'
                LIMIT 1
            ";

            $check_driver_stmt = mysqli_prepare(
                $conn,
                $check_driver_sql
            );

            if (!$check_driver_stmt) {
                throw new Exception(
                    "Database error while checking driver assignment."
                );
            }

            mysqli_stmt_bind_param(
                $check_driver_stmt,
                "i",
                $driver_id
            );

            mysqli_stmt_execute(
                $check_driver_stmt
            );

            $check_driver_result =
                mysqli_stmt_get_result(
                    $check_driver_stmt
                );

            $existing_assignment =
                mysqli_fetch_assoc(
                    $check_driver_result
                );

            mysqli_stmt_close(
                $check_driver_stmt
            );


            if ($existing_assignment) {

                throw new Exception(
                    "This driver already has an active taxi."
                );
            }


            // =================================================
            // CHECK TAXI
            // =================================================

            $taxi_sql = "
                SELECT
                    id,
                    brand,
                    model,
                    registration_number,
                    rent
                FROM taxis
                WHERE id = ?
                AND status = 'Available'
                LIMIT 1
            ";

            $taxi_stmt = mysqli_prepare(
                $conn,
                $taxi_sql
            );

            if (!$taxi_stmt) {
                throw new Exception(
                    "Database error while checking taxi."
                );
            }

            mysqli_stmt_bind_param(
                $taxi_stmt,
                "i",
                $taxi_id
            );

            mysqli_stmt_execute(
                $taxi_stmt
            );

            $taxi_result =
                mysqli_stmt_get_result(
                    $taxi_stmt
                );

            $taxi = mysqli_fetch_assoc(
                $taxi_result
            );

            mysqli_stmt_close(
                $taxi_stmt
            );


            if (!$taxi) {

                throw new Exception(
                    "Taxi is no longer available."
                );
            }


            // =================================================
            // CREATE ASSIGNMENT
            // =================================================

            $insert_sql = "
                INSERT INTO assignments
                (
                    driver_id,
                    taxi_id,
                    status
                )
                VALUES
                (
                    ?,
                    ?,
                    'Active'
                )
            ";

            $insert_stmt = mysqli_prepare(
                $conn,
                $insert_sql
            );

            if (!$insert_stmt) {
                throw new Exception(
                    "Database error while creating assignment."
                );
            }

            mysqli_stmt_bind_param(
                $insert_stmt,
                "ii",
                $driver_id,
                $taxi_id
            );

            if (
                !mysqli_stmt_execute(
                    $insert_stmt
                )
            ) {

                mysqli_stmt_close(
                    $insert_stmt
                );

                throw new Exception(
                    "Failed to create taxi assignment."
                );
            }

            mysqli_stmt_close(
                $insert_stmt
            );


            // =================================================
            // UPDATE TAXI STATUS
            // =================================================

            $update_sql = "
                UPDATE taxis
                SET status = 'Assigned'
                WHERE id = ?
                AND status = 'Available'
            ";

            $update_stmt = mysqli_prepare(
                $conn,
                $update_sql
            );

            if (!$update_stmt) {
                throw new Exception(
                    "Database error while updating taxi status."
                );
            }

            mysqli_stmt_bind_param(
                $update_stmt,
                "i",
                $taxi_id
            );

            mysqli_stmt_execute(
                $update_stmt
            );


            if (
                mysqli_stmt_affected_rows(
                    $update_stmt
                ) !== 1
            ) {

                mysqli_stmt_close(
                    $update_stmt
                );

                throw new Exception(
                    "Taxi status could not be updated."
                );
            }

            mysqli_stmt_close(
                $update_stmt
            );


            // =================================================
            // COMMIT
            // =================================================

            mysqli_commit($conn);


            $message =
                "Taxi assigned successfully to "
                . $driver["name"]
                . ". Rent: ₹"
                . number_format(
                    (float) $taxi["rent"],
                    2
                )
                . " / day";


        } catch (Exception $e) {

            // =================================================
            // ROLLBACK
            // =================================================

            mysqli_rollback($conn);

            $error = $e->getMessage();
        }
    }
}


// =====================================================
// VERIFIED DRIVERS WITHOUT ACTIVE ASSIGNMENT
// =====================================================

$drivers_sql = "
    SELECT
        d.id,
        d.name
    FROM drivers d

    LEFT JOIN assignments a
        ON d.id = a.driver_id
        AND a.status = 'Active'

    WHERE d.status = 'Verified'
    AND a.id IS NULL

    ORDER BY d.name
";

$drivers_result = mysqli_query(
    $conn,
    $drivers_sql
);


// =====================================================
// AVAILABLE TAXIS
// =====================================================

$taxis_sql = "
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

$taxis_result = mysqli_query(
    $conn,
    $taxis_sql
);


// =====================================================
// ACTIVE ASSIGNMENTS
// =====================================================

$assignments_sql = "
    SELECT
        assignments.id,
        assignments.driver_id,
        assignments.taxi_id,

        drivers.name AS driver_name,

        taxis.brand,
        taxis.model,
        taxis.registration_number,
        taxis.rent,

        assignments.assigned_at,
        assignments.status

    FROM assignments

    INNER JOIN drivers
        ON assignments.driver_id = drivers.id

    INNER JOIN taxis
        ON assignments.taxi_id = taxis.id

    WHERE assignments.status = 'Active'

    ORDER BY assignments.id DESC
";

$assignments_result = mysqli_query(
    $conn,
    $assignments_sql
);

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
        Taxi Assignment - Taxi Management System
    </title>

    <link
        rel="stylesheet"
        href="../css/style.css"
    >

    <style>

        .message {
            padding: 12px;
            margin-bottom: 20px;
            border-radius: 6px;
        }

        .success-message {
            background: #f0fdf4;
            color: #166534;
            border: 1px solid #86efac;
        }

        .error-message {
            background: #fef2f2;
            color: #991b1b;
            border: 1px solid #fca5a5;
        }

        .rent-display {

            display: none;

            margin-top: 10px;
            padding: 10px;

            background: #f8fafc;

            border: 1px solid #ddd;

            border-radius: 6px;
        }

        .assignment-form {

            max-width: 600px;

        }

        .form-group {

            margin-bottom: 15px;

        }

        .form-group label {

            display: block;

            margin-bottom: 6px;

            font-weight: bold;

        }

        .form-group select {

            width: 100%;

            box-sizing: border-box;

            padding: 10px;

        }

        .table-container {

            overflow-x: auto;

        }

        table {

            width: 100%;

            border-collapse: collapse;

        }

        th,
        td {

            padding: 10px;

            border: 1px solid #ddd;

            text-align: left;

        }

        th {

            background: #f5f5f5;

        }

        .status-active {

            color: #15803d;

            font-weight: bold;

        }

    </style>

</head>


<body>


<!-- ================================================= -->
<!-- HEADER -->
<!-- ================================================= -->

<header>

    <h1>
        Taxi Assignment
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

<main>


<!-- ================================================= -->
<!-- ASSIGN TAXI -->
<!-- ================================================= -->

<section>

    <h2>
        Assign Taxi to Driver
    </h2>


    <?php if ($message !== ""): ?>

        <div class="message success-message">

            <?php
            echo htmlspecialchars($message);
            ?>

        </div>

    <?php endif; ?>


    <?php if ($error !== ""): ?>

        <div class="message error-message">

            <?php
            echo htmlspecialchars($error);
            ?>

        </div>

    <?php endif; ?>


    <form
        method="POST"
        class="assignment-form"
    >

        <input
            type="hidden"
            name="assign_taxi"
            value="1"
        >


        <!-- DRIVER -->

        <div class="form-group">

            <label for="driver_id">
                Driver
            </label>


            <select
                id="driver_id"
                name="driver_id"
                required
            >

                <option value="">
                    Select Driver
                </option>


                <?php if (
                    $drivers_result &&
                    mysqli_num_rows($drivers_result) > 0
                ): ?>

                    <?php while (
                        $driver =
                        mysqli_fetch_assoc(
                            $drivers_result
                        )
                    ): ?>

                        <option
                            value="<?php
                                echo (int)$driver["id"];
                            ?>"
                        >

                            <?php
                            echo htmlspecialchars(
                                $driver["name"]
                            );
                            ?>

                        </option>

                    <?php endwhile; ?>

                <?php else: ?>

                    <option value="" disabled>
                        No verified drivers available
                    </option>

                <?php endif; ?>

            </select>

        </div>


        <!-- TAXI -->

        <div class="form-group">

            <label for="taxi_id">
                Taxi
            </label>


            <select
                id="taxi_id"
                name="taxi_id"
                required
                onchange="showTaxiRent(this)"
            >

                <option value="">
                    Select Taxi
                </option>


                <?php if (
                    $taxis_result &&
                    mysqli_num_rows($taxis_result) > 0
                ): ?>

                    <?php while (
                        $taxi =
                        mysqli_fetch_assoc(
                            $taxis_result
                        )
                    ): ?>

                        <option
                            value="<?php
                                echo (int)$taxi["id"];
                            ?>"
                            data-rent="<?php
                                echo htmlspecialchars(
                                    $taxi["rent"]
                                );
                            ?>"
                        >

                            <?php

                            echo htmlspecialchars(
                                $taxi["brand"]
                                . " "
                                . $taxi["model"]
                                . " - "
                                . $taxi[
                                    "registration_number"
                                ]
                            );

                            ?>

                        </option>

                    <?php endwhile; ?>

                <?php else: ?>

                    <option value="" disabled>
                        No available taxis
                    </option>

                <?php endif; ?>

            </select>


            <div
                id="rent-display"
                class="rent-display"
            >

                <strong>
                    Fixed Taxi Rent:
                </strong>

                <span id="rent-value">
                    ₹0.00
                </span>

                / day

            </div>

        </div>


        <button type="submit">
            Assign Taxi
        </button>

    </form>

</section>


<!-- ================================================= -->
<!-- ACTIVE ASSIGNMENTS -->
<!-- ================================================= -->

<section>

    <h2>
        Active Assignments
    </h2>


    <?php if (
        $assignments_result &&
        mysqli_num_rows($assignments_result) > 0
    ): ?>

        <div class="table-container">

            <table>

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
                            Rent
                        </th>

                        <th>
                            Assigned At
                        </th>

                        <th>
                            Status
                        </th>

                    </tr>

                </thead>


                <tbody>

                    <?php while (
                        $assignment =
                        mysqli_fetch_assoc(
                            $assignments_result
                        )
                    ): ?>

                        <tr>

                            <td>
                                <?php
                                echo (int)
                                    $assignment["id"];
                                ?>
                            </td>


                            <td>

                                <?php
                                echo htmlspecialchars(
                                    $assignment[
                                        "driver_name"
                                    ]
                                );
                                ?>

                            </td>


                            <td>

                                <?php

                                echo htmlspecialchars(
                                    $assignment["brand"]
                                    . " "
                                    . $assignment["model"]
                                );

                                ?>

                            </td>


                            <td>

                                <?php

                                echo htmlspecialchars(
                                    $assignment[
                                        "registration_number"
                                    ]
                                );

                                ?>

                            </td>


                            <td>

                                ₹<?php

                                echo number_format(
                                    (float)
                                    $assignment["rent"],
                                    2
                                );

                                ?>

                                / day

                            </td>


                            <td>

                                <?php

                                echo htmlspecialchars(
                                    $assignment[
                                        "assigned_at"
                                    ]
                                );

                                ?>

                            </td>


                            <td class="status-active">

                                <?php

                                echo htmlspecialchars(
                                    $assignment["status"]
                                );

                                ?>

                            </td>

                        </tr>

                    <?php endwhile; ?>

                </tbody>

            </table>

        </div>

    <?php else: ?>

        <p>
            No active assignments found.
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


<script>

function showTaxiRent(select) {

    const option =
        select.options[
            select.selectedIndex
        ];

    const rent =
        option.getAttribute(
            "data-rent"
        );

    const rentDisplay =
        document.getElementById(
            "rent-display"
        );

    const rentValue =
        document.getElementById(
            "rent-value"
        );


    if (
        rent !== null &&
        rent !== "" &&
        Number(rent) >= 0
    ) {

        rentValue.textContent =
            "₹" +
            Number(rent).toLocaleString(
                "en-IN",
                {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                }
            );

        rentDisplay.style.display =
            "block";

    } else {

        rentDisplay.style.display =
            "none";

    }

}

</script>


</body>

</html>