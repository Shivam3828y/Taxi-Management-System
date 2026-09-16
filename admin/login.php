<?php

session_start();

require_once "../config.php";

$error = "";


// =====================================================
// ADMIN LOGIN
// =====================================================

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    csrf_verify();

    $username = trim($_POST["username"] ?? "");
    $password = trim($_POST["password"] ?? "");


    // -------------------------------------------------
    // VALIDATION
    // -------------------------------------------------

    if ($username === "" || $password === "") {

        $error = "Please enter username and password.";

    } else {


        // -------------------------------------------------
        // FIND ADMIN
        // -------------------------------------------------

        $sql = "
            SELECT *
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

            $result =
                mysqli_stmt_get_result($stmt);


            // -------------------------------------------------
            // CHECK ADMIN
            // -------------------------------------------------

            if (
                mysqli_num_rows($result) === 1
            ) {

                $admin =
                    mysqli_fetch_assoc($result);


                if (
                    verify_password(
                        $password,
                        $admin["password"]
                    )
                ) {

                    // Legacy plain-text passwords are
                    // upgraded to a proper hash the
                    // moment they're used successfully.
                    if (
                        is_legacy_plain_password(
                            $admin["password"]
                        )
                    ) {

                        $new_hash =
                            hash_password($password);

                        $upgrade_stmt = mysqli_prepare(
                            $conn,
                            "UPDATE admins SET password = ? WHERE id = ?"
                        );

                        if ($upgrade_stmt) {
                            mysqli_stmt_bind_param(
                                $upgrade_stmt,
                                "si",
                                $new_hash,
                                $admin["id"]
                            );
                            mysqli_stmt_execute($upgrade_stmt);
                            mysqli_stmt_close($upgrade_stmt);
                        }
                    }


                    // -------------------------------------------------
                    // CREATE ADMIN SESSION
                    // -------------------------------------------------

                    session_regenerate_id(true);

                    $_SESSION["admin_id"] =
                        $admin["id"];

                    $_SESSION["admin_name"] =
                        $admin["name"];


                    // -------------------------------------------------
                    // GO TO ADMIN DASHBOARD
                    // -------------------------------------------------

                    header(
                        "Location: dashboard.php"
                    );

                    exit;


                } else {

                    $error =
                        "Invalid password.";

                }


            } else {

                $error =
                    "Admin account not found.";

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

            margin-bottom: 15px;

        }

        .login-links {

            margin-top: 20px;

        }

        .login-links a {

            margin-right: 15px;

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
<!-- LOGIN -->
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


        <!-- ================================================= -->
        <!-- OTHER PORTALS -->
        <!-- ================================================= -->

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