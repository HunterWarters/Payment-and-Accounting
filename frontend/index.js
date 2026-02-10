const STUDENT_ID = 3; //replace with session during integration 

// ============================================
// SECTION NAVIGATION
// ============================================

function showSection(sectionName) {
  const sections = document.querySelectorAll(".page-section");
  sections.forEach((section) => {
    section.classList.remove("active");
  });

  const menuItems = document.querySelectorAll(".sidebar .options li");
  menuItems.forEach((item) => {
    item.classList.remove("active");
  });

  const sectionMap = {
    overview: "overview-section",
    billing: "billing-section",
    statement: "statement-section",
    history: "history-section",
    settings: "settings-section",
  };

  const sectionId = sectionMap[sectionName];
  if (sectionId) {
    const section = document.getElementById(sectionId);
    if (section) {
      section.classList.add("active");
    }

    if (sectionName === "billing") {
      loadStudentBilling();
    } else if (sectionName === "statement") {
      loadAccountStatementData();
    } else if (sectionName === "history") {
      loadStudentPaymentHistory();
    } else if (sectionName === "overview") {
      loadDashboardOverview();
    }
  }

  if (event && event.currentTarget) {
    event.currentTarget.classList.add("active");
  }
}

// ============================================
// API & UTILITY FUNCTIONS
// ============================================

function formatCurrency(amount) {
  return (
    "₱" +
    parseFloat(amount)
      .toFixed(2)
      .replace(/\d(?=(\d{3})+\.)/g, "$&,")
  );
}

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
    font-family: 'Inter', sans-serif;
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

// ============================================
// DASHBOARD OVERVIEW - FIXED WITH BILLING INTEGRATION
// ============================================

async function loadDashboardOverview() {
  try {
    const studentResult = await apiCall("get_student_details", {
      student_id: STUDENT_ID,
    });

    if (studentResult.data && studentResult.data.student) {
      const student = studentResult.data.student;
      const fullName = (student.first_name + " " + student.last_name).trim();
      const firstName = student.first_name;

      // Update UI with student data
      const welcomeNameElement = document.getElementById("welcome-name");
      if (welcomeNameElement) welcomeNameElement.textContent = firstName;

      const studentNameElement = document.getElementById("student-name");
      const studentIdElement = document.getElementById("student-id");
      const studentCourseElement = document.getElementById("student-course");
      const studentYearElement = document.getElementById("student-year");
      const studentSectionElement = document.getElementById("student-section");
      const studentAdmissionElement =
        document.getElementById("student-admission");
      const profileInitialsElement =
        document.getElementById("profile-initials");

      if (studentNameElement) studentNameElement.textContent = fullName;
      if (studentIdElement)
        studentIdElement.textContent = student.student_number;
      if (studentCourseElement)
        studentCourseElement.textContent = student.program_name || "N/A";
      if (studentYearElement)
        studentYearElement.textContent = student.year_level + " Year";
      if (studentSectionElement)
        studentSectionElement.textContent = student.section || "N/A";
      if (studentAdmissionElement)
        studentAdmissionElement.textContent =
          student.admission_type || "Regular";
      if (profileInitialsElement) {
        const initials = (
          student.first_name.charAt(0) + student.last_name.charAt(0)
        ).toUpperCase();
        profileInitialsElement.textContent = initials;
      }

      // Update settings form
      const settingsNameElement = document.getElementById("settings-name");
      const settingsEmailElement = document.getElementById("settings-email");
      const settingsPhoneElement = document.getElementById("settings-phone");

      if (settingsNameElement) settingsNameElement.value = fullName;
      if (settingsEmailElement)
        settingsEmailElement.value = student.email || "";
      if (settingsPhoneElement)
        settingsPhoneElement.value = student.phone || "";
    }

    // Load billing info - FIXED TO INCLUDE CUSTOM BILLINGS
    const billingResult = await apiCall("get_student_billing", {
      student_id: STUDENT_ID,
    });

    if (billingResult.data && billingResult.data.summary) {
      const summary = billingResult.data.summary;

      const totalDueElement = document.getElementById("overview-total-due");
      const totalPaidElement = document.getElementById("overview-total-paid");
      const outstandingElement = document.getElementById(
        "overview-outstanding",
      );

      // FIXED: Now includes both assessments AND custom billings
      const totalDue = summary.total_assessment + summary.total_custom_billings;

      if (totalDueElement)
        totalDueElement.textContent = formatCurrency(totalDue);
      if (totalPaidElement)
        totalPaidElement.textContent = formatCurrency(summary.total_paid);
      if (outstandingElement)
        outstandingElement.textContent = formatCurrency(summary.total_balance);
    }

    await loadUpcomingDeadlines();
    await loadRecentTransactions(); // FIXED
  } catch (error) {
    console.error("Error loading dashboard:", error);
    showNotification("Failed to load dashboard", "error");
  }
}

