/**
 * AUPWU Management System
 * Main JavaScript file
 */

// Initialize when DOM is fully loaded
document.addEventListener('DOMContentLoaded', function() {
    // Initialize Bootstrap tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function(tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    // Initialize Bootstrap popovers
    var popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
    var popoverList = popoverTriggerList.map(function(popoverTriggerEl) {
        return new bootstrap.Popover(popoverTriggerEl);
    });
    
    // Initialize DataTables if they exist
    if (typeof $.fn.DataTable !== 'undefined') {
        $('.data-table').each(function() {
            $(this).DataTable({
                responsive: true,
                language: {
                    search: "_INPUT_",
                    searchPlaceholder: "Search...",
                    lengthMenu: "Show _MENU_ entries per page",
                    info: "Showing _START_ to _END_ of _TOTAL_ entries",
                    infoEmpty: "Showing 0 to 0 of 0 entries",
                    infoFiltered: "(filtered from _MAX_ total entries)"
                },
                dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>' +
                     '<"row"<"col-sm-12"tr>>' +
                     '<"row"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>',
                pagingType: "full_numbers"
            });
        });
    }
    
    // Form validation
    const forms = document.querySelectorAll('.needs-validation');
    
    Array.from(forms).forEach(form => {
        form.addEventListener('submit', event => {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            
            form.classList.add('was-validated');
        }, false);
    });
    
    // Password toggle visibility
    const togglePassword = document.querySelectorAll('.toggle-password');
    
    togglePassword.forEach(button => {
        button.addEventListener('click', function() {
            const passwordInput = document.querySelector(this.getAttribute('data-target'));
            
            // Toggle type attribute
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            
            // Toggle icon
            this.innerHTML = type === 'password' ? 
                '<i class="fas fa-eye"></i>' : 
                '<i class="fas fa-eye-slash"></i>';
        });
    });
    
    // File input display filename
    const fileInputs = document.querySelectorAll('.custom-file-input');
    
    fileInputs.forEach(input => {
        input.addEventListener('change', function(e) {
            const fileName = this.files[0]?.name || 'No file chosen';
            const nextSibling = this.nextElementSibling;
            
            if (nextSibling && nextSibling.classList.contains('custom-file-label')) {
                nextSibling.textContent = fileName;
            }
        });
    });
    
    // Image preview
    const imageInputs = document.querySelectorAll('.image-upload-input');
    
    imageInputs.forEach(input => {
        input.addEventListener('change', function() {
            const previewId = this.getAttribute('data-preview');
            const preview = document.getElementById(previewId);
            
            if (preview && this.files && this.files[0]) {
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    preview.src = e.target.result;
                    preview.style.display = 'block';
                };
                
                reader.readAsDataURL(this.files[0]);
            }
        });
    });
    
    // Print functionality
    const printButtons = document.querySelectorAll('.btn-print');
    
    printButtons.forEach(button => {
        button.addEventListener('click', function() {
            window.print();
        });
    });
    
    // Voting system - candidate selection
    const candidateCards = document.querySelectorAll('.candidate-card');
    
    candidateCards.forEach(card => {
        card.addEventListener('click', function() {
            const radioInput = this.querySelector('input[type="radio"]');
            
            if (radioInput) {
                radioInput.checked = true;
                
                // Remove selected class from all cards in the same group
                const name = radioInput.getAttribute('name');
                document.querySelectorAll(`input[name="${name}"]`).forEach(input => {
                    const parentCard = input.closest('.candidate-card');
                    if (parentCard) {
                        parentCard.classList.remove('selected');
                    }
                });
                
                // Add selected class to this card
                this.classList.add('selected');
            }
        });
    });
    
    // Committee membership toggle
    const committeeToggles = document.querySelectorAll('.committee-toggle');
    
    committeeToggles.forEach(toggle => {
        toggle.addEventListener('change', function() {
            const targetId = this.getAttribute('data-target');
            const targetElement = document.getElementById(targetId);
            
            if (targetElement) {
                if (this.checked) {
                    targetElement.style.display = 'block';
                } else {
                    targetElement.style.display = 'none';
                }
            }
        });
    });
    
    // Date range validation
    const startDateInputs = document.querySelectorAll('.start-date');
    
    startDateInputs.forEach(input => {
        input.addEventListener('change', function() {
            const endDateId = this.getAttribute('data-end-date');
            const endDateInput = document.getElementById(endDateId);
            
            if (endDateInput && this.value) {
                endDateInput.setAttribute('min', this.value);
            }
        });
    });
});

/**
 * Format date string to locale format
 * @param {string} dateString - Date string to format
 * @param {boolean} includeTime - Whether to include time in the formatted string
 * @return {string} Formatted date string
 */
function formatDate(dateString, includeTime = false) {
    if (!dateString) return '';
    
    const date = new Date(dateString);
    
    const options = {
        year: 'numeric',
        month: 'long',
        day: 'numeric'
    };
    
    if (includeTime) {
        options.hour = '2-digit';
        options.minute = '2-digit';
    }
    
    return date.toLocaleDateString('en-US', options);
}

/**
 * Confirm deletion with custom message
 * @param {string} message - Confirmation message
 * @return {boolean} True if confirmed, false otherwise
 */
function confirmDelete(message = 'Are you sure you want to delete this item? This action cannot be undone.') {
    return confirm(message);
}

/**
 * Dynamically load content into a target element via AJAX
 * @param {string} url - URL to fetch content from
 * @param {string} targetId - ID of target element to load content into
 * @param {Function} callback - Optional callback function to execute after loading
 */
function loadContent(url, targetId, callback = null) {
    const targetElement = document.getElementById(targetId);
    
    if (!targetElement) {
        console.error(`Target element with ID "${targetId}" not found.`);
        return;
    }
    
    // Show loading indicator
    targetElement.innerHTML = '<div class="text-center p-3"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div><p class="mt-2">Loading content...</p></div>';
    
    fetch(url)
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! Status: ${response.status}`);
            }
            return response.text();
        })
        .then(html => {
            targetElement.innerHTML = html;
            
            if (typeof callback === 'function') {
                callback();
            }
        })
        .catch(error => {
            targetElement.innerHTML = `<div class="alert alert-danger">Error loading content: ${error.message}</div>`;
            console.error('Error loading content:', error);
        });
}

/**
 * Submit form data via AJAX
 * @param {HTMLFormElement} form - Form element to submit
 * @param {Function} successCallback - Function to call on successful submission
 * @param {Function} errorCallback - Function to call on submission error
 */
function submitFormAjax(form, successCallback, errorCallback) {
    const formData = new FormData(form);
    
    fetch(form.action, {
        method: form.method,
        body: formData
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP error! Status: ${response.status}`);
        }
        return response.json();
    })
    .then(data => {
        if (typeof successCallback === 'function') {
            successCallback(data);
        }
    })
    .catch(error => {
        if (typeof errorCallback === 'function') {
            errorCallback(error);
        } else {
            console.error('Form submission error:', error);
        }
    });
}
