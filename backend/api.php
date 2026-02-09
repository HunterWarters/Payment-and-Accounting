<?php

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Start session for authentication
session_start();

// Include configuration
require_once 'config.php';

// Database connection
$conn = getDatabaseConnection();

// API response handler
function sendResponse($success, $data = array(), $message = '', $statusCode = 200) {
    http_response_code($statusCode);
    $response = array(
        "success" => (bool)$success,
        "data" => $data,
        "message" => (string)$message,
        "timestamp" => date('Y-m-d H:i:s')
    );
    echo json_encode($response, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

// Get request data
$request_method = $_SERVER['REQUEST_METHOD'];
$request_data = json_decode(file_get_contents("php://input"), true);
if (!is_array($request_data)) {
    $request_data = $_GET;
}
$action = isset($request_data['action']) ? $request_data['action'] : '';

// =============================================
// AUTHENTICATION ENDPOINTS
// =============================================

if ($action === 'login' && $request_method === 'POST') {
    try {
        $username = isset($request_data['username']) ? $conn->real_escape_string($request_data['username']) : '';
        $password = isset($request_data['password']) ? $request_data['password'] : '';

        if (empty($username) || empty($password)) {
            sendResponse(false, array(), "Username and password required", 400);
        }

        // Check admin users
        $stmt = $conn->prepare("SELECT user_id, username, password_hash, user_type FROM users WHERE username = ? AND is_active = TRUE LIMIT 1");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();
            
            if (password_verify($password, $user['password_hash'])) {
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['user_type'];
                
                sendResponse(true, array(
                    "user_id" => $user['user_id'],
                    "username" => $user['username'],
                    "role" => $user['user_type']
                ), "Login successful");
            }
        }

        // Check student login
        $stmt = $conn->prepare("SELECT student_id, student_number, first_name, last_name, email FROM students WHERE student_number = ? LIMIT 1");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $student = $result->fetch_assoc();
            
            // For demo purposes, accept student_number as password
            if ($password === $student['student_number']) {
                $_SESSION['user_id'] = $student['student_id'];
                $_SESSION['username'] = $student['student_number'];
                $_SESSION['role'] = 'student';
                $_SESSION['student_id'] = $student['student_id'];
                $_SESSION['student_name'] = trim($student['first_name'] . ' ' . $student['last_name']);
                
                sendResponse(true, array(
                    "user_id" => $student['student_id'],
                    "username" => $student['student_number'],
                    "role" => "student",
                    "student_id" => $student['student_id'],
                    "name" => $_SESSION['student_name']
                ), "Login successful");
            }
        }

        sendResponse(false, array(), "Invalid credentials", 401);

    } catch (Exception $e) {
        sendResponse(false, array(), "Login error: " . $e->getMessage(), 500);
    }
}

if ($action === 'logout') {
    session_destroy();
    sendResponse(true, array(), "Logged out successfully");
}

if ($action === 'check_session') {
    if (isset($_SESSION['user_id'])) {
        sendResponse(true, array(
            "user_id" => $_SESSION['user_id'],
            "username" => $_SESSION['username'],
            "role" => $_SESSION['role'],
            "student_id" => isset($_SESSION['student_id']) ? $_SESSION['student_id'] : null
        ), "Session active");
    } else {
        sendResponse(false, array(), "No active session", 401);
    }
}

// =============================================
// DASHBOARD & STATISTICS ENDPOINTS
// =============================================

