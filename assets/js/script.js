document.addEventListener('DOMContentLoaded', function() {
    initZenMailPoetPopups();
});

function initZenMailPoetPopups() {
    const popupWrappers = document.querySelectorAll('.zen-mp-popup-wrapper');
    
    popupWrappers.forEach(function(wrapper) {
        const listIds = wrapper.getAttribute('data-list-ids');
        if (!listIds) return;

        const customForm = wrapper.querySelector('.zen-mp-custom-form');
        const customEmailInput = customForm.querySelector('.zen-mp-input-email');
        const submitBtn = customForm.querySelector('.zen-mp-submit-btn');
        const btnText = submitBtn.querySelector('.zen-mp-btn-text');
        const btnLoader = submitBtn.querySelector('.zen-mp-btn-loader');
        const feedbackContainer = wrapper.querySelector('.zen-mp-feedback-container');
        const feedbackMessage = wrapper.querySelector('.zen-mp-feedback-message');
        const closeBtns = wrapper.querySelectorAll('.zen-mp-close-btn, .zen-mp-overlay');

        // Storage keys based on lists configuration
        const subscribedKey = `zen_mp_subscribed_${listIds.replace(/,/g, '_')}`;
        const dismissedKey = `zen_mp_dismissed_${listIds.replace(/,/g, '_')}`;
        const dismissedExpiryKey = `zen_mp_dismissed_expiry_${listIds.replace(/,/g, '_')}`;

        // 1. Check if we should display the popup
        const delayAttr = wrapper.getAttribute('data-delay');
        const delay = delayAttr ? parseInt(delayAttr, 10) : 2500;
        const frequencyAttr = wrapper.getAttribute('data-frequency');
        const frequency = frequencyAttr ? parseInt(frequencyAttr, 10) : 30;

        if (shouldShowPopup(subscribedKey, dismissedKey, dismissedExpiryKey)) {
            // Display popup after a delay
            setTimeout(function() {
                openPopup(wrapper);
            }, delay);
        }

        // 2. Close event handlers
        closeBtns.forEach(function(btn) {
            btn.addEventListener('click', function() {
                closePopup(wrapper);
                // Set dismissal cookie/localStorage for configured frequency days
                setDismissedState(dismissedKey, dismissedExpiryKey, frequency);
            });
        });

        // Close on Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && wrapper.classList.contains('zen-mp-active')) {
                closePopup(wrapper);
                setDismissedState(dismissedKey, dismissedExpiryKey, frequency);
            }
        });

        // 3. Handle submission of the custom form
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

            // Set loading state
            submitBtn.disabled = true;
            btnText.style.display = 'none';
            btnLoader.style.display = 'inline-block';
            feedbackContainer.style.display = 'none';

            // Gather custom message configs from attributes
            const msgSuccess = wrapper.getAttribute('data-msg-success') || '';
            const msgError = wrapper.getAttribute('data-msg-error') || '';
            const msgAlready = wrapper.getAttribute('data-msg-already') || '';

            // Construct payload
            const formData = new FormData();
            formData.append('action', 'zen_mailpoet_subscribe');
            formData.append('security', zenMailPoetHelper.nonce);
            formData.append('email', emailValue);
            formData.append('list_ids', listIds);
            formData.append('msg_success', msgSuccess);
            formData.append('msg_error', msgError);
            formData.append('msg_already', msgAlready);

            // Implement 10-second timeout using AbortController
            const controller = new AbortController();
            const timeoutId = setTimeout(function() {
                controller.abort();
            }, 10000); // 10 seconds

            fetch(zenMailPoetHelper.ajax_url, {
                method: 'POST',
                body: formData,
                signal: controller.signal
            })
            .then(function(response) {
                clearTimeout(timeoutId);
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(function(res) {
                if (res.success) {
                    // Success condition (subscribed)
                    showFeedback(feedbackContainer, feedbackMessage, res.message, 'success');
                    
                    if (res.code === 'subscribed') {
                        setSubscribedState(subscribedKey);
                    }

                    // Reset form fields
                    if (customEmailInput) customEmailInput.value = '';
                    if (privacyCheckbox) privacyCheckbox.checked = false;

                    // Reset form submit button state
                    resetFormState(submitBtn, btnText, btnLoader);

                    // If response contract specifies closing the popup
                    if (res.closePopup) {
                        setTimeout(function() {
                            closePopup(wrapper);
                        }, 3000);
                    }
                } else {
                    // Failure condition (already subscribed, invalid email, config issues, etc.)
                    showFeedback(feedbackContainer, feedbackMessage, res.message || 'Subscription failed.', 'error');
                    resetFormState(submitBtn, btnText, btnLoader);

                    // If the user is already subscribed, let's persist that status in localStorage too
                    if (res.code === 'already_subscribed') {
                        setSubscribedState(subscribedKey);
                        setTimeout(function() {
                            closePopup(wrapper);
                        }, 3000);
                    }
                }
            })
            .catch(function(error) {
                clearTimeout(timeoutId);
                resetFormState(submitBtn, btnText, btnLoader);

                if (error.name === 'AbortError') {
                    showFeedback(feedbackContainer, feedbackMessage, 'The request timed out. Please try again.', 'error');
                } else {
                    showFeedback(feedbackContainer, feedbackMessage, 'An unexpected network error occurred.', 'error');
                }
            });
        });

        // 4. Slideshow logic (Transitions every 4 seconds)
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
