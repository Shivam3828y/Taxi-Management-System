<?php

require_once "../config.php";

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION["admin_id"])) {
    header("Location: login.php");
    exit;
}

$message = "";
$error = "";


/* =========================================================
   MANUAL TAXI ASSIGNMENT
   ========================================================= */

if (
    $_SERVER["REQUEST_METHOD"] === "POST"
    && isset($_POST["manual_assign"])
) {

    csrf_verify();

    $driver_id = (int) ($_POST["driver_id"] ?? 0);
    $taxi_id   = (int) ($_POST["taxi_id"] ?? 0);

    if ($driver_id <= 0 || $taxi_id <= 0) {

        $error = "Please select a valid driver and taxi.";

    } else {

        mysqli_begin_transaction($conn);

        try {

            /* -------------------------------------------------
               CHECK VERIFIED DRIVER
               ------------------------------------------------- */

            $driver_sql = "
                SELECT id, name, phone
                FROM drivers
                WHERE id = ?
                AND status = 'Verified'
                FOR UPDATE
            ";

            $driver_stmt = mysqli_prepare($conn, $driver_sql);

            if (!$driver_stmt) {
                throw new Exception("Failed to prepare driver query.");
            }

            mysqli_stmt_bind_param(
                $driver_stmt,
                "i",
                $driver_id
            );

            if (!mysqli_stmt_execute($driver_stmt)) {
                mysqli_stmt_close($driver_stmt);
                throw new Exception("Failed to check driver.");
            }

            $driver_result = mysqli_stmt_get_result($driver_stmt);
            $driver = mysqli_fetch_assoc($driver_result);

            mysqli_stmt_close($driver_stmt);

            if (!$driver) {
                throw new Exception(
                    "Selected driver is not verified."
                );
            }


            /* -------------------------------------------------
               CHECK DRIVER DOES NOT ALREADY HAVE ACTIVE TAXI
               ------------------------------------------------- */

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

            mysqli_stmt_execute($existing_stmt);

            $existing_result = mysqli_stmt_get_result(
                $existing_stmt
            );

            $has_assignment =
                mysqli_num_rows($existing_result) > 0;

            mysqli_stmt_close($existing_stmt);

            if ($has_assignment) {
                throw new Exception(
                    "This driver already has an active taxi."
                );
            }


            /* -------------------------------------------------
               CHECK AVAILABLE TAXI
               ------------------------------------------------- */

            $taxi_sql = "
                SELECT
                    id,
                    brand,
                    model,
                    registration_number,
                    rent
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
                    "Failed to prepare taxi query."
                );
            }

            mysqli_stmt_bind_param(
                $taxi_stmt,
                "i",
                $taxi_id
            );

            if (!mysqli_stmt_execute($taxi_stmt)) {
                mysqli_stmt_close($taxi_stmt);
                throw new Exception(
                    "Failed to check taxi."
                );
            }

            $taxi_result = mysqli_stmt_get_result($taxi_stmt);
            $taxi = mysqli_fetch_assoc($taxi_result);

            mysqli_stmt_close($taxi_stmt);

            if (!$taxi) {
                throw new Exception(
                    "Selected taxi is not available."
                );
            }


            /* -------------------------------------------------
               CREATE ASSIGNMENT
               ------------------------------------------------- */

            $insert_sql = "
                INSERT INTO assignments
                (
                    driver_id,
                    taxi_id,
                    status
                )
                VALUES
                (
                    ?,
                    ?,
                    'Active'
                )
            ";

            $insert_stmt = mysqli_prepare(
                $conn,
                $insert_sql
            );

            if (!$insert_stmt) {
                throw new Exception(
                    "Failed to prepare assignment creation."
                );
            }

            mysqli_stmt_bind_param(
                $insert_stmt,
                "ii",
                $driver_id,
                $taxi_id
            );

            if (!mysqli_stmt_execute($insert_stmt)) {
                mysqli_stmt_close($insert_stmt);

                throw new Exception(
                    "Failed to create assignment."
                );
            }

            mysqli_stmt_close($insert_stmt);


            /* -------------------------------------------------
               UPDATE TAXI STATUS
               ------------------------------------------------- */

            $taxi_update_sql = "
                UPDATE taxis
                SET status = 'Assigned'
                WHERE id = ?
                AND status = 'Available'
            ";

            $taxi_update_stmt = mysqli_prepare(
                $conn,
                $taxi_update_sql
            );

            if (!$taxi_update_stmt) {
                throw new Exception(
                    "Failed to prepare taxi status update."
                );
            }

            mysqli_stmt_bind_param(
                $taxi_update_stmt,
                "i",
                $taxi_id
            );

            if (!mysqli_stmt_execute($taxi_update_stmt)) {
                mysqli_stmt_close($taxi_update_stmt);

                throw new Exception(
                    "Failed to update taxi status."
                );
            }

            if (
                mysqli_stmt_affected_rows(
                    $taxi_update_stmt
                ) !== 1
            ) {
                mysqli_stmt_close($taxi_update_stmt);

                throw new Exception(
                    "Taxi is no longer available."
                );
            }

            mysqli_stmt_close($taxi_update_stmt);


            /* -------------------------------------------------
               COMMIT
               ------------------------------------------------- */

            mysqli_commit($conn);

            $message =
                "Taxi "
                . $taxi["registration_number"]
                . " manually assigned to "
                . $driver["name"]
                . ".";

        } catch (Throwable $e) {

            mysqli_rollback($conn);

            $error = $e->getMessage();
        }
    }
}