if ($action === 'get_dashboard_stats') {
    try {
        // Total fees assessed
        $result = $conn->query("
            SELECT COALESCE(SUM(total_assessment), 0) as total_fees 
            FROM student_assessments
        ");
        $totalFees = 0;
        if ($result && $row = $result->fetch_assoc()) {
            $totalFees = floatval($row['total_fees']);
        }

        // Total payments collected
        $result = $conn->query("
            SELECT COALESCE(SUM(amount_paid), 0) as total_payments 
            FROM payments
        ");
        $totalPayments = 0;
        if ($result && $row = $result->fetch_assoc()) {
            $totalPayments = floatval($row['total_payments']);
        }

        // Pending balance
        $result = $conn->query("
            SELECT 
                COALESCE(SUM(sa.net_amount), 0) as total_assessment,
                COALESCE(SUM(p.amount_paid), 0) as total_paid
            FROM student_assessments sa
            LEFT JOIN payments p ON sa.assessment_id = p.assessment_id
        ");
        $pendingBalance = 0;
        if ($result && $row = $result->fetch_assoc()) {
            $pendingBalance = floatval($row['total_assessment']) - floatval($row['total_paid']);
        }

        // Monthly revenue for the last 12 months
        $monthlyRevenue = array();
        $result = $conn->query("
            SELECT 
                DATE_FORMAT(payment_date, '%b %Y') as month,
                COALESCE(SUM(amount_paid), 0) as total
            FROM payments
            WHERE payment_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
            GROUP BY DATE_FORMAT(payment_date, '%Y-%m')
            ORDER BY payment_date ASC
        ");
        
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $monthlyRevenue[] = array(
                    "month" => $row['month'],
                    "total" => floatval($row['total'])
                );
            }
        }

        sendResponse(true, array(
            "total_fees" => $totalFees,
            "total_payments" => $totalPayments,
            "pending_balance" => $pendingBalance,
            "monthly_revenue" => $monthlyRevenue
        ), "Dashboard statistics retrieved successfully");

    } catch (Exception $e) {
        sendResponse(false, array(), "Error retrieving dashboard statistics: " . $e->getMessage(), 500);
    }
}

// =============================================
// STUDENT ENDPOINTS - FIXED SCHEMA
// =============================================

if ($action === 'get_student_details') {
    try {
        if (isset($_SESSION['role']) && $_SESSION['role'] === 'student') {
            $student_id = $_SESSION['student_id'];
        } else {
            $student_id = isset($request_data['student_id']) ? intval($request_data['student_id']) : 0;
        }

        if (!$student_id) {
            sendResponse(false, array(), "Student ID is required", 400);
        }

        $query = "
            SELECT 
                s.student_id,
                s.student_number,
                s.first_name,
                s.middle_name,
                s.last_name,
                s.email,
                s.contact_number,
                s.year_level,
                s.student_status,
                s.address,
                s.city,
                s.province,
                p.program_id,
                p.program_name,
                p.program_code
            FROM students s
            LEFT JOIN programs p ON s.program_id = p.program_id
            WHERE s.student_id = $student_id
            LIMIT 1
        ";

        $result = $conn->query($query);
        if (!$result || $result->num_rows === 0) {
            sendResponse(false, array(), "Student not found", 404);
        }

        $row = $result->fetch_assoc();
        
        sendResponse(true, array(
            "student" => array(
                "student_id" => intval($row['student_id']),
                "student_number" => $row['student_number'],
                "first_name" => $row['first_name'],
                "middle_name" => $row['middle_name'],
                "last_name" => $row['last_name'],
                "email" => $row['email'],
                "phone" => $row['contact_number'],
                "year_level" => $row['year_level'],
                "section" => null, // Not in schema
                "admission_type" => $row['student_status'],
                "program_id" => intval($row['program_id']),
                "program_name" => $row['program_name']
            )
        ), "Student details retrieved successfully");

    } catch (Exception $e) {
        sendResponse(false, array(), "Error retrieving student details: " . $e->getMessage(), 500);
    }
}

// =============================================
// ASSESSMENT ENDPOINTS - FIXED SCHEMA
// =============================================

if ($action === 'get_all_assessments') {
    try {
        $assessments = array();
        
        $query = "
            SELECT 
                sa.assessment_id,
                sa.total_assessment,
                sa.net_amount,
                s.student_id,
                s.student_number,
                s.first_name,
                s.last_name,
                p.program_name
            FROM student_assessments sa
            JOIN enrollments e ON sa.enrollment_id = e.enrollment_id
            JOIN students s ON e.student_id = s.student_id
            JOIN programs p ON s.program_id = p.program_id
            ORDER BY sa.created_at DESC
            LIMIT 100
        ";

        $result = $conn->query($query);
        if (!$result) {
            throw new Exception($conn->error);
        }

        while ($row = $result->fetch_assoc()) {
            // Calculate balance
            $paid = 0;
            $paymentResult = $conn->query("
                SELECT COALESCE(SUM(amount_paid), 0) as paid 
                FROM payments 
                WHERE assessment_id = " . intval($row['assessment_id'])
            );
            if ($paymentResult && $payRow = $paymentResult->fetch_assoc()) {
                $paid = floatval($payRow['paid']);
            }
            
            $balance = floatval($row['net_amount']) - $paid;
            $status = $balance <= 0 ? 'Paid' : 'Pending';

            $assessments[] = array(
                "assessment_id" => intval($row['assessment_id']),
                "student_id" => intval($row['student_id']),
                "student_number" => $row['student_number'],
                "student_name" => trim($row['first_name'] . ' ' . $row['last_name']),
                "course_name" => $row['program_name'],
                "total_assessment" => floatval($row['total_assessment']),
                "net_amount" => floatval($row['net_amount']),
                "amount_paid" => $paid,
                "balance" => $balance,
                "status" => $status
            );
        }

        sendResponse(true, $assessments, "Assessments retrieved successfully");

    } catch (Exception $e) {
        sendResponse(false, array(), "Error retrieving assessments: " . $e->getMessage(), 500);
    }
}

if ($action === 'get_assessment_details') {
    try {
        $assessment_id = isset($request_data['assessment_id']) ? intval($request_data['assessment_id']) : 0;

        if (!$assessment_id) {
            sendResponse(false, array(), "Assessment ID is required", 400);
        }

        $query = "
            SELECT 
                sa.*,
                s.student_id,
                s.student_number,
                s.first_name,
                s.last_name,
                p.program_name,
                e.period_id,
                ep.semester,
                ep.school_year
            FROM student_assessments sa
            JOIN enrollments e ON sa.enrollment_id = e.enrollment_id
            JOIN students s ON e.student_id = s.student_id
            JOIN programs p ON s.program_id = p.program_id
            JOIN enrollment_periods ep ON e.period_id = ep.period_id
            WHERE sa.assessment_id = $assessment_id
            LIMIT 1
        ";

        $result = $conn->query($query);
        if (!$result || $result->num_rows === 0) {
            sendResponse(false, array(), "Assessment not found", 404);
        }

        $row = $result->fetch_assoc();

        // Get payment total
        $paymentResult = $conn->query("
            SELECT COALESCE(SUM(amount_paid), 0) as total_paid 
            FROM payments 
            WHERE assessment_id = $assessment_id
        ");
        $total_paid = 0;
        if ($paymentResult && $payRow = $paymentResult->fetch_assoc()) {
            $total_paid = floatval($payRow['total_paid']);
        }

        // Get assessment fees breakdown
        $feesQuery = "
            SELECT 
                ad.amount,
                ft.fee_name,
                ft.fee_type_id
            FROM assessment_details ad
            JOIN fee_types ft ON ad.fee_type_id = ft.fee_type_id
            WHERE ad.assessment_id = $assessment_id
        ";
        
        $fees = array();
        $feesResult = $conn->query($feesQuery);
        if ($feesResult) {
            while ($feeRow = $feesResult->fetch_assoc()) {
                $fees[] = array(
                    "fee_type_id" => intval($feeRow['fee_type_id']),
                    "fee_name" => $feeRow['fee_name'],
                    "amount" => floatval($feeRow['amount'])
                );
            }
        }

        sendResponse(true, array(
            "assessment_id" => intval($row['assessment_id']),
            "enrollment_id" => intval($row['enrollment_id']),
            "student" => array(
                "student_id" => intval($row['student_id']),
                "student_number" => $row['student_number'],
                "name" => trim($row['first_name'] . ' ' . $row['last_name']),
                "program" => $row['program_name']
            ),
            "period" => array(
                "semester" => $row['semester'],
                "school_year" => $row['school_year']
            ),
            "total_assessment" => floatval($row['total_assessment']),
            "discount_amount" => floatval($row['discount_amount']),
            "net_amount" => floatval($row['net_amount']),
            "total_paid" => $total_paid,
            "balance" => floatval($row['net_amount']) - $total_paid,
            "fees" => $fees,
            "created_at" => $row['created_at']
        ), "Assessment details retrieved successfully");

    } catch (Exception $e) {
        sendResponse(false, array(), "Error retrieving assessment details: " . $e->getMessage(), 500);
    }
}

if ($action === 'create_assessment' && $request_method === 'POST') {
    try {
        $enrollment_id = isset($request_data['enrollment_id']) ? intval($request_data['enrollment_id']) : 0;
        $fees = isset($request_data['fees']) ? $request_data['fees'] : array();

        if (!$enrollment_id || empty($fees)) {
            sendResponse(false, array(), "Enrollment ID and fees are required", 400);
        }

        // Check if enrollment exists
        $checkResult = $conn->query("SELECT enrollment_id FROM enrollments WHERE enrollment_id = $enrollment_id");
        if (!$checkResult || $checkResult->num_rows === 0) {
            sendResponse(false, array(), "Enrollment not found", 404);
        }

        // Calculate totals
        $total_assessment = 0;
        $tuition_fee = 0;
        $misc_fees = 0;
        $total_units = 0;
        
        foreach ($fees as $fee) {
            $total_assessment += floatval($fee['amount']);
            if (isset($fee['is_tuition']) && $fee['is_tuition']) {
                $tuition_fee += floatval($fee['amount']);
            } else {
                $misc_fees += floatval($fee['amount']);
            }
        }

        // Get scholarship discounts if any
        $discount_amount = 0;
        $discountResult = $conn->query("
            SELECT 
                CASE 
                    WHEN sch.discount_percentage > 0 THEN ($total_assessment * sch.discount_percentage / 100)
                    ELSE sch.discount_amount
                END as discount
            FROM student_scholarships ss
            JOIN scholarships sch ON ss.scholarship_id = sch.scholarship_id
            JOIN enrollments e ON ss.student_id = e.student_id
            WHERE e.enrollment_id = $enrollment_id 
                AND ss.STATUS = 'Active'
                AND e.period_id = ss.period_id
            LIMIT 1
        ");
        
        if ($discountResult && $discRow = $discountResult->fetch_assoc()) {
            $discount_amount = floatval($discRow['discount']);
        }

        $net_amount = $total_assessment - $discount_amount;

        // Insert assessment
        $stmt = $conn->prepare("
            INSERT INTO student_assessments 
            (enrollment_id, total_units, tuition_fee, misc_fees, total_assessment, discount_amount, net_amount, assessment_date)
            VALUES (?, ?, ?, ?, ?, ?, ?, CURDATE())
        ");

        if (!$stmt) {
            throw new Exception($conn->error);
        }

        $stmt->bind_param("iiddddd", $enrollment_id, $total_units, $tuition_fee, $misc_fees, $total_assessment, $discount_amount, $net_amount);

        if (!$stmt->execute()) {
            throw new Exception($stmt->error);
        }

        $assessment_id = $stmt->insert_id;

        // Insert assessment details (fees)
        $feeStmt = $conn->prepare("
            INSERT INTO assessment_details (assessment_id, fee_type_id, amount)
            VALUES (?, ?, ?)
        ");

        foreach ($fees as $fee) {
            $fee_type_id = intval($fee['fee_type_id']);
            $amount = floatval($fee['amount']);
            $feeStmt->bind_param("iid", $assessment_id, $fee_type_id, $amount);
            $feeStmt->execute();
        }

        sendResponse(true, array(
            "assessment_id" => $assessment_id,
            "total_assessment" => $total_assessment,
            "discount_amount" => $discount_amount,
            "net_amount" => $net_amount
        ), "Assessment created successfully", 201);

    } catch (Exception $e) {
        sendResponse(false, array(), "Error creating assessment: " . $e->getMessage(), 500);
    }
}

// =============================================
// PAYMENT ENDPOINTS
// =============================================

if ($action === 'create_payment' && $request_method === 'POST') {
    try {
        $assessment_id = isset($request_data['assessment_id']) ? intval($request_data['assessment_id']) : 0;
        $amount_paid = isset($request_data['amount_paid']) ? floatval($request_data['amount_paid']) : 0;
        $payment_mode = isset($request_data['payment_mode']) ? $request_data['payment_mode'] : 'Cash';
        $received_by = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 1;
        $reference_number = isset($request_data['reference_number']) ? $request_data['reference_number'] : null;
        $remarks = isset($request_data['remarks']) ? $request_data['remarks'] : null;

        if (!$assessment_id || $amount_paid <= 0) {
            sendResponse(false, array(), "Invalid assessment ID or amount", 400);
        }

        // Check if assessment exists and get balance
        $checkResult = $conn->query("
            SELECT 
                sa.net_amount,
                COALESCE(SUM(p.amount_paid), 0) as total_paid
            FROM student_assessments sa
            LEFT JOIN payments p ON sa.assessment_id = p.assessment_id
            WHERE sa.assessment_id = $assessment_id
            GROUP BY sa.assessment_id
        ");
        
        if (!$checkResult || $checkResult->num_rows === 0) {
            sendResponse(false, array(), "Assessment not found", 404);
        }

        $row = $checkResult->fetch_assoc();
        $balance = floatval($row['net_amount']) - floatval($row['total_paid']);

        if ($amount_paid > $balance) {
            sendResponse(false, array(), "Payment amount exceeds balance due (₱" . number_format($balance, 2) . ")", 400);
        }

        // Generate OR Number
        $or_number = "RCP" . date('Ymd') . str_pad(rand(1, 99999), 5, '0', STR_PAD_LEFT);

        // Insert payment
        $stmt = $conn->prepare("
            INSERT INTO payments 
            (assessment_id, or_number, payment_date, amount_paid, payment_mode, received_by, reference_number, remarks)
            VALUES (?, ?, CURDATE(), ?, ?, ?, ?, ?)
        ");

        if (!$stmt) {
            throw new Exception($conn->error);
        }

        $stmt->bind_param("isdsiss", $assessment_id, $or_number, $amount_paid, $payment_mode, $received_by, $reference_number, $remarks);

        if (!$stmt->execute()) {
            throw new Exception($stmt->error);
        }

        $new_balance = $balance - $amount_paid;

        sendResponse(true, array(
            "payment_id" => $stmt->insert_id,
            "or_number" => $or_number,
            "amount_paid" => $amount_paid,
            "new_balance" => $new_balance
        ), "Payment recorded successfully", 201);

    } catch (Exception $e) {
        sendResponse(false, array(), "Error recording payment: " . $e->getMessage(), 500);
    }
}

if ($action === 'get_payment_history') {
    try {
        if (isset($_SESSION['role']) && $_SESSION['role'] === 'student') {
            $student_id = $_SESSION['student_id'];
        } else {
            $student_id = isset($request_data['student_id']) ? intval($request_data['student_id']) : 0;
        }

        $payments = array();
        
        $query = "
            SELECT 
                p.payment_id,
                p.or_number,
                p.payment_date,
                p.amount_paid,
                p.payment_mode,
                p.reference_number,
                p.remarks,
                s.student_number,
                s.first_name,
                s.last_name,
                u.username as received_by_name
            FROM payments p
            JOIN student_assessments sa ON p.assessment_id = sa.assessment_id
            JOIN enrollments e ON sa.enrollment_id = e.enrollment_id
            JOIN students s ON e.student_id = s.student_id
            LEFT JOIN users u ON p.received_by = u.user_id
        ";

        if ($student_id > 0) {
            $query .= " WHERE s.student_id = $student_id";
        }

        $query .= " ORDER BY p.payment_date DESC, p.payment_id DESC LIMIT 100";

        $result = $conn->query($query);
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $payments[] = array(
                    "payment_id" => intval($row['payment_id']),
                    "or_number" => $row['or_number'],
                    "student_number" => $row['student_number'],
                    "student_name" => trim($row['first_name'] . ' ' . $row['last_name']),
                    "payment_date" => $row['payment_date'],
                    "amount_paid" => floatval($row['amount_paid']),
                    "payment_mode" => $row['payment_mode'],
                    "reference_number" => $row['reference_number'],
                    "remarks" => $row['remarks'],
                    "received_by_name" => $row['received_by_name']
                );
            }
        }

        sendResponse(true, $payments, "Payment history retrieved successfully");

    } catch (Exception $e) {
        sendResponse(false, array(), "Error retrieving payment history: " . $e->getMessage(), 500);
    }
}

if ($action === 'get_payment_details') {
    try {
        $payment_id = isset($request_data['payment_id']) ? intval($request_data['payment_id']) : 0;

        if (!$payment_id) {
            sendResponse(false, array(), "Payment ID is required", 400);
        }

        $query = "
            SELECT 
                p.*,
                s.student_id,
                s.student_number,
                s.first_name,
                s.last_name,
                s.email,
                prog.program_name,
                sa.assessment_id,
                sa.net_amount,
                u.username as received_by_name
            FROM payments p
            JOIN student_assessments sa ON p.assessment_id = sa.assessment_id
            JOIN enrollments e ON sa.enrollment_id = e.enrollment_id
            JOIN students s ON e.student_id = s.student_id
            JOIN programs prog ON s.program_id = prog.program_id
            LEFT JOIN users u ON p.received_by = u.user_id
            WHERE p.payment_id = $payment_id
            LIMIT 1
        ";

        $result = $conn->query($query);
        if (!$result || $result->num_rows === 0) {
            sendResponse(false, array(), "Payment not found", 404);
        }

        $row = $result->fetch_assoc();

        if (isset($_SESSION['role']) && $_SESSION['role'] === 'student' && intval($row['student_id']) !== $_SESSION['student_id']) {
            sendResponse(false, array(), "Access denied", 403);
        }

        sendResponse(true, array(
            "payment_id" => intval($row['payment_id']),
            "or_number" => $row['or_number'],
            "payment_date" => $row['payment_date'],
            "amount_paid" => floatval($row['amount_paid']),
            "payment_mode" => $row['payment_mode'],
            "reference_number" => $row['reference_number'],
            "remarks" => $row['remarks'],
            "received_by" => $row['received_by_name'],
            "student" => array(
                "student_id" => intval($row['student_id']),
                "student_number" => $row['student_number'],
                "name" => trim($row['first_name'] . ' ' . $row['last_name']),
                "email" => $row['email'],
                "program" => $row['program_name']
            ),
            "assessment_id" => intval($row['assessment_id'])
        ), "Payment details retrieved successfully");

    } catch (Exception $e) {
        sendResponse(false, array(), "Error retrieving payment details: " . $e->getMessage(), 500);
    }
}

if ($action === 'get_payment_report') {
    try {
        $date_from = isset($request_data['date_from']) ? $request_data['date_from'] : '';
        $date_to = isset($request_data['date_to']) ? $request_data['date_to'] : '';
        $payment_mode = isset($request_data['payment_mode']) ? $request_data['payment_mode'] : '';

        $query = "
            SELECT 
                p.payment_id,
                p.or_number,
                p.payment_date,
                p.amount_paid,
                p.payment_mode,
                p.reference_number,
                s.student_number,
                s.first_name,
                s.last_name,
                prog.program_name,
                u.username as received_by
            FROM payments p
            JOIN student_assessments sa ON p.assessment_id = sa.assessment_id
            JOIN enrollments e ON sa.enrollment_id = e.enrollment_id
            JOIN students s ON e.student_id = s.student_id
            JOIN programs prog ON s.program_id = prog.program_id
            LEFT JOIN users u ON p.received_by = u.user_id
            WHERE 1=1
        ";

        if (!empty($date_from)) {
            $date_from = $conn->real_escape_string($date_from);
            $query .= " AND p.payment_date >= '$date_from'";
        }

        if (!empty($date_to)) {
            $date_to = $conn->real_escape_string($date_to);
            $query .= " AND p.payment_date <= '$date_to'";
        }

        if (!empty($payment_mode)) {
            $payment_mode = $conn->real_escape_string($payment_mode);
            $query .= " AND p.payment_mode = '$payment_mode'";
        }

        $query .= " ORDER BY p.payment_date DESC, p.payment_id DESC";

        $payments = array();
        $total_amount = 0;

        $result = $conn->query($query);
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $amount = floatval($row['amount_paid']);
                $total_amount += $amount;

                $payments[] = array(
                    "payment_id" => intval($row['payment_id']),
                    "or_number" => $row['or_number'],
                    "payment_date" => $row['payment_date'],
                    "amount_paid" => $amount,
                    "payment_mode" => $row['payment_mode'],
                    "reference_number" => $row['reference_number'],
                    "student_number" => $row['student_number'],
                    "student_name" => trim($row['first_name'] . ' ' . $row['last_name']),
                    "program" => $row['program_name'],
                    "received_by" => $row['received_by']
                );
            }
        }

        sendResponse(true, array(
            "payments" => $payments,
            "summary" => array(
                "total_transactions" => count($payments),
                "total_amount" => $total_amount,
                "date_from" => $date_from ?: 'All time',
                "date_to" => $date_to ?: 'All time',
                "payment_mode" => $payment_mode ?: 'All modes'
            )
        ), "Payment report generated successfully");

    } catch (Exception $e) {
        sendResponse(false, array(), "Error generating payment report: " . $e->getMessage(), 500);
    }
}

// =============================================
// BILLING ENDPOINTS
// =============================================

if ($action === 'get_billing_summary') {
    try {
        $result = $conn->query("
            SELECT 
                COALESCE(SUM(sa.net_amount), 0) as total_assessment,
                COALESCE(SUM(p.amount_paid), 0) as total_paid
            FROM student_assessments sa
            LEFT JOIN payments p ON sa.assessment_id = p.assessment_id
        ");

        if (!$result) {
            throw new Exception($conn->error);
        }

        $row = $result->fetch_assoc();
        $total_assessment = floatval($row['total_assessment']);
        $total_paid = floatval($row['total_paid']);
        $balance_due = $total_assessment - $total_paid;

        sendResponse(true, array(
            "total_assessment" => $total_assessment,
            "total_paid" => $total_paid,
            "balance_due" => $balance_due,
            "collection_rate" => $total_assessment > 0 ? round(($total_paid / $total_assessment) * 100, 2) : 0
        ), "Billing summary retrieved successfully");

    } catch (Exception $e) {
        sendResponse(false, array(), "Error retrieving billing summary: " . $e->getMessage(), 500);
    }
}

if ($action === 'get_student_billing') {
    try {
        if (isset($_SESSION['role']) && $_SESSION['role'] === 'student') {
            $student_id = $_SESSION['student_id'];
        } else {
            $student_id = isset($request_data['student_id']) ? intval($request_data['student_id']) : 0;
        }

        if (!$student_id) {
            sendResponse(false, array(), "Student ID is required", 400);
        }

        $query = "
            SELECT 
                s.student_id,
                s.student_number,
                s.first_name,
                s.last_name,
                p.program_name,
                sa.assessment_id,
                sa.net_amount,
                COALESCE(SUM(py.amount_paid), 0) as amount_paid
            FROM students s
            JOIN programs p ON s.program_id = p.program_id
            JOIN enrollments e ON s.student_id = e.student_id
            JOIN student_assessments sa ON e.enrollment_id = sa.enrollment_id
            LEFT JOIN payments py ON sa.assessment_id = py.assessment_id
            WHERE s.student_id = $student_id
            GROUP BY sa.assessment_id
            ORDER BY sa.created_at DESC
        ";

        $result = $conn->query($query);
        if (!$result || $result->num_rows === 0) {
            sendResponse(false, array(), "Student not found", 404);
        }

        $student = null;
        $billings = array();
        $totalAssessment = 0;
        $totalPaid = 0;

        while ($row = $result->fetch_assoc()) {
            if (!$student) {
                $student = array(
                    "student_id" => intval($row['student_id']),
                    "student_number" => $row['student_number'],
                    "name" => trim($row['first_name'] . ' ' . $row['last_name']),
                    "program" => $row['program_name']
                );
            }

            $net = floatval($row['net_amount']);
            $paid = floatval($row['amount_paid']);
            $balance = $net - $paid;

            $billings[] = array(
                "assessment_id" => intval($row['assessment_id']),
                "net_amount" => $net,
                "amount_paid" => $paid,
                "balance" => $balance
            );

            $totalAssessment += $net;
            $totalPaid += $paid;
        }

        sendResponse(true, array(
            "student" => $student,
            "billings" => $billings,
            "summary" => array(
                "total_assessment" => $totalAssessment,
                "total_paid" => $totalPaid,
                "total_balance" => $totalAssessment - $totalPaid
            )
        ), "Student billing retrieved successfully");

    } catch (Exception $e) {
        sendResponse(false, array(), "Error retrieving billing: " . $e->getMessage(), 500);
    }
}

if ($action === 'get_fee_types') {
    try {
        $feeTypes = array();
        
        $result = $conn->query("
            SELECT 
                fee_type_id,
                fee_name,
                base_amount,
                fee_description
            FROM fee_types
            WHERE is_active = TRUE
            ORDER BY fee_name ASC
        ");

        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $feeTypes[] = array(
                    "fee_type_id" => intval($row['fee_type_id']),
                    "fee_name" => $row['fee_name'],
                    "base_amount" => floatval($row['base_amount']),
                    "description" => $row['fee_description']
                );
            }
        }

        sendResponse(true, $feeTypes, "Fee types retrieved successfully");

    } catch (Exception $e) {
        sendResponse(false, array(), "Error retrieving fee types: " . $e->getMessage(), 500);
    }
}

// =============================================
// SCHOLARSHIP ENDPOINTS
// =============================================

if ($action === 'get_scholarships') {
    try {
        $scholarships = array();
        
        $result = $conn->query("
            SELECT 
                sch.scholarship_id,
                sch.scholarship_name,
                sch.scholarship_type,
                sch.discount_percentage,
                sch.discount_amount,
                sch.requirements,
                COUNT(DISTINCT ss.student_id) as active_recipients
            FROM scholarships sch
            LEFT JOIN student_scholarships ss ON sch.scholarship_id = ss.scholarship_id 
                AND ss.STATUS = 'Active'
            WHERE sch.is_active = TRUE
            GROUP BY sch.scholarship_id
            ORDER BY sch.scholarship_name ASC
        ");

        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $scholarships[] = array(
                    "scholarship_id" => intval($row['scholarship_id']),
                    "scholarship_name" => $row['scholarship_name'],
                    "scholarship_type" => $row['scholarship_type'],
                    "discount_percentage" => floatval($row['discount_percentage']),
                    "discount_amount" => floatval($row['discount_amount']),
                    "description" => $row['requirements'],
                    "active_recipients" => intval($row['active_recipients'])
                );
            }
        }

        sendResponse(true, $scholarships, "Scholarships retrieved successfully");

    } catch (Exception $e) {
        sendResponse(false, array(), "Error retrieving scholarships: " . $e->getMessage(), 500);
    }
}

if ($action === 'get_scholarship_details') {
    try {
        $scholarship_id = isset($request_data['scholarship_id']) ? intval($request_data['scholarship_id']) : 0;

        if (!$scholarship_id) {
            sendResponse(false, array(), "Scholarship ID is required", 400);
        }

        $query = "
            SELECT 
                sch.scholarship_id,
                sch.scholarship_name,
                sch.scholarship_type,
                sch.discount_percentage,
                sch.discount_amount,
                sch.requirements,
                sch.is_active,
                COUNT(DISTINCT ss.student_id) as active_recipients
            FROM scholarships sch
            LEFT JOIN student_scholarships ss ON sch.scholarship_id = ss.scholarship_id 
                AND ss.STATUS = 'Active'
            WHERE sch.scholarship_id = $scholarship_id
            GROUP BY sch.scholarship_id
            LIMIT 1
        ";

        $result = $conn->query($query);
        if (!$result || $result->num_rows === 0) {
            sendResponse(false, array(), "Scholarship not found", 404);
        }

        $row = $result->fetch_assoc();

        sendResponse(true, array(
            "scholarship_id" => intval($row['scholarship_id']),
            "scholarship_name" => $row['scholarship_name'],
            "scholarship_type" => $row['scholarship_type'],
            "discount_percentage" => floatval($row['discount_percentage']),
            "discount_amount" => floatval($row['discount_amount']),
            "description" => $row['requirements'],
            "requirements" => $row['requirements'],
            "is_active" => boolval($row['is_active']),
            "active_recipients" => intval($row['active_recipients'])
        ), "Scholarship details retrieved successfully");

    } catch (Exception $e) {
        sendResponse(false, array(), "Error retrieving scholarship details: " . $e->getMessage(), 500);
    }
}

if ($action === 'create_scholarship' && $request_method === 'POST') {
    try {
        $scholarship_name = isset($request_data['scholarship_name']) ? $request_data['scholarship_name'] : '';
        $scholarship_type = isset($request_data['scholarship_type']) ? $request_data['scholarship_type'] : '';
        $discount_percentage = isset($request_data['discount_percentage']) ? floatval($request_data['discount_percentage']) : 0;
        $discount_amount = isset($request_data['discount_amount']) ? floatval($request_data['discount_amount']) : 0;
        $requirements = isset($request_data['requirements']) ? $request_data['requirements'] : '';

        if (empty($scholarship_name) || empty($scholarship_type)) {
            sendResponse(false, array(), "Scholarship name and type are required", 400);
        }

        if ($discount_percentage <= 0 && $discount_amount <= 0) {
            sendResponse(false, array(), "Either discount percentage or discount amount must be greater than 0", 400);
        }

        $stmt = $conn->prepare("
            INSERT INTO scholarships 
            (scholarship_name, scholarship_type, discount_percentage, discount_amount, requirements, is_active)
            VALUES (?, ?, ?, ?, ?, TRUE)
        ");

        if (!$stmt) {
            throw new Exception($conn->error);
        }

        $stmt->bind_param("ssdds", $scholarship_name, $scholarship_type, $discount_percentage, $discount_amount, $requirements);

        if (!$stmt->execute()) {
            throw new Exception($stmt->error);
        }

        sendResponse(true, array(
            "scholarship_id" => $stmt->insert_id,
            "scholarship_name" => $scholarship_name
        ), "Scholarship created successfully", 201);

    } catch (Exception $e) {
        sendResponse(false, array(), "Error creating scholarship: " . $e->getMessage(), 500);
    }
}

if ($action === 'update_scholarship' && $request_method === 'POST') {
    try {
        $scholarship_id = isset($request_data['scholarship_id']) ? intval($request_data['scholarship_id']) : 0;
        $scholarship_name = isset($request_data['scholarship_name']) ? $request_data['scholarship_name'] : '';
        $scholarship_type = isset($request_data['scholarship_type']) ? $request_data['scholarship_type'] : '';
        $discount_percentage = isset($request_data['discount_percentage']) ? floatval($request_data['discount_percentage']) : 0;
        $discount_amount = isset($request_data['discount_amount']) ? floatval($request_data['discount_amount']) : 0;
        $requirements = isset($request_data['requirements']) ? $request_data['requirements'] : '';

        if (!$scholarship_id || empty($scholarship_name) || empty($scholarship_type)) {
            sendResponse(false, array(), "Scholarship ID, name and type are required", 400);
        }

        $stmt = $conn->prepare("
            UPDATE scholarships 
            SET scholarship_name = ?, 
                scholarship_type = ?, 
                discount_percentage = ?, 
                discount_amount = ?, 
                requirements = ?
            WHERE scholarship_id = ?
        ");

        if (!$stmt) {
            throw new Exception($conn->error);
        }

        $stmt->bind_param("ssddsi", $scholarship_name, $scholarship_type, $discount_percentage, $discount_amount, $requirements, $scholarship_id);

        if (!$stmt->execute()) {
            throw new Exception($stmt->error);
        }

        if ($stmt->affected_rows === 0) {
            sendResponse(false, array(), "Scholarship not found or no changes made", 404);
        }

        sendResponse(true, array(
            "scholarship_id" => $scholarship_id
        ), "Scholarship updated successfully");

    } catch (Exception $e) {
        sendResponse(false, array(), "Error updating scholarship: " . $e->getMessage(), 500);
    }
}

if ($action === 'assign_scholarship' && $request_method === 'POST') {
    try {
        $student_id = isset($request_data['student_id']) ? intval($request_data['student_id']) : 0;
        $scholarship_id = isset($request_data['scholarship_id']) ? intval($request_data['scholarship_id']) : 0;
        $period_id = isset($request_data['period_id']) ? intval($request_data['period_id']) : 0;

        if (!$student_id || !$scholarship_id || !$period_id) {
            sendResponse(false, array(), "Student ID, Scholarship ID, and Period ID are required", 400);
        }

        // Check if already assigned
        $checkResult = $conn->query("
            SELECT student_scholarship_id 
            FROM student_scholarships 
            WHERE student_id = $student_id 
                AND scholarship_id = $scholarship_id 
                AND period_id = $period_id 
                AND STATUS = 'Active'
        ");

        if ($checkResult && $checkResult->num_rows > 0) {
            sendResponse(false, array(), "Student already has this scholarship for the selected period", 400);
        }

        $stmt = $conn->prepare("
            INSERT INTO student_scholarships 
            (student_id, scholarship_id, period_id, STATUS, grant_date)
            VALUES (?, ?, ?, 'Active', CURDATE())
        ");

        if (!$stmt) {
            throw new Exception($conn->error);
        }

        $stmt->bind_param("iii", $student_id, $scholarship_id, $period_id);

        if (!$stmt->execute()) {
            throw new Exception($stmt->error);
        }

        sendResponse(true, array(
            "student_scholarship_id" => $stmt->insert_id
        ), "Scholarship assigned successfully", 201);

    } catch (Exception $e) {
        sendResponse(false, array(), "Error assigning scholarship: " . $e->getMessage(), 500);
    }
}

if ($action === 'get_student_scholarships') {
    try {
        if (isset($_SESSION['role']) && $_SESSION['role'] === 'student') {
            $student_id = $_SESSION['student_id'];
        } else {
            $student_id = isset($request_data['student_id']) ? intval($request_data['student_id']) : 0;
        }

        if (!$student_id) {
            sendResponse(false, array(), "Student ID is required", 400);
        }

        $query = "
            SELECT 
                ss.student_scholarship_id,
                ss.grant_date,
                ss.expiry_date,
                ss.STATUS,
                sch.scholarship_id,
                sch.scholarship_name,
                sch.scholarship_type,
                sch.discount_percentage,
                sch.discount_amount,
                ep.semester,
                ep.school_year
            FROM student_scholarships ss
            JOIN scholarships sch ON ss.scholarship_id = sch.scholarship_id
            JOIN enrollment_periods ep ON ss.period_id = ep.period_id
            WHERE ss.student_id = $student_id
            ORDER BY ss.grant_date DESC
        ";

        $scholarships = array();
        $result = $conn->query($query);
        
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $scholarships[] = array(
                    "student_scholarship_id" => intval($row['student_scholarship_id']),
                    "scholarship_id" => intval($row['scholarship_id']),
                    "scholarship_name" => $row['scholarship_name'],
                    "scholarship_type" => $row['scholarship_type'],
                    "discount_percentage" => floatval($row['discount_percentage']),
                    "discount_amount" => floatval($row['discount_amount']),
                    "granted_date" => $row['grant_date'],
                    "expiry_date" => $row['expiry_date'],
                    "status" => $row['STATUS'],
                    "remarks" => null,
                    "period" => array(
                        "semester" => $row['semester'],
                        "school_year" => $row['school_year']
                    )
                );
            }
        }

        sendResponse(true, $scholarships, "Student scholarships retrieved successfully");

    } catch (Exception $e) {
        sendResponse(false, array(), "Error retrieving student scholarships: " . $e->getMessage(), 500);
    }
}

// =============================================
// ACCOUNT STATEMENT ENDPOINTS
// =============================================

if ($action === 'get_account_statement') {
    try {
        if (isset($_SESSION['role']) && $_SESSION['role'] === 'student') {
            $student_id = $_SESSION['student_id'];
        } else {
            $student_id = isset($request_data['student_id']) ? intval($request_data['student_id']) : 0;
        }
        
        $period_id = isset($request_data['period_id']) ? intval($request_data['period_id']) : null;

        if (!$student_id) {
            sendResponse(false, array(), "Student ID is required", 400);
        }

        // Get student info
        $studentQuery = "
            SELECT 
                s.student_id,
                s.student_number,
                s.first_name,
                s.last_name,
                p.program_name,
                s.year_level,
                ep.semester,
                ep.school_year
            FROM students s
            JOIN programs p ON s.program_id = p.program_id
            JOIN enrollments e ON s.student_id = e.student_id
            JOIN enrollment_periods ep ON e.period_id = ep.period_id
            WHERE s.student_id = $student_id
        ";

        if ($period_id) {
            $studentQuery .= " AND ep.period_id = $period_id";
        }

        $studentQuery .= " LIMIT 1";

        $result = $conn->query($studentQuery);
        if (!$result || $result->num_rows === 0) {
            sendResponse(false, array(), "Student or enrollment not found", 404);
        }

        $studentRow = $result->fetch_assoc();

        $student_info = array(
            "student_id" => intval($studentRow['student_id']),
            "student_number" => $studentRow['student_number'],
            "name" => trim($studentRow['first_name'] . ' ' . $studentRow['last_name']),
            "program" => $studentRow['program_name'],
            "year" => $studentRow['year_level'],
            "semester" => $studentRow['semester'],
            "school_year" => $studentRow['school_year']
        );

        // Get transactions
        $transQuery = "
            SELECT 
                DATE_FORMAT(sa.created_at, '%Y-%m-%d') as date,
                'Assessment' as description,
                sa.net_amount as charges,
                0 as payments,
                sa.net_amount as balance
            FROM student_assessments sa
            WHERE sa.enrollment_id IN (
                SELECT enrollment_id FROM enrollments WHERE student_id = $student_id
            )
            
            UNION ALL
            
            SELECT 
                DATE_FORMAT(p.payment_date, '%Y-%m-%d') as date,
                CONCAT('Payment - ', p.or_number) as description,
                0 as charges,
                p.amount_paid as payments,
                0 as balance
            FROM payments p
            WHERE p.assessment_id IN (
                SELECT assessment_id FROM student_assessments 
                WHERE enrollment_id IN (
                    SELECT enrollment_id FROM enrollments WHERE student_id = $student_id
                )
            )
            ORDER BY date ASC
        ";

        $transactions = array();
        $running_balance = 0;
        
        $result = $conn->query($transQuery);
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $charges = floatval($row['charges']);
                $payments = floatval($row['payments']);
                $running_balance += $charges - $payments;
                
                $transactions[] = array(
                    "date" => $row['date'],
                    "description" => $row['description'],
                    "charges" => $charges,
                    "payments" => $payments,
                    "balance" => $running_balance
                );
            }
        }

        sendResponse(true, array(
            "student_info" => $student_info,
            "transactions" => $transactions,
            "summary" => array(
                "total_charges" => array_sum(array_column($transactions, 'charges')),
                "total_payments" => array_sum(array_column($transactions, 'payments')),
                "current_balance" => $running_balance
            )
        ), "Account statement retrieved successfully");

    } catch (Exception $e) {
        sendResponse(false, array(), "Error retrieving account statement: " . $e->getMessage(), 500);
    }
}

// =============================================
// ENROLLMENT PERIODS
// =============================================

if ($action === 'get_enrollment_periods') {
    try {
        $periods = array();
        
        $result = $conn->query("
            SELECT 
                period_id,
                school_year,
                semester,
                is_active
            FROM enrollment_periods
            ORDER BY school_year DESC, semester DESC
        ");

        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $periods[] = array(
                    "period_id" => intval($row['period_id']),
                    "school_year" => $row['school_year'],
                    "semester" => $row['semester'],
                    "is_active" => boolval($row['is_active'])
                );
            }
        }

        sendResponse(true, $periods, "Periods retrieved successfully");

    } catch (Exception $e) {
        sendResponse(false, array(), "Error retrieving periods: " . $e->getMessage(), 500);
    }
}

// =============================================
// STUDENT SEARCH
// =============================================

if ($action === 'search_students') {
    try {
        $search_term = isset($request_data['search_term']) ? $request_data['search_term'] : '';
        
        if (strlen($search_term) < 2) {
            sendResponse(false, array(), "Search term too short", 400);
        }

        $students = array();
        $searchTerm = "%" . $conn->real_escape_string($search_term) . "%";

        $result = $conn->query("
            SELECT 
                s.student_id,
                s.student_number,
                s.first_name,
                s.last_name,
                p.program_name,
                s.year_level
            FROM students s
            JOIN programs p ON s.program_id = p.program_id
            WHERE s.student_number LIKE '$searchTerm' 
               OR CONCAT(s.first_name, ' ', s.last_name) LIKE '$searchTerm'
            LIMIT 20
        ");

        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $students[] = array(
                    "student_id" => intval($row['student_id']),
                    "student_number" => $row['student_number'],
                    "name" => trim($row['first_name'] . ' ' . $row['last_name']),
                    "program" => $row['program_name'],
                    "year_level" => $row['year_level'],
                    "section" => null
                );
            }
        }

        sendResponse(true, $students, "Search results retrieved");

    } catch (Exception $e) {
        sendResponse(false, array(), "Error searching: " . $e->getMessage(), 500);
    }
}

// =============================================
// COLLECTION SUMMARY
// =============================================

if ($action === 'get_collection_summary') {
    try {
        $result = $conn->query("
            SELECT 
                COUNT(DISTINCT s.student_id) as total_students,
                COALESCE(SUM(sa.net_amount), 0) as total_assessment,
                COALESCE(SUM(p.amount_paid), 0) as total_collected
            FROM students s
            LEFT JOIN enrollments e ON s.student_id = e.student_id
            LEFT JOIN student_assessments sa ON e.enrollment_id = sa.enrollment_id
            LEFT JOIN payments p ON sa.assessment_id = p.assessment_id
        ");

        if (!$result) {
            throw new Exception($conn->error);
        }

        $row = $result->fetch_assoc();
        $total_students = intval($row['total_students']);
        $total_assessment = floatval($row['total_assessment']);
        $total_collected = floatval($row['total_collected']);
        $total_outstanding = $total_assessment - $total_collected;
        
        // Count students who paid
        $paidResult = $conn->query("
            SELECT COUNT(DISTINCT s.student_id) as paid_students
            FROM students s
            WHERE s.student_id IN (
                SELECT DISTINCT e.student_id FROM enrollments e
                JOIN student_assessments sa ON e.enrollment_id = sa.enrollment_id
                JOIN payments p ON sa.assessment_id = p.assessment_id
            )
        ");
        
        $paid_students = 0;
        if ($paidResult && $payRow = $paidResult->fetch_assoc()) {
            $paid_students = intval($payRow['paid_students']);
        }

        sendResponse(true, array(
            "total_students" => $total_students,
            "total_assessment" => $total_assessment,
            "total_collected" => $total_collected,
            "total_outstanding" => $total_outstanding,
            "paid_students" => $paid_students,
            "collection_rate" => $total_assessment > 0 ? round(($total_collected / $total_assessment) * 100, 2) : 0
        ), "Collection summary retrieved");

    } catch (Exception $e) {
        sendResponse(false, array(), "Error retrieving summary: " . $e->getMessage(), 500);
    }
}

// =============================================
// DEFAULT: No Action Provided
// =============================================

sendResponse(false, array(), "No valid action provided", 400);
?>