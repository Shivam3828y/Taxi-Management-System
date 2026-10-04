<?php

// =====================================================
// DRIVER REGISTRATION
// =====================================================

require_once "../config.php";

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$message = "";
$error = "";

// =====================================================
// DRIVER REGISTRATION PROCESS
// =====================================================

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    csrf_verify();

    // =================================================
    // GET FORM DATA
    // =================================================

    $name = trim($_POST["name"] ?? "");
    $phone = trim($_POST["phone"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $address = trim($_POST["address"] ?? "");

    $driving_license = trim(
        $_POST["driving_license"] ?? ""
    );

    $document_type = trim(
        $_POST["document_type"] ?? ""
    );

    $document_number = trim(
        $_POST["document_number"] ?? ""
    );

    $password = (string) ($_POST["password"] ?? "");
    $confirm_password = (string) ($_POST["confirm_password"] ?? "");

    $terms = isset($_POST["terms"]);

    // =================================================
    // VALIDATION
    // =================================================

    if (
        $name === "" ||
        $phone === "" ||
        $address === "" ||
        $driving_license === "" ||
        $document_type === "" ||
        $document_number === "" ||
        $password === ""
    ) {

        $error = "Please fill all required fields.";

    } elseif (strlen($password) < 8) {

        $error = "Password must be at least 8 characters.";

    } elseif ($password !== $confirm_password) {

        $error = "Passwords do not match.";

    } elseif (!$terms) {

        $error = "Please accept the declaration.";

    } else {

        // =============================================
        // CHECK EXISTING DRIVER
        // =============================================

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
                "Database error: " .
                mysqli_error($conn);

        } else {

            mysqli_stmt_bind_param(
                $check_stmt,
                "ss",
                $phone,
                $driving_license
            );

            mysqli_stmt_execute($check_stmt);

            $check_result =
                mysqli_stmt_get_result(
                    $check_stmt
                );

            if (
                mysqli_num_rows($check_result) > 0
            ) {

                $error =
                    "Phone number or driving license is already registered.";

            } else {

                // =====================================
                // REGISTER DRIVER
                // =====================================

                $password_hash = hash_password($password);

                $insert_sql = "
                    INSERT INTO drivers
                    (
                        name,
                        phone,
                        email,
                        address,
                        driving_license,
                        document_type,
                        document_number,
                        password,
                        status
                    )
                    VALUES
                    (
                        ?,
                        ?,
                        ?,
                        ?,
                        ?,
                        ?,
                        ?,
                        ?,
                        'Pending'
                    )
                ";

                $insert_stmt = mysqli_prepare(
                    $conn,
                    $insert_sql
                );

                if (!$insert_stmt) {

                    $error =
                        "Database error: " .
                        mysqli_error($conn);

                } else {

                    mysqli_stmt_bind_param(
                        $insert_stmt,
                        "ssssssss",
                        $name,
                        $phone,
                        $email,
                        $address,
                        $driving_license,
                        $document_type,
                        $document_number,
                        $password_hash
                    );

                    if (
                        mysqli_stmt_execute(
                            $insert_stmt
                        )
                    ) {

                        $message =
                            "Driver application submitted successfully. " .
                            "Your application is now pending admin verification.";

                        // Clear submitted values after successful registration.
                        $_POST = [];

                    } else {

                        $error =
                            "Registration failed: " .
                            mysqli_error($conn);
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
        Driver Registration
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
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid #ddd;
        }

        .form-section:last-child {
            border-bottom: none;
        }

        .form-section h3 {
            margin-bottom: 15px;
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
        .form-group textarea,
        .form-group select {
            width: 100%;
            box-sizing: border-box;
            padding: 10px;
        }

        .form-group textarea {
            min-height: 100px;
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

        .declaration {
            display: flex;
            gap: 10px;
            align-items: flex-start;
            margin-top: 15px;
        }

        .declaration input {
            margin-top: 4px;
        }

        .submit-button {
            width: 100%;
            padding: 13px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 16px;
        }

        .register-links {
            margin-top: 20px;
            text-align: center;
        }

        .register-links a {
            margin: 0 10px;
        }

        .process-box {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 8px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
        }

        .process-box ol {
            margin-bottom: 0;
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

        <a href="../index2.php">
            Home
        </a>

        <a href="login.php">
            Driver Login
        </a>

        <a href="../admin/login.php">
            Admin Login
        </a>

    </nav>

</header>

<!-- ================================================= -->
<!-- MAIN -->
<!-- ================================================= -->

<main>

    <section class="register-container">

        <h2>
            Driver Registration / Application
        </h2>

        <p>
            Submit your details for verification and
            taxi assignment.
        </p>

        <!-- ========================================= -->
        <!-- PROCESS -->
        <!-- ========================================= -->

        <div class="process-box">

            <strong>
                Registration Process
            </strong>

            <ol>

                <li>
                    Fill in your driver information.
                </li>

                <li>
                    Submit the application.
                </li>

                <li>
                    Admin verifies your information.
                </li>

                <li>
                    After verification, admin can assign
                    an available taxi.
                </li>

                <li>
                    Admin creates the taxi agreement.
                </li>

                <li>
                    Agreement appears in your driver portal.
                </li>

            </ol>

        </div>

        <!-- ========================================= -->
        <!-- MESSAGES -->
        <!-- ========================================= -->

        <?php if ($message !== ""): ?>

            <div class="success-message">

                <?php
                echo htmlspecialchars(
                    $message
                );
                ?>

            </div>

        <?php endif; ?>

        <?php if ($error !== ""): ?>

            <div class="error-message">

                <?php
                echo htmlspecialchars(
                    $error
                );
                ?>

            </div>

        <?php endif; ?>

        <!-- ========================================= -->
        <!-- FORM -->
        <!-- ========================================= -->

        <div class="form-card">

            <form
                method="POST"
                action=""
            >

                <?php csrf_field(); ?>

                <!-- ================================= -->
                <!-- PERSONAL INFORMATION -->
                <!-- ================================= -->

                <div class="form-section">

                    <h3>
                        1. Personal Information
                    </h3>

                    <div class="form-group">

                        <label for="name">

                            Full Name

                            <span class="required">
                                *
                            </span>

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

                            Phone Number

                            <span class="required">
                                *
                            </span>

                        </label>

                        <input
                            type="tel"
                            id="phone"
                            name="phone"
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

                            Address

                            <span class="required">
                                *
                            </span>

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
                        2. Driving Licence Details
                    </h3>

                    <div class="form-group">

                        <label for="driving_license">

                            Driving Licence Number

                            <span class="required">
                                *
                            </span>

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
                <!-- DOCUMENT INFORMATION -->
                <!-- ================================= -->

                <div class="form-section">

                    <h3>
                        3. Document Information
                    </h3>

                    <div class="form-group">

                        <label for="document_type">

                            Identity Document Type

                            <span class="required">
                                *
                            </span>

                        </label>

                        <select
                            id="document_type"
                            name="document_type"
                            required
                        >

                            <option value="">
                                Select Document
                            </option>

                            <option
                                value="Aadhaar"
                                <?php
                                if (
                                    ($_POST["document_type"] ?? "") ===
                                    "Aadhaar"
                                ) {
                                    echo "selected";
                                }
                                ?>
                            >
                                Aadhaar
                            </option>

                            <option
                                value="PAN"
                                <?php
                                if (
                                    ($_POST["document_type"] ?? "") ===
                                    "PAN"
                                ) {
                                    echo "selected";
                                }
                                ?>
                            >
                                PAN
                            </option>

                            <option
                                value="Voter ID"
                                <?php
                                if (
                                    ($_POST["document_type"] ?? "") ===
                                    "Voter ID"
                                ) {
                                    echo "selected";
                                }
                                ?>
                            >
                                Voter ID
                            </option>

                        </select>

                    </div>

                    <div class="form-group">

                        <label for="document_number">

                            Document Number

                            <span class="required">
                                *
                            </span>

                        </label>

                        <input
                            type="text"
                            id="document_number"
                            name="document_number"
                            value="<?php
                                echo htmlspecialchars(
                                    $_POST["document_number"] ?? ""
                                );
                            ?>"
                            required
                        >

                    </div>

                    <p>
                        Admin will verify the submitted
                        information before approving the
                        driver application.
                    </p>

                </div>

                <!-- ================================= -->
                <!-- ACCOUNT PASSWORD -->
                <!-- ================================= -->

                <div class="form-section">

                    <h3>
                        Account Password
                    </h3>

                    <div class="form-group">

                        <label for="password">

                            Password

                            <span class="required">
                                *
                            </span>

                        </label>

                        <input
                            type="password"
                            id="password"
                            name="password"
                            autocomplete="new-password"
                            minlength="8"
                            required
                        >

                    </div>

                    <div class="form-group">

                        <label for="confirm_password">

                            Confirm Password

                            <span class="required">
                                *
                            </span>

                        </label>

                        <input
                            type="password"
                            id="confirm_password"
                            name="confirm_password"
                            autocomplete="new-password"
                            minlength="8"
                            required
                        >

                    </div>

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
                            I understand that my application
                            will be reviewed by the admin
                            before I can receive a taxi
                            assignment.

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

        <!-- ========================================= -->
        <!-- LINKS -->
        <!-- ========================================= -->

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