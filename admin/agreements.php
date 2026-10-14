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

    csrf_verify();

    $assignment_id =
        (int) ($_POST["assignment_id"] ?? 0);

    $start_date =
        $_POST["start_date"] ?? "";

    $end_date =
        $_POST["end_date"] ?? "";

    $admin_confirm =
        isset($_POST["admin_confirm"]);


    // -------------------------------------------------
    // BASIC VALIDATION
    // -------------------------------------------------

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
        // GET ACTIVE ASSIGNMENT + ACTUAL TAXI RENT
        // =================================================

        $check_sql = "
            SELECT
                assignments.id,
                taxis.rent
            FROM assignments

            INNER JOIN taxis
                ON assignments.taxi_id = taxis.id

            WHERE assignments.id = ?
            AND assignments.status = 'Active'
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
                    "Invalid or inactive assignment.";

            } else {

                // Get actual rent directly from database
                $assignment_data =
                    mysqli_fetch_assoc(
                        $check_result
                    );

                $rent =
                    (float) $assignment_data["rent"];


                // =================================================
                // CHECK EXISTING ACTIVE AGREEMENT
                // =================================================

                $existing_sql = "
                    SELECT id
                    FROM agreements
                    WHERE assignment_id = ?
                    AND status = 'Active'
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
                        // CONVERT TERMS INTO DATABASE TEXT
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
                                . "\n"
                                . $term["mr"]
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
                                accepted
                            )
                            VALUES (?, ?, ?, ?, ?, 0)
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
                                    "Agreement created successfully.";

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


            mysqli_stmt_close(
                $check_stmt
            );
        }
    }
}


// =====================================================
// GET ACTIVE ASSIGNMENTS
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

    WHERE assignments.status = 'Active'

    ORDER BY assignments.id DESC
";

$assignment_result =
    mysqli_query(
        $conn,
        $assignment_sql
);


// =====================================================
// GET AGREEMENTS
// =====================================================

$agreement_sql = "
    SELECT
        agreements.id,
        agreements.start_date,
        agreements.end_date,
        agreements.rent,
        agreements.status,
        agreements.accepted,
        agreements.accepted_at,

        drivers.name AS driver_name,

        taxis.brand,
        taxis.model,
        taxis.registration_number

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

            color: green;

            margin-bottom: 15px;

        }


        .error-message {

            color: red;

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


        /* FIXED RENT DISPLAY */

        .fixed-rent {

            display: inline-block;

            padding: 8px 12px;

            border: 1px solid #ccc;

            border-radius: 6px;

            background: #f5f5f5;

            font-weight: bold;

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

        <a href="payments.php">
            Payments
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


    <form method="POST">

    <?php csrf_field(); ?>


        <input
            type="hidden"
            name="create_agreement"
            value="1"
        >


        <!-- DRIVER / TAXI -->

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
                            echo $assignment["id"];
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


        <!-- ================================================= -->
        <!-- AGREEMENT -->
        <!-- ================================================= -->

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


        <!-- ================================================= -->
        <!-- ADMIN CONFIRMATION -->
        <!-- ================================================= -->

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

</section>


<!-- ================================================= -->
<!-- AGREEMENTS LIST -->
<!-- ================================================= -->

<section>

    <h2>
        Agreements
    </h2>


    <?php if (
        $agreement_result
        && mysqli_num_rows(
            $agreement_result
        ) > 0
    ): ?>


        <table
            border="1"
            cellpadding="10"
        >

            <thead>

                <tr>

                    <th>ID</th>

                    <th>Driver</th>

                    <th>Taxi</th>

                    <th>Registration</th>

                    <th>Start Date</th>

                    <th>End Date</th>

                    <th>Rent</th>

                    <th>Acceptance</th>

                    <th>Accepted At</th>

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
                            echo $agreement["id"];
                            ?>
                        </td>


                        <td>
                            <?php

                            echo htmlspecialchars(
                                $agreement[
                                    "driver_name"
                                ]
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
                                $agreement[
                                    "start_date"
                                ]
                            );

                            ?>

                        </td>


                        <td>

                            <?php

                            echo htmlspecialchars(
                                $agreement[
                                    "end_date"
                                ]
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

                            if (
                                $agreement["accepted"] == 1
                            ) {

                                echo '<span class="accepted">
                                    Accepted
                                </span>';

                            } else {

                                echo '<span class="pending">
                                    Pending
                                </span>';

                            }

                            ?>

                        </td>


                        <td>

                            <?php

                            if (
                                !empty(
                                    $agreement[
                                        "accepted_at"
                                    ]
                                )
                            ) {

                                echo htmlspecialchars(
                                    $agreement[
                                        "accepted_at"
                                    ]
                                );

                            } else {

                                echo "-";

                            }

                            ?>

                        </td>


                    </tr>

                <?php endwhile; ?>


            </tbody>

        </table>


    <?php else: ?>

        <p>
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
    document.getElementById(
        "assignment_id"
    );

const rentDisplay =
    document.getElementById(
        "rent-display"
    );


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

</script>


</body>

</html>