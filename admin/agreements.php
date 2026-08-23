<?php

session_start();

if (!isset($_SESSION["admin_id"])) {
    header("Location: login.php");
    exit;
}

require_once "../config.php";

$message = "";
$error = "";


// =====================================================
// AGREEMENT TERMS
// ENGLISH + MARATHI
// =====================================================

$terms = [

    [
        "en" => "Driver must maintain the assigned taxi properly.",
        "mr" => "चालकाने त्याला दिलेली टॅक्सी योग्य प्रकारे आणि काळजीपूर्वक राखणे आवश्यक आहे."
    ],

    [
        "en" => "Driver is responsible for traffic fines caused by the driver's negligence or violation.",
        "mr" => "चालकाच्या निष्काळजीपणामुळे किंवा वाहतूक नियमांच्या उल्लंघनामुळे झालेल्या दंडाची जबाबदारी चालकाची असेल."
    ],

    [
        "en" => "Driver must not transfer or hand over the taxi to another person without permission.",
        "mr" => "परवानगीशिवाय चालकाने टॅक्सी दुसऱ्या कोणत्याही व्यक्तीला चालविण्यास देऊ नये किंवा हस्तांतरित करू नये."
    ],

    [
        "en" => "Driver must pay the agreed rent on time.",
        "mr" => "चालकाने ठरलेले भाडे वेळेवर भरणे आवश्यक आहे."
    ],

    [
        "en" => "Driver must return the taxi in good condition when the agreement ends.",
        "mr" => "कराराची मुदत संपल्यानंतर चालकाने टॅक्सी चांगल्या स्थितीत परत करणे आवश्यक आहे."
    ],

    [
        "en" => "Driver must follow all applicable traffic rules and regulations.",
        "mr" => "चालकाने लागू असलेले सर्व वाहतूक नियम आणि कायदे पाळणे आवश्यक आहे."
    ],

    [
        "en" => "Any damage caused due to negligence may be recoverable from the driver.",
        "mr" => "चालकाच्या निष्काळजीपणामुळे टॅक्सीचे नुकसान झाल्यास त्या नुकसानीची भरपाई चालकाकडून वसूल केली जाऊ शकते."
    ],

    [
        "en" => "This agreement is valid only for the specified start date and end date.",
        "mr" => "हा करार फक्त नमूद केलेल्या प्रारंभ दिनांकापासून समाप्ती दिनांकापर्यंत वैध राहील."
    ]

];


// =====================================================
// CREATE AGREEMENT
// =====================================================