/* =========================================================
   APPROVE / ASSIGN TAXI REQUEST
   ========================================================= */

if (
    $_SERVER["REQUEST_METHOD"] === "POST"
    && isset($_POST["approve_request"])
) {

    csrf_verify();

    $request_id = (int) ($_POST["request_id"] ?? 0);

    if ($request_id <= 0) {

        $error = "Invalid taxi request.";

    } else {

        mysqli_begin_transaction($conn);

        try {

            /* -------------------------------------------------
               GET REQUEST
               ------------------------------------------------- */

            $request_sql = "
                SELECT
                    taxi_requests.id,
                    taxi_requests.driver_id,
                    taxi_requests.taxi_id,
                    taxi_requests.amount,
                    taxi_requests.payment_status,
                    taxi_requests.request_status,

                    drivers.name AS driver_name,

                    taxis.brand,
                    taxis.model,
                    taxis.registration_number,
                    taxis.status AS taxi_status

                FROM taxi_requests

                INNER JOIN drivers
                    ON taxi_requests.driver_id = drivers.id

                INNER JOIN taxis
                    ON taxi_requests.taxi_id = taxis.id

                WHERE taxi_requests.id = ?

                FOR UPDATE
            ";

            $request_stmt = mysqli_prepare(
                $conn,
                $request_sql
            );

            if (!$request_stmt) {
                throw new Exception(
                    "Failed to prepare request query."
                );
            }

            mysqli_stmt_bind_param(
                $request_stmt,
                "i",
                $request_id
            );

            if (!mysqli_stmt_execute($request_stmt)) {

                mysqli_stmt_close($request_stmt);

                throw new Exception(
                    "Failed to load taxi request."
                );
            }

            $request_result = mysqli_stmt_get_result(
                $request_stmt
            );

            $request = mysqli_fetch_assoc(
                $request_result
            );

            mysqli_stmt_close($request_stmt);

            if (!$request) {
                throw new Exception(
                    "Taxi request not found."
                );
            }


            /* -------------------------------------------------
               REQUEST STATUS CHECK
               ------------------------------------------------- */

            if (
                $request["request_status"] !== "Pending"
            ) {

                throw new Exception(
                    "This request has already been processed."
                );
            }


            /* -------------------------------------------------
               DRIVER CHECK
               ------------------------------------------------- */

            $driver_sql = "
                SELECT id, name
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
                    "Failed to prepare driver check."
                );
            }

            mysqli_stmt_bind_param(
                $driver_stmt,
                "i",
                $request["driver_id"]
            );

            mysqli_stmt_execute($driver_stmt);

            $driver_result = mysqli_stmt_get_result(
                $driver_stmt
            );

            $driver_exists =
                mysqli_num_rows($driver_result) === 1;

            mysqli_stmt_close($driver_stmt);

            if (!$driver_exists) {
                throw new Exception(
                    "Driver is not verified."
                );
            }


            /* -------------------------------------------------
               CHECK EXISTING ACTIVE ASSIGNMENT
               ------------------------------------------------- */

            $existing_assignment_sql = "
                SELECT id
                FROM assignments
                WHERE driver_id = ?
                AND status = 'Active'
                LIMIT 1
                FOR UPDATE
            ";

            $existing_assignment_stmt = mysqli_prepare(
                $conn,
                $existing_assignment_sql
            );

            if (!$existing_assignment_stmt) {
                throw new Exception(
                    "Failed to check driver assignment."
                );
            }

            mysqli_stmt_bind_param(
                $existing_assignment_stmt,
                "i",
                $request["driver_id"]
            );

            mysqli_stmt_execute(
                $existing_assignment_stmt
            );

            $existing_assignment_result =
                mysqli_stmt_get_result(
                    $existing_assignment_stmt
                );

            $has_assignment =
                mysqli_num_rows(
                    $existing_assignment_result
                ) > 0;

            mysqli_stmt_close(
                $existing_assignment_stmt
            );

            if ($has_assignment) {
                throw new Exception(
                    "This driver already has an active taxi."
                );
            }


            /* -------------------------------------------------
               CHECK TAXI
               ------------------------------------------------- */

            $taxi_sql = "
                SELECT
                    id,
                    brand,
                    model,
                    registration_number,
                    rent
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
                    "Failed to prepare taxi check."
                );
            }

            mysqli_stmt_bind_param(
                $taxi_stmt,
                "i",
                $request["taxi_id"]
            );

            mysqli_stmt_execute($taxi_stmt);

            $taxi_result = mysqli_stmt_get_result(
                $taxi_stmt
            );

            $taxi = mysqli_fetch_assoc($taxi_result);

            mysqli_stmt_close($taxi_stmt);

            if (!$taxi) {
                throw new Exception(
                    "This taxi is no longer available."
                );
            }


            /* -------------------------------------------------
               CREATE ASSIGNMENT
               ------------------------------------------------- */

            $insert_sql = "
                INSERT INTO assignments
                (
                    driver_id,
                    taxi_id,
                    status
                )
                VALUES
                (
                    ?,
                    ?,
                    'Active'
                )
            ";

            $insert_stmt = mysqli_prepare(
                $conn,
                $insert_sql
            );

            if (!$insert_stmt) {
                throw new Exception(
                    "Failed to prepare assignment creation."
                );
            }

            mysqli_stmt_bind_param(
                $insert_stmt,
                "ii",
                $request["driver_id"],
                $request["taxi_id"]
            );

            if (!mysqli_stmt_execute($insert_stmt)) {

                mysqli_stmt_close($insert_stmt);

                throw new Exception(
                    "Failed to create assignment."
                );
            }

            mysqli_stmt_close($insert_stmt);


            /* -------------------------------------------------
               UPDATE TAXI STATUS
               ------------------------------------------------- */

            $taxi_update_sql = "
                UPDATE taxis
                SET status = 'Assigned'
                WHERE id = ?
                AND status = 'Available'
            ";

            $taxi_update_stmt = mysqli_prepare(
                $conn,
                $taxi_update_sql
            );

            if (!$taxi_update_stmt) {
                throw new Exception(
                    "Failed to prepare taxi update."
                );
            }

            mysqli_stmt_bind_param(
                $taxi_update_stmt,
                "i",
                $request["taxi_id"]
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
                    "Failed to update taxi status."
                );
            }

            if (
                mysqli_stmt_affected_rows(
                    $taxi_update_stmt
                ) !== 1
            ) {

                mysqli_stmt_close(
                    $taxi_update_stmt
                );

                throw new Exception(
                    "Taxi is no longer available."
                );
            }

            mysqli_stmt_close(
                $taxi_update_stmt
            );


            /* -------------------------------------------------
               UPDATE REQUEST
               ------------------------------------------------- */

            $request_update_sql = "
                UPDATE taxi_requests
                SET
                    request_status = 'Approved',
                    approved_at = NOW(),
                    assigned_at = NOW()
                WHERE id = ?
                AND request_status = 'Pending'
            ";

            $request_update_stmt = mysqli_prepare(
                $conn,
                $request_update_sql
            );

            if (!$request_update_stmt) {
                throw new Exception(
                    "Failed to prepare request update."
                );
            }

            mysqli_stmt_bind_param(
                $request_update_stmt,
                "i",
                $request_id
            );

            if (
                !mysqli_stmt_execute(
                    $request_update_stmt
                )
            ) {

                mysqli_stmt_close(
                    $request_update_stmt
                );

                throw new Exception(
                    "Failed to approve request."
                );
            }

            if (
                mysqli_stmt_affected_rows(
                    $request_update_stmt
                ) !== 1
            ) {

                mysqli_stmt_close(
                    $request_update_stmt
                );

                throw new Exception(
                    "Request could not be approved."
                );
            }

            mysqli_stmt_close(
                $request_update_stmt
            );


            /* -------------------------------------------------
               COMMIT
               ------------------------------------------------- */

            mysqli_commit($conn);

            if ($request["payment_status"] === "Paid") {

                $message =
                    "Taxi request accepted and assigned successfully. "
                    . "Payment was already marked as Paid.";

            } else {

                $message =
                    "Taxi request accepted without payment. "
                    . "Taxi "
                    . $request["registration_number"]
                    . " has been assigned to "
                    . $request["driver_name"]
                    . ".";
            }

        } catch (Throwable $e) {

            mysqli_rollback($conn);

            $error = $e->getMessage();
        }
    }
}


