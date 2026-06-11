document.addEventListener('DOMContentLoaded', function() {
    initZenMailPoetPopups();
});

function initZenMailPoetPopups() {
    const popupWrappers = document.querySelectorAll('.zen-mp-popup-wrapper');
    
    popupWrappers.forEach(function(wrapper) {
        const formId = wrapper.getAttribute('data-form-id');
        if (!formId) return;

        const hiddenContainer = wrapper.querySelector('.zen-mp-hidden-form-container');
        const hiddenForm = hiddenContainer ? hiddenContainer.querySelector('form') : null;
        
        if (!hiddenForm) {
            console.error(`Zen MailPoet Helper: Native form for ID ${formId} not found.`);
            return;
        }

        const customForm = wrapper.querySelector('.zen-mp-custom-form');
        const customEmailInput = customForm.querySelector('.zen-mp-input-email');
        const submitBtn = customForm.querySelector('.zen-mp-submit-btn');
        const btnText = submitBtn.querySelector('.zen-mp-btn-text');
        const btnLoader = submitBtn.querySelector('.zen-mp-btn-loader');
        const feedbackContainer = wrapper.querySelector('.zen-mp-feedback-container');
        const feedbackMessage = wrapper.querySelector('.zen-mp-feedback-message');
        const closeBtns = wrapper.querySelectorAll('.zen-mp-close-btn, .zen-mp-overlay');

        // Storage keys
        const subscribedKey = `zen_mp_subscribed_${formId}`;
        const dismissedKey = `zen_mp_dismissed_${formId}`;
        const dismissedExpiryKey = `zen_mp_dismissed_expiry_${formId}`;

        // 1. Check if we should display the popup
        if (shouldShowPopup(subscribedKey, dismissedKey, dismissedExpiryKey)) {
            // Display popup after a delay of 2.5 seconds
            setTimeout(function() {
                openPopup(wrapper);
            }, 2500);
        }

        // 2. Close event handlers
        closeBtns.forEach(function(btn) {
            btn.addEventListener('click', function() {
                closePopup(wrapper);
                // Set dismissal cookie/localStorage for 30 days
                setDismissedState(dismissedKey, dismissedExpiryKey, 30);
            });
        });

        // Close on Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && wrapper.classList.contains('zen-mp-active')) {
                closePopup(wrapper);
                setDismissedState(dismissedKey, dismissedExpiryKey, 30);
            }
        });

        // 3. Find hidden MailPoet inputs
        // MailPoet names are obfuscated, but they have distinct data-automation-id attributes.
        const hiddenEmailInput = hiddenForm.querySelector('[data-automation-id="form_email"]') || 
                                 hiddenForm.querySelector(`#form_email_${formId}`) ||
                                 hiddenForm.querySelector('input[type="email"]');

        if (!hiddenEmailInput) {
            console.error(`Zen MailPoet Helper: Hidden email field for form ${formId} not found.`);
            return;
        }

        // 4. Setup MutationObserver to watch hidden form messages
        const messageContainer = hiddenForm.querySelector('.mailpoet_message');
        if (messageContainer) {
            setupObserver(messageContainer, {
                onSuccess: function(msg) {
                    showFeedback(feedbackContainer, feedbackMessage, msg, 'success');
                    setSubscribedState(subscribedKey);
                    resetFormState(submitBtn, btnText, btnLoader);
                    
                    // Clear custom inputs and checkboxes
                    if (customEmailInput) customEmailInput.value = '';
                    const privacyCheckbox = customForm.querySelector('.zen-mp-checkbox-input');
                    if (privacyCheckbox) privacyCheckbox.checked = false;

                    // Automatically close the popup after a brief delay
                    setTimeout(function() {
                        closePopup(wrapper);
                    }, 3000);
                },
                onError: function(msg) {
                    showFeedback(feedbackContainer, feedbackMessage, msg, 'error');
                    resetFormState(submitBtn, btnText, btnLoader);
                }
            });
        }

        // 5. Handle submission of the custom form
        customForm.addEventListener('submit', function(e) {
            e.preventDefault();

            const emailValue = customEmailInput ? customEmailInput.value.trim() : '';
            const privacyCheckbox = customForm.querySelector('.zen-mp-checkbox-input');

            if (!emailValue) {
                showFeedback(feedbackContainer, feedbackMessage, 'Please enter your email address.', 'error');
                return;
            }

            // Client side validation of the required privacy checkbox
            if (privacyCheckbox && !privacyCheckbox.checked) {
                showFeedback(feedbackContainer, feedbackMessage, 'Please accept the Privacy Policy before subscribing.', 'error');
                return;
            }

            // Copy value to MailPoet's hidden field
            hiddenEmailInput.value = emailValue;
            
            // Check if there are other fields we need to sync (like names)
            const customFirstNameInput = customForm.querySelector('.zen-mp-input-first-name');
            if (customFirstNameInput) {
                const hiddenFirstNameInput = hiddenForm.querySelector('[data-automation-id="form_first_name"]') ||
                                             hiddenForm.querySelector(`#form_first_name_${formId}`);
                if (hiddenFirstNameInput) {
                    hiddenFirstNameInput.value = customFirstNameInput.value.trim();
                }
            }

            const customLastNameInput = customForm.querySelector('.zen-mp-input-last-name');
            if (customLastNameInput) {
                const hiddenLastNameInput = hiddenForm.querySelector('[data-automation-id="form_last_name"]') ||
                                            hiddenForm.querySelector(`#form_last_name_${formId}`);
                if (hiddenLastNameInput) {
                    hiddenLastNameInput.value = customLastNameInput.value.trim();
                }
            }

            // Set loading state
            submitBtn.disabled = true;
            btnText.style.display = 'none';
            btnLoader.style.display = 'inline-block';
            feedbackContainer.style.display = 'none';

            // Trigger click on MailPoet's hidden submit button to run MailPoet's validation and AJAX
            const hiddenSubmitBtn = hiddenForm.querySelector('input[type="submit"]') || 
                                    hiddenForm.querySelector('button[type="submit"]') ||
                                    hiddenForm.querySelector('.mailpoet_submit');
            
            if (hiddenSubmitBtn) {
                hiddenSubmitBtn.click();
            } else {
                // Fallback: Dispatch submit event directly to form
                hiddenForm.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
            }
        });

        // 6. Slideshow logic (Transitions every 4 seconds)
        const slides = wrapper.querySelectorAll('.zen-mp-slide');
        if (slides.length > 1) {
            let currentSlideIndex = 0;
            setInterval(function() {
                slides[currentSlideIndex].classList.remove('zen-mp-slide-active');
                currentSlideIndex = (currentSlideIndex + 1) % slides.length;
                slides[currentSlideIndex].classList.add('zen-mp-slide-active');
            }, 4000);
        }
    });
}

