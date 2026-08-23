<?php

session_start();

if (!isset($_SESSION["admin_id"])) {
    header("Location: login.php");
    exit;
}

require_once "../config.php";

$message = "";
$error = "";


// Add Taxi
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $brand = trim($_POST["brand"] ?? "");
    $model = trim($_POST["model"] ?? "");
    $registration_number = trim($_POST["registration_number"] ?? "");
    $rent = trim($_POST["rent"] ?? "");
    $status = $_POST["status"] ?? "Available";


    if (
        $brand === "" ||
        $model === "" ||
        $registration_number === "" ||
        $rent === ""
    ) {

        $error = "Please fill all required fields.";

    } else {

        $sql = "INSERT INTO taxis
                (brand, model, registration_number, rent, status)
                VALUES (?, ?, ?, ?, ?)";

        $stmt = mysqli_prepare($conn, $sql);

        if (!$stmt) {

            $error = "Database error: " . mysqli_error($conn);

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

            if (mysqli_stmt_execute($stmt)) {

                $message = "Taxi added successfully.";

            } else {

                if (mysqli_errno($conn) == 1062) {

                    $error = "Registration number already exists.";

                } else {

                    $error = "Failed to add taxi.";

                }
            }

            mysqli_stmt_close($stmt);
        }
    }
}


// Get all taxis
$sql = "SELECT * FROM taxis ORDER BY id DESC";

$result = mysqli_query($conn, $sql);

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Taxi Management</title>

    <link rel="stylesheet" href="../css/style.css">

</head>

<body>

    <header>

        <h1>Taxi Management</h1>

        <nav>

            <a href="dashboard.php">Dashboard</a>

            <a href="logout.php">Logout</a>

        </nav>

    </header>


    <main>

        <!-- Add Taxi -->

        <section>

            <h2>Add Taxi</h2>


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

                    <select id="status" name="status">

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


        <!-- Taxi List -->

        <section>

            <h2>Taxi List</h2>


            <?php if (mysqli_num_rows($result) > 0): ?>

                <table border="1" cellpadding="10">

                    <thead>

                        <tr>

                            <th>ID</th>
                            <th>Brand</th>
                            <th>Model</th>
                            <th>Registration</th>
                            <th>Rent</th>
                            <th>Status</th>

                        </tr>

                    </thead>


                    <tbody>

                        <?php while ($taxi = mysqli_fetch_assoc($result)): ?>

                            <tr>

                                <td>
                                    <?php echo $taxi["id"]; ?>
                                </td>

                                <td>
                                    <?php echo htmlspecialchars($taxi["brand"]); ?>
                                </td>

                                <td>
                                    <?php echo htmlspecialchars($taxi["model"]); ?>
                                </td>

                                <td>
                                    <?php echo htmlspecialchars($taxi["registration_number"]); ?>
                                </td>

                                <td>
                                    ₹<?php echo htmlspecialchars($taxi["rent"]); ?>
                                </td>

                                <td>
                                    <?php echo htmlspecialchars($taxi["status"]); ?>
                                </td>

                            </tr>

                        <?php endwhile; ?>

                    </tbody>

                </table>

            <?php else: ?>

                <p>No taxis found.</p>

            <?php endif; ?>

        </section>

    </main>


    <footer>

        <p>&copy; 2026 Taxi Management System</p>

    </footer>

</body>

</html>