if (
    $_SERVER["REQUEST_METHOD"] === "POST"
    && isset($_POST["create_agreement"])
) {

    $assignment_id = (int) (
        $_POST["assignment_id"] ?? 0
    );

    $start_date = trim(
        $_POST["start_date"] ?? ""
    );

    $end_date = trim(
        $_POST["end_date"] ?? ""
    );

    $admin_confirm =
        isset($_POST["admin_confirm"]);


    // =================================================
    // BASIC VALIDATION
    // =================================================

    if (
        $assignment_id <= 0 ||
        $start_date === "" ||
        $end_date === ""
    ) {

        $error =
            "Please fill all required fields.";

    } elseif (!$admin_confirm) {

        $error =
            "Please confirm the agreement before submitting.";

    } elseif ($end_date < $start_date) {

        $error =
            "End date cannot be before start date.";

    } else {


        // =================================================
        // VALIDATE DATE FORMAT
        // =================================================

        $start_object =
            DateTime::createFromFormat(
                "Y-m-d",
                $start_date
            );

        $end_object =
            DateTime::createFromFormat(
                "Y-m-d",
                $end_date
            );


        if (
            !$start_object ||
            !$end_object ||
            $start_object->format("Y-m-d") !== $start_date ||
            $end_object->format("Y-m-d") !== $end_date
        ) {

            $error =
                "Invalid agreement date.";

        } else {


            // =================================================
            // GET ACTIVE ASSIGNMENT + TAXI RENT
            // =================================================

            $check_sql = "

                SELECT

                    assignments.id AS assignment_id,

                    assignments.driver_id,

                    assignments.taxi_id,

                    drivers.name AS driver_name,

                    taxis.brand,

                    taxis.model,

                    taxis.registration_number,

                    taxis.rent,

                    taxis.status AS taxi_status

                FROM assignments

                INNER JOIN drivers
                    ON assignments.driver_id = drivers.id

                INNER JOIN taxis
                    ON assignments.taxi_id = taxis.id

                WHERE assignments.id = ?

                AND assignments.status = 'Active'

                AND drivers.status = 'Verified'

                LIMIT 1

            ";


            $check_stmt =
                mysqli_prepare(
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
                    "i",
                    $assignment_id
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
                    ) !== 1
                ) {

                    $error =
                        "Invalid, inactive or unverified assignment.";

                } else {

                    $assignment_data =
                        mysqli_fetch_assoc(
                            $check_result
                        );


                    // =================================================
                    // GET CURRENT TAXI RENT
                    // =================================================

                    $rent =
                        (float) $assignment_data["rent"];


                    if ($rent < 0) {

                        $error =
                            "Invalid taxi rent.";

                    } else {


                        // =================================================
                        // CHECK EXISTING ACTIVE AGREEMENT
                        // =================================================

                        $existing_sql = "

                            SELECT id

                            FROM agreements

                            WHERE assignment_id = ?

                            AND status = 'Active'

                            LIMIT 1

                        ";


                        $existing_stmt =
                            mysqli_prepare(
                                $conn,
                                $existing_sql
                            );


                        if (!$existing_stmt) {

                            $error =
                                "Database error: "
                                . mysqli_error($conn);

                        } else {

                            mysqli_stmt_bind_param(
                                $existing_stmt,
                                "i",
                                $assignment_id
                            );

                            mysqli_stmt_execute(
                                $existing_stmt
                            );

                            $existing_result =
                                mysqli_stmt_get_result(
                                    $existing_stmt
                                );


                            if (
                                mysqli_num_rows(
                                    $existing_result
                                ) > 0
                            ) {

                                $error =
                                    "This assignment already has an active agreement.";

                            } else {


                                // =================================================
                                // BUILD TERMS TEXT
                                // =================================================

                                $terms_text = "";


                                foreach (
                                    $terms as $index => $term
                                ) {

                                    $number =
                                        $index + 1;

                                    $terms_text .=
                                        $number
                                        . ". "
                                        . $term["en"]
                                        . "\n";

                                    $terms_text .=
                                        $term["mr"]
                                        . "\n\n";
                                }


                                // =================================================
                                // INSERT AGREEMENT
                                // =================================================

                                $insert_sql = "

                                    INSERT INTO agreements
                                    (
                                        assignment_id,
                                        start_date,
                                        end_date,
                                        rent,
                                        terms,
                                        status,
                                        accepted
                                    )

                                    VALUES
                                    (
                                        ?,
                                        ?,
                                        ?,
                                        ?,
                                        ?,
                                        'Active',
                                        0
                                    )

                                ";


                                $insert_stmt =
                                    mysqli_prepare(
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
                                        "issds",
                                        $assignment_id,
                                        $start_date,
                                        $end_date,
                                        $rent,
                                        $terms_text
                                    );


                                    if (
                                        mysqli_stmt_execute(
                                            $insert_stmt
                                        )
                                    ) {

                                        $message =
                                            "Agreement created successfully for "
                                            . $assignment_data["driver_name"]
                                            . ".";

                                    } else {

                                        $error =
                                            "Failed to create agreement: "
                                            . mysqli_error($conn);
                                    }


                                    mysqli_stmt_close(
                                        $insert_stmt
                                    );
                                }
                            }


                            mysqli_stmt_close(
                                $existing_stmt
                            );
                        }
                    }
                }


                mysqli_stmt_close(
                    $check_stmt
                );
            }
        }
    }
}


// =====================================================
// GET ACTIVE ASSIGNMENTS WITHOUT ACTIVE AGREEMENT
// =====================================================

$assignment_sql = "

    SELECT

        assignments.id,

        drivers.name AS driver_name,

        taxis.brand,

        taxis.model,

        taxis.registration_number,

        taxis.rent

    FROM assignments

    INNER JOIN drivers
        ON assignments.driver_id = drivers.id

    INNER JOIN taxis
        ON assignments.taxi_id = taxis.id

    LEFT JOIN agreements
        ON agreements.assignment_id = assignments.id
        AND agreements.status = 'Active'

    WHERE assignments.status = 'Active'

    AND drivers.status = 'Verified'

    AND agreements.id IS NULL

    ORDER BY assignments.id DESC

";


$assignment_result =
    mysqli_query(
        $conn,
        $assignment_sql
    );


// =====================================================
// GET ALL AGREEMENTS
// =====================================================

$agreement_sql = "

    SELECT

        agreements.id,

        agreements.assignment_id,

        agreements.start_date,

        agreements.end_date,

        agreements.rent,

        agreements.status,

        agreements.accepted,

        agreements.accepted_at,

        drivers.name AS driver_name,

        taxis.brand,

        taxis.model,

        taxis.registration_number,

        assignments.status AS assignment_status

    FROM agreements

    INNER JOIN assignments
        ON agreements.assignment_id = assignments.id

    INNER JOIN drivers
        ON assignments.driver_id = drivers.id

    INNER JOIN taxis
        ON assignments.taxi_id = taxis.id

    ORDER BY agreements.id DESC

";


