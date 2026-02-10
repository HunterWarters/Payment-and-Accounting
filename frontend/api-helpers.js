

const API_BASE_URL = "http://localhost/account_payment_system/backend/api.php";

/**
 * API Call Handler
 * @param {string} action - The API action to call
 * @param {object} data - Additional data to send
 * @returns {Promise} Response promise
 */
async function apiCall(action, data = {}) {
  try {
    console.log("API Call:", action, data); // Debug log

    const response = await fetch(API_BASE_URL, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
      },
      body: JSON.stringify({
        action: action,
        ...data,
      }),
    });

    // Check if response is OK
    if (!response.ok) {
      throw new Error(`HTTP Error: ${response.status}`);
    }

    const result = await response.json();

    console.log("API Response:", result);

    if (!result.success) {
      // For some endpoints, success might be false but still valid (like check_session)
      // Only throw if there's an actual error message

      if (result.message && result.message !== "No active session") {
        throw new Error(result.message || "API request failed");
      }
    }

    return result;
  } catch (error) {
    console.error("API Error:", error);
    throw error;
  }
}

/**
 * Show notification toast
 */
function showNotification(message, type = "success") {
  const notification = document.createElement("div");
  notification.className = `notification ${type}`;
  notification.textContent = message;
  notification.style.cssText = `
    position: fixed;
    top: 20px;
    right: 20px;
    padding: 15px 20px;
    background: ${
      type === "success" ? "#10b981" : type === "error" ? "#ef4444" : "#3b82f6"
    };
    color: white;
    border-radius: 8px;
    z-index: 10000;
    box-shadow: 0 10px 25px rgba(0,0,0,0.2);
    font-family: 'Plus Jakarta Sans', sans-serif;
    font-weight: 600;
  `;
  document.body.appendChild(notification);

  setTimeout(() => {
    notification.style.opacity = "0";
    notification.style.transform = "translateY(-20px)";
    notification.style.transition = "all 0.3s ease";
    setTimeout(() => notification.remove(), 300);
  }, 3000);
}

/**
 * Format currency
 */
function formatCurrency(amount) {
  return (
    "₱" +
    parseFloat(amount)
      .toFixed(2)
      .replace(/\d(?=(\d{3})+\.)/g, "$&,")
  );
}

/**
 * Update Revenue Chart
 */
function updateRevenueChart(data) {
  const chartArea = document.getElementById("revenue-chart");
  if (!chartArea) return; // Element doesn't exist

  chartArea.innerHTML = "";

  if (!data || data.length === 0) {
    chartArea.innerHTML =
      '<p style="text-align: center; padding: 40px; color: #94a3b8;">No data available</p>';
    return;
  }

  const maxValue = Math.max(...data.map((d) => parseFloat(d.total)));

  const barValuesDiv = document.createElement("div");
  barValuesDiv.className = "bar-values";
  const valuesList = document.createElement("ul");

  // Create scale values
  const scaleSteps = 6;
  for (let i = scaleSteps; i >= 0; i--) {
    const li = document.createElement("li");
    li.textContent = formatCurrency((maxValue / scaleSteps) * i);
    valuesList.appendChild(li);
  }

  barValuesDiv.appendChild(valuesList);
  chartArea.appendChild(barValuesDiv);

  // Create bars
  data.forEach((item) => {
    const barWrapper = document.createElement("div");
    barWrapper.className = "bar-wrapper";

    const bar = document.createElement("div");
    bar.className = "bar";
    const height = maxValue > 0 ? (parseFloat(item.total) / maxValue) * 100 : 0;
    bar.style.height = height + "%";
    bar.setAttribute("data-value", formatCurrency(item.total));

    const label = document.createElement("div");
    label.className = "month-label";
    label.textContent = item.month;

    barWrapper.appendChild(bar);
    barWrapper.appendChild(label);
    chartArea.appendChild(barWrapper);
  });
}

// =============================================
// DASHBOARD API CALLS
// =============================================

/**
 * Load Dashboard Statistics
 */
