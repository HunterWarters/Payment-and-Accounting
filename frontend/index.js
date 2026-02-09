

const STUDENT_ID = 1; // Replace with actual student ID from session (placeholder for now)

// Toggle Sidebar
const sidebar = document.querySelector(".sidebar");
const body = document.body;

function toggleSidebar() {
  sidebar.classList.toggle("collapsed");
  body.classList.toggle("sidebar-collapsed");
}

// Section Navigation
function showSection(sectionName) {
  // Hide all sections
  const sections = document.querySelectorAll(".page-section");
  sections.forEach((section) => {
    section.classList.remove("active");
  });

  // Remove active class from all menu items
  const menuItems = document.querySelectorAll(".sidebar .options li");
  menuItems.forEach((item) => {
    item.classList.remove("active");
  });

  // Show selected section
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

    // Load section-specific data
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

  // Add active class to clicked menu item
  if (event && event.currentTarget) {
    event.currentTarget.classList.add("active");
  }
}

// ============================================
// API INTEGRATION FUNCTIONS
// ============================================

/**
 * Load Dashboard Overview with Real Data
 */
async function loadDashboardOverview() {
  try {
    // Get student details
    const studentResult = await getStudentDetails(STUDENT_ID);

    if (studentResult && studentResult.student) {
      const student = studentResult.student;
      const fullName = (student.first_name + " " + student.last_name).trim();
      const firstName = student.first_name;

      // Update welcome message
      const welcomeNameElement = document.getElementById("welcome-name");
      if (welcomeNameElement) {
        welcomeNameElement.textContent = firstName;
      }

      // Update student info
      const studentNameElement = document.getElementById("student-name");
      const studentIdElement = document.getElementById("student-id");
      const studentCourseElement = document.getElementById("student-course");
      const studentYearElement = document.getElementById("student-year");
      const studentSectionElement = document.getElementById("student-section");
      const studentAdmissionElement =
        document.getElementById("student-admission");

      if (studentNameElement) studentNameElement.textContent = fullName;
      if (studentIdElement)
        studentIdElement.textContent = student.student_number;
      if (studentCourseElement)
        studentCourseElement.textContent = student.program_name || "N/A";

      // Set profile initials
      const profileInitialsElement =
        document.getElementById("profile-initials");
      if (profileInitialsElement) {
        const initials = (
          student.first_name.charAt(0) + student.last_name.charAt(0)
        ).toUpperCase();
        profileInitialsElement.textContent = initials;
      }

      // Parse year level
      const yearMatch = student.program_name
        ? student.program_name.match(/(\d)\w+\s+Year/i)
        : null;
      const yearLevel = student.year_level || (yearMatch ? yearMatch[1] : "3");
      if (studentYearElement)
        studentYearElement.textContent = yearLevel + " Year";

      // Set other student info
      if (studentSectionElement)
        studentSectionElement.textContent = student.section || "N/A";
      if (studentAdmissionElement)
        studentAdmissionElement.textContent =
          student.admission_type || "Regular";

      // Update settings form
      const settingsNameElement = document.getElementById("settings-name");
      const settingsEmailElement = document.getElementById("settings-email");
      const settingsPhoneElement = document.getElementById("settings-phone");

      if (settingsNameElement) settingsNameElement.value = fullName;
      if (settingsEmailElement)
        settingsEmailElement.value = student.email || "";
      if (settingsPhoneElement)
        settingsPhoneElement.value = student.phone || "";

      // Load adviser info
      loadAdviserInfo();
    }

    // Get student billing
    const billingResult = await getStudentBilling(STUDENT_ID);

    if (billingResult && billingResult.summary) {
      const summary = billingResult.summary;

      const totalDueElement = document.getElementById("overview-total-due");
      const totalPaidElement = document.getElementById("overview-total-paid");
      const outstandingElement = document.getElementById(
        "overview-outstanding",
      );

      if (totalDueElement)
        totalDueElement.textContent = formatCurrency(summary.total_assessment);
      if (totalPaidElement)
        totalPaidElement.textContent = formatCurrency(summary.total_paid);
      if (outstandingElement)
        outstandingElement.textContent = formatCurrency(summary.total_balance);
    }

    // Load recent transactions and deadlines
    await loadRecentTransactions();
    await loadUpcomingDeadlines();
  } catch (error) {
    console.error("Error loading dashboard overview:", error);
    showNotification("Failed to load dashboard data", "error");
  }
}