$agreement_result =
    mysqli_query(
        $conn,
        $agreement_sql
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

    <title>
        Agreement Management
    </title>


    <link
        rel="stylesheet"
        href="../css/style.css"
    >


    <style>

        .agreement-box {

            max-height: 400px;

            overflow-y: auto;

            border: 1px solid #ccc;

            border-radius: 8px;

            background: #fff;

        }


        .agreement-header {

            display: grid;

            grid-template-columns: 1fr 1fr;

            position: sticky;

            top: 0;

            z-index: 2;

            background: #f5f5f5;

            border-bottom: 1px solid #ccc;

            font-weight: bold;

        }


        .agreement-header div {

            padding: 10px;

        }


        .agreement-header div:first-child {

            border-right: 1px solid #ccc;

        }


        .agreement-row {

            display: grid;

            grid-template-columns: 1fr 1fr;

            border-bottom: 1px solid #ddd;

        }


        .agreement-column {

            padding: 10px;

            line-height: 1.5;

        }


        .agreement-column:first-child {

            border-right: 1px solid #ddd;

        }


        .confirmation-box {

            margin-top: 15px;

            padding: 10px;

            border: 1px solid #ddd;

            border-radius: 6px;

            background: #f8f8f8;

        }


        .confirmation-box label {

            cursor: pointer;

        }


        .success-message {

            color: #15803d;

            background: #f0fdf4;

            border: 1px solid #86efac;

            padding: 10px;

            border-radius: 6px;

            margin-bottom: 15px;

        }


        .error-message {

            color: #b91c1c;

            background: #fef2f2;

            border: 1px solid #fca5a5;

            padding: 10px;

            border-radius: 6px;

            margin-bottom: 15px;

        }


        .pending {

            color: #b45309;

            font-weight: bold;

        }


        .accepted {

            color: #15803d;

            font-weight: bold;

        }


        .fixed-rent {

            display: inline-block;

            padding: 8px 12px;

            border: 1px solid #ccc;

            border-radius: 6px;

            background: #f5f5f5;

            font-weight: bold;

        }


        .empty-message {

            padding: 15px;

            background: #f8fafc;

            border: 1px solid #e2e8f0;

            border-radius: 6px;

        }


        @media (max-width: 700px) {

            .agreement-header,
            .agreement-row {

                grid-template-columns: 1fr;

            }


            .agreement-header div:first-child,
            .agreement-column:first-child {

                border-right: none;

                border-bottom: 1px solid #ddd;

            }

        }

    </style>

</head>


<body>


<header>

    <h1>
        Agreement Management
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
<!-- CREATE AGREEMENT -->
<!-- ================================================= -->

<section>

    <h2>
        Create Agreement
    </h2>


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


    <?php if (
        $assignment_result
        && mysqli_num_rows($assignment_result) > 0
    ): ?>


        <form method="POST">

            <input
                type="hidden"
                name="create_agreement"
                value="1"
            >


            <!-- ASSIGNMENT -->

            <div>

                <label for="assignment_id">
                    Driver / Taxi Assignment
                </label>


                <select
                    id="assignment_id"
                    name="assignment_id"
                    required
                >

                    <option value="">
                        Select Assignment
                    </option>


                    <?php while (
                        $assignment =
                        mysqli_fetch_assoc(
                            $assignment_result
                        )
                    ): ?>

                        <option
                            value="<?php
                                echo (int)$assignment["id"];
                            ?>"
                            data-rent="<?php
                                echo htmlspecialchars(
                                    $assignment["rent"]
                                );
                            ?>"
                        >

                            <?php

                            echo htmlspecialchars(
                                $assignment["driver_name"]
                                . " - "
                                . $assignment["brand"]
                                . " "
                                . $assignment["model"]
                                . " - "
                                . $assignment[
                                    "registration_number"
                                ]
                            );

                            ?>

                        </option>

                    <?php endwhile; ?>

                </select>

            </div>


            <br>


            <!-- FIXED RENT -->

            <div>

                <label>
                    Fixed Taxi Rent
                </label>


                <div
                    id="rent-display"
                    class="fixed-rent"
                >
                    Select a taxi assignment
                </div>

            </div>


            <br>


            <!-- START DATE -->

            <div>

                <label for="start_date">
                    Start Date
                </label>


                <input
                    type="date"
                    id="start_date"
                    name="start_date"
                    required
                >

            </div>


            <br>


            <!-- END DATE -->

            <div>

                <label for="end_date">
                    End Date
                </label>


                <input
                    type="date"
                    id="end_date"
                    name="end_date"
                    required
                >

            </div>


            <br>


            <!-- TERMS -->

            <h3>
                Agreement Terms
            </h3>


            <div class="agreement-box">


                <div class="agreement-header">

                    <div>
                        English
                    </div>

                    <div>
                        Marathi
                    </div>

                </div>


                <?php foreach (
                    $terms as $index => $term
                ): ?>

                    <div class="agreement-row">


                        <div class="agreement-column">

                            <strong>
                                <?php
                                echo ($index + 1) . ". ";
                                ?>
                            </strong>

                            <?php
                            echo htmlspecialchars(
                                $term["en"]
                            );
                            ?>

                        </div>


                        <div class="agreement-column">

                            <strong>
                                <?php
                                echo ($index + 1) . ". ";
                                ?>
                            </strong>

                            <?php
                            echo htmlspecialchars(
                                $term["mr"]
                            );
                            ?>

                        </div>


                    </div>

                <?php endforeach; ?>


            </div>


            <!-- ADMIN CONFIRMATION -->

            <div class="confirmation-box">

                <label>

                    <input
                        type="checkbox"
                        name="admin_confirm"
                        value="1"
                        required
                    >

                    I confirm that the agreement details
                    and terms are correct.

                </label>

            </div>


            <br>


            <button type="submit">
                Create Agreement
            </button>


        </form>


    <?php else: ?>

        <div class="empty-message">

            <strong>
                No assignments available for agreement.
            </strong>

            <p>
                An agreement can only be created for a
                verified driver with an active taxi
                assignment that does not already have
                an active agreement.
            </p>

        </div>

    <?php endif; ?>

</section>


<!-- ================================================= -->
<!-- AGREEMENT LIST -->
<!-- ================================================= -->

<section>

    <h2>
        Agreements
    </h2>


    <?php if (
        $agreement_result
        && mysqli_num_rows($agreement_result) > 0
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
                        Driver
                    </th>

                    <th>
                        Taxi
                    </th>

                    <th>
                        Registration
                    </th>

                    <th>
                        Start Date
                    </th>

                    <th>
                        End Date
                    </th>

                    <th>
                        Rent
                    </th>

                    <th>
                        Agreement Status
                    </th>

                    <th>
                        Acceptance
                    </th>

                    <th>
                        Accepted At
                    </th>

                </tr>

            </thead>


            <tbody>


                <?php while (
                    $agreement =
                    mysqli_fetch_assoc(
                        $agreement_result
                    )
                ): ?>

                    <tr>

                        <td>
                            <?php
                            echo (int)$agreement["id"];
                            ?>
                        </td>


                        <td>

                            <?php
                            echo htmlspecialchars(
                                $agreement["driver_name"]
                            );
                            ?>

                        </td>


                        <td>

                            <?php

                            echo htmlspecialchars(
                                $agreement["brand"]
                                . " "
                                . $agreement["model"]
                            );

                            ?>

                        </td>


                        <td>

                            <?php

                            echo htmlspecialchars(
                                $agreement[
                                    "registration_number"
                                ]
                            );

                            ?>

                        </td>


                        <td>

                            <?php

                            echo htmlspecialchars(
                                $agreement["start_date"]
                            );

                            ?>

                        </td>


                        <td>

                            <?php

                            echo htmlspecialchars(
                                $agreement["end_date"]
                            );

                            ?>

                        </td>


                        <td>

                            ₹<?php

                            echo number_format(
                                (float)
                                $agreement["rent"],
                                2
                            );

                            ?>

                            / day

                        </td>


                        <td>

                            <?php

                            echo htmlspecialchars(
                                $agreement["status"]
                            );

                            ?>

                        </td>


                        <td>

                            <?php if (
                                (int)$agreement["accepted"] === 1
                            ): ?>

                                <span class="accepted">
                                    Accepted
                                </span>

                            <?php else: ?>

                                <span class="pending">
                                    Pending
                                </span>

                            <?php endif; ?>

                        </td>


                        <td>

                            <?php if (
                                !empty(
                                    $agreement["accepted_at"]
                                )
                            ): ?>

                                <?php

                                echo htmlspecialchars(
                                    $agreement["accepted_at"]
                                );

                                ?>

                            <?php else: ?>

                                -

                            <?php endif; ?>

                        </td>

                    </tr>

                <?php endwhile; ?>


            </tbody>

        </table>


    <?php else: ?>

        <p class="empty-message">
            No agreements found.
        </p>

    <?php endif; ?>


</section>


</main>


<footer>

    <p>
        &copy; 2026 Taxi Management System
    </p>

</footer>


<script>

const assignmentSelect =
    document.getElementById("assignment_id");

const rentDisplay =
    document.getElementById("rent-display");


if (assignmentSelect) {

    assignmentSelect.addEventListener(
        "change",
        function () {

            const selectedOption =
                this.options[
                    this.selectedIndex
                ];

            const rent =
                selectedOption.getAttribute(
                    "data-rent"
                );


            if (
                rent !== null &&
                rent !== ""
            ) {

                const formattedRent =
                    Number(rent).toLocaleString(
                        "en-IN",
                        {
                            minimumFractionDigits: 2,
                            maximumFractionDigits: 2
                        }
                    );


                rentDisplay.textContent =
                    "₹"
                    + formattedRent
                    + " / day";

            } else {

                rentDisplay.textContent =
                    "Select a taxi assignment";

            }

        }
    );

}

</script>


</body>

</html><?php

session_start();

if (!isset($_SESSION["admin_id"])) {
    header("Location: login.php");
    exit;
}

require_once "../config.php";

$message = "";
$error = "";


// =====================================================
// ASSIGN TAXI TO DRIVER
// =====================================================

if (
    $_SERVER["REQUEST_METHOD"] === "POST"
    && isset($_POST["assign_taxi"])
) {

    $driver_id =
        (int)($_POST["driver_id"] ?? 0);

    $taxi_id =
        (int)($_POST["taxi_id"] ?? 0);


    // -------------------------------------------------
    // VALIDATION
    // -------------------------------------------------

    if (
        $driver_id <= 0 ||
        $taxi_id <= 0
    ) {

        $error =
            "Please select a driver and taxi.";

    } else {

        // =================================================
        // START TRANSACTION
        // =================================================

        mysqli_begin_transaction($conn);

        try {

            // =============================================
            // CHECK DRIVER
            // =============================================

            $driver_sql = "
                SELECT
                    id,
                    name,
                    status
                FROM drivers
                WHERE id = ?
                LIMIT 1
            ";

            $driver_stmt =
                mysqli_prepare(
                    $conn,
                    $driver_sql
                );

            if (!$driver_stmt) {
                throw new Exception(
                    mysqli_error($conn)
                );
            }

            mysqli_stmt_bind_param(
                $driver_stmt,
                "i",
                $driver_id
            );

            mysqli_stmt_execute(
                $driver_stmt
            );

            $driver_result =
                mysqli_stmt_get_result(
                    $driver_stmt
                );

            $driver =
                mysqli_fetch_assoc(
                    $driver_result
                );

            mysqli_stmt_close(
                $driver_stmt
            );


            if (!$driver) {

                throw new Exception(
                    "Driver not found."
                );
            }


            if (
                $driver["status"] !== "Verified"
            ) {

                throw new Exception(
                    "Only verified drivers can be assigned a taxi."
                );
            }


            // =============================================
            // CHECK DRIVER ACTIVE ASSIGNMENT
            // =============================================

            $driver_assignment_sql = "
                SELECT id
                FROM assignments
                WHERE driver_id = ?
                AND status = 'Active'
                LIMIT 1
            ";

            $driver_assignment_stmt =
                mysqli_prepare(
                    $conn,
                    $driver_assignment_sql
                );

            if (!$driver_assignment_stmt) {
                throw new Exception(
                    mysqli_error($conn)
                );
            }

            mysqli_stmt_bind_param(
                $driver_assignment_stmt,
                "i",
                $driver_id
            );

            mysqli_stmt_execute(
                $driver_assignment_stmt
            );

            $driver_assignment_result =
                mysqli_stmt_get_result(
                    $driver_assignment_stmt
                );

            $existing_driver_assignment =
                mysqli_fetch_assoc(
                    $driver_assignment_result
                );

            mysqli_stmt_close(
                $driver_assignment_stmt
            );


            if ($existing_driver_assignment) {

                throw new Exception(
                    "This driver already has an active taxi assignment."
                );
            }


            // =============================================
            // CHECK TAXI
            // =============================================

            $taxi_sql = "
                SELECT
                    id,
                    brand,
                    model,
                    registration_number,
                    rent,
                    status
                FROM taxis
                WHERE id = ?
                LIMIT 1
            ";

            $taxi_stmt =
                mysqli_prepare(
                    $conn,
                    $taxi_sql
                );

            if (!$taxi_stmt) {
                throw new Exception(
                    mysqli_error($conn)
                );
            }

            mysqli_stmt_bind_param(
                $taxi_stmt,
                "i",
                $taxi_id
            );

            mysqli_stmt_execute(
                $taxi_stmt
            );

            $taxi_result =
                mysqli_stmt_get_result(
                    $taxi_stmt
                );

            $taxi =
                mysqli_fetch_assoc(
                    $taxi_result
                );

            mysqli_stmt_close(
                $taxi_stmt
            );


            if (!$taxi) {

                throw new Exception(
                    "Taxi not found."
                );
            }


            if (
                $taxi["status"] !== "Available"
            ) {

                throw new Exception(
                    "Selected taxi is not available."
                );
            }


            // =============================================
            // CREATE ASSIGNMENT
            // =============================================

            $insert_sql = "
                INSERT INTO assignments
                (
                    driver_id,
                    taxi_id,
                    assigned_at,
                    status
                )
                VALUES
                (
                    ?,
                    ?,
                    NOW(),
                    'Active'
                )
            ";

            $insert_stmt =
                mysqli_prepare(
                    $conn,
                    $insert_sql
                );

            if (!$insert_stmt) {
                throw new Exception(
                    mysqli_error($conn)
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

                throw new Exception(
                    mysqli_error($conn)
                );
            }


            mysqli_stmt_close(
                $insert_stmt
            );


            // =============================================
            // UPDATE TAXI STATUS
            // =============================================

            $update_taxi_sql = "
                UPDATE taxis
                SET status = 'Assigned'
                WHERE id = ?
                AND status = 'Available'
            ";

            $update_taxi_stmt =
                mysqli_prepare(
                    $conn,
                    $update_taxi_sql
                );

            if (!$update_taxi_stmt) {
                throw new Exception(
                    mysqli_error($conn)
                );
            }

            mysqli_stmt_bind_param(
                $update_taxi_stmt,
                "i",
                $taxi_id
            );

            mysqli_stmt_execute(
                $update_taxi_stmt
            );


            if (
                mysqli_stmt_affected_rows(
                    $update_taxi_stmt
                ) !== 1
            ) {

                mysqli_stmt_close(
                    $update_taxi_stmt
                );

                throw new Exception(
                    "Taxi could not be marked as assigned."
                );
            }


            mysqli_stmt_close(
                $update_taxi_stmt
            );


            // =============================================
            // COMMIT
            // =============================================

            mysqli_commit($conn);

            $message =
                "Taxi assigned successfully to "
                . $driver["name"]
                . ".";


        } catch (Exception $e) {

            mysqli_rollback($conn);

            $error =
                $e->getMessage();
        }
    }
}


// =====================================================
// DEACTIVATE ASSIGNMENT
// =====================================================

if (
    $_SERVER["REQUEST_METHOD"] === "POST"
    && isset($_POST["remove_assignment"])
) {

    $assignment_id =
        (int)($_POST["assignment_id"] ?? 0);


    if ($assignment_id <= 0) {

        $error =
            "Invalid assignment.";

    } else {

        mysqli_begin_transaction($conn);

        try {

            // =============================================
            // GET ASSIGNMENT
            // =============================================

            $get_sql = "
                SELECT
                    id,
                    taxi_id,
                    status
                FROM assignments
                WHERE id = ?
                LIMIT 1
            ";

            $get_stmt =
                mysqli_prepare(
                    $conn,
                    $get_sql
                );

            if (!$get_stmt) {
                throw new Exception(
                    mysqli_error($conn)
                );
            }

            mysqli_stmt_bind_param(
                $get_stmt,
                "i",
                $assignment_id
            );

            mysqli_stmt_execute(
                $get_stmt
            );

            $get_result =
                mysqli_stmt_get_result(
                    $get_stmt
                );

            $assignment =
                mysqli_fetch_assoc(
                    $get_result
                );

            mysqli_stmt_close(
                $get_stmt
            );


            if (!$assignment) {

                throw new Exception(
                    "Assignment not found."
                );
            }


            if (
                $assignment["status"] !== "Active"
            ) {

                throw new Exception(
                    "This assignment is already inactive."
                );
            }


            // =============================================
            // DEACTIVATE ASSIGNMENT
            // =============================================

            $update_assignment_sql = "
                UPDATE assignments
                SET status = 'Inactive'
                WHERE id = ?
                AND status = 'Active'
            ";

            $update_assignment_stmt =
                mysqli_prepare(
                    $conn,
                    $update_assignment_sql
                );

            if (!$update_assignment_stmt) {
                throw new Exception(
                    mysqli_error($conn)
                );
            }

            mysqli_stmt_bind_param(
                $update_assignment_stmt,
                "i",
                $assignment_id
            );

            mysqli_stmt_execute(
                $update_assignment_stmt
            );

            mysqli_stmt_close(
                $update_assignment_stmt
            );


            // =============================================
            // MAKE TAXI AVAILABLE AGAIN
            // =============================================

            $update_taxi_sql = "
                UPDATE taxis
                SET status = 'Available'
                WHERE id = ?
            ";

            $update_taxi_stmt =
                mysqli_prepare(
                    $conn,
                    $update_taxi_sql
                );

            if (!$update_taxi_stmt) {
                throw new Exception(
                    mysqli_error($conn)
                );
            }

            mysqli_stmt_bind_param(
                $update_taxi_stmt,
                "i",
                $assignment["taxi_id"]
            );

            mysqli_stmt_execute(
                $update_taxi_stmt
            );

            mysqli_stmt_close(
                $update_taxi_stmt
            );


            mysqli_commit($conn);

            $message =
                "Taxi assignment removed successfully. "
                . "The taxi is now available again.";


        } catch (Exception $e) {

            mysqli_rollback($conn);

            $error =
                $e->getMessage();
        }
    }
}


// =====================================================
// GET VERIFIED DRIVERS WITHOUT ACTIVE ASSIGNMENT
// =====================================================

$drivers_sql = "
    SELECT
        d.id,
        d.name,
        d.phone,
        d.driving_license

    FROM drivers d

    LEFT JOIN assignments a
        ON d.id = a.driver_id
        AND a.status = 'Active'

    WHERE d.status = 'Verified'
    AND a.id IS NULL

    ORDER BY d.name ASC
";

$drivers_result =
    mysqli_query(
        $conn,
        $drivers_sql
    );


// =====================================================
// GET AVAILABLE TAXIS
// =====================================================

$taxis_sql = "
    SELECT
        id,
        brand,
        model,
        registration_number,
        rent,
        status

    FROM taxis

    WHERE status = 'Available'

    ORDER BY brand ASC, model ASC
";

$taxis_result =
    mysqli_query(
        $conn,
        $taxis_sql
    );


// =====================================================
// GET ALL ACTIVE ASSIGNMENTS
// =====================================================

$assignments_sql = "
    SELECT
        assignments.id,
        assignments.assigned_at,
        assignments.status,

        drivers.name AS driver_name,
        drivers.phone AS driver_phone,
        drivers.driving_license,

        taxis.brand,
        taxis.model,
        taxis.registration_number,
        taxis.rent

    FROM assignments

    INNER JOIN drivers
        ON assignments.driver_id = drivers.id

    INNER JOIN taxis
        ON assignments.taxi_id = taxis.id

    WHERE assignments.status = 'Active'

    ORDER BY assignments.id DESC
";

$assignments_result =
    mysqli_query(
        $conn,
        $assignments_sql
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

    <title>
        Taxi Assignments - Taxi Management System
    </title>

    <link
        rel="stylesheet"
        href="../css/style.css"
    >

    <style>

        .assignment-form {

            max-width: 600px;

        }


        .success-message {

            color: #15803d;

            background: #dcfce7;

            border: 1px solid #bbf7d0;

            padding: 12px;

            border-radius: 6px;

            margin-bottom: 20px;

        }


        .error-message {

            color: #b91c1c;

            background: #fee2e2;

            border: 1px solid #fecaca;

            padding: 12px;

            border-radius: 6px;

            margin-bottom: 20px;

        }


        .assignment-info {

            margin-top: 20px;

            padding: 15px;

            border: 1px solid #ddd;

            border-radius: 8px;

            background: #f8f8f8;

        }


        .assignment-table {

            width: 100%;

            border-collapse: collapse;

            margin-top: 20px;

        }


        .assignment-table th,
        .assignment-table td {

            border: 1px solid #ddd;

            padding: 10px;

            text-align: left;

        }


        .assignment-table th {

            background: #f5f5f5;

        }


        .status-active {

            color: #15803d;

            font-weight: bold;

        }


        .remove-button {

            background: #b91c1c;

            color: white;

            border: none;

            padding: 8px 12px;

            border-radius: 5px;

            cursor: pointer;

        }


        .remove-button:hover {

            opacity: 0.85;

        }


        @media (max-width: 800px) {

            .assignment-table {

                display: block;

                overflow-x: auto;

                white-space: nowrap;

            }

        }

    </style>

</head>


<body>


<!-- ================================================= -->
<!-- HEADER -->
<!-- ================================================= -->

<header>

    <h1>
        Taxi Assignments
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

        <a href="../index2.php">
            Public Portal
        </a>

        <a href="logout.php">
            Logout
        </a>

    </nav>

</header>


<!-- ================================================= -->
<!-- MAIN -->
<!-- ================================================= -->

<main>


<!-- ================================================= -->
<!-- MESSAGES -->
<!-- ================================================= -->

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


<!-- ================================================= -->
<!-- CREATE ASSIGNMENT -->
<!-- ================================================= -->

<section>

    <h2>
        Assign Taxi to Driver
    </h2>


    <p>
        Only verified drivers and available taxis
        can be assigned.
    </p>


    <?php

    $driver_count =
        $drivers_result
        ? mysqli_num_rows($drivers_result)
        : 0;

    $taxi_count =
        $taxis_result
        ? mysqli_num_rows($taxis_result)
        : 0;

    ?>


    <?php if (
        $driver_count > 0
        && $taxi_count > 0
    ): ?>


        <form
            method="POST"
            class="assignment-form"
        >

            <input
                type="hidden"
                name="assign_taxi"
                value="1"
            >


            <!-- DRIVER -->

            <div>

                <label for="driver_id">
                    Select Verified Driver
                </label>


                <select
                    id="driver_id"
                    name="driver_id"
                    required
                >

                    <option value="">
                        Select Driver
                    </option>


                    <?php while (
                        $driver =
                        mysqli_fetch_assoc(
                            $drivers_result
                        )
                    ): ?>

                        <option
                            value="<?php
                                echo (int)
                                    $driver["id"];
                            ?>"
                        >

                            <?php

                            echo htmlspecialchars(
                                $driver["name"]
                            );

                            echo " - ";

                            echo htmlspecialchars(
                                $driver["phone"]
                            );

                            echo " - License: ";

                            echo htmlspecialchars(
                                $driver[
                                    "driving_license"
                                ]
                            );

                            ?>

                        </option>

                    <?php endwhile; ?>

                </select>

            </div>


            <br>


            <!-- TAXI -->

            <div>

                <label for="taxi_id">
                    Select Available Taxi
                </label>


                <select
                    id="taxi_id"
                    name="taxi_id"
                    required
                >

                    <option value="">
                        Select Taxi
                    </option>


                    <?php while (
                        $taxi =
                        mysqli_fetch_assoc(
                            $taxis_result
                        )
                    ): ?>

                        <option
                            value="<?php
                                echo (int)
                                    $taxi["id"];
                            ?>"
                            data-rent="<?php
                                echo htmlspecialchars(
                                    $taxi["rent"]
                                );
                            ?>"
                        >

                            <?php

                            echo htmlspecialchars(
                                $taxi["brand"]
                                . " "
                                . $taxi["model"]
                                . " - "
                                . $taxi[
                                    "registration_number"
                                ]
                            );

                            ?>

                        </option>

                    <?php endwhile; ?>

                </select>

            </div>


            <br>


            <!-- RENT -->

            <div
                id="rent-info"
                class="assignment-info"
            >

                <strong>
                    Taxi Rent:
                </strong>

                <span id="rent-display">
                    Select a taxi
                </span>

            </div>


            <br>


            <button type="submit">
                Assign Taxi
            </button>


        </form>


    <?php elseif (
        $driver_count === 0
    ): ?>


        <div class="assignment-info">

            <strong>
                No drivers available.
            </strong>

            <p>
                A verified driver without an active
                taxi assignment is required.
            </p>

        </div>


    <?php elseif (
        $taxi_count === 0
    ): ?>


        <div class="assignment-info">

            <strong>
                No taxis available.
            </strong>

            <p>
                Add an available taxi from Taxi
                Management before creating an assignment.
            </p>

        </div>


    <?php endif; ?>


</section>


<!-- ================================================= -->
<!-- ACTIVE ASSIGNMENTS -->
<!-- ================================================= -->

<section>

    <h2>
        Active Assignments
    </h2>


    <?php if (
        $assignments_result
        && mysqli_num_rows(
            $assignments_result
        ) > 0
    ): ?>


        <table class="assignment-table">

            <thead>

                <tr>

                    <th>
                        ID
                    </th>

                    <th>
                        Driver
                    </th>

                    <th>
                        Phone
                    </th>

                    <th>
                        License
                    </th>

                    <th>
                        Taxi
                    </th>

                    <th>
                        Registration
                    </th>

                    <th>
                        Rent
                    </th>

                    <th>
                        Assigned At
                    </th>

                    <th>
                        Status
                    </th>

                    <th>
                        Action
                    </th>

                </tr>

            </thead>


            <tbody>


            <?php while (
                $assignment =
                mysqli_fetch_assoc(
                    $assignments_result
                )
            ): ?>


                <tr>


                    <td>

                        <?php
                        echo (int)
                            $assignment["id"];
                        ?>

                    </td>


                    <td>

                        <?php

                        echo htmlspecialchars(
                            $assignment[
                                "driver_name"
                            ]
                        );

                        ?>

                    </td>


                    <td>

                        <?php

                        echo htmlspecialchars(
                            $assignment[
                                "driver_phone"
                            ]
                        );

                        ?>

                    </td>


                    <td>

                        <?php

                        echo htmlspecialchars(
                            $assignment[
                                "driving_license"
                            ]
                        );

                        ?>

                    </td>


                    <td>

                        <?php

                        echo htmlspecialchars(
                            $assignment["brand"]
                            . " "
                            . $assignment["model"]
                        );

                        ?>

                    </td>


                    <td>

                        <?php

                        echo htmlspecialchars(
                            $assignment[
                                "registration_number"
                            ]
                        );

                        ?>

                    </td>


                    <td>

                        ₹<?php

                        echo number_format(
                            (float)
                            $assignment["rent"],
                            2
                        );

                        ?>

                        / day

                    </td>


                    <td>

                        <?php

                        echo htmlspecialchars(
                            $assignment[
                                "assigned_at"
                            ]
                        );

                        ?>

                    </td>


                    <td>

                        <span
                            class="status-active"
                        >
                            <?php
                            echo htmlspecialchars(
                                $assignment[
                                    "status"
                                ]
                            );
                            ?>
                        </span>

                    </td>


                    <td>

                        <form
                            method="POST"
                            onsubmit="
                                return confirm(
                                    'Are you sure you want to remove this taxi assignment?'
                                );
                            "
                        >

                            <input
                                type="hidden"
                                name="remove_assignment"
                                value="1"
                            >


                            <input
                                type="hidden"
                                name="assignment_id"
                                value="<?php
                                    echo (int)
                                        $assignment["id"];
                                ?>"
                            >


                            <button
                                type="submit"
                                class="remove-button"
                            >
                                Remove
                            </button>

                        </form>

                    </td>


                </tr>


            <?php endwhile; ?>


            </tbody>

        </table>


    <?php else: ?>


        <p>
            No active taxi assignments found.
        </p>


    <?php endif; ?>


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


<script>

const taxiSelect =
    document.getElementById(
        "taxi_id"
    );

const rentDisplay =
    document.getElementById(
        "rent-display"
    );


if (taxiSelect) {

    taxiSelect.addEventListener(
        "change",
        function () {

            const selectedOption =
                this.options[
                    this.selectedIndex
                ];

            const rent =
                selectedOption.getAttribute(
                    "data-rent"
                );


            if (
                rent !== null
                && rent !== ""
            ) {

                const formattedRent =
                    Number(rent).toLocaleString(
                        "en-IN",
                        {
                            minimumFractionDigits: 2,
                            maximumFractionDigits: 2
                        }
                    );


                rentDisplay.textContent =
                    "₹"
                    + formattedRent
                    + " / day";

            } else {

                rentDisplay.textContent =
                    "Select a taxi";

            }

        }
    );

}

</script>


</body>

</html>