async function loadDashboardStats() {
  try {
    const result = await apiCall("get_dashboard_stats");
    const stats = result.data;

    // Update stat cards - with null checks
    const totalFeesElement = document.getElementById("stat-total-fees");
    const totalPaymentsElement = document.getElementById("stat-total-payments");
    const pendingBalanceElement = document.getElementById(
      "stat-pending-balance",
    );

    if (totalFeesElement) {
      totalFeesElement.textContent = formatCurrency(stats.total_fees || 0);
    }
    if (totalPaymentsElement) {
      totalPaymentsElement.textContent = formatCurrency(
        stats.total_payments || 0,
      );
    }
    if (pendingBalanceElement) {
      pendingBalanceElement.textContent = formatCurrency(
        stats.pending_balance || 0,
      );
    }

    // Update revenue chart
    updateRevenueChart(stats.monthly_revenue || []);

    // Load collection summary if available
    await loadCollectionSummary();

    return stats;
  } catch (error) {
    console.error("Failed to load dashboard stats:", error);
    showNotification("Failed to load dashboard statistics", "error");
  }
}

/**
 * Load Collection Summary
 */
async function loadCollectionSummary() {
  try {
    const result = await apiCall("get_collection_summary");
    const summary = result.data;

    // Update collection rate - with null checks
    const collectionRateElement = document.getElementById("collection-rate");
    const collectionRateTextElement = document.getElementById(
      "collection-rate-text",
    );

    if (collectionRateElement) {
      const collectionRate = Math.round(summary.collection_rate);
      collectionRateElement.textContent = collectionRate + "%";
    }

    if (collectionRateTextElement) {
      collectionRateTextElement.textContent = `${summary.paid_students || 0} of ${summary.total_students || 0} students paid`;
    }

    return summary;
  } catch (error) {
    console.error("Failed to load collection summary:", error);
  }
}

// =============================================
// ASSESSMENT API CALLS
// =============================================

/**
 * Load All Assessments
 */
async function loadAssessments() {
  try {
    const result = await apiCall("get_all_assessments");
    const assessments = result.data;

    const tbody = document.getElementById("assessments-table-body");
    if (!tbody) return; // Element doesn't exist

    tbody.innerHTML = "";

    if (assessments.length === 0) {
      tbody.innerHTML =
        '<tr><td colspan="6" style="text-align: center; padding: 40px;">No assessments found</td></tr>';
      return;
    }

    assessments.forEach((assessment) => {
      const row = document.createElement("tr");

      const statusClass =
        assessment.status === "Paid" ? "fully-paid" : "status";

      row.innerHTML = `
        <td>${assessment.course_name}</td>
        <td>${assessment.student_name}</td>
        <td>${assessment.student_number}</td>
        <td>${formatCurrency(assessment.balance)}</td>
        <td><span class="${statusClass}">${assessment.status}</span></td>
        <td>
          <button onclick="showPaymentForm(${assessment.assessment_id}, '${assessment.student_name}', ${assessment.balance})">
            Process Payment
          </button>
        </td>
      `;

      tbody.appendChild(row);
    });

    return assessments;
  } catch (error) {
    const tbody = document.getElementById("assessments-table-body");
    if (tbody) {
      tbody.innerHTML =
        '<tr><td colspan="6" style="text-align: center; padding: 40px; color: #ef4444;">Failed to load assessments</td></tr>';
    }
    console.error("Failed to load assessments:", error);
  }
}

/**
 * Get Assessment Details
 */
async function getAssessmentDetails(assessmentId) {
  try {
    const result = await apiCall("get_assessment_details", {
      assessment_id: assessmentId,
    });
    return result.data;
  } catch (error) {
    console.error("Failed to load assessment details:", error);
    showNotification("Failed to load assessment details", "error");
  }
}

// =============================================
// PAYMENT API CALLS
// =============================================

/**
 * Create Payment
 */
async function submitPayment(assessmentId, paymentData) {
  try {
    const result = await apiCall("create_payment", {
      assessment_id: assessmentId,
      ...paymentData,
    });

    showNotification(
      "Payment recorded successfully! OR #: " + result.data.or_number,
      "success",
    );
    return result.data;
  } catch (error) {
    console.error("Failed to create payment:", error);
    showNotification("Failed to record payment: " + error.message, "error");
    throw error;
  }
}

/**
 * Load Payment History
 */
