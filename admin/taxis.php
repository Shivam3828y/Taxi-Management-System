<?php

require_once "../config.php";

session_start();

if (!isset($_SESSION["admin_id"])) {
    header("Location: login.php");
    exit;
}

$message = "";
$error = "";


/*
=====================================================
ADD TAXI
=====================================================
*/

if (
    $_SERVER["REQUEST_METHOD"] === "POST"
    && isset($_POST["add_taxi"])
) {

    csrf_verify();

    $brand = trim($_POST["brand"] ?? "");
    $model = trim($_POST["model"] ?? "");
    $registration_number = trim($_POST["registration_number"] ?? "");
    $rent = trim($_POST["rent"] ?? "");

    if (
        $brand === "" ||
        $model === "" ||
        $registration_number === "" ||
        $rent === ""
    ) {

        $error = "Please fill all required fields.";

    } elseif (!is_numeric($rent) || $rent < 0) {

        $error = "Please enter a valid rent.";

    } else {

        /*
         * Every newly added taxi starts as Available.
         */

        $status = "Available";

        $sql = "
            INSERT INTO taxis
            (
                brand,
                model,
                registration_number,
                rent,
                status
            )
            VALUES (?, ?, ?, ?, ?)
        ";

        $stmt = mysqli_prepare($conn, $sql);

        if (!$stmt) {

            $error = "Database error: " . mysqli_error($conn);

        } else {

            mysqli_stmt_bind_param(
                $stmt,
                "sssds",
                $brand,
                $model,
                $registration_number,
                $rent,
                $status
            );

            if (mysqli_stmt_execute($stmt)) {

                $message = "Taxi added successfully.";

            } else {

                if (mysqli_errno($conn) == 1062) {

                    $error = "Registration number already exists.";

                } else {

                    $error = "Failed to add taxi.";
                }
            }

            mysqli_stmt_close($stmt);
        }
    }
}


/*
=====================================================
UPDATE TAXI RENT
=====================================================
*/

if (
    $_SERVER["REQUEST_METHOD"] === "POST"
    && isset($_POST["update_rent"])
) {

    csrf_verify();

    $taxi_id = (int)($_POST["taxi_id"] ?? 0);
    $new_rent = trim($_POST["new_rent"] ?? "");

    if ($taxi_id <= 0) {

        $error = "Invalid taxi.";

    } elseif (
        $new_rent === ""
        || !is_numeric($new_rent)
        || $new_rent < 0
    ) {

        $error = "Please enter a valid rent.";

    } else {

        $sql = "
            UPDATE taxis
            SET rent = ?
            WHERE id = ?
        ";

        $stmt = mysqli_prepare($conn, $sql);

        if (!$stmt) {

            $error = "Database error: " . mysqli_error($conn);

        } else {

            mysqli_stmt_bind_param(
                $stmt,
                "di",
                $new_rent,
                $taxi_id
            );

            if (mysqli_stmt_execute($stmt)) {

                if (mysqli_stmt_affected_rows($stmt) > 0) {

                    $message = "Taxi rent updated successfully.";

                } else {

                    $message =
                        "Taxi rent is already set to this amount.";
                }

            } else {

                $error = "Failed to update taxi rent.";
            }

            mysqli_stmt_close($stmt);
        }
    }
}


/*
=====================================================
DELETE TAXI
=====================================================
*/

