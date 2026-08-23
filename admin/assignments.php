<?php

session_start();

if (!isset($_SESSION["admin_id"])) {
    header("Location: login.php");
    exit;
}

require_once "../config.php";

$message = "";
$error = "";


// ==============================
// ASSIGN TAXI
// ==============================

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $driver_id = (int) ($_POST["driver_id"] ?? 0);
    $taxi_id = (int) ($_POST["taxi_id"] ?? 0);

    if ($driver_id <= 0 || $taxi_id <= 0) {

        $error = "Please select a driver and taxi.";

    } else {

        // Check driver
        $driver_sql = "SELECT id
                       FROM drivers
                       WHERE id = ?
                       AND status = 'Verified'";

        $driver_stmt = mysqli_prepare($conn, $driver_sql);

        mysqli_stmt_bind_param(
            $driver_stmt,
            "i",
            $driver_id
        );

        mysqli_stmt_execute($driver_stmt);

        $driver_result =
            mysqli_stmt_get_result($driver_stmt);


        // Check taxi
        $taxi_sql = "SELECT id
                     FROM taxis
                     WHERE id = ?
                     AND status = 'Available'";

        $taxi_stmt = mysqli_prepare($conn, $taxi_sql);

        mysqli_stmt_bind_param(
            $taxi_stmt,
            "i",
            $taxi_id
        );

        mysqli_stmt_execute($taxi_stmt);

        $taxi_result =
            mysqli_stmt_get_result($taxi_stmt);


        if (mysqli_num_rows($driver_result) !== 1) {

            $error =
                "Driver must be verified.";

        } elseif (mysqli_num_rows($taxi_result) !== 1) {

            $error =
                "Taxi is not available.";

        } else {

            // Check existing active assignment
            $check_sql = "SELECT id
                          FROM assignments
                          WHERE driver_id = ?
                          AND status = 'Active'";

            $check_stmt =
                mysqli_prepare($conn, $check_sql);

            mysqli_stmt_bind_param(
                $check_stmt,
                "i",
                $driver_id
            );

            mysqli_stmt_execute($check_stmt);

            $check_result =
                mysqli_stmt_get_result($check_stmt);


            if (mysqli_num_rows($check_result) > 0) {

                $error =
                    "This driver already has an active taxi.";

            } else {

                // Insert assignment
                $insert_sql =
                    "INSERT INTO assignments
                    (driver_id, taxi_id)
                    VALUES (?, ?)";

                $insert_stmt =
                    mysqli_prepare($conn, $insert_sql);

                mysqli_stmt_bind_param(
                    $insert_stmt,
                    "ii",
                    $driver_id,
                    $taxi_id
                );


                if (mysqli_stmt_execute($insert_stmt)) {

                    // Update taxi status
                    $update_sql =
                        "UPDATE taxis
                         SET status = 'Assigned'
                         WHERE id = ?";

                    $update_stmt =
                        mysqli_prepare($conn, $update_sql);

                    mysqli_stmt_bind_param(
                        $update_stmt,
                        "i",
                        $taxi_id
                    );

                    mysqli_stmt_execute($update_stmt);

                    mysqli_stmt_close($update_stmt);

                    $message =
                        "Taxi assigned successfully.";

                } else {

                    $error =
                        "Failed to create assignment.";
                }

                mysqli_stmt_close($insert_stmt);
            }

            mysqli_stmt_close($check_stmt);
        }

        mysqli_stmt_close($driver_stmt);
        mysqli_stmt_close($taxi_stmt);
    }
}


// ==============================
// VERIFIED DRIVERS
// ==============================

$drivers_sql =
    "SELECT id, name
     FROM drivers
     WHERE status = 'Verified'
     ORDER BY name";

$drivers_result =
    mysqli_query($conn, $drivers_sql);


// ==============================
// AVAILABLE TAXIS
// ==============================

$taxis_sql =
    "SELECT id,
            brand,
            model,
            registration_number
     FROM taxis
     WHERE status = 'Available'
     ORDER BY id DESC";

$taxis_result =
    mysqli_query($conn, $taxis_sql);


// ==============================
// ACTIVE ASSIGNMENTS
// ==============================

$assignments_sql =
    "SELECT
        assignments.id,
        drivers.name AS driver_name,
        taxis.brand,
        taxis.model,
        taxis.registration_number,
        assignments.assigned_at,
        assignments.status

     FROM assignments

     INNER JOIN drivers
        ON assignments.driver_id = drivers.id

     INNER JOIN taxis
        ON assignments.taxi_id = taxis.id

     WHERE assignments.status = 'Active'

     ORDER BY assignments.id DESC";

$assignments_result =
    mysqli_query($conn, $assignments_sql);

?>

<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Taxi Assignment</title>

    <link
        rel="stylesheet"
        href="../css/style.css"
    >

</head>


<body>


<header>

    <h1>Taxi Assignment</h1>

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

        <a href="logout.php">
            Logout
        </a>

    </nav>

</header>


<main>


<!-- ============================== -->
<!-- ASSIGN TAXI -->
<!-- ============================== -->

<section>

    <h2>Assign Taxi to Driver</h2>


    <?php if ($message !== ""): ?>

        <p>
            <?php echo htmlspecialchars($message); ?>
        </p>

    <?php endif; ?>


    <?php if ($error !== ""): ?>

        <p>
            <?php echo htmlspecialchars($error); ?>
        </p>

    <?php endif; ?>


    <form method="POST">


        <div>

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


                <?php while (
                    $driver =
                    mysqli_fetch_assoc($drivers_result)
                ): ?>

                    <option
                        value="<?php
                            echo $driver["id"];
                        ?>"
                    >

                        <?php
                        echo htmlspecialchars(
                            $driver["name"]
                        );
                        ?>

                    </option>

                <?php endwhile; ?>

            </select>

        </div>


        <br>


        <div>

            <label for="taxi_id">
                Taxi
            </label>

            <select
                id="taxi_id"
                name="taxi_id"
                required
            >

                <option value="">
                    Select Taxi
                </option>


                <?php while (
                    $taxi =
                    mysqli_fetch_assoc($taxis_result)
                ): ?>

                    <option
                        value="<?php
                            echo $taxi["id"];
                        ?>"
                    >

                        <?php

                        echo htmlspecialchars(
                            $taxi["brand"] .
                            " " .
                            $taxi["model"] .
                            " - " .
                            $taxi[
                                "registration_number"
                            ]
                        );

                        ?>

                    </option>

                <?php endwhile; ?>

            </select>

        </div>


        <br>


        <button type="submit">
            Assign Taxi
        </button>


    </form>

</section>


<!-- ============================== -->
<!-- ACTIVE ASSIGNMENTS -->
<!-- ============================== -->

<section>

    <h2>Active Assignments</h2>


    <?php if (
        mysqli_num_rows($assignments_result) > 0
    ): ?>


        <table
            border="1"
            cellpadding="10"
        >

            <thead>

                <tr>

                    <th>ID</th>

                    <th>Driver</th>

                    <th>Taxi</th>

                    <th>Registration</th>

                    <th>Assigned At</th>

                    <th>Status</th>

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
                            echo $assignment["id"];
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
                                $assignment["brand"] .
                                " " .
                                $assignment["model"]
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
                            <?php
                            echo $assignment[
                                "assigned_at"
                            ];
                            ?>
                        </td>


                        <td>
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


    <?php else: ?>

        <p>
            No active assignments found.
        </p>

    <?php endif; ?>


</section>


</main>


<footer>

    <p>
        &copy; 2026 Taxi Management System
    </p>

</footer>


</body>

</html>