async function loadPaymentHistory(filters = {}) {
  try {
    const result = await apiCall("get_payment_history", filters);
    const payments = result.data;

    const tbody = document.getElementById("payment-history-body");
    if (!tbody) return;
    tbody.innerHTML = "";

    if (payments.length === 0) {
      tbody.innerHTML =
        '<tr><td colspan="5" style="text-align: center; padding: 40px;">No payments found</td></tr>';
      return;
    }

    payments.forEach((payment) => {
      const row = document.createElement("tr");
      row.innerHTML = `
        <td>${payment.or_number}</td>
        <td>${new Date(payment.payment_date).toLocaleDateString("en-US", {
          month: "short",
          day: "2-digit",
          year: "numeric",
        })}</td>
        <td>${formatCurrency(payment.amount_paid)}</td>
        <td>${payment.payment_mode}</td>
        <td>${payment.received_by_name || "N/A"}</td>
      `;
      tbody.appendChild(row);
    });

    return payments;
  } catch (error) {
    const tbody = document.getElementById("payment-history-body");
    if (tbody) {
      tbody.innerHTML =
        '<tr><td colspan="5" style="text-align: center; padding: 40px; color: #ef4444;">Failed to load payment history</td></tr>';
    }
    console.error("Failed to load payment history:", error);
  }
}

/**
 * Get Payment Details
 */
async function getPaymentDetails(paymentId) {
  try {
    const result = await apiCall("get_payment_details", {
      payment_id: paymentId,
    });
    return result.data;
  } catch (error) {
    console.error("Failed to load payment details:", error);
    showNotification("Failed to load payment details", "error");
  }
}

// =============================================
// BILLING API CALLS
// =============================================

/**
 * Load Billing Summary
 */
async function loadBillingSummary() {
  try {
    const result = await apiCall("get_billing_summary");
    const summary = result.data;

    // Update billing cards with null checks
    const totalAssessmentElement = document.getElementById(
      "billing-total-assessment",
    );
    const totalPaidElement = document.getElementById("billing-total-paid");
    const balanceDueElement = document.getElementById("billing-balance-due");

    if (totalAssessmentElement) {
      totalAssessmentElement.textContent = formatCurrency(
        summary.total_assessment,
      );
    }
    if (totalPaidElement) {
      totalPaidElement.textContent = formatCurrency(summary.total_paid);
    }
    if (balanceDueElement) {
      balanceDueElement.textContent = formatCurrency(summary.balance_due);
    }

    return summary;
  } catch (error) {
    console.error("Failed to load billing summary:", error);
  }
}

/**
 * Get Student Billing
 */
async function getStudentBilling(studentId) {
  try {
    const result = await apiCall("get_student_billing", {
      student_id: studentId,
    });
    return result.data;
  } catch (error) {
    console.error("Failed to load student billing:", error);
    showNotification("Failed to load student billing", "error");
  }
}

/**
 * Get Student Details
 */
async function getStudentDetails(studentId) {
  try {
    const result = await apiCall("get_student_details", {
      student_id: studentId,
    });
    return result.data;
  } catch (error) {
    console.error("Failed to load student details:", error);
    showNotification("Failed to load student details", "error");
  }
}

/**
 * Load Fee Types
 */
async function loadFeeTypes() {
  try {
    const result = await apiCall("get_fee_types");
    return result.data;
  } catch (error) {
    console.error("Failed to load fee types:", error);
    showNotification("Failed to load fee types", "error");
  }
}

/**
 * Create Assessment
 */
async function createAssessment(enrollmentId, assessmentData) {
  try {
    const result = await apiCall("create_assessment", {
      enrollment_id: enrollmentId,
      ...assessmentData,
    });
    showNotification("Assessment created successfully", "success");
    return result.data;
  } catch (error) {
    console.error("Failed to create assessment:", error);
    showNotification("Failed to create assessment: " + error.message, "error");
    throw error;
  }
}

// =============================================
// SCHOLARSHIP API CALLS
// =============================================

/**
 * Load All Scholarships
 */