/* =========================================================
   REJECT TAXI REQUEST
   ========================================================= */

if (
    $_SERVER["REQUEST_METHOD"] === "POST"
    && isset($_POST["reject_request"])
) {

    csrf_verify();

    $request_id =
        (int) ($_POST["request_id"] ?? 0);

    $rejection_reason =
        trim(
            $_POST["rejection_reason"] ?? ""
        );

    if ($request_id <= 0) {

        $error = "Invalid taxi request.";

    } else {

        $reject_sql = "
            UPDATE taxi_requests
            SET
                request_status = 'Rejected',
                rejection_reason = ?
            WHERE id = ?
            AND request_status = 'Pending'
        ";

        $reject_stmt = mysqli_prepare(
            $conn,
            $reject_sql
        );

        if (!$reject_stmt) {

            $error =
                "Failed to prepare rejection.";

        } else {

            mysqli_stmt_bind_param(
                $reject_stmt,
                "si",
                $rejection_reason,
                $request_id
            );

            if (
                mysqli_stmt_execute(
                    $reject_stmt
                )
            ) {

                if (
                    mysqli_stmt_affected_rows(
                        $reject_stmt
                    ) === 1
                ) {

                    $message =
                        "Taxi request rejected successfully.";

                } else {

                    $error =
                        "Request could not be rejected. "
                        . "It may already have been processed.";
                }

            } else {

                $error =
                    "Failed to reject taxi request.";
            }

            mysqli_stmt_close($reject_stmt);
        }
    }
}


/* =========================================================
   END ACTIVE ASSIGNMENT
   ========================================================= */