/**
 * Load Adviser Information
 */
function loadAdviserInfo() {
  // In production, this would come from the API
  // For now, using placeholder data that can be populated
  const adviserNameElement = document.getElementById("adviser-name");
  const adviserEmailElement = document.getElementById("adviser-email");
  const adviserOfficeElement = document.getElementById("adviser-office");
  const adviserHoursElement = document.getElementById("adviser-hours");

  if (adviserNameElement) adviserNameElement.textContent = "Prof. Maria Santos";
  if (adviserEmailElement)
    adviserEmailElement.textContent = "m.santos@university.edu";
  if (adviserOfficeElement)
    adviserOfficeElement.textContent = "Room 304, CS Building";
  if (adviserHoursElement)
    adviserHoursElement.textContent = "Mon-Fri, 2:00 PM - 4:00 PM";
}

/**
 * Load Upcoming Deadlines
 */
async function loadUpcomingDeadlines() {
  try {
    const billingResult = await getStudentBilling(STUDENT_ID);

    if (billingResult && billingResult.billings) {
      const billings = billingResult.billings || [];
      const deadlinesContainer = document.getElementById("deadlines-container");

      if (!deadlinesContainer) return;

      deadlinesContainer.innerHTML = "";

      if (billings.length === 0) {
        deadlinesContainer.innerHTML =
          '<p style="text-align: center; padding: 20px;">No pending deadlines</p>';
        return;
      }

      billings.forEach((billing, index) => {
        if (billing.balance > 0) {
          const dueDate = new Date();
          dueDate.setDate(dueDate.getDate() + 7 * (index + 1));

          const isOverdue = billing.balance > 0 && dueDate < new Date();
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
                  <p class="task-name">Assessment Payment #${index + 1}</p>
                  <p class="due-date">Due: ${dueDate.toLocaleDateString(
                    "en-US",
                    {
                      year: "numeric",
                      month: "short",
                      day: "numeric",
                    },
                  )}</p>
                </div>
              </div>
              <span class="status-pill ${statusClass}">${statusText}</span>
            </div>
          `;
          deadlinesContainer.innerHTML += deadlineHtml;
        }
      });
    }
  } catch (error) {
    console.error("Error loading deadlines:", error);
  }
}

/**
 * Load Recent Transactions
 */
async function loadRecentTransactions() {
  try {
    const result = await loadPaymentHistory({ student_id: STUDENT_ID });

    if (result && result.length > 0) {
      const payments = result.slice(0, 3);
      const tbody = document.getElementById("recent-transactions-body");

      if (!tbody) return;

      tbody.innerHTML = "";

      payments.forEach((payment) => {
        const row = tbody.insertRow();
        row.innerHTML = `
          <td>${new Date(payment.payment_date).toLocaleDateString()}</td>
          <td>${payment.or_number}</td>
          <td>${formatCurrency(payment.amount_paid)}</td>
          <td><span class="status-badge paid">Paid</span></td>
        `;
      });
    }
  } catch (error) {
    console.error("Error loading transactions:", error);
  }
}

/**
 * Load Student Billing Information
 */
async function loadStudentBilling() {
  try {
    const result = await getStudentBilling(STUDENT_ID);

    if (result && result.billings) {
      const { billings, summary } = result;

      // Update summary cards
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
        balanceDueElement.textContent = formatCurrency(summary.total_balance);
      }

      // Build billing breakdown
      const breakdownList = document.getElementById("billing-breakdown-list");

      if (!breakdownList) return;

      breakdownList.innerHTML = "";

      if (billings.length === 0) {
        breakdownList.innerHTML =
          '<p style="text-align: center; padding: 20px;">No billing information available</p>';
        return;
      }

      billings.forEach((billing, index) => {
        const card = document.createElement("div");
        card.className = "billing-card";
        const status = billing.balance > 0 ? "Partial" : "Paid";
        const badgeClass = billing.balance > 0 ? "partial" : "paid";

        card.innerHTML = `
          <div class="billing-header">
            <div class="billing-category">
              <i class="fas fa-book-open"></i>
              <h4>Assessment ${index + 1}</h4>
            </div>
            <span class="badge ${badgeClass}">${status}</span>
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
          </div>
        `;
        breakdownList.appendChild(card);
      });
    }
  } catch (error) {
    console.error("Error loading billing:", error);
    showNotification("Failed to load billing information", "error");
  }
}

/**
 * Load Account Statement
 */
async function loadAccountStatementData() {
  try {
    // Load enrollment periods for dropdown
    const periodsResult = await getEnrollmentPeriods();

    if (periodsResult && periodsResult.length > 0) {
      const select = document.getElementById("statement-period-select");

      if (!select) return;

      select.innerHTML = "";

      periodsResult.forEach((period) => {
        const option = document.createElement("option");
        option.value = period.period_id;
        option.textContent = `${period.semester} ${period.school_year}`;
        select.appendChild(option);
      });

      // Load statement for first period
      await loadStatementForPeriod(periodsResult[0].period_id);

      // Add change listener
      select.addEventListener("change", async function () {
        await loadStatementForPeriod(this.value);
      });
    }
  } catch (error) {
    console.error("Error loading statement:", error);
    showNotification("Failed to load statement periods", "error");
  }
}

/**
 * Load Statement for Specific Period
 */
async function loadStatementForPeriod(periodId) {
  try {
    const result = await getAccountStatement(STUDENT_ID, periodId);

    if (result && result.student_info) {
      const { student_info, transactions, summary } = result;

      // Update header
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
      if (stmtSemesterElement) {
        stmtSemesterElement.textContent = `${student_info.semester} ${student_info.school_year}`;
      }
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

      // Build transaction table
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
          <td>${formatCurrency(trans.balance)}</td>
        `;
      });
    }
  } catch (error) {
    console.error("Error loading period statement:", error);
    showNotification("Failed to load statement", "error");
  }
}

/**
 * Load Student Payment History
 */
async function loadStudentPaymentHistory() {
  try {
    const result = await loadPaymentHistory({ student_id: STUDENT_ID });

    if (result && result.length > 0) {
      const payments = result;

      // Update stats
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

      // Build timeline
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
// INITIALIZATION
// ============================================

document.addEventListener("DOMContentLoaded", function () {
  // Load dashboard on page load
  loadDashboardOverview();

  // Add smooth scrolling
  document.querySelectorAll('a[href^="#"]').forEach((anchor) => {
    anchor.addEventListener("click", function (e) {
      e.preventDefault();
      const target = document.querySelector(this.getAttribute("href"));
      if (target) {
        target.scrollIntoView({
          behavior: "smooth",
        });
      }
    });
  });

  // Initialize interactive elements
  initializeInteractiveElements();
});

function initializeInteractiveElements() {
  // Notification bell click
  const notificationBtn = document.querySelector(".notification-btn");
  if (notificationBtn) {
    notificationBtn.addEventListener("click", function () {
      showNotification("You have 3 new notifications", "info");
    });
  }

  // Profile icon click
  const profileIcon = document.querySelector(".profile-icon");
  if (profileIcon) {
    profileIcon.addEventListener("click", function () {
      showSection("settings");
    });
  }

  // Logout button
  const logoutBtn = document.querySelector(".logout-btn");
  if (logoutBtn) {
    logoutBtn.addEventListener("click", function () {
      logout();
    });
  }
}

// Add animation on scroll
window.addEventListener("scroll", function () {
  const cards = document.querySelectorAll(
    ".card, .billing-card, .timeline-item",
  );
  cards.forEach((card) => {
    const cardTop = card.getBoundingClientRect().top;
    const windowHeight = window.innerHeight;
    if (cardTop < windowHeight - 100) {
      card.style.opacity = "1";
      card.style.transform = "translateY(0)";
    }
  });
});
  