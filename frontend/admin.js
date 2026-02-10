// =============================================
// PAGE NAVIGATION FUNCTIONS
// =============================================

/**
 * Show Overview Page
 */
function showOverview() {
  switchPage("overview-section");
  loadDashboardStats();
  loadRecentActivity(); // ADDED
}

/**
 * Show Assessment/Payments Page
 */
function showAssessment() {
  switchPage("fee-assesment-page");
  loadAssessments();
}

/**
 * Show Payment History Page
 */
function showPayments() {
  switchPage("payments");
  loadPaymentHistory();
}

/**
 * Show Billing Page
 */
function showbilling() {
  switchPage("billing");
  loadBillingSummary();
}

/**
 * Show Scholarships Page
 */
function showScholarships() {
  switchPage("scholarships");
  loadScholarships();
  loadScholarshipStats(); // ADDED
}

/**
 * Show Student Scholarships Page
 */
function showStudentScholarships() {
  switchPage("student-scholarship");
  loadStudentScholarshipsTable();
  loadStudentScholarshipStats(); // ADDED
}

/**
 * Show Settings Page
 */
function showSettings() {
  switchPage("settings");
}

/**
 * Switch Page Helper
 */
function switchPage(pageId) {
  // Hide all sections
  const sections = document.querySelectorAll(".page-section");
  sections.forEach((section) => {
    section.classList.remove("active");
  });

  // Show selected section
  const selectedSection = document.getElementById(pageId);
  if (selectedSection) {
    selectedSection.classList.add("active");
  }

  // Update sidebar active state
  const navLinks = document.querySelectorAll(".sidebar .options li");
  navLinks.forEach((li) => {
    li.classList.remove("active");
  });

  // Find and mark the clicked link as active
  const activeLink = document.querySelector(
    `li[onclick="show${capitalize(pageId.split("-")[0])}()"]`,
  );
  if (activeLink) {
    activeLink.classList.add("active");
  }
}

/**
 * Capitalize String
 */
function capitalize(str) {
  return str.charAt(0).toUpperCase() + str.slice(1);
}

/**
 * Toggle Sidebar
 */
function toggleSidebar() {
  const sidebar = document.querySelector(".sidebar");
  if (sidebar) {
    sidebar.classList.toggle("collapsed");
  }
}

// =============================================
// DASHBOARD STATS - FIXED TO FETCH REAL DATA
// =============================================

async function loadRecentActivity() {
  try {
    const paymentsResult = await apiCall("get_payment_history");

    const activityList = document.querySelector(".activity-list");
    if (!activityList) return;

    activityList.innerHTML = "";

    if (!paymentsResult.data || paymentsResult.data.length === 0) {
      activityList.innerHTML =
        '<p style="text-align: center; padding: 20px;">No recent activity</p>';
      return;
    }

    // Get the 4 most recent payments
    const recentPayments = paymentsResult.data.slice(0, 4);

    recentPayments.forEach((payment) => {
      const timeAgo = getTimeAgo(new Date(payment.payment_date));

      const activityHtml = `
        <div class="activity-item">
          <div class="activity-icon payment">
            <i class="fas fa-money-bill-wave"></i>
          </div>
          <div class="activity-content">
            <h4>Payment Received</h4>
            <p>${payment.student_name} - ${formatCurrency(payment.amount_paid)}</p>
            <span class="activity-time">${timeAgo}</span>
          </div>
        </div>
      `;

      activityList.innerHTML += activityHtml;
    });
  } catch (error) {
    console.error("Error loading recent activity:", error);
  }
}

/**
 * Calculate time ago from date
 */
function getTimeAgo(date) {
  const now = new Date();
  const diffMs = now - date;
  const diffMins = Math.floor(diffMs / 60000);
  const diffHours = Math.floor(diffMs / 3600000);
  const diffDays = Math.floor(diffMs / 86400000);

  if (diffMins < 1) return "Just now";
  if (diffMins < 60)
    return `${diffMins} minute${diffMins !== 1 ? "s" : ""} ago`;
  if (diffHours < 24)
    return `${diffHours} hour${diffHours !== 1 ? "s" : ""} ago`;
  if (diffDays < 7) return `${diffDays} day${diffDays !== 1 ? "s" : ""} ago`;

  return date.toLocaleDateString();
}

// =============================================
// SCHOLARSHIP STATS - NEW FUNCTION
// =============================================