if (
    $_SERVER["REQUEST_METHOD"] === "POST"
    && isset($_POST["end_assignment"])
) {

    csrf_verify();

    $assignment_id =
        (int) ($_POST["assignment_id"] ?? 0);

    if ($assignment_id <= 0) {

        $error = "Invalid assignment.";

    } else {

        mysqli_begin_transaction($conn);

        try {

            /* -------------------------------------------------
               GET ACTIVE ASSIGNMENT
               ------------------------------------------------- */

            $assignment_sql = "
                SELECT
                    id,
                    taxi_id
                FROM assignments
                WHERE id = ?
                AND status = 'Active'
                FOR UPDATE
            ";

            $assignment_stmt = mysqli_prepare(
                $conn,
                $assignment_sql
            );

            if (!$assignment_stmt) {
                throw new Exception(
                    "Failed to prepare assignment query."
                );
            }

            mysqli_stmt_bind_param(
                $assignment_stmt,
                "i",
                $assignment_id
            );

            mysqli_stmt_execute(
                $assignment_stmt
            );

            $assignment_result =
                mysqli_stmt_get_result(
                    $assignment_stmt
                );

            $assignment =
                mysqli_fetch_assoc(
                    $assignment_result
                );

            mysqli_stmt_close(
                $assignment_stmt
            );

            if (!$assignment) {
                throw new Exception(
                    "Active assignment not found."
                );
            }

            $taxi_id =
                (int) $assignment["taxi_id"];


            /* -------------------------------------------------
               END ASSIGNMENT
               ------------------------------------------------- */

            $end_sql = "
                UPDATE assignments
                SET status = 'Ended'
                WHERE id = ?
                AND status = 'Active'
            ";

            $end_stmt = mysqli_prepare(
                $conn,
                $end_sql
            );

            if (!$end_stmt) {
                throw new Exception(
                    "Failed to prepare assignment update."
                );
            }

            mysqli_stmt_bind_param(
                $end_stmt,
                "i",
                $assignment_id
            );

            mysqli_stmt_execute($end_stmt);

            if (
                mysqli_stmt_affected_rows(
                    $end_stmt
                ) !== 1
            ) {

                mysqli_stmt_close($end_stmt);

                throw new Exception(
                    "Assignment could not be ended."
                );
            }

            mysqli_stmt_close($end_stmt);


            /* -------------------------------------------------
               MAKE TAXI AVAILABLE
               ------------------------------------------------- */

            $taxi_update_sql = "
                UPDATE taxis
                SET status = 'Available'
                WHERE id = ?
                AND status = 'Assigned'
            ";

            $taxi_update_stmt = mysqli_prepare(
                $conn,
                $taxi_update_sql
            );

            if (!$taxi_update_stmt) {
                throw new Exception(
                    "Failed to prepare taxi status update."
                );
            }

            mysqli_stmt_bind_param(
                $taxi_update_stmt,
                "i",
                $taxi_id
            );

            mysqli_stmt_execute(
                $taxi_update_stmt
            );

            if (
                mysqli_stmt_affected_rows(
                    $taxi_update_stmt
                ) !== 1
            ) {

                mysqli_stmt_close(
                    $taxi_update_stmt
                );

                throw new Exception(
                    "Taxi status could not be updated."
                );
            }

            mysqli_stmt_close(
                $taxi_update_stmt
            );


            mysqli_commit($conn);

            $message =
                "Assignment ended successfully. "
                . "Taxi is now available.";

        } catch (Throwable $e) {

            mysqli_rollback($conn);

            $error = $e->getMessage();
        }
    }
}


/* =========================================================
   VERIFIED DRIVERS FOR MANUAL ASSIGNMENT
   ========================================================= */

$manual_drivers_sql = "
    SELECT
        id,
        name,
        phone
    FROM drivers
    WHERE status = 'Verified'
    ORDER BY name ASC
";

$manual_drivers_result = mysqli_query(
    $conn,
    $manual_drivers_sql
);


/* =========================================================
   AVAILABLE TAXIS FOR MANUAL ASSIGNMENT
   ========================================================= */

$manual_taxis_sql = "
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

$manual_taxis_result = mysqli_query(
    $conn,
    $manual_taxis_sql
);


/* =========================================================
   PENDING TAXI REQUESTS
   ========================================================= */

$requests_sql = "
    SELECT
        taxi_requests.id,
        taxi_requests.driver_id,
        taxi_requests.taxi_id,
        taxi_requests.amount,
        taxi_requests.payment_status,
        taxi_requests.request_status,
        taxi_requests.requested_at,

        drivers.name AS driver_name,
        drivers.phone AS driver_phone,

        taxis.brand,
        taxis.model,
        taxis.registration_number

    FROM taxi_requests

    INNER JOIN drivers
        ON taxi_requests.driver_id = drivers.id

    INNER JOIN taxis
        ON taxi_requests.taxi_id = taxis.id

    WHERE taxi_requests.request_status = 'Pending'

    ORDER BY taxi_requests.id DESC
";

$requests_result = mysqli_query(
    $conn,
    $requests_sql
);


/* =========================================================
   ACTIVE ASSIGNMENTS
   ========================================================= */

