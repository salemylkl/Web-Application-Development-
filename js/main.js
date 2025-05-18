// Common utility functions
const utils = {
  // Format date to readable string
  formatDate: (date) => {
    return new Date(date).toLocaleDateString("en-US", {
      year: "numeric",
      month: "long",
      day: "numeric",
    });
  },

  // Show notification
  showNotification: (message, type = "success") => {
    const notification = document.createElement("div");
    notification.className = `notification ${type}`;
    notification.textContent = message;
    document.body.appendChild(notification);

    setTimeout(() => {
      notification.remove();
    }, 3000);
  },

  // Handle API errors
  handleError: (error) => {
    console.error("API Error:", error);
    utils.showNotification(error.message || "An error occurred", "error");
  },
};

// Authentication functions
const auth = {
  // Check if user is logged in
  isLoggedIn: () => {
    return localStorage.getItem("user") !== null;
  },

  // Get current user
  getCurrentUser: () => {
    const user = localStorage.getItem("user");
    return user ? JSON.parse(user) : null;
  },

  // Logout user
  logout: () => {
    localStorage.removeItem("user");
    window.location.href = "/HTML/login.html";
  },
};

// Navigation functions
const navigation = {
  // Update active nav link
  updateActiveNav: () => {
    const currentPath = window.location.pathname;
    const navLinks = document.querySelectorAll(".nav-links a");

    navLinks.forEach((link) => {
      if (link.getAttribute("href") === currentPath) {
        link.classList.add("active");
      } else {
        link.classList.remove("active");
      }
    });
  },

  // Initialize navigation
  init: () => {
    navigation.updateActiveNav();

    // Add logout handler if user is logged in
    if (auth.isLoggedIn()) {
      const userMenu = document.querySelector(".user-menu");
      if (userMenu) {
        userMenu.addEventListener("click", () => {
          auth.logout();
        });
      }
    }
  },
};

// Search functionality
const search = {
  // Initialize search
  init: () => {
    const searchForm = document.querySelector(".search-form");
    if (searchForm) {
      searchForm.addEventListener("submit", (e) => {
        e.preventDefault();
        const query = searchForm.querySelector('input[type="text"]').value;
        search.performSearch(query);
      });
    }
  },

  // Perform search
  performSearch: async (query) => {
    try {
      const response = await fetch(
        `/api/search.php?q=${encodeURIComponent(query)}`
      );
      const data = await response.json();

      if (data.success) {
        search.displayResults(data.results);
      } else {
        utils.showNotification(data.message || "No results found", "error");
      }
    } catch (error) {
      utils.handleError(error);
    }
  },

  // Display search results
  displayResults: (results) => {
    const resultsContainer = document.querySelector(".results-grid");
    if (!resultsContainer) return;

    resultsContainer.innerHTML = results
      .map(
        (result) => `
            <div class="teacher-card">
                <div class="teacher-header">
                    <img src="${
                      result.profile_image || "../Images/default.jpg"
                    }" 
                         alt="${result.full_name}" 
                         class="teacher-image" />
                    <div class="teacher-rating">${"★".repeat(
                      result.rating
                    )}${"☆".repeat(5 - result.rating)} ${result.rating}</div>
                </div>
                <div class="teacher-content">
                    <h4>${result.full_name}</h4>
                    <p class="teacher-location">${result.location}</p>
                    <div class="teacher-skills">
                        ${result.skills
                          .map(
                            (skill) => `
                            <span class="skill-tag">${skill.name}</span>
                        `
                          )
                          .join("")}
                    </div>
                    <div class="teacher-actions">
                        <a href="/HTML/profile.html?id=${
                          result.id
                        }" class="btn secondary">View Profile</a>
                        <button class="btn primary request-exchange" data-user-id="${
                          result.id
                        }">Request Exchange</button>
                    </div>
                </div>
            </div>
        `
      )
      .join("");

    // Add event listeners to exchange request buttons
    document.querySelectorAll(".request-exchange").forEach((button) => {
      button.addEventListener("click", (e) => {
        const userId = e.target.dataset.userId;
        search.requestExchange(userId);
      });
    });
  },

  // Request exchange
  requestExchange: async (userId) => {
    if (!auth.isLoggedIn()) {
      window.location.href = "/HTML/login.html";
      return;
    }

    try {
      const response = await fetch("/api/exchange.php", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify({
          teacher_id: userId,
        }),
      });

      const data = await response.json();
      if (data.success) {
        utils.showNotification("Exchange request sent successfully!");
      } else {
        utils.showNotification(
          data.message || "Failed to send request",
          "error"
        );
      }
    } catch (error) {
      utils.handleError(error);
    }
  },
};

// Initialize everything when DOM is loaded
document.addEventListener("DOMContentLoaded", () => {
  navigation.init();
  search.init();
});