async function loadScholarshipStats() {
  try {
    const scholarshipsResult = await apiCall("get_scholarships");

    if (!scholarshipsResult.data) return;

    const scholarships = scholarshipsResult.data;
    const totalScholarships = scholarships.length;
    const totalRecipients = scholarships.reduce(
      (sum, sch) => sum + parseInt(sch.active_recipients),
      0,
    );

    // Calculate total scholarship value
    let totalValue = 0;
    scholarships.forEach((sch) => {
      const recipients = parseInt(sch.active_recipients) || 0;
      if (sch.discount_amount > 0) {
        totalValue += sch.discount_amount * recipients;
      }
    });

    // Update stats if elements exist (they would need to be added to HTML)
    console.log("Scholarship Stats:", {
      totalScholarships,
      totalRecipients,
      totalValue,
    });
  } catch (error) {
    console.error("Error loading scholarship stats:", error);
  }
}

// =============================================
// STUDENT SCHOLARSHIP STATS - NEW FUNCTION
// =============================================

async function loadStudentScholarshipStats() {
  try {
    const result = await apiCall("get_all_student_scholarships");

    if (!result.data) return;

    const studentScholarships = result.data;

    // Count active scholarships
    const activeCount = studentScholarships.filter(
      (s) => s.status === "Active",
    ).length;

    // Calculate total scholarship value
    let totalValue = 0;
    studentScholarships.forEach((ss) => {
      if (ss.status === "Active") {
        if (ss.discount_amount > 0) {
          totalValue += ss.discount_amount;
        }
      }
    });

    // Count pending applications
    const pendingCount = studentScholarships.filter(
      (s) => s.status === "Pending",
    ).length;

    // Update UI cards
    const recipientsCard = document.querySelector(
      ".scholarship-summary-card h3",
    );
    if (recipientsCard) {
      recipientsCard.textContent = activeCount;
    }

    const valueCards = document.querySelectorAll(
      ".scholarship-summary-card h3",
    );
    if (valueCards[1]) {
      valueCards[1].textContent = formatCurrency(totalValue);
    }

    if (valueCards[2]) {
      valueCards[2].textContent = pendingCount;
    }
  } catch (error) {
    console.error("Error loading student scholarship stats:", error);
  }
}

// =============================================
// LOAD STUDENT SCHOLARSHIPS TABLE - FIXED
// =============================================

async function loadStudentScholarshipsTable() {
  try {
    const result = await apiCall("get_all_student_scholarships");

    const tbody = document.querySelector("#student-scholarship table tbody");
    if (!tbody) return;

    tbody.innerHTML = "";

    if (!result.data || result.data.length === 0) {
      tbody.innerHTML =
        '<tr><td colspan="7" style="text-align: center; padding: 40px;">No student scholarships found</td></tr>';
      return;
    }

    result.data.forEach((ss) => {
      const row = document.createElement("tr");

      const statusClass = ss.status === "Active" ? "active" : "pending";
      const discountDisplay =
        ss.discount_percentage > 0
          ? `${ss.discount_percentage}%`
          : formatCurrency(ss.discount_amount);

      row.innerHTML = `
        <td>${ss.student_name}</td>
        <td>${ss.student_number}</td>
        <td>${ss.scholarship_name}</td>
        <td>${discountDisplay}</td>
        <td><span class="status-badge ${statusClass}">${ss.status}</span></td>
        <td>${ss.valid_until || "N/A"}</td>
        <td>
          <button class="btn-icon-sm" onclick="viewStudentScholarship(${ss.student_scholarship_id})" title="View Details">
            <i class="fas fa-eye"></i>
          </button>
          ${
            ss.status === "Active"
              ? `
            <button class="btn-icon-sm" onclick="revokeScholarship(${ss.student_scholarship_id})" title="Revoke">
              <i class="fas fa-ban"></i>
            </button>
          `
              : ""
          }
        </td>
      `;

      tbody.appendChild(row);
    });
  } catch (error) {
    console.error("Error loading student scholarships table:", error);
  }
}

/**
 * View Student Scholarship Details
 */
async function viewStudentScholarship(studentScholarshipId) {
  try {
    const result = await apiCall("get_student_scholarship_details", {
      student_scholarship_id: studentScholarshipId,
    });

    if (!result.data) {
      showNotification("Failed to load scholarship details", "error");
      return;
    }

    const details = result.data;
    const discountDisplay =
      details.discount_percentage > 0
        ? `${details.discount_percentage}%`
        : formatCurrency(details.discount_amount);

    const modalContent = `
      <div style="padding: 20px;">
        <h3>${details.student_name}</h3>
        <p><strong>Student Number:</strong> ${details.student_number}</p>
        <p><strong>Scholarship:</strong> ${details.scholarship_name}</p>
        <p><strong>Type:</strong> ${details.scholarship_type}</p>
        <p><strong>Discount:</strong> ${discountDisplay}</p>
        <p><strong>Status:</strong> ${details.status}</p>
        <p><strong>Granted:</strong> ${new Date(details.granted_date).toLocaleDateString()}</p>
        <p><strong>Valid Until:</strong> ${details.valid_until || "N/A"}</p>
        <p><strong>Period:</strong> ${details.period}</p>
      </div>
    `;

    // You can create a modal to show this, for now just alert
    alert(modalContent.replace(/<[^>]*>/g, "\n"));
  } catch (error) {
    console.error("Error viewing scholarship:", error);
    showNotification("Failed to load scholarship details", "error");
  }
}

