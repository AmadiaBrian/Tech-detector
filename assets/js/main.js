// Global loading indicator
const createLoadingOverlay = () => {
    const overlay = document.createElement('div');
    overlay.id = 'loading-overlay';
    overlay.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden';
    overlay.innerHTML = `
        <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-xl text-center">
            <div class="mx-auto h-16 w-16 border-4 border-gray-200 border-t-orange-500 rounded-full animate-spin"></div>
            <p class="mt-2 text-gray-700 dark:text-gray-300">Loading, please wait...</p>
        </div>
    `;
    document.body.appendChild(overlay);
    return overlay;
};

let loadingOverlay = null;

// Show loading overlay
const showLoading = () => {
    if (!loadingOverlay) {
        loadingOverlay = createLoadingOverlay();
    }
    loadingOverlay.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
};

// Hide loading overlay
const hideLoading = () => {
    if (loadingOverlay) {
        loadingOverlay.classList.add('hidden');
        document.body.style.overflow = '';
    }
};

// Theme Toggle
const themeToggle = document.getElementById('theme-toggle');
const themeIcon = document.getElementById('theme-icon');
const html = document.documentElement;
const prefersDarkScheme = window.matchMedia('(prefers-color-scheme: dark)');

// Function to set the theme
function setTheme(theme) {
    if (theme === 'dark') {
        html.classList.add('dark');
        html.setAttribute('data-theme', 'dark');
        if (themeIcon) themeIcon.textContent = '☀️';
        localStorage.setItem('theme', 'dark');
    } else {
        html.classList.remove('dark');
        html.removeAttribute('data-theme');
        if (themeIcon) themeIcon.textContent = '🌙';
        localStorage.setItem('theme', 'light');
    }
}

// Check for saved user preference or use system preference
const savedTheme = localStorage.getItem('theme');
let currentTheme;

if (savedTheme) {
    currentTheme = savedTheme;
} else {
    currentTheme = prefersDarkScheme.matches ? 'dark' : 'light';
}

// Apply the theme
setTheme(currentTheme);

// Listen for system theme changes
prefersDarkScheme.addListener((e) => {
    if (!localStorage.getItem('theme')) {
        setTheme(e.matches ? 'dark' : 'light');
    }
});

// Toggle theme on button click
if (themeToggle) {
    themeToggle.addEventListener('click', () => {
        const currentTheme = localStorage.getItem('theme') || (prefersDarkScheme.matches ? 'dark' : 'light');
        setTheme(currentTheme === 'dark' ? 'light' : 'dark');
    });
}

// Form submission with loading state
document.addEventListener('DOMContentLoaded', () => {
  // Hide any existing loading overlay on page load
  const existingOverlay = document.getElementById('loading-overlay');
  if (existingOverlay) {
    existingOverlay.classList.add('hidden');
    document.body.style.overflow = '';
  }

  // Handle form submissions
  const scanForm = document.getElementById('scan-form');
  if (scanForm) {
    scanForm.addEventListener('submit', function(e) {
      const submitBtn = this.querySelector('button[type="submit"]');
      
      if (submitBtn) {
        submitBtn.disabled = true;
        const originalText = submitBtn.innerHTML;
        submitBtn.innerHTML = '<span class="inline-block animate-spin mr-2">⏳</span> Scanning...';
        
        // Show loading overlay
        showLoading();
        
        // Re-enable button if form submission fails
        setTimeout(() => {
          submitBtn.disabled = false;
          submitBtn.innerHTML = originalText;
        }, 10000); // 10 second timeout
      }
    });
  }

  // Copy to clipboard functionality
  const copyButtons = document.querySelectorAll('[data-copy]');
  copyButtons.forEach(button => {
    button.addEventListener('click', function() {
      const targetId = this.getAttribute('data-copy');
      const targetElement = document.getElementById(targetId);
      
      if (targetElement) {
        const textToCopy = targetElement.innerText || targetElement.value;
        navigator.clipboard.writeText(textToCopy).then(() => {
          const originalText = this.innerHTML;
          this.innerHTML = '✅ Copied!';
          setTimeout(() => {
            this.innerHTML = originalText;
          }, 2000);
        }).catch(err => {
          console.error('Failed to copy text: ', err);
        });
      }
    });
  });

  // Toggle sections
  const toggleButtons = document.querySelectorAll('[data-toggle]');
  toggleButtons.forEach(button => {
    button.addEventListener('click', function() {
      const targetId = this.getAttribute('data-toggle');
      const targetElement = document.getElementById(targetId);
      
      if (targetElement) {
        const isHidden = targetElement.style.display === 'none';
        targetElement.style.display = isHidden ? 'block' : 'none';
        this.innerHTML = isHidden ? '▼ ' + this.textContent.replace('▶ ', '') : '▶ ' + this.textContent.replace('▼ ', '');
      }
    });
  });
});