if (
    $_SERVER["REQUEST_METHOD"] === "POST"
    && isset($_POST["delete_taxi"])
) {

    csrf_verify();

    $taxi_id = (int)($_POST["taxi_id"] ?? 0);

    if ($taxi_id <= 0) {

        $error = "Invalid taxi.";

    } else {

        /*
         * First check the current taxi status.
         */

        $check_sql = "
            SELECT
                id,
                brand,
                model,
                registration_number,
                status
            FROM taxis
            WHERE id = ?
            LIMIT 1
        ";

        $check_stmt = mysqli_prepare(
            $conn,
            $check_sql
        );

        if (!$check_stmt) {

            $error =
                "Database error: " .
                mysqli_error($conn);

        } else {

            mysqli_stmt_bind_param(
                $check_stmt,
                "i",
                $taxi_id
            );

            mysqli_stmt_execute($check_stmt);

            $check_result =
                mysqli_stmt_get_result($check_stmt);

            $taxi =
                mysqli_fetch_assoc($check_result);

            mysqli_stmt_close($check_stmt);


            if (!$taxi) {

                $error = "Taxi not found.";

            } elseif (
                $taxi["status"] === "Assigned"
            ) {

                /*
                 * Never delete an actively assigned taxi.
                 */

                $error =
                    "This taxi cannot be deleted because it is currently assigned to a driver.";

            } else {

                /*
                 * Delete only when the taxi is not Assigned.
                 */

                $delete_sql = "
                    DELETE FROM taxis
                    WHERE id = ?
                    AND status <> 'Assigned'
                ";

                $delete_stmt = mysqli_prepare(
                    $conn,
                    $delete_sql
                );

                if (!$delete_stmt) {

                    $error =
                        "Database error: " .
                        mysqli_error($conn);

                } else {

                    mysqli_stmt_bind_param(
                        $delete_stmt,
                        "i",
                        $taxi_id
                    );

                    if (
                        mysqli_stmt_execute(
                            $delete_stmt
                        )
                    ) {

                        if (
                            mysqli_stmt_affected_rows(
                                $delete_stmt
                            ) > 0
                        ) {

                            $message =
                                "Taxi deleted successfully.";

                        } else {

                            $error =
                                "Taxi could not be deleted.";
                        }

                    } else {

                        /*
                         * This can happen if another table
                         * is referencing this taxi.
                         */

                        $error =
                            "Taxi could not be deleted. It may have related records.";

                    }

                    mysqli_stmt_close(
                        $delete_stmt
                    );
                }
            }
        }
    }
}


/*
=====================================================
GET ALL TAXIS
=====================================================
*/

$sql = "
    SELECT
        id,
        brand,
        model,
        registration_number,
        rent,
        status,
        created_at
    FROM taxis
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

    <title>Taxi Management</title>

    <link
        rel="stylesheet"
        href="../css/style.css"
    >

    <style>

        .rent-update-form {
            display: flex;
            gap: 5px;
            align-items: center;
        }

        .rent-update-form input {
            width: 100px;
        }

        .success-message {
            color: green;
            font-weight: bold;
        }

        .error-message {
            color: red;
            font-weight: bold;
        }

        .status-available {
            color: green;
            font-weight: bold;
        }

        .status-assigned {
            color: #b36b00;
            font-weight: bold;
        }

        .status-inactive {
            color: #777;
            font-weight: bold;
        }

        .delete-button {
            background: #b91c1c;
            color: white;
            border: none;
            padding: 7px 10px;
            cursor: pointer;
            border-radius: 4px;
        }

        .delete-button:hover {
            background: #991b1b;
        }

        .delete-disabled {
            color: #777;
            font-size: 13px;
        }

    </style>

</head>


<body>


<header>

    <h1>
        Taxi Management
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


<main>


<!-- ================================================= -->
<!-- ADD TAXI -->
<!-- ================================================= -->

<section>

    <h2>
        Add Taxi
    </h2>


    <?php if ($message !== ""): ?>

        <p class="success-message">

            <?php

            echo htmlspecialchars(
                $message,
                ENT_QUOTES,
                "UTF-8"
            );

            ?>

        </p>

    <?php endif; ?>


    <?php if ($error !== ""): ?>

        <p class="error-message">

            <?php

            echo htmlspecialchars(
                $error,
                ENT_QUOTES,
                "UTF-8"
            );

            ?>

        </p>

    <?php endif; ?>


    <form method="POST">

        <?php csrf_field(); ?>

        <input
            type="hidden"
            name="add_taxi"
            value="1"
        >


        <div>

            <label for="brand">
                Brand
            </label>

            <input
                type="text"
                id="brand"
                name="brand"
                maxlength="50"
                required
            >

        </div>


        <br>


        <div>

            <label for="model">
                Model
            </label>

            <input
                type="text"
                id="model"
                name="model"
                maxlength="50"
                required
            >

        </div>


        <br>


        <div>

            <label for="registration_number">
                Registration Number
            </label>

            <input
                type="text"
                id="registration_number"
                name="registration_number"
                maxlength="20"
                required
            >

        </div>


        <br>


        <div>

            <label for="rent">
                Rent / Day
            </label>

            <input
                type="number"
                id="rent"
                name="rent"
                step="0.01"
                min="0"
                required
            >

        </div>


        <br>


        <p>
            New taxis are automatically set to
            <strong>Available</strong>.
        </p>


        <button type="submit">
            Add Taxi
        </button>

    </form>

</section>


<!-- ================================================= -->
<!-- TAXI LIST -->
<!-- ================================================= -->

