/**
 * DeviceHub — Flash Sale Countdown
 *
 * Reads data-countdown="YYYY-MM-DDTHH:mm:ss" from each .devhub-flash__timer
 * and ticks every second. Stops when countdown reaches zero.
 *
 * No dependencies. Loaded only on is_front_page() via inc/enqueue.php.
 */

(function () {
  "use strict";

  const TICK_INTERVAL_MS = 1000;

  function pad(n) {
    return String(n).padStart(2, "0");
  }

  /**
   * Update a single timer element.
   * @param {HTMLElement} el  .devhub-flash__timer
   */
  function tick(el) {
    const end = new Date(el.dataset.countdown).getTime();
    const diff = Math.max(0, end - Date.now());

    const days = Math.floor(diff / 86_400_000);
    const hours = Math.floor((diff % 86_400_000) / 3_600_000);
    const minutes = Math.floor((diff % 3_600_000) / 60_000);
    const seconds = Math.floor((diff % 60_000) / 1_000);

    el.querySelector('[data-part="days"]').textContent = pad(days);
    el.querySelector('[data-part="hours"]').textContent = pad(hours);
    el.querySelector('[data-part="minutes"]').textContent = pad(minutes);
    el.querySelector('[data-part="seconds"]').textContent = pad(seconds);

    // Stop interval when expired
    if (diff === 0) {
      clearInterval(el._countdownInterval);
    }
  }

  function initCountdowns() {
    document
      .querySelectorAll(".devhub-flash__timer[data-countdown]")
      .forEach(function (el) {
        tick(el); // immediate first tick — no blank flash on load
        el._countdownInterval = setInterval(() => tick(el), TICK_INTERVAL_MS);
      });
  }

  // Wait for DOM ready
  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", initCountdowns);
  } else {
    initCountdowns();
  }
})();
