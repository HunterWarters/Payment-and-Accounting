


let currentStudentData = null;

// ============================================
// SIDEBAR & NAVIGATION
// ============================================

const sidebar = document.querySelector(".sidebar");

function toggleSidebar() {
  sidebar.classList.toggle("collapsed");
}

function hideAllSections() {
  document.querySelectorAll(".page-section").forEach((section) => {
    section.classList.remove("active");
  });

  const postPaymentElement = document.getElementById("post-payment");
  if (postPaymentElement) {
    postPaymentElement.classList.remove("active");
  }
}

function updateActiveMenu(sectionName) {
  document.querySelectorAll(".sidebar .options li").forEach((li) => {
    li.classList.remove("active");
  });

  const menuItems = {
    "overview-section": 0,
    "fee-assesment-page": 1,
    payments: 2,
    billing: 3,
    scholarships: 4,
    "student-scholarship": 5,
    settings: 6,
  };

  const index = menuItems[sectionName];
  if (index !== undefined) {
    const menuElements = document.querySelectorAll(".sidebar .options li");
    if (menuElements[index]) {
      menuElements[index].classList.add("active");
    }
  }
}

// ============================================
// SECTION DISPLAY FUNCTIONS
// ============================================

function showOverview() {
  hideAllSections();
  const section = document.getElementById("overview-section");
  if (section) {
    section.classList.add("active");
  }
  updateActiveMenu("overview-section");
  loadDashboardStats();
}

function showAssessment() {
  hideAllSections();
  const section = document.getElementById("fee-assesment-page");
  if (section) {
    section.classList.add("active");
  }
  const table = document.getElementById("transaction-table");
  if (table) {
    table.style.display = "block";
  }
  updateActiveMenu("fee-assesment-page");
  loadAssessments();
}

function showPayments() {
  hideAllSections();
  const section = document.getElementById("payments");
  if (section) {
    section.classList.add("active");
  }
  updateActiveMenu("payments");
  loadPaymentHistory();
}

function showbilling() {
  hideAllSections();
  const section = document.getElementById("billing");
  if (section) {
    section.classList.add("active");
  }
  updateActiveMenu("billing");
}

function showScholarships() {
  hideAllSections();
  const section = document.getElementById("scholarships");
  if (section) {
    section.classList.add("active");
  }
  updateActiveMenu("scholarships");
  loadScholarships();
}

function showStudentScholarships() {
  hideAllSections();
  const section = document.getElementById("student-scholarship");
  if (section) {
    section.classList.add("active");
  }
  updateActiveMenu("student-scholarship");
}

function showSettings() {
  hideAllSections();
  const section = document.getElementById("settings");
  if (section) {
    section.classList.add("active");
  }
  updateActiveMenu("settings");
}

// ============================================
// PAYMENT FORM FUNCTIONS
// ============================================

function hidePostPayment() {
  // Use the hidePaymentForm function from api-helpers.js
  hidePaymentForm();
}

function showPostPayment(assessmentId, studentData) {
  // Use the showPaymentForm function from api-helpers.js
  showPaymentForm(assessmentId, studentData.studentName, studentData.balance);
}

// submitPaymentForm is defined in api-helpers.js

// ============================================
// DATA LOADING FUNCTIONS
// ============================================

// loadDashboardStats is defined in api-helpers.js

// loadAssessments is defined in api-helpers.js

// loadPaymentHistory is defined in api-helpers.js

// loadScholarships is defined in api-helpers.js

// ============================================
// UTILITY FUNCTIONS
// ============================================

// showNotification is defined in api-helpers.js

// showCreateAssessment is defined in api-helpers.js
// showCreateScholarship is defined in api-helpers.js

function viewAssessmentDetails(assessmentId) {
  showNotification(`Viewing details for assessment #${assessmentId}`, "info");
}

// editScholarship is defined in api-helpers.js

function deleteScholarship(scholarshipId) {
  if (confirm("Are you sure you want to delete this scholarship?")) {
    showNotification(`Scholarship #${scholarshipId} deleted`, "success");
    loadScholarships();
  }
}

// logout is defined in api-helpers.js

// ============================================
// INITIALIZATION
// ============================================

document.addEventListener("DOMContentLoaded", function () {
  // Load overview on page load
  showOverview();

  // Initialize interactive elements
  initializeInteractiveElements();
});

function initializeInteractiveElements() {
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
    profileIcon.addEventListener("click", function () {
      showSettings();
    });
  }


  document.addEventListener("keydown", function (e) {
    if ((e.ctrlKey || e.metaKey) && e.key === "k") {
      e.preventDefault();
      const searchInput = document.querySelector(".search-box input");
      if (searchInput) searchInput.focus();
    }
  });

  // Logout button
  const logoutBtn = document.querySelector(".logout-btn");
  if (logoutBtn) {
    logoutBtn.addEventListener("click", function () {
      logout();
    });
  }
}