// Show/hide password
function togglePasswordVisibility(inputId) {
  const input = document.getElementById(inputId);
  const icon = document.querySelector(`[onclick="togglePasswordVisibility('${inputId}')"]`);
  
  if (input.type === 'password') {
    input.type = 'text';
    icon.innerHTML = '👁️';
  } else {
    input.type = 'password';
    icon.innerHTML = '👁️‍🗨️';
  }
}

// Tab functionality
function openTab(evt, tabName) {
  const tabContents = document.getElementsByClassName('tab-content');
  for (let i = 0; i < tabContents.length; i++) {
    tabContents[i].style.display = 'none';
  }
  
  const tabLinks = document.getElementsByClassName('tab-link');
  for (let i = 0; i < tabLinks.length; i++) {
    tabLinks[i].className = tabLinks[i].className.replace(' active', '');
  }
  
  document.getElementById(tabName).style.display = 'block';
  evt.currentTarget.className += ' active';
}

// Initialize first tab as active by default
document.addEventListener('DOMContentLoaded', () => {
  const firstTab = document.querySelector('.tab-content');
  const firstTabLink = document.querySelector('.tab-link');
  
  if (firstTab && firstTabLink) {
    firstTab.style.display = 'block';
    firstTabLink.classList.add('active');
  }

  // Initialize search functionality
  const searchInput = document.querySelector('input[placeholder="Search..."]');
  if (searchInput) {
    // Add search-input class for styling
    searchInput.classList.add('search-input');
    
    searchInput.addEventListener('keypress', function(e) {
      if (e.key === 'Enter') {
        e.preventDefault();
        const searchQuery = this.value.trim();
        if (searchQuery) {
          // Show loading indicator
          showLoading();
          
          // Redirect to search results page with the query
          // You can replace this with your actual search endpoint
          setTimeout(() => {
            window.location.href = `/search.php?q=${encodeURIComponent(searchQuery)}`;
          }, 500);
        }
      }
    });
  }

  // Sidebar toggle functionality
  const sidebarToggle = document.getElementById('sidebarToggle');
  const sidebar = document.getElementById('gscSidebar');
  const sidebarOverlay = document.getElementById('sidebarOverlay');
  
  if (sidebarToggle && sidebar) {
    sidebarToggle.addEventListener('click', function(e) {
      e.preventDefault();
      
      // Check if we're on mobile or desktop
      if (window.innerWidth <= 992) {
        // Mobile: toggle mobile-open class
        sidebar.classList.toggle('gsc-sidebar-mobile-open');
        
        if (sidebar.classList.contains('gsc-sidebar-mobile-open')) {
          document.body.style.overflow = 'hidden';
          if (sidebarOverlay) {
            sidebarOverlay.classList.add('active');
          }
        } else {
          document.body.style.overflow = '';
          if (sidebarOverlay) {
            sidebarOverlay.classList.remove('active');
          }
        }
      } else {
        // Desktop: toggle collapsed class
        sidebar.classList.toggle('gsc-sidebar-collapsed');
      }
    });
  }

  // Close sidebar when clicking overlay on mobile
  if (sidebarOverlay) {
    sidebarOverlay.addEventListener('click', function() {
      sidebar.classList.remove('gsc-sidebar-mobile-open');
      document.body.style.overflow = '';
      sidebarOverlay.classList.remove('active');
    });
  }

  // Close sidebar when clicking outside on mobile
  document.addEventListener('click', function(e) {
    const sidebar = document.getElementById('gscSidebar');
    const sidebarToggle = document.getElementById('sidebarToggle');
    
    if (sidebar && sidebarToggle) {
      if (!sidebar.contains(e.target) && !sidebarToggle.contains(e.target) && sidebar.classList.contains('gsc-sidebar-mobile-open')) {
        sidebar.classList.remove('gsc-sidebar-mobile-open');
        document.body.style.overflow = '';
        if (sidebarOverlay) {
          sidebarOverlay.classList.remove('active');
        }
      }
    }
  });
});
