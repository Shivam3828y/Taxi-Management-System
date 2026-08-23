<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

require_once "../config.php";

$error = "";


// =====================================================
// ALREADY LOGGED IN
// =====================================================

if (isset($_SESSION["driver_id"])) {

    header("Location: dashboard.php");
    exit;

}


// =====================================================
// DRIVER LOGIN
// =====================================================

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $phone = trim($_POST["phone"] ?? "");

    $driving_license = trim(
        $_POST["driving_license"] ?? ""
    );


    // =================================================
    // VALIDATION
    // =================================================

    if (
        $phone === "" ||
        $driving_license === ""
    ) {

        $error =
            "Please enter your phone number and driving license number.";

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
                status
            FROM drivers
            WHERE phone = ?
            AND driving_license = ?
            LIMIT 1
        ";


        $stmt = mysqli_prepare(
            $conn,
            $sql
        );


        if (!$stmt) {

            $error =
                "Database error. Please try again later.";

        } else {

            mysqli_stmt_bind_param(
                $stmt,
                "ss",
                $phone,
                $driving_license
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


                // =====================================
                // VERIFIED DRIVER
                // =====================================

                if (
                    $driver["status"] === "Verified"
                ) {

                    /*
                     * Regenerate session ID after
                     * successful authentication.
                     */

                    session_regenerate_id(true);


                    $_SESSION["driver_id"] =
                        (int) $driver["id"];

                    $_SESSION["driver_name"] =
                        $driver["name"];

                    $_SESSION["driver_phone"] =
                        $driver["phone"];


                    header(
                        "Location: dashboard.php"
                    );

                    exit;

                }


                // =====================================
                // PENDING
                // =====================================

                elseif (
                    $driver["status"] === "Pending"
                ) {

                    $error =
                        "Your driver application is still pending admin verification.";

                }


                // =====================================
                // INACTIVE
                // =====================================

                elseif (
                    $driver["status"] === "Inactive"
                ) {

                    $error =
                        "Your driver account is inactive. Please contact the admin.";

                }


                // =====================================
                // OTHER STATUS
                // =====================================

                else {

                    $error =
                        "Your driver account is not allowed to login.";

                }

            } else {

                $error =
                    "Invalid phone number or driving license number.";

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
        href="../css/style.css"
    >


    <style>

        .login-container {

            max-width: 450px;

            margin: 50px auto;

            padding: 20px;

        }


        .login-card {

            border: 1px solid #ddd;

            border-radius: 10px;

            padding: 25px;

            background: #fff;

        }


        .form-group {

            margin-bottom: 18px;

        }


        .form-group label {

            display: block;

            margin-bottom: 6px;

            font-weight: bold;

        }


        .form-group input {

            width: 100%;

            box-sizing: border-box;

            padding: 10px;

        }


        .error-message {

            padding: 12px;

            margin-bottom: 20px;

            background: #fef2f2;

            border: 1px solid #fca5a5;

            color: #991b1b;

            border-radius: 6px;

        }


        .login-button {

            width: 100%;

            padding: 12px;

            border: none;

            border-radius: 6px;

            cursor: pointer;

            font-size: 16px;

        }


        .login-links {

            margin-top: 20px;

            text-align: center;

        }


        .login-links a {

            margin: 0 8px;

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
<!-- MAIN -->
<!-- ================================================= -->

<main>


    <section class="login-container">


        <h2>
            Driver Login
        </h2>


        <p>
            Login using your registered phone number
            and driving license number.
        </p>


        <?php if ($error !== ""): ?>

            <div class="error-message">

                <?php
                echo htmlspecialchars($error);
                ?>

            </div>

        <?php endif; ?>


        <!-- ========================================= -->
        <!-- LOGIN CARD -->
        <!-- ========================================= -->

        <div class="login-card">


            <form
                method="POST"
                action=""
            >


                <!-- ================================= -->
                <!-- PHONE -->
                <!-- ================================= -->

                <div class="form-group">

                    <label for="phone">
                        Phone Number
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
                        autocomplete="tel"
                        required
                    >

                </div>


                <!-- ================================= -->
                <!-- DRIVING LICENSE -->
                <!-- ================================= -->

                <div class="form-group">

                    <label for="driving_license">
                        Driving License Number
                    </label>


                    <input
                        type="text"
                        id="driving_license"
                        name="driving_license"
                        value="<?php
                            echo htmlspecialchars(
                                $_POST[
                                    "driving_license"
                                ] ?? ""
                            );
                        ?>"
                        autocomplete="off"
                        required
                    >

                </div>


                <!-- ================================= -->
                <!-- LOGIN -->
                <!-- ================================= -->

                <button
                    type="submit"
                    class="login-button"
                >
                    Login
                </button>


            </form>


            <!-- ===================================== -->
            <!-- LINKS -->
            <!-- ===================================== -->

            <div class="login-links">

                <p>
                    New driver?
                </p>


                <a href="register.php">
                    Register / Apply
                </a>


                <a href="../index2.php">
                    Back to Home
                </a>

            </div>


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