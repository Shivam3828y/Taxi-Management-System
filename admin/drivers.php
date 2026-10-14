<?php

require_once "../config.php";
session_start();

if (!isset($_SESSION["admin_id"])) {
    header("Location: login.php");
    exit;
}

$message = "";
$error = "";


/*
=====================================================
VERIFY DRIVER
=====================================================
*/

if (
    $_SERVER["REQUEST_METHOD"] === "POST"
    && isset($_POST["verify_driver"])
) {

    csrf_verify();

    $driver_id = (int)($_POST["driver_id"] ?? 0);

    if ($driver_id <= 0) {

        $error = "Invalid driver.";

    } else {

        $sql = "
            UPDATE drivers
            SET status = 'Verified'
            WHERE id = ?
            AND status = 'Pending'
        ";

        $stmt = mysqli_prepare($conn, $sql);

        if (!$stmt) {

            $error = "Database error: " . mysqli_error($conn);

        } else {

            mysqli_stmt_bind_param(
                $stmt,
                "i",
                $driver_id
            );

            mysqli_stmt_execute($stmt);

            if (mysqli_stmt_affected_rows($stmt) > 0) {

                $message = "Driver verified successfully.";

            } else {

                $error =
                    "Driver could not be verified. It may already be verified or inactive.";
            }

            mysqli_stmt_close($stmt);
        }
    }
}


/*
=====================================================
DEACTIVATE DRIVER
=====================================================
*/

if (
    $_SERVER["REQUEST_METHOD"] === "POST"
    && isset($_POST["deactivate_driver"])
) {

    csrf_verify();

    $driver_id = (int)($_POST["driver_id"] ?? 0);

    if ($driver_id <= 0) {

        $error = "Invalid driver.";

    } else {

        /*
         * Do not allow deactivation while a taxi
         * is actively assigned to the driver.
         */
        $check_sql = "
            SELECT id
            FROM assignments
            WHERE driver_id = ?
            AND status = 'Active'
            LIMIT 1
        ";

        $check_stmt = mysqli_prepare(
            $conn,
            $check_sql
        );

        if (!$check_stmt) {

            $error = "Database error: " . mysqli_error($conn);

        } else {

            mysqli_stmt_bind_param(
                $check_stmt,
                "i",
                $driver_id
            );

            mysqli_stmt_execute($check_stmt);

            $check_result =
                mysqli_stmt_get_result($check_stmt);

            if (mysqli_num_rows($check_result) > 0) {

                $error =
                    "End the driver's active taxi assignment before deactivating the driver.";

            } else {

                $sql = "
                    UPDATE drivers
                    SET status = 'Inactive'
                    WHERE id = ?
                    AND status = 'Verified'
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
                        "i",
                        $driver_id
                    );

                    mysqli_stmt_execute($stmt);

                    if (
                        mysqli_stmt_affected_rows($stmt) > 0
                    ) {

                        $message =
                            "Driver deactivated successfully.";

                    } else {

                        $error =
                            "Driver could not be deactivated.";
                    }

                    mysqli_stmt_close($stmt);
                }
            }

            mysqli_stmt_close($check_stmt);
        }
    }
}


/*
=====================================================
ADD DRIVER
=====================================================
*/

if (
    $_SERVER["REQUEST_METHOD"] === "POST"
    && isset($_POST["add_driver"])
) {

    csrf_verify();

    $name =
        trim($_POST["name"] ?? "");

    $phone =
        trim($_POST["phone"] ?? "");

    $email =
        trim($_POST["email"] ?? "");

    $address =
        trim($_POST["address"] ?? "");

    $driving_license =
        trim($_POST["driving_license"] ?? "");


    if (
        $name === ""
        || $phone === ""
        || $address === ""
        || $driving_license === ""
    ) {

        $error =
            "Please fill all required fields.";

    } elseif (
        $email !== ""
        && !filter_var(
            $email,
            FILTER_VALIDATE_EMAIL
        )
    ) {

        $error =
            "Please enter a valid email address.";

    } else {

        $sql = "
            INSERT INTO drivers
            (
                name,
                phone,
                email,
                address,
                driving_license
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
                "sssss",
                $name,
                $phone,
                $email,
                $address,
                $driving_license
            );

            if (
                mysqli_stmt_execute($stmt)
            ) {

                $message =
                    "Driver added successfully.";

            } else {

                if (
                    mysqli_errno($conn) == 1062
                ) {

                    $error =
                        "Phone number or driving license already exists.";

                } else {

                    $error =
                        "Failed to add driver.";
                }
            }

            mysqli_stmt_close($stmt);
        }
    }
}


