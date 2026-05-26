/**
 * My Account — accordion toggle for "Your account" group
 */
(function () {
    'use strict';

    function initAccountAccordion() {
        var btn = document.querySelector('.devhub-account-nav__group-btn');
        if (!btn) return;

        var group = btn.parentElement;

        btn.addEventListener('click', function (e) {
            e.preventDefault();
            var isOpen = group.classList.toggle('is-open');
            btn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        });
    }

    function normalizeIntlTelInputValue(input) {
        if (!input || !input.iti || typeof input.iti.getSelectedCountry !== 'function') {
            return false;
        }

        var selectedCountry = input.iti.getSelectedCountry();
        var dialCode = selectedCountry && selectedCountry.dialCode ? String(selectedCountry.dialCode) : '';
        var rawValue = String(input.value || '').replace(/\s+/g, '').trim();

        if (!dialCode || !rawValue || rawValue.charAt(0) !== '+') {
            return false;
        }

        var prefix = '+' + dialCode;
        if (rawValue.indexOf(prefix) !== 0) {
            return false;
        }

        var nationalValue = rawValue.slice(prefix.length);
        if (!nationalValue) {
            return false;
        }

        input.value = nationalValue;
        input.dispatchEvent(new Event('input', { bubbles: true }));
        return true;
    }

    function bindIntlTelInputNormalizers(input) {
        if (!input || input.dataset.devhubPhoneNormalizeBound === '1') {
            return;
        }

        input.dataset.devhubPhoneNormalizeBound = '1';

        input.addEventListener('blur', function () {
            normalizeIntlTelInputValue(input);
        });

        input.addEventListener('paste', function () {
            window.setTimeout(function () {
                normalizeIntlTelInputValue(input);
            }, 0);
        });
    }

    function normalizeAccountPhoneFields() {
        var selectors = ['#billing_phone', '#shipping_phone', '#address-phone'];

        selectors.forEach(function (selector) {
            document.querySelectorAll(selector).forEach(function (input) {
                bindIntlTelInputNormalizers(input);

                if (input.dataset.devhubPhoneNormalized === '1') {
                    return;
                }

                if (normalizeIntlTelInputValue(input)) {
                    input.dataset.devhubPhoneNormalized = '1';
                }
            });
        });
    }

    function initAccountPhoneNormalizer() {
        normalizeAccountPhoneFields();

        var attempts = 0;
        var maxAttempts = 20;
        var intervalId = window.setInterval(function () {
            normalizeAccountPhoneFields();
            attempts += 1;

            if (attempts >= maxAttempts) {
                window.clearInterval(intervalId);
            }
        }, 200);

        var observer = new MutationObserver(function () {
            normalizeAccountPhoneFields();
        });

        observer.observe(document.body, {
            childList: true,
            subtree: true
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            initAccountAccordion();
            initAccountPhoneNormalizer();
        });
    } else {
        initAccountAccordion();
        initAccountPhoneNormalizer();
    }
}());
