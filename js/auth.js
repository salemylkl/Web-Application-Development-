// Authentication and Profile Management
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

  // Handle login
  login: async (email, password) => {
    try {
      const response = await fetch("api/auth.php", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify({
          action: "login",
          email: email,
          password: password,
        }),
      });

      const data = await response.json();

      if (data.success) {
        localStorage.setItem("user", JSON.stringify(data.data));
        window.location.href = "dashboard.html";
      } else {
        throw new Error(data.message);
      }
    } catch (error) {
      utils.showNotification(error.message, "error");
    }
  },

  // Handle registration
  register: async (userData) => {
    try {
      const response = await fetch("api/auth.php", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify({
          action: "register",
          ...userData,
        }),
      });

      const data = await response.json();

      if (data.success) {
        localStorage.setItem("user", JSON.stringify(data.data));
        window.location.href = "dashboard.html";
      } else {
        throw new Error(data.message);
      }
    } catch (error) {
      utils.showNotification(error.message, "error");
    }
  },

  // Handle logout
  logout: () => {
    localStorage.removeItem("user");
    window.location.href = "index.html";
  },

  // Update user profile
  updateProfile: async (profileData) => {
    try {
      const user = auth.getCurrentUser();
      if (!user) throw new Error("Not logged in");

      const response = await fetch("api/profile.php", {
        method: "PUT",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify({
          user_id: user.id,
          ...profileData,
        }),
      });

      const data = await response.json();

      if (data.success) {
        localStorage.setItem("user", JSON.stringify(data.data));
        utils.showNotification("Profile updated successfully");
      } else {
        throw new Error(data.message);
      }
    } catch (error) {
      utils.showNotification(error.message, "error");
    }
  },

  // Add skill to user profile
  addSkill: async (skillData) => {
    try {
      const user = auth.getCurrentUser();
      if (!user) throw new Error("Not logged in");

      const response = await fetch("api/skills.php", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify({
          user_id: user.id,
          ...skillData,
        }),
      });

      const data = await response.json();

      if (data.success) {
        utils.showNotification("Skill added successfully");
        return data.data;
      } else {
        throw new Error(data.message);
      }
    } catch (error) {
      utils.showNotification(error.message, "error");
      return null;
    }
  },

  // Remove skill from user profile
  removeSkill: async (skillId) => {
    try {
      const user = auth.getCurrentUser();
      if (!user) throw new Error("Not logged in");

      const response = await fetch(`api/skills.php?skill_id=${skillId}`, {
        method: "DELETE",
        headers: {
          "Content-Type": "application/json",
        },
      });

      const data = await response.json();

      if (data.success) {
        utils.showNotification("Skill removed successfully");
      } else {
        throw new Error(data.message);
      }
    } catch (error) {
      utils.showNotification(error.message, "error");
    }
  },
};

// Initialize authentication functionality
document.addEventListener("DOMContentLoaded", () => {
  // Login form handler
  const loginForm = document.getElementById("loginForm");
  if (loginForm) {
    loginForm.addEventListener("submit", async (e) => {
      e.preventDefault();
      const email = document.getElementById("email").value;
      const password = document.getElementById("password").value;
      await auth.login(email, password);
    });
  }

  // Registration form handler
  const registerForm = document.getElementById("registerForm");
  if (registerForm) {
    registerForm.addEventListener("submit", async (e) => {
      e.preventDefault();
      const formData = new FormData(registerForm);
      const userData = Object.fromEntries(formData.entries());
      await auth.register(userData);
    });
  }

  // Logout handler
  const logoutBtn = document.getElementById("logoutBtn");
  if (logoutBtn) {
    logoutBtn.addEventListener("click", () => {
      auth.logout();
    });
  }

  // Profile update handler
  const profileForm = document.getElementById("profileForm");
  if (profileForm) {
    profileForm.addEventListener("submit", async (e) => {
      e.preventDefault();
      const formData = new FormData(profileForm);
      const profileData = Object.fromEntries(formData.entries());
      await auth.updateProfile(profileData);
    });
  }

  // Add skill handler
  const addSkillForm = document.getElementById("addSkillForm");
  if (addSkillForm) {
    addSkillForm.addEventListener("submit", async (e) => {
      e.preventDefault();
      const formData = new FormData(addSkillForm);
      const skillData = Object.fromEntries(formData.entries());
      const newSkill = await auth.addSkill(skillData);
      if (newSkill) {
        // Refresh skills list
        location.reload();
      }
    });
  }

  // Remove skill handlers
  document.querySelectorAll(".remove-skill").forEach((button) => {
    button.addEventListener("click", async (e) => {
      const skillId = e.target.dataset.skillId;
      await auth.removeSkill(skillId);
      // Remove skill element from DOM
      e.target.closest(".skill-card").remove();
    });
  });
});
