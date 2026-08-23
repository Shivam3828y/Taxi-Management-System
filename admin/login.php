<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

require_once "../config.php";

$error = "";


// =====================================================
// ADMIN LOGIN
// =====================================================

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $username = trim($_POST["username"] ?? "");
    $password = $_POST["password"] ?? "";


    // =================================================
    // VALIDATION
    // =================================================

    if ($username === "" || $password === "") {

        $error = "Please enter username and password.";

    } else {

        // =============================================
        // FIND ADMIN
        // =============================================

        $sql = "
            SELECT
                id,
                name,
                username,
                password
            FROM admins
            WHERE username = ?
            LIMIT 1
        ";

        $stmt = mysqli_prepare($conn, $sql);


        if (!$stmt) {

            $error =
                "Database error: "
                . mysqli_error($conn);

        } else {

            mysqli_stmt_bind_param(
                $stmt,
                "s",
                $username
            );

            mysqli_stmt_execute($stmt);

            $result = mysqli_stmt_get_result($stmt);


            // =========================================
            // CHECK ADMIN
            // =========================================

            if (mysqli_num_rows($result) === 1) {

                $admin = mysqli_fetch_assoc($result);

                $stored_password = $admin["password"];

                /*
                 * First try secure password_hash() password.
                 */
                $password_valid = password_verify(
                    $password,
                    $stored_password
                );


                /*
                 * Backward compatibility:
                 *
                 * If the current database still contains
                 * a plain-text password, allow it once.
                 *
                 * After successful login, immediately
                 * convert it to password_hash().
                 */
                if (
                    !$password_valid &&
                    hash_equals(
                        $stored_password,
                        $password
                    )
                ) {

                    $password_valid = true;

                    $new_hash = password_hash(
                        $password,
                        PASSWORD_DEFAULT
                    );


                    $update_sql = "
                        UPDATE admins
                        SET password = ?
                        WHERE id = ?
                    ";

                    $update_stmt = mysqli_prepare(
                        $conn,
                        $update_sql
                    );


                    if ($update_stmt) {

                        mysqli_stmt_bind_param(
                            $update_stmt,
                            "si",
                            $new_hash,
                            $admin["id"]
                        );

                        mysqli_stmt_execute(
                            $update_stmt
                        );

                        mysqli_stmt_close(
                            $update_stmt
                        );
                    }
                }


                // =====================================
                // LOGIN SUCCESS
                // =====================================

                if ($password_valid) {

                    /*
                     * Prevent session fixation.
                     */
                    session_regenerate_id(true);


                    $_SESSION["admin_id"] =
                        (int) $admin["id"];

                    $_SESSION["admin_name"] =
                        $admin["name"];

                    $_SESSION["admin_username"] =
                        $admin["username"];


                    header(
                        "Location: dashboard.php"
                    );

                    exit;

                } else {

                    $error =
                        "Invalid username or password.";

                }


            } else {

                /*
                 * Do not reveal whether the username
                 * actually exists.
                 */
                $error =
                    "Invalid username or password.";

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
        Admin Login - Taxi Management System
    </title>


    <link
        rel="stylesheet"
        href="../css/style.css"
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

</header>


<!-- ================================================= -->
<!-- MAIN -->
<!-- ================================================= -->

<main>

    <section class="login-container">

        <h2>
            Admin Login
        </h2>


        <p>
            Login to manage drivers, taxis,
            assignments and agreements.
        </p>


        <!-- ========================================= -->
        <!-- ERROR -->
        <!-- ========================================= -->

        <?php if ($error !== ""): ?>

            <div class="error-message">

                <?php
                echo htmlspecialchars($error);
                ?>

            </div>

        <?php endif; ?>


        <!-- ========================================= -->
        <!-- LOGIN FORM -->
        <!-- ========================================= -->

        <form
            method="POST"
            action=""
        >


            <!-- USERNAME -->

            <div>

                <label for="username">
                    Username
                </label>


                <input
                    type="text"
                    id="username"
                    name="username"
                    autocomplete="username"
                    value="<?php
                        echo htmlspecialchars(
                            $_POST["username"] ?? ""
                        );
                    ?>"
                    required
                >

            </div>


            <br>


            <!-- PASSWORD -->

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

            </div>


            <br>


            <!-- LOGIN -->

            <button type="submit">
                Login
            </button>


        </form>


        <!-- ========================================= -->
        <!-- OTHER PORTALS -->
        <!-- ========================================= -->

        <div class="login-links">

            <a href="../index2.php">
                Back to Public Portal
            </a>


            <a href="../driver/login.php">
                Driver Login
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