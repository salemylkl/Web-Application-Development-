document.addEventListener("DOMContentLoaded", function () {
  // Initialize reviews if on a user profile page
  const userId = document.getElementById("user-profile")?.dataset.userId;
  if (userId) {
    loadUserReviews(userId);
  }

  // Initialize review form if it exists
  const reviewForm = document.getElementById("review-form");
  if (reviewForm) {
    reviewForm.addEventListener("submit", handleReviewSubmit);
  }
});

async function loadUserReviews(userId) {
  try {
    const response = await fetch(`api/reviews.php?user_id=${userId}`);
    const data = await response.json();

    if (data.success) {
      updateReviewsDisplay(data.data.reviews, data.data.stats);
    } else {
      utils.showNotification(
        "Failed to load reviews: " + data.message,
        "error"
      );
    }
  } catch (error) {
    utils.showNotification("Failed to load reviews: " + error.message, "error");
  }
}

function updateReviewsDisplay(reviews, stats) {
  // Update review statistics
  const statsContainer = document.getElementById("review-stats");
  if (statsContainer) {
    statsContainer.innerHTML = `
            <div class="rating-summary">
                <div class="average-rating">
                    <span class="rating-number">${stats.average_rating.toFixed(
                      1
                    )}</span>
                    <div class="stars">${generateStarRating(
                      stats.average_rating
                    )}</div>
                </div>
                <div class="total-reviews">
                    ${stats.total_reviews} ${
      stats.total_reviews === 1 ? "review" : "reviews"
    }
                </div>
            </div>
        `;
  }

  // Update reviews list
  const reviewsContainer = document.getElementById("reviews-list");
  if (reviewsContainer) {
    reviewsContainer.innerHTML = "";

    if (reviews.length === 0) {
      reviewsContainer.innerHTML = '<p class="no-reviews">No reviews yet</p>';
      return;
    }

    reviews.forEach((review) => {
      const reviewElement = document.createElement("div");
      reviewElement.className = "review-card";
      reviewElement.innerHTML = `
                <div class="review-header">
                    <div class="reviewer-info">
                        <h4>${review.reviewer_name}</h4>
                        <p class="location"><i class="fas fa-map-marker-alt"></i> ${
                          review.reviewer_location
                        }</p>
                    </div>
                    <div class="review-rating">
                        ${generateStarRating(review.rating)}
                    </div>
                </div>
                <p class="review-date"><i class="far fa-calendar"></i> ${formatDate(
                  review.created_at
                )}</p>
                <p class="review-comment">${review.comment}</p>
                ${
                  review.reviewer_id === currentUser.id
                    ? `
                    <div class="review-actions">
                        <button onclick="editReview(${review.id}, ${review.rating}, '${review.comment}')" class="btn-edit">
                            <i class="fas fa-edit"></i> Edit
                        </button>
                        <button onclick="deleteReview(${review.id})" class="btn-delete">
                            <i class="fas fa-trash"></i> Delete
                        </button>
                    </div>
                `
                    : ""
                }
            `;
      reviewsContainer.appendChild(reviewElement);
    });
  }
}

async function handleReviewSubmit(event) {
  event.preventDefault();

  const form = event.target;
  const userId = form.dataset.userId;
  const rating = form.querySelector('input[name="rating"]:checked')?.value;
  const comment = form.querySelector('textarea[name="comment"]').value;

  if (!rating || !comment) {
    utils.showNotification("Please provide both rating and comment", "error");
    return;
  }

  try {
    const response = await fetch("api/reviews.php", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
      },
      body: JSON.stringify({
        receiver_id: userId,
        rating: parseInt(rating),
        comment: comment,
      }),
    });

    const data = await response.json();

    if (data.success) {
      utils.showNotification("Review submitted successfully", "success");
      form.reset();
      loadUserReviews(userId); // Refresh reviews
    } else {
      utils.showNotification(
        "Failed to submit review: " + data.message,
        "error"
      );
    }
  } catch (error) {
    utils.showNotification(
      "Failed to submit review: " + error.message,
      "error"
    );
  }
}

async function editReview(reviewId, currentRating, currentComment) {
  // Create and show edit modal
  const modal = document.createElement("div");
  modal.className = "modal";
  modal.innerHTML = `
        <div class="modal-content">
            <h3>Edit Review</h3>
            <form id="edit-review-form">
                <div class="rating-input">
                    ${[5, 4, 3, 2, 1]
                      .map(
                        (rating) => `
                        <label>
                            <input type="radio" name="rating" value="${rating}" ${
                          rating === currentRating ? "checked" : ""
                        }>
                            ${generateStarRating(rating)}
                        </label>
                    `
                      )
                      .join("")}
                </div>
                <textarea name="comment" required>${currentComment}</textarea>
                <div class="modal-actions">
                    <button type="submit" class="btn-primary">Save Changes</button>
                    <button type="button" class="btn-secondary" onclick="closeModal()">Cancel</button>
                </div>
            </form>
        </div>
    `;

  document.body.appendChild(modal);

  // Handle form submission
  const form = modal.querySelector("#edit-review-form");
  form.addEventListener("submit", async (event) => {
    event.preventDefault();

    const rating = form.querySelector('input[name="rating"]:checked')?.value;
    const comment = form.querySelector('textarea[name="comment"]').value;

    try {
      const response = await fetch("api/reviews.php", {
        method: "PUT",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify({
          review_id: reviewId,
          rating: parseInt(rating),
          comment: comment,
        }),
      });

      const data = await response.json();

      if (data.success) {
        utils.showNotification("Review updated successfully", "success");
        closeModal();
        loadUserReviews(document.getElementById("user-profile").dataset.userId);
      } else {
        utils.showNotification(
          "Failed to update review: " + data.message,
          "error"
        );
      }
    } catch (error) {
      utils.showNotification(
        "Failed to update review: " + error.message,
        "error"
      );
    }
  });
}

async function deleteReview(reviewId) {
  if (!confirm("Are you sure you want to delete this review?")) {
    return;
  }

  try {
    const response = await fetch("api/reviews.php", {
      method: "DELETE",
      headers: {
        "Content-Type": "application/json",
      },
      body: JSON.stringify({
        review_id: reviewId,
      }),
    });

    const data = await response.json();

    if (data.success) {
      utils.showNotification("Review deleted successfully", "success");
      loadUserReviews(document.getElementById("user-profile").dataset.userId);
    } else {
      utils.showNotification(
        "Failed to delete review: " + data.message,
        "error"
      );
    }
  } catch (error) {
    utils.showNotification(
      "Failed to delete review: " + error.message,
      "error"
    );
  }
}

function closeModal() {
  const modal = document.querySelector(".modal");
  if (modal) {
    modal.remove();
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
