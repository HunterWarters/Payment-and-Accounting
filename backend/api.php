<?php

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

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
// DASHBOARD & STATISTICS ENDPOINTS
// =============================================

/**
 * GET: Dashboard Statistics
 * Returns total fees, payments, pending balance, and monthly revenue
 */
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
            "monthly_revenue" => $monthlyRevenue,
            "paid_students" => 0,
            "total_students" => 0
        ), "Dashboard statistics retrieved successfully");

    } catch (Exception $e) {
        sendResponse(false, array(), "Error retrieving dashboard statistics: " . $e->getMessage(), 500);
    }
}

// =============================================
// STUDENT ENDPOINTS
// =============================================

/**
 * GET: Student Details (NEWLY ADDED - WAS MISSING)
 */
if ($action === 'get_student_details') {
    try {
        $student_id = isset($request_data['student_id']) ? intval($request_data['student_id']) : 0;

        if (!$student_id) {
            sendResponse(false, array(), "Student ID is required", 400);
        }

        $query = "
            SELECT 
                s.student_id,
                s.student_number,
                s.first_name,
                s.last_name,
                s.email,
                s.phone,
                s.year_level,
                s.section,
                s.admission_type,
                p.program_id,
                p.program_name
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
                "last_name" => $row['last_name'],
                "email" => $row['email'],
                "phone" => $row['phone'],
                "year_level" => $row['year_level'],
                "section" => $row['section'],
                "admission_type" => $row['admission_type'],
                "program_id" => intval($row['program_id']),
                "program_name" => $row['program_name']
            )
        ), "Student details retrieved successfully");

    } catch (Exception $e) {
        sendResponse(false, array(), "Error retrieving student details: " . $e->getMessage(), 500);
    }
}

// =============================================
// ASSESSMENT ENDPOINTS
// =============================================

/**
 * GET: All Assessments
 */
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
            LIMIT 10
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

// =============================================
// PAYMENT ENDPOINTS
// =============================================

/**
 * POST: Create Payment
 */
if ($action === 'create_payment' && $request_method === 'POST') {
    try {
        $assessment_id = isset($request_data['assessment_id']) ? intval($request_data['assessment_id']) : 0;
        $amount_paid = isset($request_data['amount_paid']) ? floatval($request_data['amount_paid']) : 0;
        $payment_mode = isset($request_data['payment_mode']) ? $request_data['payment_mode'] : 'Cash';
        $received_by = isset($request_data['received_by']) ? intval($request_data['received_by']) : 1;

        if (!$assessment_id || $amount_paid <= 0) {
            sendResponse(false, array(), "Invalid assessment ID or amount", 400);
        }

        // Check if assessment exists
        $checkResult = $conn->query("SELECT net_amount FROM student_assessments WHERE assessment_id = $assessment_id");
        if (!$checkResult || $checkResult->num_rows === 0) {
            sendResponse(false, array(), "Assessment not found", 404);
        }

        // Generate OR Number
        $or_number = "RCP" . date('Ymd') . str_pad(rand(1, 99999), 5, '0', STR_PAD_LEFT);

        // Insert payment
        $stmt = $conn->prepare("
            INSERT INTO payments 
            (assessment_id, or_number, payment_date, amount_paid, payment_mode, received_by)
            VALUES (?, ?, CURDATE(), ?, ?, ?)
        ");

        if (!$stmt) {
            throw new Exception($conn->error);
        }

        $stmt->bind_param("isdsi", $assessment_id, $or_number, $amount_paid, $payment_mode, $received_by);

        if (!$stmt->execute()) {
            throw new Exception($stmt->error);
        }

        sendResponse(true, array(
            "payment_id" => $stmt->insert_id,
            "or_number" => $or_number,
            "amount_paid" => $amount_paid
        ), "Payment recorded successfully", 201);

    } catch (Exception $e) {
        sendResponse(false, array(), "Error recording payment: " . $e->getMessage(), 500);
    }
}

/**
 * GET: Payment History
 */
if ($action === 'get_payment_history') {
    try {
        $student_id = isset($request_data['student_id']) ? intval($request_data['student_id']) : 0;

        $payments = array();
        
        $query = "
            SELECT 
                p.payment_id,
                p.or_number,
                p.payment_date,
                p.amount_paid,
                p.payment_mode,
                s.student_number,
                s.first_name,
                s.last_name
            FROM payments p
            JOIN student_assessments sa ON p.assessment_id = sa.assessment_id
            JOIN enrollments e ON sa.enrollment_id = e.enrollment_id
            JOIN students s ON e.student_id = s.student_id
        ";

        if ($student_id > 0) {
            $query .= " WHERE s.student_id = $student_id";
        }

        $query .= " ORDER BY p.payment_date DESC LIMIT 50";

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
                    "received_by_name" => null
                );
            }
        }

        sendResponse(true, $payments, "Payment history retrieved successfully");

    } catch (Exception $e) {
        sendResponse(false, array(), "Error retrieving payment history: " . $e->getMessage(), 500);
    }
}