<section>

    <h2>
        Taxi List
    </h2>


    <?php if (
        $result &&
        mysqli_num_rows($result) > 0
    ): ?>


        <table
            border="1"
            cellpadding="10"
        >

            <thead>

                <tr>

                    <th>
                        ID
                    </th>

                    <th>
                        Brand
                    </th>

                    <th>
                        Model
                    </th>

                    <th>
                        Registration
                    </th>

                    <th>
                        Rent / Day
                    </th>

                    <th>
                        Update Rent
                    </th>

                    <th>
                        Status
                    </th>

                    <th>
                        Created
                    </th>

                    <th>
                        Action
                    </th>

                </tr>

            </thead>


            <tbody>

                <?php while (
                    $taxi = mysqli_fetch_assoc($result)
                ): ?>

                    <tr>


                        <td>

                            <?php

                            echo (int)$taxi["id"];

                            ?>

                        </td>


                        <td>

                            <?php

                            echo htmlspecialchars(
                                $taxi["brand"],
                                ENT_QUOTES,
                                "UTF-8"
                            );

                            ?>

                        </td>


                        <td>

                            <?php

                            echo htmlspecialchars(
                                $taxi["model"],
                                ENT_QUOTES,
                                "UTF-8"
                            );

                            ?>

                        </td>


                        <td>

                            <?php

                            echo htmlspecialchars(
                                $taxi["registration_number"],
                                ENT_QUOTES,
                                "UTF-8"
                            );

                            ?>

                        </td>


                        <td>

                            ₹<?php

                            echo number_format(
                                (float)$taxi["rent"],
                                2
                            );

                            ?>

                            / day

                        </td>


                        <td>

                            <form
                                method="POST"
                                class="rent-update-form"
                            >

                                <?php csrf_field(); ?>

                                <input
                                    type="hidden"
                                    name="update_rent"
                                    value="1"
                                >

                                <input
                                    type="hidden"
                                    name="taxi_id"
                                    value="<?php
                                        echo (int)$taxi["id"];
                                    ?>"
                                >

                                <input
                                    type="number"
                                    name="new_rent"
                                    value="<?php
                                        echo htmlspecialchars(
                                            $taxi["rent"],
                                            ENT_QUOTES,
                                            "UTF-8"
                                        );
                                    ?>"
                                    step="0.01"
                                    min="0"
                                    required
                                >

                                <button type="submit">
                                    Update
                                </button>

                            </form>

                        </td>


                        <td>

                            <?php

                            $status =
                                $taxi["status"];

                            $status_class = "";

                            if (
                                $status === "Available"
                            ) {

                                $status_class =
                                    "status-available";

                            } elseif (
                                $status === "Assigned"
                            ) {

                                $status_class =
                                    "status-assigned";

                            } elseif (
                                $status === "Inactive"
                            ) {

                                $status_class =
                                    "status-inactive";
                            }

                            ?>


                            <span
                                class="<?php
                                    echo $status_class;
                                ?>"
                            >

                                <?php

                                echo htmlspecialchars(
                                    $status,
                                    ENT_QUOTES,
                                    "UTF-8"
                                );

                                ?>

                            </span>

                        </td>


                        <td>

                            <?php

                            echo htmlspecialchars(
                                $taxi["created_at"],
                                ENT_QUOTES,
                                "UTF-8"
                            );

                            ?>

                        </td>


                        <!-- DELETE -->

                        <td>

                            <?php if (
                                $taxi["status"] !== "Assigned"
                            ): ?>

                                <form
                                    method="POST"
                                >

                                    <?php csrf_field(); ?>

                                    <input
                                        type="hidden"
                                        name="delete_taxi"
                                        value="1"
                                    >

                                    <input
                                        type="hidden"
                                        name="taxi_id"
                                        value="<?php
                                            echo (int)$taxi["id"];
                                        ?>"
                                    >

                                    <button
                                        type="submit"
                                        class="delete-button"
                                        onclick="
                                            return confirm(
                                                'Are you sure you want to permanently delete this taxi?'
                                            );
                                        "
                                    >
                                        Delete
                                    </button>

                                </form>

                            <?php else: ?>

                                <span
                                    class="delete-disabled"
                                >
                                    Assigned
                                </span>

                            <?php endif; ?>

                        </td>


                    </tr>

                <?php endwhile; ?>

            </tbody>

        </table>


    <?php else: ?>

        <p>
            No taxis added yet.
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