$assignments_sql = "
    SELECT
        assignments.id,
        assignments.driver_id,
        assignments.taxi_id,

        drivers.name AS driver_name,

        taxis.brand,
        taxis.model,
        taxis.registration_number,
        taxis.rent,

        assignments.assigned_at,
        assignments.status

    FROM assignments

    INNER JOIN drivers
        ON assignments.driver_id = drivers.id

    INNER JOIN taxis
        ON assignments.taxi_id = taxis.id

    WHERE assignments.status = 'Active'

    ORDER BY assignments.id DESC
";

$assignments_result = mysqli_query(
    $conn,
    $assignments_sql
);


/* =========================================================
   ASSIGNMENT REQUEST HISTORY
   ========================================================= */

$history_sql = "
    SELECT
        taxi_requests.id AS request_id,
        taxi_requests.driver_id,
        taxi_requests.taxi_id,
        taxi_requests.amount,
        taxi_requests.payment_status,
        taxi_requests.request_status,
        taxi_requests.requested_at,
        taxi_requests.approved_at,
        taxi_requests.assigned_at,
        taxi_requests.rejection_reason,

        drivers.name AS driver_name,
        drivers.phone AS driver_phone,

        taxis.brand,
        taxis.model,
        taxis.registration_number

    FROM taxi_requests

    INNER JOIN drivers
        ON taxi_requests.driver_id = drivers.id

    INNER JOIN taxis
        ON taxi_requests.taxi_id = taxis.id

    WHERE taxi_requests.request_status
        IN ('Approved', 'Rejected')

    ORDER BY taxi_requests.id DESC
";

$history_result = mysqli_query(
    $conn,
    $history_sql
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

    <title>Taxi Assignments</title>

    <link
        rel="stylesheet"
        href="../css/style.css"
    >

    <style>

        main {
            max-width: 1300px;
            margin: 30px auto;
            padding: 0 15px;
        }

        .message {
            padding: 12px 15px;
            border-radius: 7px;
            margin-bottom: 20px;
        }

        .success-message {
            background: #dcfce7;
            color: #166534;
        }

        .error-message {
            background: #fee2e2;
            color: #991b1b;
        }

        .top-assignment-layout {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 35px;
        }

        .panel {
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 12px;
            padding: 22px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
        }

        .panel h2 {
            margin-top: 0;
            margin-bottom: 8px;
        }

        .panel-description {
            color: #666;
            margin-bottom: 20px;
        }

        .form-group {
            margin-bottom: 17px;
        }

        .form-group label {
            display: block;
            margin-bottom: 7px;
            font-weight: bold;
        }

        .form-group select {
            width: 100%;
            box-sizing: border-box;
            padding: 11px;
            border: 1px solid #ccc;
            border-radius: 7px;
            background: white;
        }

        .assign-button {
            background: #2563eb;
            color: white;
            border: none;
            padding: 11px 18px;
            border-radius: 7px;
            cursor: pointer;
            font-weight: bold;
        }

        .assign-button:hover {
            background: #1d4ed8;
        }

        .request-list {
            max-height: 520px;
            overflow-y: auto;
        }

        .request-card {
            border: 1px solid #ddd;
            border-radius: 10px;
            padding: 16px;
            margin-bottom: 15px;
            background: #fafafa;
        }

        .request-card:last-child {
            margin-bottom: 0;
        }

        .request-card h3 {
            margin-top: 0;
        }

        .request-row {
            display: flex;
            justify-content: space-between;
            gap: 15px;
            padding: 8px 0;
            border-bottom: 1px solid #eee;
        }

        .request-row:last-child {
            border-bottom: none;
        }

        .paid {
            color: #15803d;
            font-weight: bold;
        }

        .pending {
            color: #b45309;
            font-weight: bold;
        }

        .failed,
        .rejected {
            color: #b91c1c;
            font-weight: bold;
        }

        .request-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
            flex-wrap: wrap;
        }

        .approve-button {
            background: #15803d;
            color: white;
            border: none;
            padding: 9px 14px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: bold;
        }

        .approve-button:hover {
            background: #166534;
        }

        .approve-without-payment-button {
            background: #2563eb;
            color: white;
            border: none;
            padding: 9px 14px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: bold;
        }

        .approve-without-payment-button:hover {
            background: #1d4ed8;
        }

        .reject-button {
            background: #b91c1c;
            color: white;
            border: none;
            padding: 9px 14px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: bold;
        }

        .reject-button:hover {
            background: #991b1b;
        }

        .reject-box {
            margin-top: 12px;
        }

        .reject-box input {
            width: 100%;
            box-sizing: border-box;
            padding: 9px;
            border: 1px solid #ccc;
            border-radius: 6px;
            margin-bottom: 8px;
        }

        .no-requests {
            padding: 20px;
            border: 1px dashed #aaa;
            border-radius: 8px;
            background: #fafafa;
            text-align: center;
        }

        .section {
            margin-bottom: 40px;
        }

        .table-container {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            background: white;
        }

        th,
        td {
            padding: 11px;
            border: 1px solid #ddd;
            text-align: left;
            vertical-align: top;
        }

        th {
            background: #f5f5f5;
        }

        .end-button {
            background: #b45309;
            color: white;
            border: none;
            padding: 8px 12px;
            border-radius: 6px;
            cursor: pointer;
        }

        .end-button:hover {
            background: #92400e;
        }

        .history-section {
            margin-top: 40px;
        }

        .history-table {
            min-width: 1100px;
        }

        .history-approved {
            color: #15803d;
            font-weight: bold;
        }

        .history-rejected {
            color: #b91c1c;
            font-weight: bold;
        }

        .history-note {
            margin-bottom: 15px;
            color: #555;
        }

        .payment-note {
            margin-top: 10px;
            padding: 10px;
            border-radius: 6px;
            background: #fff7ed;
            color: #9a3412;
            font-size: 14px;
        }

        @media (max-width: 850px) {

            .top-assignment-layout {
                grid-template-columns: 1fr;
            }

        }

        @media (max-width: 700px) {

            .request-row {
                flex-direction: column;
                gap: 3px;
            }

        }

    </style>