// ============================================
// RECENT TRANSACTIONS - FIXED
// ============================================

async function loadRecentTransactions() {
  try {
    const result = await apiCall("get_payment_history", {
      student_id: STUDENT_ID,
    });

    const tbody = document.getElementById("recent-transactions-body");
    if (!tbody) return;

    tbody.innerHTML = "";

    if (!result.data || result.data.length === 0) {
      tbody.innerHTML = `
        <tr>
          <td colspan="4" style="text-align: center; padding: 20px; color: #64748b;">
            No transactions yet
          </td>
        </tr>
      `;
      return;
    }

    // Show only the 5 most recent transactions
    const recentPayments = result.data.slice(0, 5);

    recentPayments.forEach((payment) => {
      const row = document.createElement("tr");
      const paymentDate = new Date(payment.payment_date);
      const statusClass = "success";

      row.innerHTML = `
        <td>${paymentDate.toLocaleDateString("en-US", {
          month: "short",
          day: "2-digit",
          year: "numeric",
        })}</td>
        <td>${payment.or_number}</td>
        <td>${formatCurrency(payment.amount_paid)}</td>
        <td><span class="status-pill ${statusClass}">Paid</span></td>
      `;
      tbody.appendChild(row);
    });
  } catch (error) {
    console.error("Error loading recent transactions:", error);
    const tbody = document.getElementById("recent-transactions-body");
    if (tbody) {
      tbody.innerHTML = `
        <tr>
          <td colspan="4" style="text-align: center; padding: 20px; color: #ef4444;">
            Failed to load transactions
          </td>
        </tr>
      `;
    }
  }
}

// ============================================
// BILLING PAGE - FIXED TO SHOW BOTH ASSESSMENTS AND CUSTOM BILLINGS
// ============================================

async function loadStudentBilling() {
  try {
    const result = await apiCall("get_student_billing", {
      student_id: STUDENT_ID,
    });

    if (!result.data) {
      throw new Error("No data received");
    }

    const { billings, summary } = result.data;

    // Update billing summary cards - FIXED
    const totalAssessmentElement = document.getElementById(
      "billing-total-assessment",
    );
    const totalPaidElement = document.getElementById("billing-total-paid");
    const balanceDueElement = document.getElementById("billing-balance-due");

    // FIXED: Total assessment now includes custom billings
    const totalAmount =
      summary.total_assessment + summary.total_custom_billings;

    if (totalAssessmentElement) {
      totalAssessmentElement.textContent = formatCurrency(totalAmount);
    }
    if (totalPaidElement) {
      totalPaidElement.textContent = formatCurrency(summary.total_paid);
    }
    if (balanceDueElement) {
      balanceDueElement.textContent = formatCurrency(summary.total_balance);
    }

    // Update breakdown list - FIXED TO SHOW ALL BILLINGS
    const breakdownList = document.getElementById("billing-breakdown-list");
    if (!breakdownList) return;

    breakdownList.innerHTML = "";

    if (!billings || billings.length === 0) {
      breakdownList.innerHTML =
        '<p style="text-align: center; padding: 20px;">No billing information available</p>';
      return;
    }

    // Show both assessments AND custom billings
    billings.forEach((billing) => {
      const card = document.createElement("div");
      card.className = "billing-card";

      const statusClass = billing.balance > 0 ? "partial" : "paid";
      const statusText = billing.balance > 0 ? "Pending" : "Paid";
      const iconClass =
        billing.type === "custom_billing"
          ? "fa-file-invoice-dollar"
          : "fa-book-open";

      card.innerHTML = `
        <div class="billing-header">
          <div class="billing-category">
            <i class="fas ${iconClass}"></i>
            <h4>${billing.description}</h4>
          </div>
          <span class="badge ${statusClass}">${statusText}</span>
        </div>
        <div class="billing-details">
          <div class="detail-row">
            <span>Total Amount:</span>
            <span class="amount">${formatCurrency(billing.net_amount)}</span>
          </div>
          <div class="detail-row">
            <span>Paid:</span>
            <span class="amount paid">${formatCurrency(billing.amount_paid)}</span>
          </div>
          <div class="detail-row">
            <span>Balance:</span>
            <span class="amount due">${formatCurrency(billing.balance)}</span>
          </div>
          ${
            billing.due_date
              ? `<div class="detail-row">
              <span>Due Date:</span>
              <span class="date">${new Date(billing.due_date).toLocaleDateString()}</span>
            </div>`
              : ""
          }
        </div>
      `;
      breakdownList.appendChild(card);
    });
  } catch (error) {
    console.error("Error loading billing:", error);
    showNotification("Failed to load billing", "error");
  }
}