/*
=====================================================
ASSIGN TAXI TO VERIFIED DRIVER
=====================================================
*/

if (
    $_SERVER["REQUEST_METHOD"] === "POST"
    && isset($_POST["assign_taxi"])
) {

    csrf_verify();

    $driver_id =
        (int)($_POST["driver_id"] ?? 0);

    $taxi_id =
        (int)($_POST["taxi_id"] ?? 0);


    if (
        $driver_id <= 0
        || $taxi_id <= 0
    ) {

        $error =
            "Please select a valid driver and taxi.";

    } else {

        mysqli_begin_transaction($conn);

        try {

            /*
            =============================================
            CHECK VERIFIED DRIVER
            =============================================
            */

            $driver_sql = "
                SELECT id
                FROM drivers
                WHERE id = ?
                AND status = 'Verified'
                FOR UPDATE
            ";

            $driver_stmt = mysqli_prepare(
                $conn,
                $driver_sql
            );

            if (!$driver_stmt) {

                throw new Exception(
                    "Failed to check driver."
                );
            }

            mysqli_stmt_bind_param(
                $driver_stmt,
                "i",
                $driver_id
            );

            if (
                !mysqli_stmt_execute(
                    $driver_stmt
                )
            ) {

                mysqli_stmt_close(
                    $driver_stmt
                );

                throw new Exception(
                    "Failed to check driver."
                );
            }

            $driver_result =
                mysqli_stmt_get_result(
                    $driver_stmt
                );

            if (
                mysqli_num_rows(
                    $driver_result
                ) !== 1
            ) {

                mysqli_stmt_close(
                    $driver_stmt
                );

                throw new Exception(
                    "Only a verified driver can be assigned a taxi."
                );
            }

            mysqli_stmt_close(
                $driver_stmt
            );


            /*
            =============================================
            CHECK EXISTING DRIVER ASSIGNMENT
            =============================================
            */

            $existing_sql = "
                SELECT id
                FROM assignments
                WHERE driver_id = ?
                AND status = 'Active'
                LIMIT 1
                FOR UPDATE
            ";

            $existing_stmt = mysqli_prepare(
                $conn,
                $existing_sql
            );

            if (!$existing_stmt) {

                throw new Exception(
                    "Failed to check existing assignment."
                );
            }

            mysqli_stmt_bind_param(
                $existing_stmt,
                "i",
                $driver_id
            );

            if (
                !mysqli_stmt_execute(
                    $existing_stmt
                )
            ) {

                mysqli_stmt_close(
                    $existing_stmt
                );

                throw new Exception(
                    "Failed to check existing assignment."
                );
            }

            $existing_result =
                mysqli_stmt_get_result(
                    $existing_stmt
                );

            if (
                mysqli_num_rows(
                    $existing_result
                ) > 0
            ) {

                mysqli_stmt_close(
                    $existing_stmt
                );

                throw new Exception(
                    "This driver already has an active taxi."
                );
            }

            mysqli_stmt_close(
                $existing_stmt
            );


            /*
            =============================================
            CHECK AVAILABLE TAXI
            =============================================
            */

            $taxi_sql = "
                SELECT id, rent
                FROM taxis
                WHERE id = ?
                AND status = 'Available'
                FOR UPDATE
            ";

            $taxi_stmt = mysqli_prepare(
                $conn,
                $taxi_sql
            );

            if (!$taxi_stmt) {

                throw new Exception(
                    "Failed to check taxi."
                );
            }

            mysqli_stmt_bind_param(
                $taxi_stmt,
                "i",
                $taxi_id
            );

            if (
                !mysqli_stmt_execute(
                    $taxi_stmt
                )
            ) {

                mysqli_stmt_close(
                    $taxi_stmt
                );

                throw new Exception(
                    "Failed to check taxi."
                );
            }

            $taxi_result =
                mysqli_stmt_get_result(
                    $taxi_stmt
                );


            if (
                mysqli_num_rows(
                    $taxi_result
                ) !== 1
            ) {

                mysqli_stmt_close(
                    $taxi_stmt
                );

                throw new Exception(
                    "Selected taxi is not available."
                );
            }


            $taxi_data =
                mysqli_fetch_assoc(
                    $taxi_result
                );

            $taxi_rent =
                (float)$taxi_data["rent"];

            mysqli_stmt_close(
                $taxi_stmt
            );


            /*
            =============================================
            CREATE ASSIGNMENT
            =============================================
            */

            $insert_sql = "
                INSERT INTO assignments
                (
                    driver_id,
                    taxi_id
                )
                VALUES (?, ?)
            ";

            $insert_stmt = mysqli_prepare(
                $conn,
                $insert_sql
            );

            if (!$insert_stmt) {

                throw new Exception(
                    "Failed to create assignment."
                );
            }

            mysqli_stmt_bind_param(
                $insert_stmt,
                "ii",
                $driver_id,
                $taxi_id
            );

            if (
                !mysqli_stmt_execute(
                    $insert_stmt
                )
            ) {

                mysqli_stmt_close(
                    $insert_stmt
                );

                throw new Exception(
                    "Failed to create assignment."
                );
            }

            mysqli_stmt_close(
                $insert_stmt
            );


            /*
            =============================================
            UPDATE TAXI STATUS
            =============================================
            */

            $update_sql = "
                UPDATE taxis
                SET status = 'Assigned'
                WHERE id = ?
                AND status = 'Available'
            ";

            $update_stmt = mysqli_prepare(
                $conn,
                $update_sql
            );

            if (!$update_stmt) {

                throw new Exception(
                    "Failed to update taxi status."
                );
            }

            mysqli_stmt_bind_param(
                $update_stmt,
                "i",
                $taxi_id
            );

            if (
                !mysqli_stmt_execute(
                    $update_stmt
                )
            ) {

                mysqli_stmt_close(
                    $update_stmt
                );

                throw new Exception(
                    "Failed to update taxi status."
                );
            }


            if (
                mysqli_stmt_affected_rows(
                    $update_stmt
                ) !== 1
            ) {

                mysqli_stmt_close(
                    $update_stmt
                );

                throw new Exception(
                    "Taxi is no longer available."
                );
            }

            mysqli_stmt_close(
                $update_stmt
            );


            /*
            =============================================
            COMMIT
            =============================================
            */

            mysqli_commit($conn);

            $message =
                "Taxi assigned successfully. Rent: ₹"
                . number_format(
                    $taxi_rent,
                    2
                )
                . " / day";


        } catch (Throwable $e) {

            mysqli_rollback($conn);

            $error =
                $e->getMessage();
        }
    }
}


