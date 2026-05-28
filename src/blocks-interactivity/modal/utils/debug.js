/**
 * Simple debug utility for development
 */
export const Debug = {
  messages: [],
  enabled: false, // Disable debug output by default
  // Track critical messages we've already logged to prevent duplication
  loggedCritical: new Set(),

  /**
   * Add a debug message
   * @param {string}  message  - The message to add
   * @param {boolean} critical - Whether this is a critical error message
   */
  add(message, critical = false) {
    // Always log critical errors, regardless of enabled state
    if (!this.enabled && !critical) {
      return;
    }

    // For critical messages, check if we've already logged this exact message or similar
    if (critical) {
      // Check if this is a block existence message and extract the block ID
      if (
        message.includes('no longer exists') ||
        message.includes('not found') ||
        message.includes('clearing reference')
      ) {
        // Extract block ID if present using regex (looks for format matching UUIDs)
        const blockIdMatch = message.match(
          /[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}/i
        );

        if (blockIdMatch) {
          const blockId = blockIdMatch[0];
          const blockMessageKey = `block-not-exists:${blockId}`;

          // If we've already logged about this block, skip it
          if (this.loggedCritical.has(blockMessageKey)) {
            return;
          }

          // Mark this block as processed
          this.loggedCritical.add(blockMessageKey);
        }
      } else {
        // For other critical messages, use the full message as key
        if (this.loggedCritical.has(message)) {
          return;
        }
        this.loggedCritical.add(message);
      }
    }

    this.messages.push({
      time: Date.now(),
      message,
      critical,
    });

    // Log to console only if enabled or critical
    if (this.enabled || critical) {
       
      console.log('[Modal Debug]', message);
    }
  },

  /**
   * Get all debug messages
   * @return {Array} Array of debug messages
   */
  getAll() {
    return this.messages;
  },

  /**
   * Clear all debug messages
   */
  clear() {
    this.messages = [];
    this.loggedCritical.clear();
  },
};