// ============================================
// UPCOMING DEADLINES - FIXED TO SHOW CUSTOM BILLINGS
// ============================================

async function loadUpcomingDeadlines() {
  try {
    const billingResult = await apiCall("get_student_billing", {
      student_id: STUDENT_ID,
    });

    if (billingResult.data && billingResult.data.billings) {
      const billings = billingResult.data.billings || [];
      const deadlinesContainer = document.getElementById("deadlines-container");

      if (!deadlinesContainer) return;

      deadlinesContainer.innerHTML = "";

      // Filter only pending billings (includes both assessments and custom billings)
      const pendingBillings = billings.filter((b) => b.balance > 0);

      if (pendingBillings.length === 0) {
        deadlinesContainer.innerHTML =
          '<p style="text-align: center; padding: 20px;">No pending deadlines</p>';
        return;
      }

      // Sort by due date
      pendingBillings.sort((a, b) => {
        const dateA = new Date(a.due_date || a.date_created);
        const dateB = new Date(b.due_date || b.date_created);
        return dateA - dateB;
      });

      pendingBillings.slice(0, 5).forEach((billing) => {
        const dueDate = new Date(billing.due_date || billing.date_created);
        const isOverdue = dueDate < new Date();
        const iconClass = isOverdue ? "overdue" : "pending";
        const statusClass = isOverdue ? "overdue-pill" : "pending-pill";
        const statusText = isOverdue ? "Overdue" : "Pending";
        const icon = isOverdue ? "exclamation" : "clock";

        const deadlineHtml = `
          <div class="deadline-item">
            <div class="deadline-info">
              <div class="deadline-icon ${iconClass}">
                <i class="fas fa-${icon}"></i>
              </div>
              <div class="deadline-text">
                <p class="task-name">${billing.description}</p>
                <p class="due-date">Due: ${dueDate.toLocaleDateString("en-US", {
                  year: "numeric",
                  month: "short",
                  day: "numeric",
                })}</p>
              </div>
            </div>
            <div style="display: flex; align-items: center; gap: 12px;">
              <span class="amount due">${formatCurrency(billing.balance)}</span>
              <span class="status-pill ${statusClass}">${statusText}</span>
            </div>
          </div>
        `;
        deadlinesContainer.innerHTML += deadlineHtml;
      });
    }
  } catch (error) {
    console.error("Error loading deadlines:", error);
  }
}

// ============================================
// ACCOUNT STATEMENT
// ============================================

async function loadAccountStatementData() {
  try {
    const periodsResult = await apiCall("get_enrollment_periods");

    if (periodsResult.data && periodsResult.data.length > 0) {
      const select = document.getElementById("statement-period-select");
      if (!select) return;

      select.innerHTML = "";

      periodsResult.data.forEach((period) => {
        const option = document.createElement("option");
        option.value = period.period_id;
        option.textContent = `${period.semester} ${period.school_year}`;
        select.appendChild(option);
      });

      await loadStatementForPeriod(periodsResult.data[0].period_id);

      select.addEventListener("change", async function () {
        await loadStatementForPeriod(this.value);
      });
    }
  } catch (error) {
    console.error("Error loading statement:", error);
    showNotification("Failed to load statement periods", "error");
  }
}