/**
 * Open the popup wrapper
 */
function openPopup(wrapper) {
    wrapper.style.display = 'flex';
    // Force reflow
    wrapper.offsetHeight;
    wrapper.classList.add('zen-mp-active');
}

/**
 * Close the popup wrapper
 */
function closePopup(wrapper) {
    wrapper.classList.remove('zen-mp-active');
    setTimeout(function() {
        wrapper.style.display = 'none';
    }, 400); // Matches the CSS transition duration
}

/**
 * Show success or error feedback messages
 */
function showFeedback(container, messageEl, text, type) {
    messageEl.textContent = text;
    container.className = `zen-mp-feedback-container zen-mp-${type}`;
    container.style.display = 'block';
}

/**
 * Restore CTA button submit state
 */
function resetFormState(btn, textEl, loaderEl) {
    btn.disabled = false;
    textEl.style.display = 'inline-block';
    loaderEl.style.display = 'none';
}

/**
 * Check if the popup should be shown based on localStorage subscription and dismissal status
 */
function shouldShowPopup(subscribedKey, dismissedKey, expiryKey) {
    // 1. If subscribed, do not show
    if (localStorage.getItem(subscribedKey) === 'true') {
        return false;
    }

    // 2. If dismissed, check if the exclusion period has expired
    if (localStorage.getItem(dismissedKey) === 'true') {
        const expiryTime = localStorage.getItem(expiryKey);
        if (expiryTime && Date.now() < parseInt(expiryTime, 10)) {
            return false;
        }
        // Expiration passed, clear state
        localStorage.removeItem(dismissedKey);
        localStorage.removeItem(expiryKey);
    }

    return true;
}

/**
 * Set the subscribed flag
 */
function setSubscribedState(subscribedKey) {
    localStorage.setItem(subscribedKey, 'true');
}

/**
 * Set the dismissed flag with expiry
 */
function setDismissedState(dismissedKey, expiryKey, days) {
    localStorage.setItem(dismissedKey, 'true');
    const expiryTime = Date.now() + (days * 24 * 60 * 60 * 1000);
    localStorage.setItem(expiryKey, expiryTime.toString());
}

/**
 * Set up a MutationObserver to listen to MailPoet's AJAX feedback changes
 */
function setupObserver(targetNode, callbacks) {
    const config = { attributes: true, childList: true, subtree: true, characterData: true };

    const observer = new MutationObserver(function(mutationsList) {
        const successParagraph = targetNode.querySelector('.mailpoet_validate_success');
        const errorParagraph = targetNode.querySelector('.mailpoet_validate_error');

        // Check success message
        if (successParagraph && isElementVisible(successParagraph) && successParagraph.textContent.trim() !== '') {
            callbacks.onSuccess(successParagraph.textContent.trim());
        }
        // Check error message
        else if (errorParagraph && isElementVisible(errorParagraph) && errorParagraph.textContent.trim() !== '') {
            callbacks.onError(errorParagraph.textContent.trim());
        }
    });

    observer.observe(targetNode, config);
}

/**
 * Helper to check if a DOM element is visible
 */
function isElementVisible(element) {
    if (!element) return false;
    const style = window.getComputedStyle(element);
    return style.display !== 'none' && style.visibility !== 'hidden' && element.offsetWidth > 0 && element.offsetHeight > 0;
}