/**
 * Revoke Scholarship
 */
async function revokeScholarship(studentScholarshipId) {
  if (!confirm("Are you sure you want to revoke this scholarship?")) {
    return;
  }

  try {
    await apiCall("revoke_scholarship", {
      student_scholarship_id: studentScholarshipId,
    });

    showNotification("Scholarship revoked successfully", "success");
    await loadStudentScholarshipsTable();
    await loadStudentScholarshipStats();
  } catch (error) {
    console.error("Error revoking scholarship:", error);
    showNotification("Failed to revoke scholarship", "error");
  }
}

// =============================================
// BILLING FUNCTIONS - UPDATED WITH REAL-TIME REFRESH
// =============================================

/**
 * Handle Billing Form Submission - FIXED TO REFRESH STUDENT DATA
 */
async function handleBillingFormSubmit(e) {
  if (e) e.preventDefault();

  const billingForm = document.querySelector(".billing-form");
  if (!billingForm) {
    showNotification("Billing form not found", "error");
    return;
  }

  const descriptionInput = document.getElementById("description");
  const amountInput = billingForm.querySelector(
    'input[type="number"][id="billing-amount"]',
  );
  const dueDateInput = billingForm.querySelector('input[type="date"]');
  const studentIdInput = document.getElementById("billing-student-id");

  if (!descriptionInput || !amountInput || !dueDateInput) {
    showNotification("Required form fields not found", "error");
    return;
  }

  const billingType = descriptionInput.value;
  const amount = parseFloat(amountInput.value);
  const dueDate = dueDateInput.value;
  const studentId = studentIdInput ? parseInt(studentIdInput.value) : 0;

  if (!billingType || billingType.trim() === "") {
    showNotification("Please select a billing description", "error");
    return;
  }

  if (!amount || isNaN(amount) || amount <= 0) {
    showNotification(
      "Please enter a valid amount (must be greater than 0)",
      "error",
    );
    return;
  }

  if (!dueDate) {
    showNotification("Please select a due date", "error");
    return;
  }

  try {
    const result = await apiCall("create_billing", {
      billing_description: billingType,
      amount: amount,
      due_date: dueDate,
      remarks: "",
      student_id: studentId,
    });

    showNotification(
      "Billing created successfully! Student balance updated.",
      "success",
    );

    // Reset form
    amountInput.value = "";
    dueDateInput.value = "";
    descriptionInput.value = "";
    if (studentIdInput) studentIdInput.value = "";

    // Reload data
    if (typeof loadBillingSummary === "function") {
      await loadBillingSummary();
    }
    if (typeof loadAssessments === "function") {
      await loadAssessments();
    }

    // Reload dashboard stats to reflect new billing
    if (typeof loadDashboardStats === "function") {
      await loadDashboardStats();
    }
  } catch (error) {
    showNotification("Failed to create billing: " + error.message, "error");
  }
}

// =============================================
// SCHOLARSHIP MANAGEMENT FUNCTIONS
// =============================================

/**
 * Handle Create Scholarship Submit
 */
async function handleCreateScholarshipSubmit(e) {
  if (e) e.preventDefault();
  await submitCreateScholarshipForm();
}

/**
 * Handle Edit Scholarship Submit
 */
async function handleEditScholarshipSubmit(e) {
  if (e) e.preventDefault();
  await submitEditScholarshipForm();
}

/**
 * Show Assign Scholarship Modal
 */
function showAssignScholarshipModal() {
  const modal = document.getElementById("assign-scholarship-modal");
  if (modal) {
    modal.classList.add("active");
    loadAssignScholarshipForm();
  } else {
    showNotification("Assign scholarship form not found", "error");
  }
}

/**
 * Load Assign Scholarship Form - NEW FUNCTION
 */