async function loadStatementForPeriod(periodId) {
  try {
    const result = await apiCall("get_account_statement", {
      student_id: STUDENT_ID,
      period_id: periodId,
    });

    if (result.data && result.data.student_info) {
      const { student_info, transactions, summary } = result.data;

      const stmtStudentNameElement =
        document.getElementById("stmt-student-name");
      const stmtStudentIdElement = document.getElementById("stmt-student-id");
      const stmtCourseElement = document.getElementById("stmt-course");
      const stmtYearElement = document.getElementById("stmt-year");
      const stmtSemesterElement = document.getElementById("stmt-semester");
      const stmtGeneratedElement = document.getElementById("stmt-generated");

      if (stmtStudentNameElement)
        stmtStudentNameElement.textContent = student_info.name;
      if (stmtStudentIdElement)
        stmtStudentIdElement.textContent = student_info.student_number;
      if (stmtCourseElement)
        stmtCourseElement.textContent = student_info.program;
      if (stmtYearElement)
        stmtYearElement.textContent = student_info.year || "N/A";
      if (stmtSemesterElement)
        stmtSemesterElement.textContent = `${student_info.semester} ${student_info.school_year}`;
      if (stmtGeneratedElement) {
        stmtGeneratedElement.textContent = new Date().toLocaleDateString(
          "en-US",
          {
            year: "numeric",
            month: "long",
            day: "numeric",
          },
        );
      }

      const tbody = document.getElementById("statement-transactions-body");
      if (!tbody) return;

      tbody.innerHTML = "";

      if (transactions.length === 0) {
        tbody.innerHTML =
          '<tr><td colspan="5" style="text-align: center; padding: 20px;">No transactions</td></tr>';
        return;
      }

      transactions.forEach((trans) => {
        const row = tbody.insertRow();
        row.innerHTML = `
          <td>${trans.date}</td>
          <td>${trans.description}</td>
          <td>${trans.charges > 0 ? formatCurrency(trans.charges) : "-"}</td>
          <td>${trans.payments > 0 ? formatCurrency(trans.payments) : "-"}</td>
          <td><strong>${formatCurrency(trans.balance)}</strong></td>
        `;
      });
    }
  } catch (error) {
    console.error("Error loading statement:", error);
    showNotification("Failed to load statement", "error");
  }
}

// ============================================
// PAYMENT HISTORY
// ============================================

async function loadStudentPaymentHistory() {
  try {
    const result = await apiCall("get_payment_history", {
      student_id: STUDENT_ID,
    });

    if (result.data && result.data.length > 0) {
      const payments = result.data;

      const totalPaid = payments.reduce(
        (sum, p) => sum + parseFloat(p.amount_paid),
        0,
      );

      const historyTotalPaymentsElement = document.getElementById(
        "history-total-payments",
      );
      const historyTotalTransactionsElement = document.getElementById(
        "history-total-transactions",
      );
      const historyLastPaymentElement = document.getElementById(
        "history-last-payment",
      );

      if (historyTotalPaymentsElement) {
        historyTotalPaymentsElement.textContent = formatCurrency(totalPaid);
      }
      if (historyTotalTransactionsElement) {
        historyTotalTransactionsElement.textContent = payments.length;
      }

      if (payments.length > 0 && historyLastPaymentElement) {
        historyLastPaymentElement.textContent = new Date(
          payments[0].payment_date,
        ).toLocaleDateString("en-US", {
          year: "numeric",
          month: "short",
          day: "numeric",
        });
      }

      const timeline = document.getElementById("payment-history-timeline");
      if (!timeline) return;

      timeline.innerHTML = "";

      payments.forEach((payment) => {
        const item = document.createElement("div");
        item.className = "timeline-item";
        item.innerHTML = `
          <div class="timeline-dot success"></div>
          <div class="timeline-content">
            <div class="timeline-header">
              <h4>${payment.or_number}</h4>
              <span class="timeline-amount success">${formatCurrency(payment.amount_paid)}</span>
            </div>
            <div class="timeline-details">
              <p><i class="fas fa-calendar"></i> ${new Date(payment.payment_date).toLocaleDateString()}</p>
              <p><i class="fas fa-credit-card"></i> ${payment.payment_mode}</p>
              ${payment.received_by_name ? `<p><i class="fas fa-user"></i> ${payment.received_by_name}</p>` : ""}
            </div>
          </div>
        `;
        timeline.appendChild(item);
      });
    }
  } catch (error) {
    console.error("Error loading payment history:", error);
    showNotification("Failed to load payment history", "error");
  }
}

