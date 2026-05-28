/**
 * Utility functions for modal blocks
 */

/**
 * Debug utility to help with debugging
 */
export class Debug {
  static enabled = false;
  static logs = [];
  static maxLogs = 100;
  static showCritical = true; // Always show critical messages

  /**
   * Add a debug message
   *
   * @param {string}  message  The debug message
   * @param {boolean} critical Whether this is a critical message that should be shown even when debug is disabled
   */
  static add(message, critical = false) {
    // Skip non-critical messages when debugging is disabled
    if (!this.enabled && !critical) {
      return;
    }

    // Skip critical messages if explicitly disabled
    if (critical && !this.showCritical) {
      return;
    }

    // Add timestamp
    const timestamp = new Date().toISOString().split('T')[1].slice(0, -1);
    const logMessage = `[${timestamp}] ${critical ? '[CRITICAL] ' : ''}${message}`;

    // Log to console
    console.log(
      `%c MODAL ${critical ? 'ERROR' : 'DEBUG'} `,
      `background: ${critical ? '#e63946' : '#335c67'}; color: #fff`,
      logMessage
    );

    // Store in array
    this.logs.push({
      message: logMessage,
      critical,
      timestamp: new Date().getTime(),
    });

    // Trim array if needed
    if (this.logs.length > this.maxLogs) {
      this.logs = this.logs.slice(-this.maxLogs);
    }
  }

  /**
   * Enable debug mode
   */
  static enable() {
    this.enabled = true;
    this.add('Debug mode enabled');
  }

  /**
   * Disable debug mode
   */
  static disable() {
    this.add('Debug mode disabled');
    this.enabled = false;
  }

  /**
   * Enable or disable critical messages
   *
   * @param {boolean} show Whether to show critical messages
   */
  static setCritical(show) {
    this.showCritical = show;
  }

  /**
   * Clear debug logs
   */
  static clear() {
    this.logs = [];
    if (this.enabled) {
      console.clear();
      this.add('Debug logs cleared');
    }
  }

  /**
   * Get all debug logs
   *
   * @return {Array} Array of log messages
   */
  static getLogs() {
    return this.logs;
  }

  /**
   * Get only critical logs
   *
   * @return {Array} Array of critical log messages
   */
  static getCriticalLogs() {
    return this.logs.filter(log => log.critical);
  }
}

/**
 * Helper function to generate a unique ID
 *
 * @return {string} A unique ID
 */
export const generateUniqueId = () => {
  return 'id-' + Math.random().toString(36).substring(2, 9);
};

/**
 * Helper function to check if an element is visible in viewport
 *
 * @param {HTMLElement} element The element to check
 * @return {boolean} Whether the element is visible
 */
export const isElementVisible = element => {
  if (!element) {
    return false;
  }

  const rect = element.getBoundingClientRect();
  return (
    rect.top >= 0 &&
    rect.left >= 0 &&
    rect.bottom <=
      (window.innerHeight || document.documentElement.clientHeight) &&
    rect.right <= (window.innerWidth || document.documentElement.clientWidth)
  );
};
