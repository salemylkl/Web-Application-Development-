// Utility functions
const utils = {
  showNotification: (message, type = "info") => {
    const notification = document.createElement("div");
    notification.className = `notification ${type}`;
    notification.textContent = message;
    document.body.appendChild(notification);
    setTimeout(() => notification.remove(), 3000);
  },
};

document.addEventListener("DOMContentLoaded", function () {
  const searchForm = document.getElementById("searchForm");
  const searchInput = document.getElementById("searchInput");
  const categorySelect = document.getElementById("category");
  const locationInput = document.getElementById("location");
  const skillLevelSelect = document.getElementById("skillLevel");
  const sortBySelect = document.getElementById("sortBy");
  const resultsContainer = document.getElementById("searchResults");
  const loadMoreBtn = document.getElementById("loadMoreBtn");

  let currentPage = 1;
  let isLoading = false;
  let hasMoreResults = true;

  // Initialize search
  function initSearch() {
    if (!searchForm) {
      console.error("Search form not found");
      return;
    }

    searchForm.addEventListener("submit", handleSearch);
    if (loadMoreBtn) {
      loadMoreBtn.addEventListener("click", loadMoreResults);
    }

    // Test API connection
    testApiConnection();
  }

  // Test API connection
  async function testApiConnection() {
    try {
      const response = await fetch("../api/test.php");
      const data = await response.json();
      console.log("API Test:", data);
    } catch (error) {
      console.error("API Test Failed:", error);
      utils.showNotification(
        "Unable to connect to the server. Please check your internet connection.",
        "error"
      );
    }
  }

  // Handle search form submission
  function handleSearch(e) {
    e.preventDefault();
    currentPage = 1;
    hasMoreResults = true;
    if (resultsContainer) {
      resultsContainer.innerHTML = "";
    }
    performSearch();
  }

  // Perform search
  async function performSearch() {
    if (isLoading || !hasMoreResults) return;

    isLoading = true;
    if (loadMoreBtn) {
      loadMoreBtn.textContent = "Loading...";
    }

    try {
      const params = new URLSearchParams({
        q: searchInput.value,
        category: categorySelect.value,
        location: locationInput.value,
        skill_level: skillLevelSelect.value,
        sort_by: sortBySelect.value,
        page: currentPage,
      });

      console.log("Searching with params:", params.toString());
      const response = await fetch(`../api/search.php?${params}`);

      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
      }

      const data = await response.json();
      console.log("Search response:", data);

      if (data.success) {
        displayResults(data.results);
        hasMoreResults = data.results.length === 10; // Assuming 10 results per page
        if (loadMoreBtn) {
          loadMoreBtn.style.display = hasMoreResults ? "block" : "none";
        }
      } else {
        utils.showNotification(data.message || "No results found", "error");
      }
    } catch (error) {
      console.error("Search error:", error);
      utils.showNotification(
        "Failed to perform search. Please try again.",
        "error"
      );
    } finally {
      isLoading = false;
      if (loadMoreBtn) {
        loadMoreBtn.textContent = "Load More Results";
      }
    }
  }

  // Display search results
  function displayResults(results) {
    if (!resultsContainer) {
      console.error("Results container not found");
      return;
    }

    if (results.length === 0) {
      resultsContainer.innerHTML = '<p class="no-results">No results found</p>';
      return;
    }

    results.forEach((user) => {
      const userCard = createUserCard(user);
      resultsContainer.appendChild(userCard);
    });
  }

  // Create user card element
  function createUserCard(user) {
    const card = document.createElement("div");
    card.className = "user-card";

    const skillsList = user.skills
      .map(
        (skill) =>
          `<span class="skill-tag">${skill.name} (${skill.skill_level})</span>`
      )
      .join("");

    card.innerHTML = `
            <div class="user-info">
                <img src="../Images/${user.id}.jpg" alt="${
      user.full_name
    }" class="user-avatar" onerror="this.src='../Images/default.jpg'">
                <h3>${user.full_name}</h3>
                <p class="location">${user.location}</p>
                <div class="rating">
                    ${generateStarRating(user.rating)}
                    <span>(${user.review_count} reviews)</span>
                </div>
            </div>
            <div class="skills-section">
                <h4>Skills Offered:</h4>
                <div class="skills-list">
                    ${skillsList}
                </div>
            </div>
            <div class="bio">
                <p>${user.bio || "No bio available"}</p>
            </div>
            <div class="actions">
                <button class="btn btn-primary view-profile" data-user-id="${
                  user.id
                }">View Profile</button>
                <button class="btn btn-secondary request-exchange" data-user-id="${
                  user.id
                }">Request Exchange</button>
            </div>
        `;

    // Add event listeners
    card.querySelector(".view-profile").addEventListener("click", () => {
      window.location.href = `profile.html?id=${user.id}`;
    });

    card.querySelector(".request-exchange").addEventListener("click", () => {
      handleExchangeRequest(user.id);
    });

    return card;
  }

  // Generate star rating HTML
  function generateStarRating(rating) {
    const fullStars = Math.floor(rating);
    const hasHalfStar = rating % 1 >= 0.5;
    const emptyStars = 5 - fullStars - (hasHalfStar ? 1 : 0);

    return `
            ${'<i class="fas fa-star"></i>'.repeat(fullStars)}
            ${hasHalfStar ? '<i class="fas fa-star-half-alt"></i>' : ""}
            ${'<i class="far fa-star"></i>'.repeat(emptyStars)}
        `;
  }

  // Handle exchange request
  async function handleExchangeRequest(userId) {
    const message = prompt("Enter a message for your exchange request:");
    if (!message) return;

    try {
      const response = await fetch("../api/exchange.php", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify({
          receiver_id: userId,
          message: message,
        }),
      });

      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
      }

      const data = await response.json();

      if (data.success) {
        utils.showNotification(
          "Exchange request sent successfully!",
          "success"
        );
      } else {
        utils.showNotification(data.message, "error");
      }
    } catch (error) {
      console.error("Exchange request error:", error);
      utils.showNotification(
        "Failed to send exchange request. Please try again.",
        "error"
      );
    }
  }

  // Load more results
  function loadMoreResults() {
    currentPage++;
    performSearch();
  }

  // Initialize search functionality
  initSearch();
});
