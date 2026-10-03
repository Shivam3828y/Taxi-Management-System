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
// ADD TAXI
// =====================================================

if (
    $_SERVER["REQUEST_METHOD"] === "POST"
    && isset($_POST["add_taxi"])
) {

    csrf_verify();

    $brand = trim($_POST["brand"] ?? "");

    $model = trim($_POST["model"] ?? "");

    $registration_number =
        trim($_POST["registration_number"] ?? "");

    $rent = trim($_POST["rent"] ?? "");

    $status =
        $_POST["status"] ?? "Available";


    if (
        $brand === "" ||
        $model === "" ||
        $registration_number === "" ||
        $rent === ""
    ) {

        $error =
            "Please fill all required fields.";

    } elseif (!is_numeric($rent) || $rent < 0) {

        $error =
            "Please enter a valid rent.";

    } else {


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


        $stmt =
            mysqli_prepare(
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
                "sssds",
                $brand,
                $model,
                $registration_number,
                $rent,
                $status
            );


            if (
                mysqli_stmt_execute(
                    $stmt
                )
            ) {

                $message =
                    "Taxi added successfully.";

            } else {

                if (
                    mysqli_errno($conn) == 1062
                ) {

                    $error =
                        "Registration number already exists.";

                } else {

                    $error =
                        "Failed to add taxi.";

                }

            }


            mysqli_stmt_close($stmt);
        }
    }
}


// =====================================================
// UPDATE TAXI RENT
// =====================================================

if (
    $_SERVER["REQUEST_METHOD"] === "POST"
    && isset($_POST["update_rent"])
) {

    csrf_verify();

    $taxi_id =
        (int)($_POST["taxi_id"] ?? 0);

    $new_rent =
        trim($_POST["new_rent"] ?? "");


    if ($taxi_id <= 0) {

        $error =
            "Invalid taxi.";

    } elseif (
        $new_rent === ""
        || !is_numeric($new_rent)
        || $new_rent < 0
    ) {

        $error =
            "Please enter a valid rent.";

    } else {


        $sql = "
            UPDATE taxis
            SET rent = ?
            WHERE id = ?
        ";


        $stmt =
            mysqli_prepare(
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
                "di",
                $new_rent,
                $taxi_id
            );


            if (
                mysqli_stmt_execute(
                    $stmt
                )
            ) {

                $message =
                    "Taxi rent updated successfully.";

            } else {

                $error =
                    "Failed to update taxi rent.";

            }


            mysqli_stmt_close($stmt);
        }
    }
}


// =====================================================
// GET ALL TAXIS
// =====================================================

$sql = "
    SELECT *
    FROM taxis
    ORDER BY id DESC
";


$result =
    mysqli_query(
        $conn,
        $sql
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
        Taxi Management
    </title>


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

        }


        .error-message {

            color: red;

        }

    </style>

</head>


<body>


<!-- ================================================= -->
<!-- HEADER -->
<!-- ================================================= -->

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
                required
            >

        </div>


        <br>


        <div>

            <label for="rent">
                Rent
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


        <div>

            <label for="status">
                Status
            </label>

            <select
                id="status"
                name="status"
            >

                <option value="Available">
                    Available
                </option>

                <option value="Assigned">
                    Assigned
                </option>

                <option value="Maintenance">
                    Maintenance
                </option>

            </select>

        </div>


        <br>


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
                        Current Rent
                    </th>

                    <th>
                        Update Rent
                    </th>

                    <th>
                        Status
                    </th>

                </tr>

            </thead>


            <tbody>


                <?php while (
                    $taxi =
                    mysqli_fetch_assoc($result)
                ): ?>


                    <tr>


                        <td>

                            <?php
                            echo $taxi["id"];
                            ?>

                        </td>


                        <td>

                            <?php

                            echo htmlspecialchars(
                                $taxi["brand"]
                            );

                            ?>

                        </td>


                        <td>

                            <?php

                            echo htmlspecialchars(
                                $taxi["model"]
                            );

                            ?>

                        </td>


                        <td>

                            <?php

                            echo htmlspecialchars(
                                $taxi[
                                    "registration_number"
                                ]
                            );

                            ?>

                        </td>


                        <!-- CURRENT RENT -->

                        <td>

                            ₹<?php

                            echo number_format(
                                (float)$taxi["rent"],
                                2
                            );

                            ?>

                            / day

                        </td>


                        <!-- UPDATE RENT -->

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
                                        echo $taxi["id"];
                                    ?>"
                                >


                                <input
                                    type="number"
                                    name="new_rent"
                                    value="<?php
                                        echo $taxi["rent"];
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


                        <!-- STATUS -->

                        <td>

                            <?php

                            echo htmlspecialchars(
                                $taxi["status"]
                            );

                            ?>

                        </td>


                    </tr>


                <?php endwhile; ?>


            </tbody>

        </table>


    <?php else: ?>


        <p>
            No taxis found.
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