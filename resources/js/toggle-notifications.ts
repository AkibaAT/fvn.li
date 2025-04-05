/**
 * Toggle Notifications Functionality
 * 
 * This module handles the toggle notifications functionality for games.
 * It sets up event listeners for the toggle notifications forms and handles
 * the AJAX requests to update the notification preferences.
 */

interface ToggleResponse {
    success: boolean;
    message: string;
    receive_updates: boolean;
}

/**
 * Set up a toggle form with event listeners
 */
function setupToggleForm(form: HTMLFormElement): void {
    const checkbox = form.querySelector('input[type="checkbox"]') as HTMLInputElement;
    const toggleLabel = form.querySelector('label') as HTMLLabelElement;

    if (!checkbox || !toggleLabel) return;

    // Prevent default label behavior and handle the toggle manually
    toggleLabel.addEventListener('click', function(e: Event) {
        e.preventDefault();

        // Toggle the checkbox state
        checkbox.checked = !checkbox.checked;

        // Submit the form
        form.dispatchEvent(new Event('submit'));
    });

    // Don't double-bind if this is the toggle-all-updates form
    // which has its own special handler
    if (form.classList.contains('toggle-all-updates-form')) {
        return;
    }

    // Handle form submission via AJAX
    form.addEventListener('submit', async function(e: Event) {
        e.preventDefault();

        try {
            // Get the form data
            const formData = new FormData(form);
            
            // Send the AJAX request
            const response = await fetch(form.action, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: formData,
            });

            if (!response.ok) {
                throw new Error(`HTTP error! Status: ${response.status}`);
            }

            const data = await response.json() as ToggleResponse;

            if (data.success) {
                // Update the checkbox state
                checkbox.checked = data.receive_updates;

                // Update the screen reader text
                const toggleDiv = checkbox.nextElementSibling;
                if (toggleDiv) {
                    const srOnlySpan = toggleDiv.querySelector('.sr-only');
                    if (srOnlySpan) {
                        srOnlySpan.textContent = data.receive_updates ? 'Turn off notifications' : 'Turn on notifications';
                    }
                }

                // Show success message
                showSuccessMessage(data.message);
            }
        } catch (error) {
            console.error('Error toggling notifications:', error);
            showErrorMessage('An error occurred. Please try again.');

            // Revert the checkbox state
            checkbox.checked = !checkbox.checked;
        }
    });
}

/**
 * Set up the toggle all updates form
 */
function setupToggleAllUpdatesForm(form: HTMLFormElement): void {
    const checkbox = form.querySelector('input[type="checkbox"]') as HTMLInputElement;
    
    if (!checkbox) return;

    // Handle form submission via AJAX
    form.addEventListener('submit', async function(e: Event) {
        e.preventDefault();

        try {
            // Get the form data
            const formData = new FormData(form);
            const checked = checkbox.checked;
            
            // Send the AJAX request
            const response = await fetch(form.action, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: formData,
            });

            if (!response.ok) {
                throw new Error(`HTTP error! Status: ${response.status}`);
            }

            const data = await response.json() as ToggleResponse;

            if (data.success) {
                // Update all individual toggle switches
                document.querySelectorAll('.toggle-updates-form').forEach(formElement => {
                    const individualForm = formElement as HTMLFormElement;
                    const individualCheckbox = individualForm.querySelector('input[type="checkbox"]') as HTMLInputElement;
                    const individualToggleDiv = individualCheckbox.nextElementSibling;
                    
                    if (individualToggleDiv) {
                        const individualSrOnlySpan = individualToggleDiv.querySelector('.sr-only');

                        // Update checkbox state
                        individualCheckbox.checked = data.receive_updates;

                        // Update screen reader text
                        if (individualSrOnlySpan) {
                            individualSrOnlySpan.textContent = data.receive_updates ? 'Turn off notifications' : 'Turn on notifications';
                        }
                    }
                });

                showSuccessMessage(data.message);
            }
        } catch (error) {
            console.error('Error toggling all notifications:', error);
            showErrorMessage('An error occurred. Please try again.');

            // Revert the checkbox state
            checkbox.checked = !checked;
        }
    });
}

/**
 * Show a success message
 */
function showSuccessMessage(message: string): void {
    // Check if we have a toast notification system
    if (typeof window.showToast === 'function') {
        window.showToast(message, 'success');
    } else {
        console.log('Success:', message);
    }
}

/**
 * Show an error message
 */
function showErrorMessage(message: string): void {
    // Check if we have a toast notification system
    if (typeof window.showToast === 'function') {
        window.showToast(message, 'error');
    } else {
        console.error('Error:', message);
    }
}

/**
 * Initialize the toggle notifications functionality
 */
function initToggleNotifications(): void {
    // Set up event handlers for toggle updates forms
    document.querySelectorAll('.toggle-updates-form').forEach(form => {
        setupToggleForm(form as HTMLFormElement);
    });

    // Set up event handler for toggle all updates form
    const toggleAllForm = document.querySelector('.toggle-all-updates-form') as HTMLFormElement;
    if (toggleAllForm) {
        setupToggleForm(toggleAllForm);
        setupToggleAllUpdatesForm(toggleAllForm);
    }
}

// Initialize when the DOM is loaded
document.addEventListener('DOMContentLoaded', initToggleNotifications);

// Export functions for potential use in other modules
export {
    setupToggleForm,
    setupToggleAllUpdatesForm,
    initToggleNotifications
};

// Add type definition for showToast function
declare global {
    interface Window {
        showToast?: (message: string, type: 'success' | 'error' | 'info' | 'warning') => void;
    }
}