// =============================================
// BILLING ENDPOINTS
// =============================================

/**
 * GET: Billing Summary
 */
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

/**
 * GET: Student Billing
 */
if ($action === 'get_student_billing') {
    try {
        $student_id = isset($request_data['student_id']) ? intval($request_data['student_id']) : 0;

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

/**
 * GET: Fee Types
 */
if ($action === 'get_fee_types') {
    try {
        $feeTypes = array();
        
        $result = $conn->query("
            SELECT 
                fee_type_id,
                fee_name,
                base_amount
            FROM fee_types
            WHERE is_active = TRUE
            ORDER BY fee_name ASC
        ");

        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $feeTypes[] = array(
                    "fee_type_id" => intval($row['fee_type_id']),
                    "fee_name" => $row['fee_name'],
                    "base_amount" => floatval($row['base_amount'])
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

/**
 * GET: All Scholarships
 */
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
                COUNT(DISTINCT ss.student_id) as active_recipients
            FROM scholarships sch
            LEFT JOIN student_scholarships ss ON sch.scholarship_id = ss.scholarship_id 
                AND ss.status = 'Active'
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
                    "active_recipients" => intval($row['active_recipients'])
                );
            }
        }

        sendResponse(true, $scholarships, "Scholarships retrieved successfully");

    } catch (Exception $e) {
        sendResponse(false, array(), "Error retrieving scholarships: " . $e->getMessage(), 500);
    }
}

/**
 * GET: Scholarship Details (NEWLY ADDED - WAS MISSING)
 */
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
                sch.description,
                sch.requirements,
                sch.is_active,
                COUNT(DISTINCT ss.student_id) as active_recipients
            FROM scholarships sch
            LEFT JOIN student_scholarships ss ON sch.scholarship_id = ss.scholarship_id 
                AND ss.status = 'Active'
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
            "description" => $row['description'],
            "requirements" => $row['requirements'],
            "is_active" => boolval($row['is_active']),
            "active_recipients" => intval($row['active_recipients'])
        ), "Scholarship details retrieved successfully");

    } catch (Exception $e) {
        sendResponse(false, array(), "Error retrieving scholarship details: " . $e->getMessage(), 500);
    }
}

// =============================================
// ACCOUNT STATEMENT ENDPOINTS
// =============================================

/**
 * GET: Account Statement (NEWLY ADDED - WAS MISSING)
 */
if ($action === 'get_account_statement') {
    try {
        $student_id = isset($request_data['student_id']) ? intval($request_data['student_id']) : 0;
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

        // Build student info
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
        $result = $conn->query($transQuery);
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $transactions[] = array(
                    "date" => $row['date'],
                    "description" => $row['description'],
                    "charges" => floatval($row['charges']),
                    "payments" => floatval($row['payments']),
                    "balance" => floatval($row['balance'])
                );
            }
        }

        sendResponse(true, array(
            "student_info" => $student_info,
            "transactions" => $transactions,
            "summary" => array(
                "total_charges" => array_sum(array_column($transactions, 'charges')),
                "total_payments" => array_sum(array_column($transactions, 'payments'))
            )
        ), "Account statement retrieved successfully");

    } catch (Exception $e) {
        sendResponse(false, array(), "Error retrieving account statement: " . $e->getMessage(), 500);
    }
}

// =============================================
// ENROLLMENT PERIODS
// =============================================

/**
 * GET: Enrollment Periods
 */
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

/**
 * GET: Search Students
 */
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
                p.program_name
            FROM students s
            JOIN programs p ON s.program_id = p.program_id
            WHERE s.student_number LIKE '$searchTerm' 
               OR CONCAT(s.first_name, ' ', s.last_name) LIKE '$searchTerm'
            LIMIT 10
        ");

        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $students[] = array(
                    "student_id" => intval($row['student_id']),
                    "student_number" => $row['student_number'],
                    "name" => trim($row['first_name'] . ' ' . $row['last_name']),
                    "program" => $row['program_name']
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

/**
 * GET: Collection Summary
 */
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