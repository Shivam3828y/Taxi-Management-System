<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

require_once "../config.php";

$message = "";
$error = "";


// =====================================================
// DRIVER REGISTRATION
// =====================================================

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $name = trim($_POST["name"] ?? "");
    $phone = trim($_POST["phone"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $address = trim($_POST["address"] ?? "");
    $driving_license = trim($_POST["driving_license"] ?? "");
    $terms = isset($_POST["terms"]);


    // =================================================
    // VALIDATION
    // =================================================

    if (
        $name === "" ||
        $phone === "" ||
        $address === "" ||
        $driving_license === ""
    ) {

        $error = "Please fill all required fields.";

    } elseif (!$terms) {

        $error = "Please accept the declaration.";

    } elseif (!preg_match("/^[0-9]{10}$/", $phone)) {

        $error = "Please enter a valid 10-digit phone number.";

    } else {


        // =================================================
        // CHECK EXISTING DRIVER
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
                mysqli_num_rows(
                    $check_result
                ) > 0
            ) {

                $error =
                    "Phone number or driving license is already registered.";

            } else {


                // =================================================
                // INSERT DRIVER
                // =================================================

                $insert_sql = "
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
                    (?, ?, ?, ?, ?, 'Pending')
                ";


                $insert_stmt = mysqli_prepare(
                    $conn,
                    $insert_sql
                );


                if (!$insert_stmt) {

                    $error =
                        "Database error: "
                        . mysqli_error($conn);

                } else {

                    mysqli_stmt_bind_param(
                        $insert_stmt,
                        "sssss",
                        $name,
                        $phone,
                        $email,
                        $address,
                        $driving_license
                    );


                    if (
                        mysqli_stmt_execute(
                            $insert_stmt
                        )
                    ) {

                        $message =
                            "Driver registration submitted successfully. "
                            . "Please wait for admin verification.";

                        $_POST = [];

                    } else {

                        $error =
                            "Registration failed: "
                            . mysqli_error($conn);
                    }


                    mysqli_stmt_close(
                        $insert_stmt
                    );
                }
            }


            mysqli_stmt_close(
                $check_stmt
            );
        }
    }
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
        Driver Registration - Taxi Management System
    </title>

    <link
        rel="stylesheet"
        href="../css/style.css"
    >

    <style>

        .register-container {
            max-width: 750px;
            margin: 30px auto;
            padding: 20px;
        }

        .form-card {
            border: 1px solid #ddd;
            border-radius: 10px;
            padding: 25px;
            background: #fff;
        }

        .form-section {
            margin-bottom: 25px;
            padding-bottom: 20px;
            border-bottom: 1px solid #ddd;
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
            min-height: 100px;
            resize: vertical;
        }

        .declaration {
            display: flex;
            gap: 10px;
            align-items: flex-start;
        }

        .submit-button {
            padding: 12px 20px;
            border: none;
            border-radius: 6px;
            background: #333;
            color: white;
            cursor: pointer;
        }

        .success-message {
            color: green;
            margin-bottom: 15px;
        }

        .error-message {
            color: #b91c1c;
            margin-bottom: 15px;
        }

        .register-links {
            margin-top: 20px;
        }

        .register-links a {
            margin-right: 15px;
        }

    </style>

</head>


<body>


<header>

    <h1>
        Taxi Management System
    </h1>

</header>


<main class="register-container">

    <section>

        <h2>
            Driver Registration
        </h2>

        <p>
            Register as a driver to apply for a taxi assignment.
        </p>


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


        <div class="form-card">

            <form method="POST">


                <!-- ================================= -->
                <!-- PERSONAL INFORMATION -->
                <!-- ================================= -->

                <div class="form-section">

                    <h3>
                        1. Personal Information
                    </h3>


                    <div class="form-group">

                        <label for="name">
                            Full Name *
                        </label>

                        <input
                            type="text"
                            id="name"
                            name="name"
                            value="<?php
                                echo htmlspecialchars(
                                    $_POST["name"] ?? ""
                                );
                            ?>"
                            required
                        >

                    </div>


                    <div class="form-group">

                        <label for="phone">
                            Phone Number *
                        </label>

                        <input
                            type="text"
                            id="phone"
                            name="phone"
                            maxlength="10"
                            value="<?php
                                echo htmlspecialchars(
                                    $_POST["phone"] ?? ""
                                );
                            ?>"
                            required
                        >

                    </div>


                    <div class="form-group">

                        <label for="email">
                            Email
                        </label>

                        <input
                            type="email"
                            id="email"
                            name="email"
                            value="<?php
                                echo htmlspecialchars(
                                    $_POST["email"] ?? ""
                                );
                            ?>"
                        >

                    </div>


                    <div class="form-group">

                        <label for="address">
                            Address *
                        </label>

                        <textarea
                            id="address"
                            name="address"
                            required
                        ><?php
                            echo htmlspecialchars(
                                $_POST["address"] ?? ""
                            );
                        ?></textarea>

                    </div>

                </div>


                <!-- ================================= -->
                <!-- LICENSE -->
                <!-- ================================= -->

                <div class="form-section">

                    <h3>
                        2. Driving License
                    </h3>


                    <div class="form-group">

                        <label for="driving_license">
                            Driving License Number *
                        </label>

                        <input
                            type="text"
                            id="driving_license"
                            name="driving_license"
                            value="<?php
                                echo htmlspecialchars(
                                    $_POST["driving_license"] ?? ""
                                );
                            ?>"
                            required
                        >

                    </div>

                </div>


                <!-- ================================= -->
                <!-- ADMIN VERIFICATION -->
                <!-- ================================= -->

                <div class="form-section">

                    <h3>
                        3. Verification
                    </h3>

                    <p>
                        Your registration will be reviewed
                        and verified by the administrator.
                    </p>

                </div>


                <!-- ================================= -->
                <!-- DECLARATION -->
                <!-- ================================= -->

                <div class="form-section">

                    <h3>
                        4. Declaration
                    </h3>


                    <label class="declaration">

                        <input
                            type="checkbox"
                            name="terms"
                            required
                        >

                        <span>
                            I confirm that the information
                            provided by me is correct and
                            understand that my application
                            will be reviewed by the admin
                            before I receive a taxi assignment.
                        </span>

                    </label>

                </div>


                <!-- ================================= -->
                <!-- SUBMIT -->
                <!-- ================================= -->

                <button
                    type="submit"
                    class="submit-button"
                >
                    Submit Driver Application
                </button>


            </form>

        </div>


        <div class="register-links">

            <a href="login.php">
                Already Registered? Driver Login
            </a>

            <a href="../index2.php">
                Back to Home
            </a>

        </div>

    </section>

</main>


<footer>

    <p>
        &copy; 2026 Taxi Management System
    </p>

</footer>


</body>

</html>