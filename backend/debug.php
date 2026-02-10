<!DOCTYPE html>
<html>
<head>
    <title>DEBUG - Check Database</title>
    <style>
        body { font-family: monospace; padding: 20px; background: #1a1a1a; color: #0f0; }
        .section { background: #000; padding: 20px; margin: 20px 0; border: 2px solid #0f0; }
        h2 { color: #0ff; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #0f0; padding: 8px; text-align: left; }
        th { background: #003300; }
        .error { color: #f00; }
        .success { color: #0f0; }
    </style>
</head>
<body>
    <h1>🔍 DATABASE DEBUG TOOL 🔍</h1>
    
    <?php
    // Include your config
    require_once 'config.php';
    $conn = getDatabaseConnection();
    
    echo "<div class='section'>";
    echo "<h2>1. CHECK BILLINGS TABLE</h2>";
    $result = $conn->query("SELECT * FROM billings WHERE status = 'Active' ORDER BY created_date DESC LIMIT 10");
    if ($result && $result->num_rows > 0) {
        echo "<table>";
        echo "<tr><th>ID</th><th>Student ID</th><th>Description</th><th>Amount</th><th>Due Date</th><th>Status</th></tr>";
        while ($row = $result->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . $row['billing_id'] . "</td>";
            echo "<td>" . ($row['student_id'] ?? 'NULL') . "</td>";
            echo "<td>" . $row['billing_description'] . "</td>";
            echo "<td>₱" . number_format($row['amount'], 2) . "</td>";
            echo "<td>" . $row['due_date'] . "</td>";
            echo "<td>" . $row['status'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
        echo "<p class='success'>✓ Found " . $result->num_rows . " active billings</p>";
    } else {
        echo "<p class='error'>✗ NO BILLINGS FOUND IN DATABASE!</p>";
        echo "<p>Either the billings table is empty OR billings were not created successfully.</p>";
    }
    echo "</div>";
    
    echo "<div class='section'>";
    echo "<h2>2. CHECK STUDENTS</h2>";
    $result = $conn->query("SELECT student_id, student_number, first_name, last_name FROM students LIMIT 10");
    if ($result && $result->num_rows > 0) {
        echo "<table>";
        echo "<tr><th>Student ID</th><th>Student Number</th><th>Name</th></tr>";
        while ($row = $result->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . $row['student_id'] . "</td>";
            echo "<td>" . $row['student_number'] . "</td>";
            echo "<td>" . $row['first_name'] . " " . $row['last_name'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
        echo "<p class='success'>✓ Found " . $result->num_rows . " students</p>";
    } else {
        echo "<p class='error'>✗ NO STUDENTS FOUND!</p>";
    }
    echo "</div>";
    
    echo "<div class='section'>";
    echo "<h2>3. CHECK ASSESSMENTS</h2>";
    $result = $conn->query("
        SELECT 
            sa.assessment_id,
            s.student_id,
            s.student_number,
            s.first_name,
            s.last_name,
            sa.net_amount
        FROM student_assessments sa
        JOIN enrollments e ON sa.enrollment_id = e.enrollment_id
        JOIN students s ON e.student_id = s.student_id
        LIMIT 10
    ");
    if ($result && $result->num_rows > 0) {
        echo "<table>";
        echo "<tr><th>Assessment ID</th><th>Student ID</th><th>Student</th><th>Amount</th></tr>";
        while ($row = $result->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . $row['assessment_id'] . "</td>";
            echo "<td>" . $row['student_id'] . "</td>";
            echo "<td>" . $row['first_name'] . " " . $row['last_name'] . " (" . $row['student_number'] . ")</td>";
            echo "<td>₱" . number_format($row['net_amount'], 2) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
        echo "<p class='success'>✓ Found " . $result->num_rows . " assessments</p>";
    } else {
        echo "<p class='error'>✗ NO ASSESSMENTS FOUND!</p>";
    }
    echo "</div>";
    
    echo "<div class='section'>";
    echo "<h2>4. CALCULATE TOTAL BALANCE FOR EACH STUDENT</h2>";
    $result = $conn->query("
        SELECT 
            s.student_id,
            s.student_number,
            s.first_name,
            s.last_name,
            COALESCE(SUM(sa.net_amount), 0) as assessment_total,
            COALESCE(SUM(p.amount_paid), 0) as paid_total,
            COALESCE(SUM(b.amount), 0) as billing_total
        FROM students s
        LEFT JOIN enrollments e ON s.student_id = e.student_id
        LEFT JOIN student_assessments sa ON e.enrollment_id = sa.enrollment_id
        LEFT JOIN payments p ON sa.assessment_id = p.assessment_id
        LEFT JOIN billings b ON s.student_id = b.student_id AND b.status = 'Active'
        GROUP BY s.student_id
        LIMIT 10
    ");
    
    if ($result && $result->num_rows > 0) {
        echo "<table>";
        echo "<tr><th>Student</th><th>Assessment</th><th>Paid</th><th>Custom Billings</th><th>TOTAL BALANCE</th></tr>";
        while ($row = $result->fetch_assoc()) {
            $assessmentBalance = floatval($row['assessment_total']) - floatval($row['paid_total']);
            $billingBalance = floatval($row['billing_total']);
            $totalBalance = $assessmentBalance + $billingBalance;
            
            echo "<tr>";
            echo "<td>" . $row['first_name'] . " " . $row['last_name'] . " (" . $row['student_number'] . ")</td>";
            echo "<td>₱" . number_format($row['assessment_total'], 2) . "</td>";
            echo "<td>₱" . number_format($row['paid_total'], 2) . "</td>";
            echo "<td class='" . ($billingBalance > 0 ? 'success' : '') . "'>₱" . number_format($billingBalance, 2) . "</td>";
            echo "<td><strong>₱" . number_format($totalBalance, 2) . "</strong></td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    echo "</div>";
    
    echo "<div class='section'>";
    echo "<h2>5. TEST API CALL</h2>";
    echo "<p>Simulating what the API returns:</p>";
    
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
        LIMIT 5
    ";

    $result = $conn->query($query);
    $assessments = array();
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $paid = 0;
            $paymentResult = $conn->query("
                SELECT COALESCE(SUM(amount_paid), 0) as paid 
                FROM payments 
                WHERE assessment_id = " . intval($row['assessment_id'])
            );
            if ($paymentResult && $payRow = $paymentResult->fetch_assoc()) {
                $paid = floatval($payRow['paid']);
            }
            
            $assessmentBalance = floatval($row['net_amount']) - $paid;
            
            $customBillingsBalance = 0;
            $billingsResult = $conn->query("
                SELECT COALESCE(SUM(amount), 0) as total_billings
                FROM billings
                WHERE student_id = " . intval($row['student_id']) . "
                AND status = 'Active'
            ");
            
            if ($billingsResult && $billRow = $billingsResult->fetch_assoc()) {
                $customBillingsBalance = floatval($billRow['total_billings']);
            }
            
            $totalBalance = $assessmentBalance + $customBillingsBalance;
            $status = $totalBalance <= 0 ? 'Paid' : 'Pending';

            $assessments[] = array(
                "student_name" => trim($row['first_name'] . ' ' . $row['last_name']),
                "student_number" => $row['student_number'],
                "assessment_balance" => $assessmentBalance,
                "custom_billings" => $customBillingsBalance,
                "total_balance" => $totalBalance,
                "status" => $status
            );
        }
    }
    
    echo "<table>";
    echo "<tr><th>Student</th><th>Assessment Balance</th><th>Custom Billings</th><th>TOTAL</th><th>Status</th></tr>";
    foreach ($assessments as $a) {
        echo "<tr>";
        echo "<td>" . $a['student_name'] . " (" . $a['student_number'] . ")</td>";
        echo "<td>₱" . number_format($a['assessment_balance'], 2) . "</td>";
        echo "<td class='" . ($a['custom_billings'] > 0 ? 'success' : '') . "'>₱" . number_format($a['custom_billings'], 2) . "</td>";
        echo "<td><strong>₱" . number_format($a['total_balance'], 2) . "</strong></td>";
        echo "<td>" . $a['status'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    echo "</div>";
    
    echo "<div class='section'>";
    echo "<h2>6. DIAGNOSIS</h2>";
    
    // Check if billings table has student_id set
    $result = $conn->query("SELECT COUNT(*) as count FROM billings WHERE student_id IS NULL");
    $row = $result->fetch_assoc();
    if ($row['count'] > 0) {
        echo "<p class='error'>⚠️ WARNING: You have " . $row['count'] . " billings with NULL student_id!</p>";
        echo "<p>When you create a billing, you MUST specify which student it's for!</p>";
    } else {
        echo "<p class='success'>✓ All billings have student_id set</p>";
    }
    echo "</div>";
    ?>
    
    <div class="section">
        <h2>📋 INSTRUCTIONS</h2>
        <ol>
            <li>If Section 1 shows NO BILLINGS → The billing was NOT saved to database</li>
            <li>If Section 1 shows billings but student_id is NULL → You need to specify the student when creating billing</li>
            <li>If Section 5 shows ₱0.00 in custom billings → The student_id in billings doesn't match the student_id in assessments</li>
            <li>If everything looks correct here but admin dashboard shows ₱0.00 → Browser cache issue, hard refresh (Ctrl+Shift+R)</li>
        </ol>
    </div>
</body>
</html>
