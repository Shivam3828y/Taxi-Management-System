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
// VERIFY / DEACTIVATE DRIVER
// =====================================================

if (
    $_SERVER["REQUEST_METHOD"] === "POST"
    && isset($_POST["driver_action"])
) {

    $action = $_POST["driver_action"];
    $driver_id = (int) ($_POST["driver_id"] ?? 0);


    if ($driver_id <= 0) {

        $error = "Invalid driver ID.";

    } elseif ($action === "verify") {


        // =================================================
        // VERIFY DRIVER
        // =================================================

        $sql = "
            UPDATE drivers
            SET status = 'Verified'
            WHERE id = ?
            AND status = 'Pending'
        ";

        $stmt = mysqli_prepare($conn, $sql);


        if (!$stmt) {

            $error =
                "Database error: "
                . mysqli_error($conn);

        } else {

            mysqli_stmt_bind_param(
                $stmt,
                "i",
                $driver_id
            );

            mysqli_stmt_execute($stmt);


            if (
                mysqli_stmt_affected_rows($stmt) > 0
            ) {

                $message =
                    "Driver verified successfully.";

            } else {

                $error =
                    "Driver could not be verified. "
                    . "The driver may already be verified or may not exist.";

            }


            mysqli_stmt_close($stmt);

        }

    } elseif ($action === "deactivate") {


        // =================================================
        // CHECK ACTIVE ASSIGNMENT
        // =================================================

        $check_sql = "
            SELECT id
            FROM assignments
            WHERE driver_id = ?
            AND status = 'Active'
            LIMIT 1
        ";

        $check_stmt = mysqli_prepare(
            $conn,
            $check_sql
        );


        if (!$check_stmt) {

            $error =
                "Database error: "
                . mysqli_error($conn);

        } else {

            mysqli_stmt_bind_param(
                $check_stmt,
                "i",
                $driver_id
            );

            mysqli_stmt_execute(
                $check_stmt
            );

            $check_result =
                mysqli_stmt_get_result(
                    $check_stmt
                );


            if (
                mysqli_num_rows($check_result) > 0
            ) {

                /*
                 * Do not deactivate a driver who still
                 * has an active taxi assignment.
                 *
                 * Otherwise:
                 *
                 * Driver = Inactive
                 * Assignment = Active
                 *
                 * This would create inconsistent data.
                 */

                $error =
                    "Driver cannot be deactivated while "
                    . "an active taxi assignment exists.";

            } else {


                // =========================================
                // DEACTIVATE DRIVER
                // =========================================

                $sql = "
                    UPDATE drivers
                    SET status = 'Inactive'
                    WHERE id = ?
                    AND status = 'Verified'
                ";

                $stmt = mysqli_prepare(
                    $conn,
                    $sql
                );


                if (!$stmt) {

                    $error =
                        "Database error: "
                        . mysqli_error($conn);

                } else {

                    mysqli_stmt_bind_param(
                        $stmt,
                        "i",
                        $driver_id
                    );

                    mysqli_stmt_execute(
                        $stmt
                    );


                    if (
                        mysqli_stmt_affected_rows($stmt) > 0
                    ) {

                        $message =
                            "Driver deactivated successfully.";

                    } else {

                        $error =
                            "Driver could not be deactivated.";

                    }


                    mysqli_stmt_close($stmt);

                }

            }


            mysqli_stmt_close(
                $check_stmt
            );

        }

    } else {

        $error = "Invalid driver action.";

    }

}


// =====================================================
// ADD DRIVER
// =====================================================

if (
    $_SERVER["REQUEST_METHOD"] === "POST"
    && isset($_POST["add_driver"])
) {

    $name = trim(
        $_POST["name"] ?? ""
    );

    $phone = trim(
        $_POST["phone"] ?? ""
    );

    $email = trim(
        $_POST["email"] ?? ""
    );

    $address = trim(
        $_POST["address"] ?? ""
    );

    $driving_license = trim(
        $_POST["driving_license"] ?? ""
    );


    // =================================================
    // VALIDATION
    // =================================================

    if (
        $name === ""
        || $phone === ""
        || $driving_license === ""
    ) {

        $error =
            "Please fill all required fields.";

    } else {


        // =================================================
        // CHECK DUPLICATE DRIVER
        // =================================================

        $check_sql = "
            SELECT id
            FROM drivers
            WHERE phone = ?
            OR driving_license = ?
            LIMIT 1
        ";

        $check_stmt = mysqli_prepare(
            $conn,
            $check_sql
        );


        if (!$check_stmt) {

            $error =
                "Database error: "
                . mysqli_error($conn);

        } else {

            mysqli_stmt_bind_param(
                $check_stmt,
                "ss",
                $phone,
                $driving_license
            );

            mysqli_stmt_execute(
                $check_stmt
            );

            $check_result =
                mysqli_stmt_get_result(
                    $check_stmt
                );


            if (
                mysqli_num_rows($check_result) > 0
            ) {

                $error =
                    "Phone number or driving license already exists.";

            } else {


                // =========================================
                // INSERT DRIVER
                // =========================================

                $sql = "
                    INSERT INTO drivers
                    (
                        name,
                        phone,
                        email,
                        address,
                        driving_license,
                        status
                    )
                    VALUES
                    (
                        ?,
                        ?,
                        ?,
                        ?,
                        ?,
                        'Pending'
                    )
                ";

                $stmt = mysqli_prepare(
                    $conn,
                    $sql
                );


                if (!$stmt) {

                    $error =
                        "Database error: "
                        . mysqli_error($conn);

                } else {

                    mysqli_stmt_bind_param(
                        $stmt,
                        "sssss",
                        $name,
                        $phone,
                        $email,
                        $address,
                        $driving_license
                    );


                    if (
                        mysqli_stmt_execute($stmt)
                    ) {

                        $message =
                            "Driver added successfully "
                            . "and marked as Pending verification.";

                    } else {

                        if (
                            mysqli_errno($conn) === 1062
                        ) {

                            $error =
                                "Phone number or driving license already exists.";

                        } else {

                            $error =
                                "Failed to add driver: "
                                . mysqli_error($conn);

                        }

                    }


                    mysqli_stmt_close($stmt);

                }

            }


            mysqli_stmt_close(
                $check_stmt
            );

        }

    }

}


