<?php

require_once "../config.php";

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (isset($_SESSION["driver_id"])) {
    header("Location: dashboard.php");
    exit;
}

$error = "";
$success = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    csrf_verify();

    $phone = trim($_POST["phone"] ?? "");
    $password = $_POST["password"] ?? "";

    if ($phone === "" || $password === "") {

        $error = "Please enter your phone number and password.";

    } else {

        $stmt = mysqli_prepare(
            $conn,
            "SELECT
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
             LIMIT 1"
        );

        if (!$stmt) {

            $error = "Database error. Please try again.";

        } else {

            mysqli_stmt_bind_param(
                $stmt,
                "s",
                $phone
            );

            mysqli_stmt_execute($stmt);

            $result = mysqli_stmt_get_result($stmt);

            $driver = mysqli_fetch_assoc($result);

            mysqli_stmt_close($stmt);

            if (!$driver) {

                $error = "No driver account found with this phone number.";

            } else {

                /*
                 * Password verification
                 *
                 * New accounts:
                 * password column contains a password_hash()
                 *
                 * Legacy accounts:
                 * password may be empty.
                 * Driving license can temporarily be used
                 * as the original credential.
                 */

                $password_valid = false;
                $legacy_login = false;

                if (!empty($driver["password"])) {

                    $password_valid = verify_password(
                        $password,
                        $driver["password"]
                    );

                } elseif (!empty($driver["driving_license"])) {

                    $password_valid = hash_equals(
                        strtolower(trim($driver["driving_license"])),
                        strtolower(trim($password))
                    );

                    if ($password_valid) {
                        $legacy_login = true;
                    }
                }

                if (!$password_valid) {

                    $error = "Incorrect phone number or password.";

                } elseif ($driver["status"] === "Pending") {

                    $error =
                        "Your driver account is still pending verification by the admin.";

                } elseif ($driver["status"] === "Inactive") {

                    $error =
                        "Your driver account is inactive. Please contact the administrator.";

                } elseif ($driver["status"] !== "Verified") {

                    $error =
                        "Your driver account is not verified yet.";

                } else {

                    /*
                     * Convert legacy login credentials
                     * into a secure password hash.
                     */

                    if (
                        $legacy_login ||
                        (
                            !empty($driver["password"]) &&
                            is_legacy_plain_password($driver["password"])
                        )
                    ) {

                        $new_hash = hash_password($password);

                        $update_stmt = mysqli_prepare(
                            $conn,
                            "UPDATE drivers
                             SET password = ?
                             WHERE id = ?"
                        );

                        if ($update_stmt) {

                            mysqli_stmt_bind_param(
                                $update_stmt,
                                "si",
                                $new_hash,
                                $driver["id"]
                            );

                            mysqli_stmt_execute($update_stmt);

                            mysqli_stmt_close($update_stmt);
                        }
                    }

                    /*
                     * Create a new session ID after successful login.
                     */

                    session_regenerate_id(true);

                    $_SESSION["driver_id"] =
                        (int)$driver["id"];

                    $_SESSION["driver_name"] =
                        $driver["name"];

                    $_SESSION["driver_phone"] =
                        $driver["phone"];

                    header("Location: dashboard.php");
                    exit;
                }
            }
        }
    }
}

$csrf_token = csrf_token();

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Driver Login | Taxi Management System</title>

    <link
        rel="stylesheet"
        href="../css/style.css"
    >

    <style>

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f4f6f8;
        }

        .login-container {
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }

        .login-card {
            width: 100%;
            max-width: 420px;
            background: #ffffff;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 5px 25px rgba(0, 0, 0, 0.08);
        }

        .login-card h1 {
            margin-top: 0;
            margin-bottom: 8px;
            text-align: center;
        }

        .login-card p.subtitle {
            text-align: center;
            color: #666;
            margin-bottom: 25px;
        }

        .form-group {
            margin-bottom: 18px;
        }

        .form-group label {
            display: block;
            margin-bottom: 7px;
            font-weight: 600;
        }

        .form-group input {
            width: 100%;
            box-sizing: border-box;
            padding: 12px;
            border: 1px solid #ccc;
            border-radius: 7px;
            font-size: 15px;
        }

        .form-group input:focus {
            outline: none;
            border-color: #333;
        }

        .login-btn {
            width: 100%;
            padding: 13px;
            border: none;
            border-radius: 7px;
            background: #222;
            color: white;
            font-size: 16px;
            cursor: pointer;
        }

        .login-btn:hover {
            background: #000;
        }

        .error {
            background: #ffe8e8;
            color: #b00020;
            padding: 12px;
            border-radius: 7px;
            margin-bottom: 18px;
        }

        .success {
            background: #e8f8ed;
            color: #187a35;
            padding: 12px;
            border-radius: 7px;
            margin-bottom: 18px;
        }

        .hint {
            margin-top: 18px;
            padding: 12px;
            background: #f1f3f5;
            border-radius: 7px;
            font-size: 13px;
            color: #555;
        }

        .links {
            text-align: center;
            margin-top: 20px;
        }

        .links a {
            color: #222;
            text-decoration: none;
        }

        .links a:hover {
            text-decoration: underline;
        }

    </style>

</head>

<body>

<div class="login-container">

    <div class="login-card">

        <h1>Driver Login</h1>

        <p class="subtitle">
            Login to your Taxi Management System account
        </p>

        <?php if ($error !== ""): ?>

            <div class="error">
                <?= e($error) ?>
            </div>

        <?php endif; ?>

        <?php if ($success !== ""): ?>

            <div class="success">
                <?= e($success) ?>
            </div>

        <?php endif; ?>

        <form method="POST" action="">

            <input
                type="hidden"
                name="csrf_token"
                value="<?= e($csrf_token) ?>"
            >

            <div class="form-group">

                <label for="phone">
                    Phone Number
                </label>

                <input
                    type="text"
                    id="phone"
                    name="phone"
                    placeholder="Enter your phone number"
                    value="<?= e($_POST["phone"] ?? "") ?>"
                    required
                    autocomplete="tel"
                >

            </div>

            <div class="form-group">

                <label for="password">
                    Password
                </label>

                <input
                    type="password"
                    id="password"
                    name="password"
                    placeholder="Enter your password"
                    required
                    autocomplete="current-password"
                >

            </div>

            <button
                type="submit"
                class="login-btn"
            >
                Login
            </button>

        </form>

        <div class="hint">

            <strong>First-time login:</strong><br>

            If your account was created before password login
            was added, your driving license number can be used
            as the temporary password.

            After successful login, it will automatically be
            converted to a secure password.

        </div>

        <div class="links">

            <a href="register.php">
                New Driver? Register Here
            </a>

            <br><br>

            <a href="../index2.php">
                Back to Home
            </a>

        </div>

    </div>

</div>

</body>

</html>