/*
=====================================================
END DRIVER'S ACTIVE TAXI ASSIGNMENT
=====================================================
*/

if (
    $_SERVER["REQUEST_METHOD"] === "POST"
    && isset($_POST["end_driver_assignment"])
) {

    csrf_verify();

    $assignment_id =
        (int)(
            $_POST["assignment_id"] ?? 0
        );


    if ($assignment_id <= 0) {

        $error =
            "Invalid assignment.";

    } else {

        mysqli_begin_transaction($conn);

        try {

            /*
            =============================================
            GET ACTIVE ASSIGNMENT
            =============================================
            */

            $assignment_sql = "
                SELECT
                    id,
                    taxi_id
                FROM assignments
                WHERE id = ?
                AND status = 'Active'
                FOR UPDATE
            ";

            $assignment_stmt =
                mysqli_prepare(
                    $conn,
                    $assignment_sql
                );

            if (!$assignment_stmt) {

                throw new Exception(
                    "Failed to find assignment."
                );
            }

            mysqli_stmt_bind_param(
                $assignment_stmt,
                "i",
                $assignment_id
            );

            if (
                !mysqli_stmt_execute(
                    $assignment_stmt
                )
            ) {

                mysqli_stmt_close(
                    $assignment_stmt
                );

                throw new Exception(
                    "Failed to find assignment."
                );
            }

            $assignment_result =
                mysqli_stmt_get_result(
                    $assignment_stmt
                );


            if (
                mysqli_num_rows(
                    $assignment_result
                ) !== 1
            ) {

                mysqli_stmt_close(
                    $assignment_stmt
                );

                throw new Exception(
                    "Active assignment not found."
                );
            }


            $assignment =
                mysqli_fetch_assoc(
                    $assignment_result
                );

            mysqli_stmt_close(
                $assignment_stmt
            );


            $taxi_id =
                (int)$assignment["taxi_id"];


            /*
            =============================================
            END ASSIGNMENT
            =============================================
            */

            $end_sql = "
                UPDATE assignments
                SET status = 'Ended'
                WHERE id = ?
                AND status = 'Active'
            ";

            $end_stmt =
                mysqli_prepare(
                    $conn,
                    $end_sql
                );

            if (!$end_stmt) {

                throw new Exception(
                    "Failed to end assignment."
                );
            }

            mysqli_stmt_bind_param(
                $end_stmt,
                "i",
                $assignment_id
            );

            if (
                !mysqli_stmt_execute(
                    $end_stmt
                )
            ) {

                mysqli_stmt_close(
                    $end_stmt
                );

                throw new Exception(
                    "Failed to end assignment."
                );
            }

            mysqli_stmt_close(
                $end_stmt
            );


            /*
            =============================================
            MAKE TAXI AVAILABLE
            =============================================
            */

            $taxi_update_sql = "
                UPDATE taxis
                SET status = 'Available'
                WHERE id = ?
                AND status = 'Assigned'
            ";

            $taxi_update_stmt =
                mysqli_prepare(
                    $conn,
                    $taxi_update_sql
                );

            if (!$taxi_update_stmt) {

                throw new Exception(
                    "Failed to update taxi status."
                );
            }

            mysqli_stmt_bind_param(
                $taxi_update_stmt,
                "i",
                $taxi_id
            );

            if (
                !mysqli_stmt_execute(
                    $taxi_update_stmt
                )
            ) {

                mysqli_stmt_close(
                    $taxi_update_stmt
                );

                throw new Exception(
                    "Failed to make taxi available."
                );
            }

            mysqli_stmt_close(
                $taxi_update_stmt
            );


            mysqli_commit($conn);

            $message =
                "Taxi assignment ended successfully. Taxi is now available.";

        } catch (Throwable $e) {

            mysqli_rollback($conn);

            $error =
                $e->getMessage();
        }
    }
}