// =====================================================
// GET ALL DRIVERS
// =====================================================

$sql = "
    SELECT
        id,
        name,
        phone,
        email,
        address,
        driving_license,
        status,
        created_at
    FROM drivers
    ORDER BY id DESC
";

$result = mysqli_query(
    $conn,
    $sql
);


if (!$result) {

    $error =
        "Failed to load drivers: "
        . mysqli_error($conn);

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
        Driver Management - Taxi Management System
    </title>

    <link
        rel="stylesheet"
        href="../css/style.css"
    >

    <style>

        .page-container {
            max-width: 1200px;
            margin: 30px auto;
        }


        .form-card,
        .list-card {

            background: #fff;

            border: 1px solid #ddd;

            border-radius: 10px;

            padding: 20px;

            margin-bottom: 30px;

        }


        .form-group {

            margin-bottom: 15px;

        }


        .form-group label {

            display: block;

            margin-bottom: 6px;

            font-weight: bold;

        }


        .form-group input,
        .form-group textarea {

            width: 100%;

            box-sizing: border-box;

            padding: 10px;

        }


        .form-group textarea {

            min-height: 90px;

            resize: vertical;

        }


        .required {

            color: #b91c1c;

        }


        .success-message {

            padding: 12px;

            margin-bottom: 20px;

            background: #f0fdf4;

            border: 1px solid #86efac;

            color: #166534;

            border-radius: 6px;

        }


        .error-message {

            padding: 12px;

            margin-bottom: 20px;

            background: #fef2f2;

            border: 1px solid #fca5a5;

            color: #991b1b;

            border-radius: 6px;

        }


        .table-container {

            overflow-x: auto;

        }


        table {

            width: 100%;

            border-collapse: collapse;

            min-width: 850px;

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


        .status {

            font-weight: bold;

        }


        .status-pending {

            color: #b45309;

        }


        .status-verified {

            color: #15803d;

        }


        .status-inactive {

            color: #b91c1c;

        }


        .action-form {

            display: inline;

        }


        .action-button {

            padding: 7px 10px;

            border: 1px solid #333;

            border-radius: 5px;

            background: #fff;

            cursor: pointer;

        }


        .verify-button {

            color: #15803d;

            border-color: #15803d;

        }


        .deactivate-button {

            color: #b91c1c;

            border-color: #b91c1c;

        }


        .empty-message {

            color: #666;

        }


        @media (max-width: 700px) {

            .page-container {

                margin: 20px 10px;

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
        Driver Management
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

<main class="page-container">


    <!-- ================================================= -->
    <!-- MESSAGES -->
    <!-- ================================================= -->

    <?php if ($message !== ""): ?>

        <div class="success-message">

            <?php
            echo htmlspecialchars($message);
            ?>

        </div>

    <?php endif; ?>


    <?php if ($error !== ""): ?>

        <div class="error-message">

            <?php
            echo htmlspecialchars($error);
            ?>

        </div>

    <?php endif; ?>


    <!-- ================================================= -->
    <!-- ADD DRIVER -->
    <!-- ================================================= -->

    <section class="form-card">

        <h2>
            Add Driver
        </h2>


        <p>
            Drivers added here will remain
            <strong>Pending</strong> until verified.
        </p>


        <form method="POST">


            <input
                type="hidden"
                name="add_driver"
                value="1"
            >


            <!-- NAME -->

            <div class="form-group">

                <label for="name">

                    Driver Name

                    <span class="required">*</span>

                </label>

                <input
                    type="text"
                    id="name"
                    name="name"
                    maxlength="100"
                    required
                >

            </div>


            <!-- PHONE -->

            <div class="form-group">

                <label for="phone">

                    Phone Number

                    <span class="required">*</span>

                </label>

                <input
                    type="tel"
                    id="phone"
                    name="phone"
                    maxlength="20"
                    required
                >

            </div>


            <!-- EMAIL -->

            <div class="form-group">

                <label for="email">
                    Email
                </label>

                <input
                    type="email"
                    id="email"
                    name="email"
                    maxlength="100"
                >

            </div>


            <!-- ADDRESS -->

            <div class="form-group">

                <label for="address">
                    Address
                </label>

                <textarea
                    id="address"
                    name="address"
                ></textarea>

            </div>


            <!-- LICENSE -->

            <div class="form-group">

                <label for="driving_license">

                    Driving License Number

                    <span class="required">*</span>

                </label>

                <input
                    type="text"
                    id="driving_license"
                    name="driving_license"
                    maxlength="50"
                    required
                >

            </div>


            <button type="submit">
                Add Driver
            </button>


        </form>

    </section>


    <!-- ================================================= -->
    <!-- DRIVER LIST -->
    <!-- ================================================= -->

    <section class="list-card">

        <h2>
            Registered Drivers
        </h2>


        <?php if (
            $result &&
            mysqli_num_rows($result) > 0
        ): ?>


            <div class="table-container">

                <table>

                    <thead>

                        <tr>

                            <th>
                                ID
                            </th>

                            <th>
                                Name
                            </th>

                            <th>
                                Phone
                            </th>

                            <th>
                                Email
                            </th>

                            <th>
                                License
                            </th>

                            <th>
                                Status
                            </th>

                            <th>
                                Registered
                            </th>

                            <th>
                                Action
                            </th>

                        </tr>

                    </thead>


                    <tbody>


                    <?php while (
                        $driver =
                        mysqli_fetch_assoc($result)
                    ): ?>


                        <tr>


                            <!-- ID -->

                            <td>

                                <?php
                                echo (int)
                                    $driver["id"];
                                ?>

                            </td>


                            <!-- NAME -->

                            <td>

                                <?php
                                echo htmlspecialchars(
                                    $driver["name"]
                                );
                                ?>

                            </td>


                            <!-- PHONE -->

                            <td>

                                <?php
                                echo htmlspecialchars(
                                    $driver["phone"]
                                );
                                ?>

                            </td>


                            <!-- EMAIL -->

                            <td>

                                <?php

                                echo htmlspecialchars(
                                    $driver["email"] ?? ""
                                );

                                ?>

                            </td>


                            <!-- LICENSE -->

                            <td>

                                <?php
                                echo htmlspecialchars(
                                    $driver[
                                        "driving_license"
                                    ]
                                );
                                ?>

                            </td>


                            <!-- STATUS -->

                            <td>

                                <?php

                                $status_class =
                                    "status-pending";


                                if (
                                    $driver["status"]
                                    === "Verified"
                                ) {

                                    $status_class =
                                        "status-verified";

                                } elseif (
                                    $driver["status"]
                                    === "Inactive"
                                ) {

                                    $status_class =
                                        "status-inactive";

                                }

                                ?>

                                <span
                                    class="status
                                    <?php
                                    echo $status_class;
                                    ?>"
                                >

                                    <?php
                                    echo htmlspecialchars(
                                        $driver["status"]
                                    );
                                    ?>

                                </span>

                            </td>


                            <!-- CREATED -->

                            <td>

                                <?php

                                echo htmlspecialchars(
                                    $driver["created_at"]
                                );

                                ?>

                            </td>


                            <!-- ACTION -->

                            <td>


                                <?php if (
                                    $driver["status"]
                                    === "Pending"
                                ): ?>


                                    <form
                                        method="POST"
                                        class="action-form"
                                    >

                                        <input
                                            type="hidden"
                                            name="driver_id"
                                            value="<?php
                                                echo (int)
                                                    $driver["id"];
                                            ?>"
                                        >

                                        <input
                                            type="hidden"
                                            name="driver_action"
                                            value="verify"
                                        >

                                        <button
                                            type="submit"
                                            class="action-button verify-button"
                                        >
                                            Verify
                                        </button>

                                    </form>


                                <?php elseif (
                                    $driver["status"]
                                    === "Verified"
                                ): ?>


                                    <form
                                        method="POST"
                                        class="action-form"
                                        onsubmit="return confirm(
                                            'Are you sure you want to deactivate this driver?'
                                        );"
                                    >

                                        <input
                                            type="hidden"
                                            name="driver_id"
                                            value="<?php
                                                echo (int)
                                                    $driver["id"];
                                            ?>"
                                        >

                                        <input
                                            type="hidden"
                                            name="driver_action"
                                            value="deactivate"
                                        >

                                        <button
                                            type="submit"
                                            class="action-button deactivate-button"
                                        >
                                            Deactivate
                                        </button>

                                    </form>


                                <?php elseif (
                                    $driver["status"]
                                    === "Inactive"
                                ): ?>


                                    <span>
                                        Inactive
                                    </span>


                                <?php else: ?>


                                    <span>
                                        No Action
                                    </span>


                                <?php endif; ?>


                            </td>


                        </tr>


                    <?php endwhile; ?>


                    </tbody>

                </table>

            </div>


        <?php else: ?>


            <p class="empty-message">
                No drivers found.
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