async function loadScholarships() {
  try {
    const result = await apiCall("get_scholarships");
    const scholarships = result.data;

    const tbody = document.getElementById("scholarships-table-body");
    if (!tbody) return; // Element doesn't exist

    tbody.innerHTML = "";

    if (scholarships.length === 0) {
      tbody.innerHTML =
        '<tr><td colspan="5" style="text-align: center; padding: 40px;">No scholarships found</td></tr>';
      return;
    }

    scholarships.forEach((scholarship) => {
      const row = document.createElement("tr");

      let discountDisplay = "";
      if (scholarship.discount_percentage) {
        discountDisplay = scholarship.discount_percentage + "%";
      } else if (scholarship.discount_amount) {
        discountDisplay = formatCurrency(scholarship.discount_amount);
      } else {
        discountDisplay = "-";
      }

      row.innerHTML = `
        <td>${scholarship.scholarship_name}</td>
        <td>${scholarship.scholarship_type}</td>
        <td>${discountDisplay}</td>
        <td>${scholarship.active_recipients}</td>
        <td>
          <button class="btn-icon-sm" onclick="editScholarship(${scholarship.scholarship_id})" title="Edit">
            <i class="fas fa-edit"></i>
          </button>
          <button class="btn-icon-sm" onclick="viewScholarship(${scholarship.scholarship_id})" title="View">
            <i class="fas fa-eye"></i>
          </button>
        </td>
      `;
      tbody.appendChild(row);
    });

    return scholarships;
  } catch (error) {
    const tbody = document.getElementById("scholarships-table-body");
    if (tbody) {
      tbody.innerHTML =
        '<tr><td colspan="5" style="text-align: center; padding: 40px; color: #ef4444;">Failed to load scholarships</td></tr>';
    }
    console.error("Failed to load scholarships:", error);
  }
}

/**
 * Get Scholarship Details
 */
async function getScholarshipDetails(scholarshipId) {
  try {
    const result = await apiCall("get_scholarship_details", {
      scholarship_id: scholarshipId,
    });
    return result.data;
  } catch (error) {
    console.error("Failed to load scholarship details:", error);
    showNotification("Failed to load scholarship details", "error");
    throw error;
  }
}

/**
 * Create Scholarship
 */
async function createScholarship(scholarshipData) {
  try {
    const result = await apiCall("create_scholarship", scholarshipData);
    showNotification("Scholarship created successfully", "success");
    await loadScholarships();
    hideCreateScholarshipForm();
    return result.data;
  } catch (error) {
    console.error("Failed to create scholarship:", error);
    showNotification("Failed to create scholarship: " + error.message, "error");
    throw error;
  }
}

/**
 * Update Scholarship
 */
async function updateScholarship(scholarshipId, scholarshipData) {
  try {
    const result = await apiCall("update_scholarship", {
      scholarship_id: scholarshipId,
      ...scholarshipData,
    });
    showNotification("Scholarship updated successfully", "success");
    await loadScholarships();
    hideEditScholarshipForm();
    return result.data;
  } catch (error) {
    console.error("Failed to update scholarship:", error);
    showNotification("Failed to update scholarship: " + error.message, "error");
    throw error;
  }
}

/**
 * Assign Scholarship
 */
async function assignScholarship(assignmentData) {
  try {
    const result = await apiCall("assign_scholarship", assignmentData);
    showNotification("Scholarship assigned successfully", "success");
    return result.data;
  } catch (error) {
    console.error("Failed to assign scholarship:", error);
    showNotification("Failed to assign scholarship: " + error.message, "error");
    throw error;
  }
}

/**
 * Get Student Scholarships
 */
async function getStudentScholarships(studentId) {
  try {
    const result = await apiCall("get_student_scholarships", {
      student_id: studentId,
    });
    return result.data;
  } catch (error) {
    console.error("Failed to load student scholarships:", error);
    showNotification("Failed to load student scholarships", "error");
    throw error;
  }
}

// =============================================
// ACCOUNT STATEMENT API CALLS
// =============================================

/**
 * Get Account Statement
 */
async function getAccountStatement(studentId, periodId = null) {
  try {
    const result = await apiCall("get_account_statement", {
      student_id: studentId,
      period_id: periodId,
    });
    return result.data;
  } catch (error) {
    console.error("Failed to load account statement:", error);
    showNotification("Failed to load account statement", "error");
  }
}

/**
 * Get Enrollment Periods
 */
async function getEnrollmentPeriods() {
  try {
    const result = await apiCall("get_enrollment_periods");
    return result.data;
  } catch (error) {
    console.error("Failed to load enrollment periods:", error);
    showNotification("Failed to load enrollment periods", "error");
  }
}

// =============================================
// STUDENT SEARCH API CALLS
// =============================================

/**
 * Search Students
 */
async function searchStudents(searchTerm, limit = 10) {
  try {
    const result = await apiCall("search_students", {
      search_term: searchTerm,
      limit: limit,
    });
    return result.data;
  } catch (error) {
    console.error("Failed to search students:", error);
    showNotification("Failed to search students", "error");
  }
}

