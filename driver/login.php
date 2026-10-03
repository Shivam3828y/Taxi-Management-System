<?php

session_start();

require_once "../config.php";

$error = "";


// =====================================================
// DRIVER LOGIN
// =====================================================

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    csrf_verify();

    $phone = trim($_POST["phone"] ?? "");

    $password =
        trim($_POST["password"] ?? "");


    // =================================================
    // VALIDATION
    // =================================================

    if (
        $phone === "" ||
        $password === ""
    ) {

        $error =
            "Please enter your phone number and password.";

    } else {


        // =============================================
        // FIND DRIVER
        // =============================================

        $sql = "
            SELECT
                id,
                name,
                phone,
                email,
                address,
                driving_license,
                password,
                status
            FROM drivers
            WHERE phone = ?
            LIMIT 1
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
                "s",
                $phone
            );

            mysqli_stmt_execute($stmt);

            $result =
                mysqli_stmt_get_result($stmt);


            // =========================================
            // DRIVER FOUND
            // =========================================

            if (
                mysqli_num_rows($result) === 1
            ) {

                $driver =
                    mysqli_fetch_assoc($result);

                $stored_password = $driver["password"] ?? "";

                // Accounts created before the password field
                // existed have no password saved yet. For those
                // only, fall back to checking the driving license
                // number they registered with (their original
                // credential), then immediately save a real
                // password hash so future logins use it instead.
                if ($stored_password === "" || $stored_password === null) {
                    $password_ok = hash_equals(
                        (string) $driver["driving_license"],
                        $password
                    );
                } else {
                    $password_ok = verify_password($password, $stored_password);
                }

                if (!$password_ok) {

                    $error =
                        "Incorrect phone number or password.";

                } else {

                if ($stored_password === "" || $stored_password === null || is_legacy_plain_password($stored_password)) {

                    $new_hash = hash_password($password);

                    $upgrade_stmt = mysqli_prepare(
                        $conn,
                        "UPDATE drivers SET password = ? WHERE id = ?"
                    );

                    if ($upgrade_stmt) {
                        mysqli_stmt_bind_param(
                            $upgrade_stmt,
                            "si",
                            $new_hash,
                            $driver["id"]
                        );
                        mysqli_stmt_execute($upgrade_stmt);
                        mysqli_stmt_close($upgrade_stmt);
                    }
                }


                // =====================================
                // CHECK STATUS
                // =====================================

                if (
                    $driver["status"] === "Verified"
                ) {

                    // ===============================
                    // CREATE SESSION
                    // ===============================

                    session_regenerate_id(true);

                    $_SESSION["driver_id"] =
                        $driver["id"];

                    $_SESSION["driver_name"] =
                        $driver["name"];

                    $_SESSION["driver_phone"] =
                        $driver["phone"];


                    // ===============================
                    // DASHBOARD
                    // ===============================

                    header(
                        "Location: dashboard.php"
                    );

                    exit;


                } elseif (
                    $driver["status"] === "Pending"
                ) {

                    $error =
                        "Your registration is pending admin verification. "
                        . "Please wait until the admin verifies your account.";


                } elseif (
                    $driver["status"] === "Inactive"
                ) {

                    $error =
                        "Your driver account is inactive. "
                        . "Please contact the admin.";


                } else {

                    $error =
                        "Your driver account cannot login currently.";

                }

                }


            } else {

                $error =
                    "Incorrect phone number or password.";

            }


            mysqli_stmt_close($stmt);

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
        Driver Login - Taxi Management System
    </title>


    <link
        rel="stylesheet"
        href="/css/style.css"
    >


    <style>

        .login-container {

            max-width: 450px;

            margin: 50px auto;

        }


        .error-message {

            color: #b91c1c;

            background: #fee2e2;

            border: 1px solid #fecaca;

            padding: 12px;

            border-radius: 6px;

            margin-bottom: 20px;

        }


        .login-links {

            margin-top: 20px;

            display: flex;

            gap: 15px;

            flex-wrap: wrap;

        }


        .login-links a {

            text-decoration: none;

        }


        .register-button {

            display: inline-block;

            padding: 10px 15px;

            border: 1px solid #333;

            border-radius: 6px;

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


        <a href="../admin/login.php">
            Admin Login
        </a>

    </nav>

</header>


<!-- ================================================= -->
<!-- DRIVER LOGIN -->
<!-- ================================================= -->

<main>

    <section class="login-container">

        <h2>
            Driver Login
        </h2>


        <p>
            Login to access your driver portal.
        </p>


        <?php if ($error !== ""): ?>

            <p class="error-message">

                <?php
                echo htmlspecialchars($error);
                ?>

            </p>

        <?php endif; ?>


        <form
            method="POST"
            action=""
        >

            <?php csrf_field(); ?>

            <!-- ===================================== -->
            <!-- PHONE -->
            <!-- ===================================== -->

            <div>

                <label for="phone">
                    Phone Number
                </label>


                <input
                    type="text"
                    id="phone"
                    name="phone"
                    autocomplete="tel"
                    required
                >

            </div>


            <br>


            <!-- ===================================== -->
            <!-- PASSWORD -->
            <!-- ===================================== -->

            <div>

                <label for="password">
                    Password
                </label>


                <input
                    type="password"
                    id="password"
                    name="password"
                    autocomplete="current-password"
                    required
                >

                <p style="font-size: 0.85em; color: #666; margin-top: 4px;">
                    First time logging in since this update?
                    Use your driving license number as your password —
                    it'll be upgraded to a real password automatically.
                </p>

            </div>


            <br>


            <!-- ===================================== -->
            <!-- LOGIN -->
            <!-- ===================================== -->

            <button type="submit">
                Driver Login
            </button>


        </form>


        <!-- ========================================= -->
        <!-- REGISTRATION / HOME -->
        <!-- ========================================= -->

        <div class="login-links">

            <a
                href="register.php"
                class="register-button"
            >
                New Driver? Register
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