async function loadAssignScholarshipForm() {
  try {
    // Load students
    const studentsResult = await apiCall("search_students", {
      search_term: "",
      limit: 100,
    });

    const studentSelect = document.getElementById("assign-student-id");
    if (studentSelect && studentsResult.data) {
      studentSelect.innerHTML = '<option value="">Select Student</option>';
      studentsResult.data.forEach((student) => {
        const option = document.createElement("option");
        option.value = student.student_id;
        option.textContent = `${student.name} (${student.student_number})`;
        studentSelect.appendChild(option);
      });
    }

    // Load scholarships
    const scholarshipsResult = await apiCall("get_scholarships");
    const scholarshipSelect = document.getElementById("assign-scholarship-id");
    if (scholarshipSelect && scholarshipsResult.data) {
      scholarshipSelect.innerHTML =
        '<option value="">Select Scholarship</option>';
      scholarshipsResult.data.forEach((scholarship) => {
        const option = document.createElement("option");
        option.value = scholarship.scholarship_id;
        option.textContent = scholarship.scholarship_name;
        scholarshipSelect.appendChild(option);
      });
    }

    // Load periods
    const periodsResult = await apiCall("get_enrollment_periods");
    const periodSelect = document.getElementById("assign-period-id");
    if (periodSelect && periodsResult.data) {
      periodSelect.innerHTML = '<option value="">Select Period</option>';
      periodsResult.data.forEach((period) => {
        const option = document.createElement("option");
        option.value = period.period_id;
        option.textContent = `${period.semester} ${period.school_year}`;
        periodSelect.appendChild(option);
      });
    }
  } catch (error) {
    console.error("Error loading assign scholarship form:", error);
  }
}

/**
 * Hide Assign Scholarship Modal
 */
function hideAssignScholarshipModal() {
  const modal = document.getElementById("assign-scholarship-modal");
  if (modal) {
    modal.classList.remove("active");
  }
}

/**
 * Submit Assign Scholarship Form - FIXED
 */
async function submitAssignScholarshipForm() {
  const studentInput = document.getElementById("assign-student-id");
  const scholarshipInput = document.getElementById("assign-scholarship-id");
  const periodInput = document.getElementById("assign-period-id");

  if (!studentInput || !scholarshipInput || !periodInput) {
    showNotification("Required form fields not found", "error");
    return;
  }

  const studentId = parseInt(studentInput.value);
  const scholarshipId = parseInt(scholarshipInput.value);
  const periodId = parseInt(periodInput.value);

  if (!studentId || !scholarshipId || !periodId) {
    showNotification("Please fill in all required fields", "error");
    return;
  }

  try {
    await assignScholarship({
      student_id: studentId,
      scholarship_id: scholarshipId,
      period_id: periodId,
    });
    hideAssignScholarshipModal();

    // Reload student scholarships table
    await loadStudentScholarshipsTable();
    await loadStudentScholarshipStats();
  } catch (error) {
    console.error("Error assigning scholarship:", error);
  }
}

// =============================================
// ADDITIONAL PAGE FUNCTIONS
// =============================================

/**
 * Generate Bill (stub function for future implementation)
 */
function generateBill() {
  showNotification("Generate bill functionality - to be implemented", "info");
}

/**
 * Generate Bulk Bills (stub function for future implementation)
 */
function generateBulkBills() {
  showNotification("Bulk billing functionality - to be implemented", "info");
}

/**
 * Set Billing Cycle (stub function for future implementation)
 */
function setBillingCycle() {
  showNotification("Billing cycle configuration - to be implemented", "info");
}

/**
 * Export CSV
 */
function exportCSV() {
  showNotification("CSV export - to be implemented", "info");
}

/**
 * Print Report
 */
function printReport() {
  window.print();
}

// =============================================
// INITIALIZATION ON PAGE LOAD
// =============================================

document.addEventListener("DOMContentLoaded", function () {
  // Load initial dashboard data
  loadDashboardStats();
  loadRecentActivity();

  // Setup billing form submission if it exists
  const billingForm = document.querySelector(".billing-form");
  if (billingForm) {
    billingForm.addEventListener("submit", handleBillingFormSubmit);
  }

  // Setup billing action buttons
  const generateBillBtn = document.querySelector(".action-card");
  if (generateBillBtn) {
    generateBillBtn.addEventListener("click", generateBill);
  }

  const billingActionCards = document.querySelectorAll(".action-card");
  if (billingActionCards.length >= 3) {
    billingActionCards[0].addEventListener("click", generateBill);
    billingActionCards[1].addEventListener("click", generateBulkBills);
    billingActionCards[2].addEventListener("click", setBillingCycle);
  }

  // Initialize scholarship modals close buttons if they exist
  const modals = document.querySelectorAll("[id$='-modal']");
  modals.forEach((modal) => {
    const closeBtn = modal.querySelector(".close-btn");
    if (closeBtn) {
      closeBtn.addEventListener("click", function () {
        modal.classList.remove("active");
      });
    }
  });

  // Close modal on background click
  modals.forEach((modal) => {
    modal.addEventListener("click", function (e) {
      if (e.target === this) {
        this.classList.remove("active");
      }
    });
  });
});