// =============================================
// REPORTS API CALLS
// =============================================

/**
 * Get Payment Report
 */
async function getPaymentReport(dateFrom = "", dateTo = "", paymentMode = "") {
  try {
    const result = await apiCall("get_payment_report", {
      date_from: dateFrom,
      date_to: dateTo,
      payment_mode: paymentMode,
    });
    return result.data;
  } catch (error) {
    console.error("Failed to load payment report:", error);
    showNotification("Failed to load payment report", "error");
  }
}

/**
 * Get Collection Summary
 */
async function getCollectionSummary() {
  try {
    const result = await apiCall("get_collection_summary");
    return result.data;
  } catch (error) {
    console.error("Failed to load collection summary:", error);
    showNotification("Failed to load collection summary", "error");
  }
}

// =============================================
// PAYMENT FORM HELPERS
// =============================================

let currentAssessmentId = null;

/**
 * Show Payment Form Modal
 */
function showPaymentForm(assessmentId, studentName, balance) {
  const postPaymentElement = document.getElementById("post-payment");
  const transactionTableElement = document.getElementById("transaction-table");

  if (!postPaymentElement) return;

  postPaymentElement.classList.add("active");
  if (transactionTableElement) {
    transactionTableElement.style.display = "none";
  }

  currentAssessmentId = assessmentId;

  // Update UI with null checks
  const studentNameElement = document.getElementById("payment-student-name");
  const balanceElement = document.getElementById("payment-balance");
  const dateElement = document.getElementById("payment-date");

  if (studentNameElement) studentNameElement.textContent = studentName;
  if (balanceElement) balanceElement.textContent = formatCurrency(balance);
  if (dateElement) dateElement.textContent = new Date().toLocaleDateString();
}

/**
 * Hide Payment Form Modal (FIXED - renamed from hidePostPayment)
 */
function hidePaymentForm() {
  const postPaymentElement = document.getElementById("post-payment");
  const transactionTableElement = document.getElementById("transaction-table");

  if (postPaymentElement) {
    postPaymentElement.classList.remove("active");
  }
  if (transactionTableElement) {
    transactionTableElement.style.display = "block";
  }
}

/**
 * DEPRECATED: Kept for backward compatibility
 */
function hidePostPayment() {
  hidePaymentForm();
}

/**
 * Submit Payment Form
 */
async function submitPaymentForm() {
  if (!currentAssessmentId) {
    showNotification("No assessment selected", "error");
    return;
  }

  const amountInput = document.getElementById("payment-amount-input");
  const referenceInput = document.getElementById("payment-reference-input");
  const paymentModeInput = document.querySelector(
    'input[name="payment-mode"]:checked',
  );

  if (!amountInput || !paymentModeInput) {
    showNotification("Payment form elements not found", "error");
    return;
  }

  const amount = amountInput.value;
  const reference = referenceInput ? referenceInput.value : "";
  const paymentMode = paymentModeInput.value;

  if (!amount || parseFloat(amount) <= 0) {
    showNotification("Please enter a valid amount", "error");
    return;
  }

  try {
    const result = await submitPayment(currentAssessmentId, {
      amount_paid: parseFloat(amount),
      payment_mode: paymentMode,
      reference_number: reference || null,
      received_by: 1,
      remarks: null,
    });

    // Reset form
    amountInput.value = "";
    if (referenceInput) referenceInput.value = "";

    hidePaymentForm();

    // Reload assessments
    await loadAssessments();
  } catch (error) {
    console.error("Error submitting payment:", error);
  }
}

// =============================================
// STUB FUNCTIONS FOR FEATURES TO BE IMPLEMENTED
// =============================================

/**
 * Show Create Assessment Form
 */
function showCreateAssessment() {
  showNotification("Create assessment form - to be implemented", "info");
  // TODO: Implement assessment creation form
}

/**
 * Show Create Scholarship Form
 */
function showCreateScholarship() {
  const modal = document.getElementById("create-scholarship-modal");
  if (modal) {
    modal.classList.add("active");
    resetScholarshipForm();
  } else {
    showNotification("Create scholarship form not found", "error");
  }
}

/**
 * Hide Create Scholarship Form
 */
function hideCreateScholarshipForm() {
  const modal = document.getElementById("create-scholarship-modal");
  if (modal) {
    modal.classList.remove("active");
  }
}