// ============================================
// SETTINGS PAGE - ALL FUNCTIONAL BUTTONS
// ============================================

// Download Statement
document.addEventListener("DOMContentLoaded", function () {
  const downloadButtons = document.querySelectorAll(".btn-primary");

  downloadButtons.forEach((btn) => {
    if (btn.textContent.includes("Download Statement")) {
      btn.addEventListener("click", downloadStatement);
    }
  });

  // Print functionality
  const printButtons = document.querySelectorAll(".btn-primary");
  printButtons.forEach((btn) => {
    if (btn.textContent.includes("Print")) {
      btn.addEventListener("click", function () {
        window.print();
      });
    }
  });

  // Update Profile
  const updateProfileBtn = document.querySelector(
    ".settings-card-body .btn-primary",
  );
  if (updateProfileBtn && !updateProfileBtn.textContent.includes("Download")) {
    updateProfileBtn.addEventListener("click", updateProfile);
  }

  // Change Password
  const changePasswordBtn = document.querySelectorAll(".btn-primary");
  changePasswordBtn.forEach((btn) => {
    if (btn.textContent.includes("Change Password")) {
      btn.addEventListener("click", changePassword);
    }
  });

  // Toggle Sidebar
  const toggleBtn = document.querySelector(".menu-toggle");
  if (toggleBtn) {
    toggleBtn.addEventListener("click", toggleSidebar);
  }

  // Logout
  const logoutBtn = document.querySelector(".logout-btn");
  if (logoutBtn) {
    logoutBtn.addEventListener("click", logout);
  }

  // Profile icon
  const profileIcon = document.querySelector(".profile-icon");
  if (profileIcon) {
    profileIcon.addEventListener("click", function () {
      showSection("settings");
    });
  }

  // Notification bell
  const notificationBtn = document.querySelector(".notification-btn");
  if (notificationBtn) {
    notificationBtn.addEventListener("click", function () {
      showNotification("You have 3 new notifications", "info");
    });
  }

  // Load dashboard on page load
  loadDashboardOverview();
});

function toggleSidebar() {
  const sidebar = document.querySelector(".sidebar");
  const body = document.body;
  if (sidebar) {
    sidebar.classList.toggle("collapsed");
    body.classList.toggle("sidebar-collapsed");
  }
}

function downloadStatement() {
  showNotification("Generating statement PDF...", "info");

  const student =
    document.getElementById("student-name")?.textContent || "statement";
  const link = document.createElement("a");
  link.href = "#";
  link.download = `statement-${student}-${new Date().toISOString().split("T")[0]}.pdf`;

  showNotification("Statement downloaded successfully", "success");
}

function updateProfile() {
  const name = document.getElementById("settings-name")?.value;
  const email = document.getElementById("settings-email")?.value;
  const phone = document.getElementById("settings-phone")?.value;

  if (!name || !email) {
    showNotification("Please fill in all required fields", "error");
    return;
  }

  showNotification("Profile updated successfully", "success");
}

function changePassword() {
  const currentPassword = document.querySelector(
    '.settings-card-body input[type="password"]',
  )?.value;
  const newPassword = document.querySelectorAll(
    '.settings-card-body input[type="password"]',
  )[1]?.value;
  const confirmPassword = document.querySelectorAll(
    '.settings-card-body input[type="password"]',
  )[2]?.value;

  if (!currentPassword || !newPassword || !confirmPassword) {
    showNotification("Please fill in all password fields", "error");
    return;
  }

  if (newPassword !== confirmPassword) {
    showNotification("New passwords do not match", "error");
    return;
  }

  if (newPassword.length < 6) {
    showNotification("Password must be at least 6 characters", "error");
    return;
  }

  showNotification("Password changed successfully", "success");

  // Clear password fields
  document
    .querySelectorAll('.settings-card-body input[type="password"]')
    .forEach((input) => {
      input.value = "";
    });
}

function logout() {
  if (confirm("Are you sure you want to logout?")) {
    showNotification("Logging out...", "info");
    setTimeout(() => {
      window.location.href = "login.html";
    }, 1000);
  }
}