</head>

<body>

<header>

    <h1>Taxi Assignment</h1>

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

    <?php if ($message !== ""): ?>

        <div class="message success-message">
            <?= e($message) ?>
        </div>

    <?php endif; ?>


    <?php if ($error !== ""): ?>

        <div class="message error-message">
            <?= e($error) ?>
        </div>

    <?php endif; ?>


    <!-- =================================================
         MANUAL ASSIGNMENT + REQUESTS
         ================================================= -->

    <section class="top-assignment-layout">


        <!-- =================================================
             MANUAL TAXI ASSIGNMENT
             ================================================= -->

        <div class="panel">

            <h2>
                Manual Taxi Assignment
            </h2>

            <p class="panel-description">
                Admin can directly assign an available taxi
                to a verified driver.
            </p>


            <form method="POST">

                <?= csrf_field() ?>


                <div class="form-group">

                    <label for="driver_id">
                        Select Verified Driver
                    </label>

                    <select
                        name="driver_id"
                        id="driver_id"
                        required
                    >

                        <option value="">
                            -- Select Driver --
                        </option>

                        <?php if ($manual_drivers_result): ?>

                            <?php while (
                                $driver_option =
                                mysqli_fetch_assoc(
                                    $manual_drivers_result
                                )
                            ): ?>

                                <option
                                    value="<?= (int) $driver_option["id"] ?>"
                                >

                                    <?= e(
                                        $driver_option["name"]
                                    ) ?>

                                    -
                                    <?= e(
                                        $driver_option["phone"]
                                    ) ?>

                                </option>

                            <?php endwhile; ?>

                        <?php endif; ?>

                    </select>

                </div>


                <div class="form-group">

                    <label for="taxi_id">
                        Select Available Taxi
                    </label>

                    <select
                        name="taxi_id"
                        id="taxi_id"
                        required
                    >

                        <option value="">
                            -- Select Taxi --
                        </option>

                        <?php if ($manual_taxis_result): ?>

                            <?php while (
                                $taxi_option =
                                mysqli_fetch_assoc(
                                    $manual_taxis_result
                                )
                            ): ?>

                                <option
                                    value="<?= (int) $taxi_option["id"] ?>"
                                >

                                    <?= e(
                                        $taxi_option["brand"]
                                    ) ?>

                                    <?= e(
                                        $taxi_option["model"]
                                    ) ?>

                                    -

                                    <?= e(
                                        $taxi_option[
                                            "registration_number"
                                        ]
                                    ) ?>

                                    -

                                    ₹<?= number_format(
                                        (float) $taxi_option["rent"],
                                        2
                                    ) ?>/day

                                </option>

                            <?php endwhile; ?>

                        <?php endif; ?>

                    </select>

                </div>


                <button
                    type="submit"
                    name="manual_assign"
                    value="1"
                    class="assign-button"
                    onclick="return confirm('Assign this taxi to the selected driver?');"
                >
                    Assign Taxi
                </button>

            </form>

        </div>


        <!-- =================================================
             TAXI ASSIGNMENT REQUESTS
             ================================================= -->

        <div class="panel">

            <h2>
                Taxi Assignment Requests
            </h2>

            <p class="panel-description">
                Driver requests appear here. Admin can assign
                the taxi whether payment is Paid or Pending.
            </p>


            <div class="request-list">

                <?php if (
                    $requests_result
                    && mysqli_num_rows($requests_result) > 0
                ): ?>


                    <?php while (
                        $request =
                        mysqli_fetch_assoc(
                            $requests_result
                        )
                    ): ?>


                        <article class="request-card">

                            <h3>
                                Request #<?= (int) $request["id"] ?>
                            </h3>


                            <div class="request-row">

                                <strong>
                                    Driver
                                </strong>

                                <span>
                                    <?= e(
                                        $request["driver_name"]
                                    ) ?>
                                </span>

                            </div>


                            <div class="request-row">

                                <strong>
                                    Driver ID
                                </strong>

                                <span>
                                    <?= (int) $request["driver_id"] ?>
                                </span>

                            </div>


                            <div class="request-row">

                                <strong>
                                    Phone
                                </strong>

                                <span>
                                    <?= e(
                                        $request["driver_phone"]
                                    ) ?>
                                </span>

                            </div>


                            <div class="request-row">

                                <strong>
                                    Taxi
                                </strong>

                                <span>
                                    <?= e(
                                        $request["brand"]
                                    ) ?>

                                    <?= e(
                                        $request["model"]
                                    ) ?>
                                </span>

                            </div>


                            <div class="request-row">

                                <strong>
                                    Registration
                                </strong>

                                <span>
                                    <?= e(
                                        $request[
                                            "registration_number"
                                        ]
                                    ) ?>
                                </span>

                            </div>


                            <div class="request-row">

                                <strong>
                                    Rent
                                </strong>

                                <span>
                                    ₹<?= number_format(
                                        (float) $request["amount"],
                                        2
                                    ) ?>

                                    / day
                                </span>

                            </div>


                            <div class="request-row">

                                <strong>
                                    Payment
                                </strong>

                                <span
                                    class="<?= strtolower(
                                        e(
                                            $request[
                                                "payment_status"
                                            ]
                                        )
                                    ) ?>"
                                >

                                    <?= e(
                                        $request[
                                            "payment_status"
                                        ]
                                    ) ?>

                                </span>

                            </div>


                            <div class="request-row">

                                <strong>
                                    Requested At
                                </strong>

                                <span>
                                    <?= e(
                                        $request[
                                            "requested_at"
                                        ]
                                    ) ?>
                                </span>

                            </div>


                            <?php if (
                                $request["payment_status"]
                                !== "Paid"
                            ): ?>

                                <div class="payment-note">

                                    Payment is currently
                                    <strong>
                                        <?= e(
                                            $request[
                                                "payment_status"
                                            ]
                                        ) ?>
                                    </strong>.

                                    Admin can still assign the
                                    taxi using
                                    <strong>
                                        Accept Without Payment
                                    </strong>.

                                </div>

                            <?php endif; ?>


                            <div class="request-actions">


                                <?php if (
                                    $request[
                                        "payment_status"
                                    ] === "Paid"
                                ): ?>

                                    <form
                                        method="POST"
                                        style="display:inline;"
                                    >

                                        <?= csrf_field() ?>

                                        <input
                                            type="hidden"
                                            name="request_id"
                                            value="<?= (int) $request["id"] ?>"
                                        >

                                        <button
                                            type="submit"
                                            name="approve_request"
                                            value="1"
                                            class="approve-button"
                                            onclick="return confirm('Accept this paid request and assign the taxi to the driver?');"
                                        >
                                            Accept & Assign
                                        </button>

                                    </form>

                                <?php else: ?>

                                    <form
                                        method="POST"
                                        style="display:inline;"
                                    >

                                        <?= csrf_field() ?>

                                        <input
                                            type="hidden"
                                            name="request_id"
                                            value="<?= (int) $request["id"] ?>"
                                        >

                                        <button
                                            type="submit"
                                            name="approve_request"
                                            value="1"
                                            class="approve-without-payment-button"
                                            onclick="return confirm('Accept this request WITHOUT payment and assign the taxi to the driver?');"
                                        >
                                            Accept Without Payment
                                        </button>

                                    </form>

                                <?php endif; ?>


                                <button
                                    type="button"
                                    class="reject-button"
                                    onclick="toggleReject(<?= (int) $request["id"] ?>)"
                                >
                                    Reject
                                </button>

                            </div>


                            <!-- REJECTION FORM -->

                            <div
                                id="reject-<?= (int) $request["id"] ?>"
                                class="reject-box"
                                style="display:none;"
                            >

                                <form method="POST">

                                    <?= csrf_field() ?>

                                    <input
                                        type="hidden"
                                        name="request_id"
                                        value="<?= (int) $request["id"] ?>"
                                    >

                                    <input
                                        type="text"
                                        name="rejection_reason"
                                        placeholder="Reason for rejection (optional)"
                                        maxlength="255"
                                    >

                                    <button
                                        type="submit"
                                        name="reject_request"
                                        value="1"
                                        class="reject-button"
                                        onclick="return confirm('Reject this taxi request?');"
                                    >
                                        Confirm Reject
                                    </button>

                                </form>

                            </div>

                        </article>


                    <?php endwhile; ?>


                <?php else: ?>

                    <div class="no-requests">

                        <strong>
                            No pending taxi requests.
                        </strong>

                        <p>
                            New driver requests will appear here.
                        </p>

                    </div>

                <?php endif; ?>

            </div>

        </div>

    </section>


    <!-- =================================================
         ACTIVE ASSIGNMENTS
         ================================================= -->

    <section class="section">

        <h2>
            Active Assignments
        </h2>


        <?php if (
            $assignments_result
            && mysqli_num_rows(
                $assignments_result
            ) > 0
        ): ?>


            <div class="table-container">

                <table>

                    <thead>

                        <tr>

                            <th>
                                Assignment ID
                            </th>

                            <th>
                                Driver ID
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
                                    <?= (int) $assignment["id"] ?>
                                </td>

                                <td>
                                    <?= (int) $assignment["driver_id"] ?>
                                </td>

                                <td>
                                    <?= e(
                                        $assignment["driver_name"]
                                    ) ?>
                                </td>

                                <td>
                                    <?= e(
                                        $assignment["brand"]
                                    ) ?>

                                    <?= e(
                                        $assignment["model"]
                                    ) ?>
                                </td>

                                <td>
                                    <?= e(
                                        $assignment[
                                            "registration_number"
                                        ]
                                    ) ?>
                                </td>

                                <td>
                                    ₹<?= number_format(
                                        (float) $assignment["rent"],
                                        2
                                    ) ?>

                                    / day
                                </td>

                                <td>
                                    <?= e(
                                        $assignment[
                                            "assigned_at"
                                        ]
                                    ) ?>
                                </td>

                                <td>
                                    <?= e(
                                        $assignment["status"]
                                    ) ?>
                                </td>

                                <td>

                                    <form
                                        method="POST"
                                        onsubmit="return confirm('End this assignment and make the taxi available again?');"
                                    >

                                        <?= csrf_field() ?>

                                        <input
                                            type="hidden"
                                            name="assignment_id"
                                            value="<?= (int) $assignment["id"] ?>"
                                        >

                                        <button
                                            type="submit"
                                            name="end_assignment"
                                            value="1"
                                            class="end-button"
                                        >
                                            End Assignment
                                        </button>

                                    </form>

                                </td>

                            </tr>

                        <?php endwhile; ?>


                    </tbody>

                </table>

            </div>


        <?php else: ?>

            <p>
                No active assignments found.
            </p>

        <?php endif; ?>

    </section>


    <!-- =================================================
         ASSIGNMENT REQUEST HISTORY
         ================================================= -->

    <section class="history-section">

        <h2>
            Assignment Request History
        </h2>

        <p class="history-note">
            This history shows taxi requests that were
            approved or rejected.
        </p>


        <?php if (
            $history_result
            && mysqli_num_rows(
                $history_result
            ) > 0
        ): ?>


            <div class="table-container">

                <table class="history-table">

                    <thead>

                        <tr>

                            <th>
                                Request ID
                            </th>

                            <th>
                                Driver ID
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
                                Amount
                            </th>

                            <th>
                                Payment
                            </th>

                            <th>
                                Requested At
                            </th>

                            <th>
                                Approved At
                            </th>

                            <th>
                                Assigned At
                            </th>

                            <th>
                                Result
                            </th>

                        </tr>

                    </thead>


                    <tbody>


                        <?php while (
                            $history =
                            mysqli_fetch_assoc(
                                $history_result
                            )
                        ): ?>

                            <tr>

                                <td>
                                    <?= (int) $history["request_id"] ?>
                                </td>

                                <td>
                                    <?= (int) $history["driver_id"] ?>
                                </td>

                                <td>

                                    <?= e(
                                        $history["driver_name"]
                                    ) ?>

                                    <br>

                                    <small>
                                        <?= e(
                                            $history["driver_phone"]
                                        ) ?>
                                    </small>

                                </td>

                                <td>

                                    <?= e(
                                        $history["brand"]
                                    ) ?>

                                    <?= e(
                                        $history["model"]
                                    ) ?>

                                </td>

                                <td>
                                    <?= e(
                                        $history[
                                            "registration_number"
                                        ]
                                    ) ?>
                                </td>

                                <td>
                                    ₹<?= number_format(
                                        (float) $history["amount"],
                                        2
                                    ) ?>
                                </td>

                                <td
                                    class="<?= strtolower(
                                        e(
                                            $history[
                                                "payment_status"
                                            ]
                                        )
                                    ) ?>"
                                >

                                    <?= e(
                                        $history[
                                            "payment_status"
                                        ]
                                    ) ?>

                                </td>

                                <td>
                                    <?= e(
                                        $history[
                                            "requested_at"
                                        ]
                                    ) ?>
                                </td>

                                <td>

                                    <?php if (
                                        $history[
                                            "approved_at"
                                        ]
                                    ): ?>

                                        <?= e(
                                            $history[
                                                "approved_at"
                                            ]
                                        ) ?>

                                    <?php else: ?>

                                        -

                                    <?php endif; ?>

                                </td>

                                <td>

                                    <?php if (
                                        $history[
                                            "assigned_at"
                                        ]
                                    ): ?>

                                        <?= e(
                                            $history[
                                                "assigned_at"
                                            ]
                                        ) ?>

                                    <?php else: ?>

                                        -

                                    <?php endif; ?>

                                </td>

                                <td>

                                    <?php if (
                                        $history[
                                            "request_status"
                                        ] === "Approved"
                                    ): ?>

                                        <span
                                            class="history-approved"
                                        >
                                            Approved / Assigned
                                        </span>

                                    <?php else: ?>

                                        <span
                                            class="history-rejected"
                                        >
                                            Rejected
                                        </span>


                                        <?php if (
                                            !empty(
                                                $history[
                                                    "rejection_reason"
                                                ]
                                            )
                                        ): ?>

                                            <br>

                                            <small>
                                                Reason:
                                                <?= e(
                                                    $history[
                                                        "rejection_reason"
                                                    ]
                                                ) ?>
                                            </small>

                                        <?php endif; ?>

                                    <?php endif; ?>

                                </td>

                            </tr>

                        <?php endwhile; ?>


                    </tbody>

                </table>

            </div>


        <?php else: ?>

            <p>
                No assignment request history found.
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

function toggleReject(requestId) {

    const box =
        document.getElementById(
            "reject-" + requestId
        );

    if (!box) {
        return;
    }

    if (box.style.display === "none") {

        box.style.display = "block";

    } else {

        box.style.display = "none";
    }
}

</script>


</body>

</html>