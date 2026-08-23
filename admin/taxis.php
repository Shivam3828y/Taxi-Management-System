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
// ADD TAXI
// =====================================================

if (
    $_SERVER["REQUEST_METHOD"] === "POST"
    && isset($_POST["add_taxi"])
) {

    $brand = trim(
        $_POST["brand"] ?? ""
    );

    $model = trim(
        $_POST["model"] ?? ""
    );

    $registration_number = trim(
        $_POST["registration_number"] ?? ""
    );

    $rent = trim(
        $_POST["rent"] ?? ""
    );


    // =================================================
    // VALIDATION
    // =================================================

    if (
        $brand === ""
        || $model === ""
        || $registration_number === ""
        || $rent === ""
    ) {

        $error =
            "Please fill all required fields.";

    } elseif (
        !is_numeric($rent)
        || (float)$rent < 0
    ) {

        $error =
            "Please enter a valid rent.";

    } else {


        /*
         * A newly added taxi must start as Available.
         *
         * Assigned status should be controlled by the
         * assignment system, not manually during creation.
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
                "sssds",
                $brand,
                $model,
                $registration_number,
                $rent,
                $status
            );


            if (
                mysqli_stmt_execute($stmt)
            ) {

                $message =
                    "Taxi added successfully "
                    . "and marked as Available.";

            } else {

                if (
                    mysqli_errno($conn) === 1062
                ) {

                    $error =
                        "Registration number already exists.";

                } else {

                    $error =
                        "Failed to add taxi: "
                        . mysqli_error($conn);

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

    $taxi_id = (int)(
        $_POST["taxi_id"] ?? 0
    );

    $new_rent = trim(
        $_POST["new_rent"] ?? ""
    );


    if ($taxi_id <= 0) {

        $error =
            "Invalid taxi.";

    } elseif (
        $new_rent === ""
        || !is_numeric($new_rent)
        || (float)$new_rent < 0
    ) {

        $error =
            "Please enter a valid rent.";

    } else {


        // =============================================
        // CHECK TAXI EXISTS
        // =============================================

        $check_sql = "
            SELECT id
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
                "Database error: "
                . mysqli_error($conn);

        } else {

            mysqli_stmt_bind_param(
                $check_stmt,
                "i",
                $taxi_id
            );

            mysqli_stmt_execute(
                $check_stmt
            );

            $check_result =
                mysqli_stmt_get_result(
                    $check_stmt
                );


            if (
                mysqli_num_rows($check_result) === 0
            ) {

                $error =
                    "Taxi not found.";

            } else {


                // =====================================
                // UPDATE RENT
                // =====================================

                $sql = "
                    UPDATE taxis
                    SET rent = ?
                    WHERE id = ?
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
                        "di",
                        $new_rent,
                        $taxi_id
                    );


                    if (
                        mysqli_stmt_execute($stmt)
                    ) {

                        $message =
                            "Taxi rent updated successfully.";

                    } else {

                        $error =
                            "Failed to update taxi rent: "
                            . mysqli_error($conn);

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
// UPDATE TAXI STATUS
// =====================================================

if (
    $_SERVER["REQUEST_METHOD"] === "POST"
    && isset($_POST["update_status"])
) {

    $taxi_id = (int)(
        $_POST["taxi_id"] ?? 0
    );

    $new_status = trim(
        $_POST["new_status"] ?? ""
    );


    /*
     * Assigned is intentionally NOT allowed here.
     *
     * Assignment status should be controlled by
     * assignments.php.
     */

    $allowed_statuses = [
        "Available",
        "Maintenance",
        "Inactive"
    ];


    if ($taxi_id <= 0) {

        $error =
            "Invalid taxi.";

    } elseif (
        !in_array(
            $new_status,
            $allowed_statuses,
            true
        )
    ) {

        $error =
            "Invalid taxi status.";

    } else {


        // =============================================
        // CHECK ACTIVE ASSIGNMENT
        // =============================================

        $check_sql = "
            SELECT id
            FROM assignments
            WHERE taxi_id = ?
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
                $taxi_id
            );

            mysqli_stmt_execute(
                $check_stmt
            );

            $check_result =
                mysqli_stmt_get_result(
                    $check_stmt
                );


            $has_active_assignment =
                mysqli_num_rows(
                    $check_result
                ) > 0;


            /*
             * A taxi with an active assignment cannot
             * be manually changed to Maintenance or
             * Inactive.
             *
             * The assignment must be ended first.
             */

            if (
                $has_active_assignment
                && $new_status !== "Available"
            ) {

                $error =
                    "This taxi has an active assignment. "
                    . "End the assignment before changing "
                    . "the taxi to Maintenance or Inactive.";

            } elseif (
                $has_active_assignment
                && $new_status === "Available"
            ) {

                /*
                 * Do not allow Available while an active
                 * assignment still exists.
                 */

                $error =
                    "This taxi has an active assignment "
                    . "and cannot be marked Available.";

            } else {


                // =====================================
                // UPDATE STATUS
                // =====================================

                $sql = "
                    UPDATE taxis
                    SET status = ?
                    WHERE id = ?
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
                        "si",
                        $new_status,
                        $taxi_id
                    );


                    if (
                        mysqli_stmt_execute($stmt)
                    ) {

                        $message =
                            "Taxi status updated to "
                            . $new_status
                            . ".";

                    } else {

                        $error =
                            "Failed to update taxi status: "
                            . mysqli_error($conn);

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
// GET ALL TAXIS
// =====================================================

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


$result = mysqli_query(
    $conn,
    $sql
);


if (!$result) {

    $error =
        "Failed to load taxis: "
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
        Taxi Management - Taxi Management System
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
        .form-group select {

            width: 100%;

            box-sizing: border-box;

            padding: 10px;

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

            min-width: 1000px;

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


        .status-available {

            color: #15803d;

        }


        .status-assigned {

            color: #1d4ed8;

        }


        .status-maintenance {

            color: #b45309;

        }


        .status-inactive {

            color: #b91c1c;

        }


        .rent-update-form,
        .status-update-form {

            display: flex;

            gap: 6px;

            align-items: center;

        }


        .rent-update-form input {

            width: 100px;

            padding: 7px;

        }


        .status-update-form select {

            padding: 7px;

        }


        .action-button {

            padding: 7px 10px;

            border: 1px solid #333;

            border-radius: 5px;

            background: #fff;

            cursor: pointer;

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
    <!-- ADD TAXI -->
    <!-- ================================================= -->

    <section class="form-card">

        <h2>
            Add Taxi
        </h2>


        <p>
            New taxis are automatically added as
            <strong>Available</strong>.
        </p>


        <form method="POST">


            <input
                type="hidden"
                name="add_taxi"
                value="1"
            >


            <!-- BRAND -->

            <div class="form-group">

                <label for="brand">

                    Brand

                    <span class="required">*</span>

                </label>

                <input
                    type="text"
                    id="brand"
                    name="brand"
                    maxlength="50"
                    required
                >

            </div>


            <!-- MODEL -->

            <div class="form-group">

                <label for="model">

                    Model

                    <span class="required">*</span>

                </label>

                <input
                    type="text"
                    id="model"
                    name="model"
                    maxlength="50"
                    required
                >

            </div>


            <!-- REGISTRATION -->

            <div class="form-group">

                <label for="registration_number">

                    Registration Number

                    <span class="required">*</span>

                </label>

                <input
                    type="text"
                    id="registration_number"
                    name="registration_number"
                    maxlength="20"
                    required
                >

            </div>


            <!-- RENT -->

            <div class="form-group">

                <label for="rent">

                    Daily Rent (₹)

                    <span class="required">*</span>

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


            <button type="submit">
                Add Taxi
            </button>


        </form>

    </section>


    <!-- ================================================= -->
    <!-- TAXI LIST -->
    <!-- ================================================= -->

    <section class="list-card">

        <h2>
            Taxi List
        </h2>


        <?php if (
            $result
            && mysqli_num_rows($result) > 0
        ): ?>


            <div class="table-container">

                <table>

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

                            <th>
                                Update Status
                            </th>

                            <th>
                                Created
                            </th>

                        </tr>

                    </thead>


                    <tbody>


                    <?php while (
                        $taxi =
                        mysqli_fetch_assoc($result)
                    ): ?>


                        <tr>


                            <!-- ID -->

                            <td>

                                <?php
                                echo (int)
                                    $taxi["id"];
                                ?>

                            </td>


                            <!-- BRAND -->

                            <td>

                                <?php
                                echo htmlspecialchars(
                                    $taxi["brand"]
                                );
                                ?>

                            </td>


                            <!-- MODEL -->

                            <td>

                                <?php
                                echo htmlspecialchars(
                                    $taxi["model"]
                                );
                                ?>

                            </td>


                            <!-- REGISTRATION -->

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

                                    <input
                                        type="hidden"
                                        name="update_rent"
                                        value="1"
                                    >

                                    <input
                                        type="hidden"
                                        name="taxi_id"
                                        value="<?php
                                            echo (int)
                                                $taxi["id"];
                                        ?>"
                                    >

                                    <input
                                        type="number"
                                        name="new_rent"
                                        value="<?php
                                            echo htmlspecialchars(
                                                $taxi["rent"]
                                            );
                                        ?>"
                                        step="0.01"
                                        min="0"
                                        required
                                    >

                                    <button
                                        type="submit"
                                        class="action-button"
                                    >
                                        Update
                                    </button>

                                </form>

                            </td>


                            <!-- STATUS -->

                            <td>

                                <?php

                                $status_class =
                                    "status-inactive";


                                if (
                                    $taxi["status"]
                                    === "Available"
                                ) {

                                    $status_class =
                                        "status-available";

                                } elseif (
                                    $taxi["status"]
                                    === "Assigned"
                                ) {

                                    $status_class =
                                        "status-assigned";

                                } elseif (
                                    $taxi["status"]
                                    === "Maintenance"
                                ) {

                                    $status_class =
                                        "status-maintenance";

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
                                        $taxi["status"]
                                    );
                                    ?>

                                </span>

                            </td>


                            <!-- UPDATE STATUS -->

                            <td>

                                <?php if (
                                    $taxi["status"]
                                    === "Assigned"
                                ): ?>

                                    <span>
                                        Controlled by Assignment
                                    </span>

                                <?php else: ?>


                                    <form
                                        method="POST"
                                        class="status-update-form"
                                    >

                                        <input
                                            type="hidden"
                                            name="update_status"
                                            value="1"
                                        >

                                        <input
                                            type="hidden"
                                            name="taxi_id"
                                            value="<?php
                                                echo (int)
                                                    $taxi["id"];
                                            ?>"
                                        >


                                        <select
                                            name="new_status"
                                            required
                                        >

                                            <option
                                                value="Available"
                                                <?php
                                                if (
                                                    $taxi["status"]
                                                    === "Available"
                                                ) {
                                                    echo "selected";
                                                }
                                                ?>
                                            >
                                                Available
                                            </option>


                                            <option
                                                value="Maintenance"
                                                <?php
                                                if (
                                                    $taxi["status"]
                                                    === "Maintenance"
                                                ) {
                                                    echo "selected";
                                                }
                                                ?>
                                            >
                                                Maintenance
                                            </option>


                                            <option
                                                value="Inactive"
                                                <?php
                                                if (
                                                    $taxi["status"]
                                                    === "Inactive"
                                                ) {
                                                    echo "selected";
                                                }
                                                ?>
                                            >
                                                Inactive
                                            </option>

                                        </select>


                                        <button
                                            type="submit"
                                            class="action-button"
                                        >
                                            Update
                                        </button>

                                    </form>


                                <?php endif; ?>

                            </td>


                            <!-- CREATED -->

                            <td>

                                <?php

                                echo htmlspecialchars(
                                    $taxi["created_at"]
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
                No taxis found.
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