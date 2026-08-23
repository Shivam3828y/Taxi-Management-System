<?php

session_start();

if (!isset($_SESSION["admin_id"])) {
    header("Location: login.php");
    exit;
}

require_once "../config.php";

$message = "";
$error = "";


// Add Driver
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $name = trim($_POST["name"] ?? "");
    $phone = trim($_POST["phone"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $address = trim($_POST["address"] ?? "");
    $driving_license = trim($_POST["driving_license"] ?? "");

    if (
        $name === "" ||
        $phone === "" ||
        $driving_license === ""
    ) {

        $error = "Please fill all required fields.";

    } else {

        $sql = "INSERT INTO drivers 
                (name, phone, email, address, driving_license)
                VALUES (?, ?, ?, ?, ?)";

        $stmt = mysqli_prepare($conn, $sql);

        if (!$stmt) {

            $error = "Database error.";

        } else {

            mysqli_stmt_bind_param(
                $stmt,
                "sssss",
                $name,
                $phone,
                $email,
                $address,
                $driving_license
            );

            if (mysqli_stmt_execute($stmt)) {

                $message = "Driver added successfully.";

            } else {

                if (mysqli_errno($conn) == 1062) {

                    $error = "Phone number or driving license already exists.";

                } else {

                    $error = "Failed to add driver.";

                }
            }

            mysqli_stmt_close($stmt);
        }
    }
}


// Get Drivers
$sql = "SELECT * FROM drivers ORDER BY id DESC";

$result = mysqli_query($conn, $sql);

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Driver Management</title>

    <link rel="stylesheet" href="../css/style.css">

</head>

<body>

    <header>

        <h1>Driver Management</h1>

        <nav>

            <a href="dashboard.php">Dashboard</a>

            <a href="logout.php">Logout</a>

        </nav>

    </header>


    <main>

        <section>

            <h2>Add Driver</h2>


            <?php if ($message !== ""): ?>

                <p>
                    <?php echo htmlspecialchars($message); ?>
                </p>

            <?php endif; ?>


            <?php if ($error !== ""): ?>

                <p>
                    <?php echo htmlspecialchars($error); ?>
                </p>

            <?php endif; ?>


            <form method="POST">

                <div>

                    <label for="name">
                        Driver Name
                    </label>

                    <input
                        type="text"
                        id="name"
                        name="name"
                        required
                    >

                </div>

                <br>


                <div>

                    <label for="phone">
                        Phone Number
                    </label>

                    <input
                        type="text"
                        id="phone"
                        name="phone"
                        required
                    >

                </div>

                <br>


                <div>

                    <label for="email">
                        Email
                    </label>

                    <input
                        type="email"
                        id="email"
                        name="email"
                    >

                </div>

                <br>


                <div>

                    <label for="address">
                        Address
                    </label>

                    <textarea
                        id="address"
                        name="address"
                    ></textarea>

                </div>

                <br>


                <div>

                    <label for="driving_license">
                        Driving License Number
                    </label>

                    <input
                        type="text"
                        id="driving_license"
                        name="driving_license"
                        required
                    >

                </div>

                <br>


                <button type="submit">
                    Add Driver
                </button>

            </form>

        </section>


        <section>

            <h2>Drivers</h2>

            <?php if (mysqli_num_rows($result) > 0): ?>

                <table border="1" cellpadding="10">

                    <thead>

                        <tr>

                            <th>ID</th>
                            <th>Name</th>
                            <th>Phone</th>
                            <th>Email</th>
                            <th>License</th>
                            <th>Status</th>

                        </tr>

                    </thead>


                    <tbody>

                        <?php while ($driver = mysqli_fetch_assoc($result)): ?>

                            <tr>

                                <td>
                                    <?php echo $driver["id"]; ?>
                                </td>

                                <td>
                                    <?php echo htmlspecialchars($driver["name"]); ?>
                                </td>

                                <td>
                                    <?php echo htmlspecialchars($driver["phone"]); ?>
                                </td>

                                <td>
                                    <?php echo htmlspecialchars($driver["email"]); ?>
                                </td>

                                <td>
                                    <?php echo htmlspecialchars($driver["driving_license"]); ?>
                                </td>

                                <td>
                                    <?php echo htmlspecialchars($driver["status"]); ?>
                                </td>

                            </tr>

                        <?php endwhile; ?>

                    </tbody>

                </table>

            <?php else: ?>

                <p>No drivers found.</p>

            <?php endif; ?>

        </section>

    </main>


    <footer>

        <p>&copy; 2026 Taxi Management System</p>

    </footer>

</body>

</html>