/*
=====================================================
GET ALL DRIVERS
=====================================================
*/

$sql = "
    SELECT
        d.id,
        d.name,
        d.phone,
        d.email,
        d.address,
        d.driving_license,
        d.document_type,
        d.document_number,
        d.status,
        d.created_at,

        a.id AS assignment_id,
        a.assigned_at,

        t.brand AS taxi_brand,
        t.model AS taxi_model,
        t.registration_number AS taxi_registration,
        t.rent AS taxi_rent

    FROM drivers d

    LEFT JOIN assignments a
        ON d.id = a.driver_id
        AND a.status = 'Active'

    LEFT JOIN taxis t
        ON a.taxi_id = t.id

    ORDER BY d.id DESC
";

$result = mysqli_query(
    $conn,
    $sql
);


/*
=====================================================
GET AVAILABLE TAXIS
=====================================================
*/

$available_taxis_sql = "
    SELECT
        id,
        brand,
        model,
        registration_number,
        rent
    FROM taxis
    WHERE status = 'Available'
    ORDER BY id DESC
";

$available_taxis_result =
    mysqli_query(
        $conn,
        $available_taxis_sql
    );

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Driver Management</title>

    <link
        rel="stylesheet"
        href="../css/style.css"
    >

    <style>

        .success-message {
            color: green;
            font-weight: bold;
        }

        .error-message {
            color: red;
            font-weight: bold;
        }

        .status-pending {
            color: #b36b00;
            font-weight: bold;
        }

        .status-verified {
            color: green;
            font-weight: bold;
        }

        .status-inactive {
            color: #777;
            font-weight: bold;
        }

        .action-form {
            display: inline;
        }

        .action-form button {
            margin: 2px;
        }

        .assignment-box {
            margin-top: 8px;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 5px;
        }

        .assignment-box select {
            max-width: 220px;
            margin-bottom: 5px;
        }

        .assigned-taxi {
            color: #333;
            font-weight: bold;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th,
        td {
            padding: 10px;
            vertical-align: top;
        }

    </style>

</head>


<body>


<!-- ================================================= -->
<!-- HEADER -->
<!-- ================================================= -->

<header>

    <h1>
        Driver Management
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

        <a href="payments.php">
            Payments
        </a>

        <a href="../index2.php">
            Public Portal
        </a>

        <a href="logout.php">
            Logout
        </a>

    </nav>

</header>


<main>


<!-- ================================================= -->
<!-- MESSAGES -->
<!-- ================================================= -->

<?php if ($message !== ""): ?>

    <p class="success-message">

        <?php

        echo htmlspecialchars(
            $message,
            ENT_QUOTES,
            "UTF-8"
        );

        ?>

    </p>

<?php endif; ?>


<?php if ($error !== ""): ?>

    <p class="error-message">

        <?php

        echo htmlspecialchars(
            $error,
            ENT_QUOTES,
            "UTF-8"
        );

        ?>

    </p>

<?php endif; ?>


<!-- ================================================= -->
<!-- ADD DRIVER -->
<!-- ================================================= -->

<section>

    <h2>
        Add Driver
    </h2>

    <form method="POST">

        <?php csrf_field(); ?>

        <input
            type="hidden"
            name="add_driver"
            value="1"
        >


        <div>

            <label for="name">
                Driver Name
            </label>

            <input
                type="text"
                id="name"
                name="name"
                maxlength="100"
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
                maxlength="20"
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
                maxlength="100"
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
                maxlength="255"
                required
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
                maxlength="50"
                required
            >

        </div>


        <br>


        <button type="submit">
            Add Driver
        </button>

    </form>

</section>


<!-- ================================================= -->
<!-- DRIVER LIST -->
<!-- ================================================= -->

<section>

    <h2>
        Drivers
    </h2>


    <?php if (
        $result
        && mysqli_num_rows($result) > 0
    ): ?>


        <table
            border="1"
            cellpadding="10"
        >

            <thead>

                <tr>

                    <th>
                        ID
                    </th>

                    <th>
                        Name
                    </th>

                    <th>
                        Phone
                    </th>

                    <th>
                        Email
                    </th>

                    <th>
                        License
                    </th>

                    <th>
                        Document
                    </th>

                    <th>
                        Status
                    </th>

                    <th>
                        Taxi
                    </th>

                    <th>
                        Created
                    </th>

                    <th>
                        Action
                    </th>

                </tr>

            </thead>


            <tbody>


                <?php while (
                    $driver =
                    mysqli_fetch_assoc($result)
                ): ?>


                    <?php

                    $status =
                        $driver["status"];

                    $status_class = "";

                    if ($status === "Pending") {

                        $status_class =
                            "status-pending";

                    } elseif ($status === "Verified") {

                        $status_class =
                            "status-verified";

                    } elseif ($status === "Inactive") {

                        $status_class =
                            "status-inactive";
                    }

                    ?>


                    <tr>


                        <td>

                            <?php
                            echo (int)$driver["id"];
                            ?>

                        </td>


                        <td>

                            <?php

                            echo htmlspecialchars(
                                $driver["name"],
                                ENT_QUOTES,
                                "UTF-8"
                            );

                            ?>

                        </td>


                        <td>

                            <?php

                            echo htmlspecialchars(
                                $driver["phone"],
                                ENT_QUOTES,
                                "UTF-8"
                            );

                            ?>

                        </td>


                        <td>

                            <?php

                            echo htmlspecialchars(
                                $driver["email"] ?? "",
                                ENT_QUOTES,
                                "UTF-8"
                            );

                            ?>

                        </td>


                        <td>

                            <?php

                            echo htmlspecialchars(
                                $driver[
                                    "driving_license"
                                ],
                                ENT_QUOTES,
                                "UTF-8"
                            );

                            ?>

                        </td>


                        <td>

                            <?php if (
                                !empty(
                                    $driver["document_type"]
                                )
                                ||
                                !empty(
                                    $driver["document_number"]
                                )
                            ): ?>

                                <?php

                                echo htmlspecialchars(
                                    $driver[
                                        "document_type"
                                    ] ?? "",
                                    ENT_QUOTES,
                                    "UTF-8"
                                );

                                ?>

                                <?php if (
                                    !empty(
                                        $driver[
                                            "document_number"
                                        ]
                                    )
                                ): ?>

                                    <br>

                                    <?php

                                    echo htmlspecialchars(
                                        $driver[
                                            "document_number"
                                        ],
                                        ENT_QUOTES,
                                        "UTF-8"
                                    );

                                    ?>

                                <?php endif; ?>

                            <?php else: ?>

                                Not provided

                            <?php endif; ?>

                        </td>


                        <!-- STATUS -->

                        <td>

                            <span
                                class="<?php
                                echo $status_class;
                                ?>"
                            >

                                <?php

                                echo htmlspecialchars(
                                    $status,
                                    ENT_QUOTES,
                                    "UTF-8"
                                );

                                ?>

                            </span>

                        </td>


                        <!-- TAXI -->

                        <td>


                            <?php if (
                                !empty(
                                    $driver["assignment_id"]
                                )
                            ): ?>


                                <div class="assigned-taxi">

                                    <?php

                                    echo htmlspecialchars(
                                        $driver[
                                            "taxi_brand"
                                        ]
                                        . " "
                                        . $driver[
                                            "taxi_model"
                                        ],
                                        ENT_QUOTES,
                                        "UTF-8"
                                    );

                                    ?>

                                    <br>

                                    <?php

                                    echo htmlspecialchars(
                                        $driver[
                                            "taxi_registration"
                                        ],
                                        ENT_QUOTES,
                                        "UTF-8"
                                    );

                                    ?>

                                    <br>

                                    ₹<?php

                                    echo number_format(
                                        (float)$driver[
                                            "taxi_rent"
                                        ],
                                        2
                                    );

                                    ?>

                                    / day

                                </div>


                                <form
                                    method="POST"
                                    class="action-form"
                                >

                                    <?php csrf_field(); ?>

                                    <input
                                        type="hidden"
                                        name="end_driver_assignment"
                                        value="1"
                                    >

                                    <input
                                        type="hidden"
                                        name="assignment_id"
                                        value="<?php
                                        echo (int)$driver[
                                            "assignment_id"
                                        ];
                                        ?>"
                                    >

                                    <button
                                        type="submit"
                                        onclick="return confirm('End this taxi assignment and make the taxi available?');"
                                    >
                                        End Assignment
                                    </button>

                                </form>


                            <?php elseif (
                                $status === "Verified"
                            ): ?>


                                <div class="assignment-box">

                                    <?php if (
                                        $available_taxis_result
                                        &&
                                        mysqli_num_rows(
                                            $available_taxis_result
                                        ) > 0
                                    ): ?>


                                        <form method="POST">

                                            <?php csrf_field(); ?>

                                            <input
                                                type="hidden"
                                                name="assign_taxi"
                                                value="1"
                                            >

                                            <input
                                                type="hidden"
                                                name="driver_id"
                                                value="<?php
                                                echo (int)$driver[
                                                    "id"
                                                ];
                                                ?>"
                                            >


                                            <select
                                                name="taxi_id"
                                                required
                                            >

                                                <option value="">
                                                    Select Available Taxi
                                                </option>


                                                <?php

                                                /*
                                                 * Reset the available
                                                 * taxi result pointer for
                                                 * every driver.
                                                 */
                                                mysqli_data_seek(
                                                    $available_taxis_result,
                                                    0
                                                );

                                                ?>


                                                <?php while (
                                                    $taxi =
                                                    mysqli_fetch_assoc(
                                                        $available_taxis_result
                                                    )
                                                ): ?>

                                                    <option
                                                        value="<?php
                                                        echo (int)$taxi[
                                                            "id"
                                                        ];
                                                        ?>"
                                                    >

                                                        <?php

                                                        echo htmlspecialchars(
                                                            $taxi[
                                                                "brand"
                                                            ]
                                                            . " "
                                                            . $taxi[
                                                                "model"
                                                            ]
                                                            . " - "
                                                            . $taxi[
                                                                "registration_number"
                                                            ],
                                                            ENT_QUOTES,
                                                            "UTF-8"
                                                        );

                                                        ?>

                                                        -
                                                        ₹<?php

                                                        echo number_format(
                                                            (float)$taxi[
                                                                "rent"
                                                            ],
                                                            2
                                                        );

                                                        ?>/day

                                                    </option>

                                                <?php endwhile; ?>

                                            </select>


                                            <br>


                                            <button
                                                type="submit"
                                            >
                                                Assign Taxi
                                            </button>

                                        </form>


                                    <?php else: ?>


                                        <span>
                                            No available taxi
                                        </span>


                                    <?php endif; ?>

                                </div>


                            <?php elseif (
                                $status === "Pending"
                            ): ?>

                                <span>
                                    Verify driver first
                                </span>


                            <?php else: ?>

                                <span>
                                    No taxi
                                </span>

                            <?php endif; ?>


                        </td>


                        <!-- CREATED -->

                        <td>

                            <?php

                            echo htmlspecialchars(
                                $driver[
                                    "created_at"
                                ],
                                ENT_QUOTES,
                                "UTF-8"
                            );

                            ?>

                        </td>


                        <!-- ACTION -->

                        <td>


                            <?php if (
                                $status === "Pending"
                            ): ?>


                                <form
                                    method="POST"
                                    class="action-form"
                                >

                                    <?php csrf_field(); ?>

                                    <input
                                        type="hidden"
                                        name="verify_driver"
                                        value="1"
                                    >

                                    <input
                                        type="hidden"
                                        name="driver_id"
                                        value="<?php
                                        echo (int)$driver[
                                            "id"
                                        ];
                                        ?>"
                                    >

                                    <button
                                        type="submit"
                                    >
                                        Verify
                                    </button>

                                </form>


                            <?php elseif (
                                $status === "Verified"
                            ): ?>


                                <form
                                    method="POST"
                                    class="action-form"
                                >

                                    <?php csrf_field(); ?>

                                    <input
                                        type="hidden"
                                        name="deactivate_driver"
                                        value="1"
                                    >

                                    <input
                                        type="hidden"
                                        name="driver_id"
                                        value="<?php
                                        echo (int)$driver[
                                            "id"
                                        ];
                                        ?>"
                                    >

                                    <button
                                        type="submit"
                                        onclick="return confirm('Deactivate this driver?');"
                                    >
                                        Deactivate
                                    </button>

                                </form>


                            <?php else: ?>

                                Inactive

                            <?php endif; ?>


                        </td>


                    </tr>


                <?php endwhile; ?>


            </tbody>

        </table>


    <?php else: ?>


        <p>
            No drivers found.
        </p>


    <?php endif; ?>


</section>


</main>


<footer>

    <p>
        &copy; 2026 Taxi Management System
    </p>

</footer>


</body>

</html>