/**
 * Hide Edit Scholarship Form
 */
function hideEditScholarshipForm() {
  const modal = document.getElementById("edit-scholarship-modal");
  if (modal) {
    modal.classList.remove("active");
  }
}

/**
 * Submit Create Scholarship Form
 */
async function submitCreateScholarshipForm() {
  const nameInput = document.getElementById("scholarship-name");
  const typeInput = document.getElementById("scholarship-type");
  const percentageInput = document.getElementById("discount-percentage");
  const amountInput = document.getElementById("discount-amount");
  const requirementsInput = document.getElementById("scholarship-requirements");

  if (!nameInput || !typeInput) {
    showNotification("Required form fields not found", "error");
    return;
  }

  const name = nameInput.value.trim();
  const type = typeInput.value.trim();
  const percentage = parseFloat(percentageInput?.value || 0);
  const amount = parseFloat(amountInput?.value || 0);
  const requirements = requirementsInput?.value || "";

  if (!name || !type) {
    showNotification("Scholarship name and type are required", "error");
    return;
  }

  if (percentage <= 0 && amount <= 0) {
    showNotification(
      "Either discount percentage or amount must be greater than 0",
      "error",
    );
    return;
  }

  try {
    await createScholarship({
      scholarship_name: name,
      scholarship_type: type,
      discount_percentage: percentage,
      discount_amount: amount,
      requirements: requirements,
    });
  } catch (error) {
    console.error("Error creating scholarship:", error);
  }
}

/**
 * Edit Scholarship
 */
async function editScholarship(id) {
  try {
    const details = await getScholarshipDetails(id);
    const modal = document.getElementById("edit-scholarship-modal");

    if (modal) {
      // Populate form with scholarship data
      const nameInput = document.getElementById("edit-scholarship-name");
      const typeInput = document.getElementById("edit-scholarship-type");
      const percentageInput = document.getElementById(
        "edit-discount-percentage",
      );
      const amountInput = document.getElementById("edit-discount-amount");
      const requirementsInput = document.getElementById(
        "edit-scholarship-requirements",
      );
      const idInput = document.getElementById("edit-scholarship-id");

      if (nameInput) nameInput.value = details.scholarship_name;
      if (typeInput) typeInput.value = details.scholarship_type;
      if (percentageInput)
        percentageInput.value = details.discount_percentage || 0;
      if (amountInput) amountInput.value = details.discount_amount || 0;
      if (requirementsInput)
        requirementsInput.value = details.description || "";
      if (idInput) idInput.value = id;

      modal.classList.add("active");
    }
  } catch (error) {
    showNotification("Failed to load scholarship for editing", "error");
  }
}

/**
 * Submit Edit Scholarship Form
 */
async function submitEditScholarshipForm() {
  const idInput = document.getElementById("edit-scholarship-id");
  const nameInput = document.getElementById("edit-scholarship-name");
  const typeInput = document.getElementById("edit-scholarship-type");
  const percentageInput = document.getElementById("edit-discount-percentage");
  const amountInput = document.getElementById("edit-discount-amount");
  const requirementsInput = document.getElementById(
    "edit-scholarship-requirements",
  );

  if (!idInput || !nameInput || !typeInput) {
    showNotification("Required form fields not found", "error");
    return;
  }

  const id = parseInt(idInput.value);
  const name = nameInput.value.trim();
  const type = typeInput.value.trim();
  const percentage = parseFloat(percentageInput?.value || 0);
  const amount = parseFloat(amountInput?.value || 0);
  const requirements = requirementsInput?.value || "";

  if (!name || !type) {
    showNotification("Scholarship name and type are required", "error");
    return;
  }

  if (percentage <= 0 && amount <= 0) {
    showNotification(
      "Either discount percentage or amount must be greater than 0",
      "error",
    );
    return;
  }

  try {
    await updateScholarship(id, {
      scholarship_name: name,
      scholarship_type: type,
      discount_percentage: percentage,
      discount_amount: amount,
      requirements: requirements,
    });
  } catch (error) {
    console.error("Error updating scholarship:", error);
  }
}

/**
 * View Scholarship Details
 */
