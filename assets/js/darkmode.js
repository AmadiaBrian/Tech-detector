// Dark mode functionality
document.addEventListener('DOMContentLoaded', function() {
    const themeToggle = document.getElementById('theme-toggle');
    
    // Check for saved user preference, if any, on load
    const currentTheme = localStorage.getItem('theme') || 'light';
    
    // Apply the saved theme
    if (currentTheme === 'dark') {
        document.documentElement.classList.add('dark-mode');
        if (themeToggle) themeToggle.innerHTML = '<i class="fas fa-sun"></i>';
    } else {
        document.documentElement.classList.remove('dark-mode');
        if (themeToggle) themeToggle.innerHTML = '<i class="fas fa-moon"></i>';
    }
    
    // Toggle theme when button is clicked
    if (themeToggle) {
        themeToggle.addEventListener('click', function() {
            const html = document.documentElement;
            const isDark = !html.classList.contains('dark-mode');
            
            if (isDark) {
                html.classList.add('dark-mode');
                localStorage.setItem('theme', 'dark');
                themeToggle.innerHTML = '<i class="fas fa-sun"></i>';
            } else {
                html.classList.remove('dark-mode');
                localStorage.setItem('theme', 'light');
                themeToggle.innerHTML = '<i class="fas fa-moon"></i>';
            }
        });
    }
});
