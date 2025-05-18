document.addEventListener("DOMContentLoaded", function () {
  // Initialize dashboard
  loadDashboardData();
});

async function loadDashboardData() {
  try {
    const response = await fetch("api/dashboard.php");
    const data = await response.json();

    if (data.success) {
      updateDashboardStats(data.data.stats);
      updateUpcomingExchanges(data.data.upcoming_exchanges);
      updateTopSkills(data.data.top_skills);
    } else {
      utils.showNotification(
        "Failed to load dashboard data: " + data.message,
        "error"
      );
    }
  } catch (error) {
    utils.showNotification(
      "Failed to load dashboard data: " + error.message,
      "error"
    );
  }
}

function updateDashboardStats(stats) {
  // Update statistics cards
  document.getElementById("teaching-skills-count").textContent =
    stats.teaching_skills;
  document.getElementById("learning-goals-count").textContent =
    stats.learning_goals;
  document.getElementById("total-exchanges-count").textContent =
    stats.total_exchanges;
  document.getElementById("completed-exchanges-count").textContent =
    stats.completed_exchanges;
  document.getElementById("pending-requests-count").textContent =
    stats.pending_requests;

  // Update rating display
  const ratingElement = document.getElementById("user-rating");
  ratingElement.textContent = stats.average_rating.toFixed(1);

  // Update star rating display
  const starsContainer = document.getElementById("rating-stars");
  starsContainer.innerHTML = generateStarRating(stats.average_rating);
}

function updateUpcomingExchanges(exchanges) {
  const container = document.getElementById("upcoming-exchanges");
  container.innerHTML = "";

  if (exchanges.length === 0) {
    container.innerHTML = '<p class="no-data">No upcoming exchanges</p>';
    return;
  }

  exchanges.forEach((exchange) => {
    const exchangeElement = document.createElement("div");
    exchangeElement.className = "exchange-card";
    exchangeElement.innerHTML = `
            <div class="exchange-header">
                <h4>${exchange.partner_name}</h4>
                <span class="status-badge ${exchange.status}">${
      exchange.status
    }</span>
            </div>
            <p class="location"><i class="fas fa-map-marker-alt"></i> ${
              exchange.partner_location
            }</p>
            <p class="date"><i class="far fa-calendar"></i> ${formatDate(
              exchange.created_at
            )}</p>
            ${
              exchange.status === "pending" &&
              exchange.receiver_id === currentUser.id
                ? `
                <div class="exchange-actions">
                    <button onclick="handleExchangeResponse(${exchange.id}, 'accepted')" class="btn-accept">Accept</button>
                    <button onclick="handleExchangeResponse(${exchange.id}, 'rejected')" class="btn-reject">Reject</button>
                </div>
            `
                : ""
            }
        `;
    container.appendChild(exchangeElement);
  });
}

function updateTopSkills(skills) {
  const container = document.getElementById("top-skills");
  container.innerHTML = "";

  if (skills.length === 0) {
    container.innerHTML = '<p class="no-data">No skills added yet</p>';
    return;
  }

  skills.forEach((skill) => {
    const skillElement = document.createElement("div");
    skillElement.className = "skill-card";
    skillElement.innerHTML = `
            <div class="skill-header">
                <h4>${skill.name}</h4>
                <span class="category-badge">${skill.category}</span>
            </div>
            <div class="skill-details">
                <p class="level">Level: ${skill.skill_level}</p>
                <p class="matches">${skill.potential_matches} potential matches</p>
            </div>
        `;
    container.appendChild(skillElement);
  });
}

async function handleExchangeResponse(exchangeId, status) {
  try {
    const response = await fetch("api/exchange.php", {
      method: "PUT",
      headers: {
        "Content-Type": "application/json",
      },
      body: JSON.stringify({
        exchange_id: exchangeId,
        status: status,
      }),
    });

    const data = await response.json();

    if (data.success) {
      utils.showNotification(`Exchange ${status} successfully`, "success");
      loadDashboardData(); // Refresh dashboard data
    } else {
      utils.showNotification(
        "Failed to update exchange: " + data.message,
        "error"
      );
    }
  } catch (error) {
    utils.showNotification(
      "Failed to update exchange: " + error.message,
      "error"
    );
  }
}

function generateStarRating(rating) {
  const fullStars = Math.floor(rating);
  const hasHalfStar = rating % 1 >= 0.5;
  let stars = "";

  for (let i = 0; i < 5; i++) {
    if (i < fullStars) {
      stars += '<i class="fas fa-star"></i>';
    } else if (i === fullStars && hasHalfStar) {
      stars += '<i class="fas fa-star-half-alt"></i>';
    } else {
      stars += '<i class="far fa-star"></i>';
    }
  }

  return stars;
}

function formatDate(dateString) {
  const date = new Date(dateString);
  return date.toLocaleDateString("en-US", {
    year: "numeric",
    month: "short",
    day: "numeric",
  });
}