async function viewScholarship(scholarshipId) {
  try {
    const details = await getScholarshipDetails(scholarshipId);
    const modal = document.getElementById("view-scholarship-modal");

    if (modal) {
      // Populate modal with scholarship data
      const nameElement = document.getElementById("view-scholarship-name");
      const typeElement = document.getElementById("view-scholarship-type");
      const discountElement = document.getElementById(
        "view-scholarship-discount",
      );
      const recipientsElement = document.getElementById(
        "view-scholarship-recipients",
      );
      const requirementsElement = document.getElementById(
        "view-scholarship-requirements",
      );

      if (nameElement) nameElement.textContent = details.scholarship_name;
      if (typeElement) typeElement.textContent = details.scholarship_type;

      let discountDisplay = "";
      if (details.discount_percentage) {
        discountDisplay = details.discount_percentage + "%";
      } else if (details.discount_amount) {
        discountDisplay = formatCurrency(details.discount_amount);
      }
      if (discountElement) discountElement.textContent = discountDisplay;

      if (recipientsElement)
        recipientsElement.textContent = details.active_recipients;
      if (requirementsElement)
        requirementsElement.textContent =
          details.requirements || "No requirements specified";

      modal.classList.add("active");
    }
  } catch (error) {
    showNotification("Failed to view scholarship", "error");
  }
}

/**
 * Close View Scholarship Modal
 */
function closeViewScholarshipModal() {
  const modal = document.getElementById("view-scholarship-modal");
  if (modal) {
    modal.classList.remove("active");
  }
}

/**
 * Reset Scholarship Form
 */
function resetScholarshipForm() {
  const nameInput = document.getElementById("scholarship-name");
  const typeInput = document.getElementById("scholarship-type");
  const percentageInput = document.getElementById("discount-percentage");
  const amountInput = document.getElementById("discount-amount");
  const requirementsInput = document.getElementById("scholarship-requirements");

  if (nameInput) nameInput.value = "";
  if (typeInput) typeInput.value = "";
  if (percentageInput) percentageInput.value = "";
  if (amountInput) amountInput.value = "";
  if (requirementsInput) requirementsInput.value = "";
}

/**
 * Show Create Billing Form
 */
function showCreateBillingForm() {
  const modal = document.getElementById("create-billing-modal");
  if (modal) {
    modal.classList.add("active");
    resetBillingForm();
  } else {
    showNotification("Create billing form not found", "error");
  }
}

/**
 * Hide Create Billing Form
 */
function hideCreateBillingForm() {
  const modal = document.getElementById("create-billing-modal");
  if (modal) {
    modal.classList.remove("active");
  }
}

/**
 * Reset Billing Form
 */
function resetBillingForm() {
  const descriptionInput = document.getElementById("description");
  const amountInput = document.getElementById("billing-amount");
  const dueDateInput = document.getElementById("billing-due-date");

  if (descriptionInput) descriptionInput.value = "";
  if (amountInput) amountInput.value = "";
  if (dueDateInput) dueDateInput.value = "";
}

/**
 * Logout
 */
function logout() {
  if (confirm("Are you sure you want to logout?")) {
    showNotification("Logging out...", "info");
    setTimeout(() => {
      window.location.href = "login.html";
    }, 1000);
  }
}

// =============================================
// INITIALIZATION
// =============================================

document.addEventListener("DOMContentLoaded", function () {
  // Setup global event listeners

  // Notification bell click
  const notificationBtn = document.querySelector(".notification-btn");
  if (notificationBtn) {
    notificationBtn.addEventListener("click", function () {
      showNotification("You have 5 new notifications", "info");
    });
  }

  // Profile icon click
  const profileIcon = document.querySelector(".profile-icon");
  if (profileIcon) {
    profileIcon.addEventListener("click", function () {});
  }

  // Payment form close button
  const closeBtn = document.querySelector(".close-btn");
  if (closeBtn) {
    closeBtn.addEventListener("click", hidePaymentForm);
  }

  // Cancel button
  const cancelBtn = document.querySelector(".btn-cancel");
  if (cancelBtn) {
    cancelBtn.addEventListener("click", hidePaymentForm);
  }

  // Payment form submit button
  const submitBtn = document.querySelector(".btn-submit");
  if (submitBtn) {
    submitBtn.addEventListener("click", submitPaymentForm);
  }

  document.addEventListener("keydown", function (e) {
    if ((e.ctrlKey || e.metaKey) && e.key === "k") {
      e.preventDefault();
      const searchInput = document.querySelector(".search-box input");
      if (searchInput) searchInput.focus();
    }
  });
});
