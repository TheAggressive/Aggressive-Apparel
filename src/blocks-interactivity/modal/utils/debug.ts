/**
 * Debug utility for the modal block editor code.
 *
 * Disabled by default; non-critical messages are suppressed unless enabled.
 * Critical messages are retained once, deduplicated, so repeated editor warnings
 * such as block-existence checks that fire on every selection change do not
 * flood the in-memory debug buffer.
 */

interface LogEntry {
  message: string;
  critical: boolean;
  timestamp: number;
}

export class Debug {
  static enabled = false;
  static showCritical = true;
  static logs: LogEntry[] = [];
  static maxLogs = 100;
  // Dedup keys for critical messages already logged this session.
  static loggedCritical = new Set<string>();

  /**
   * Add a debug message.
   *
   * @param message  - The message to log.
   * @param critical - Whether this is a critical error message.
   */
  static add(message: string, critical = false): void {
    // Skip non-critical messages while debugging is disabled.
    if (!this.enabled && !critical) {
      return;
    }

    // Skip critical messages if they have been explicitly silenced.
    if (critical && !this.showCritical) {
      return;
    }

    // Deduplicate critical messages so the same warning logs only once.
    if (critical) {
      const key = this.getCriticalKey(message);
      if (this.loggedCritical.has(key)) {
        return;
      }
      this.loggedCritical.add(key);
    }

    const timestamp = new Date().toISOString().split('T')[1].slice(0, -1);
    const logMessage = `[${timestamp}] ${critical ? '[CRITICAL] ' : ''}${message}`;

    this.logs.push({
      message: logMessage,
      critical,
      timestamp: new Date().getTime(),
    });

    // Cap memory usage by keeping only the most recent entries.
    if (this.logs.length > this.maxLogs) {
      this.logs = this.logs.slice(-this.maxLogs);
    }
  }

  /**
   * Derive a dedup key for a critical message. Block-existence warnings are
   * keyed by their block UUID so each block only logs once.
   *
   * @param message - The critical message.
   * @return The dedup key.
   */
  static getCriticalKey(message: string): string {
    if (
      message.includes('no longer exists') ||
      message.includes('not found') ||
      message.includes('clearing reference')
    ) {
      const match = message.match(
        /[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}/i
      );
      if (match) {
        return `block-not-exists:${match[0]}`;
      }
    }
    return message;
  }

  /**
   * Enable debug mode.
   */
  static enable(): void {
    this.enabled = true;
    this.add('Debug mode enabled');
  }

  /**
   * Disable debug mode.
   */
  static disable(): void {
    this.add('Debug mode disabled');
    this.enabled = false;
  }

  /**
   * Enable or disable critical messages.
   *
   * @param show - Whether to show critical messages.
   */
  static setCritical(show: boolean): void {
    this.showCritical = show;
  }

  /**
   * Clear all stored logs and dedup state.
   */
  static clear(): void {
    this.logs = [];
    this.loggedCritical.clear();
    if (this.enabled) {
      this.add('Debug logs cleared');
    }
  }

  /**
   * Get all stored log entries.
   *
   * @return Array of log entries.
   */
  static getLogs(): LogEntry[] {
    return this.logs;
  }

  /**
   * Get only critical log entries.
   *
   * @return Array of critical log entries.
   */
  static getCriticalLogs(): LogEntry[] {
    return this.logs.filter(log => log.critical);
  }
}
