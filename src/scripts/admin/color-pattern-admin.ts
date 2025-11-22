/**
 * Color Pattern Admin TypeScript
 *
 * Handles pattern image uploads and management in the WordPress admin
 *
 * @package Aggressive_Apparel
 * @since 1.0.0
 */

/**
 * Color Pattern Admin Class
 */
class AggressiveApparelColorPatternAdmin {
  private mediaFrame: any;
  private uploadButton!: HTMLButtonElement | null;
  private changeButton!: HTMLButtonElement | null;
  private removeButton!: HTMLButtonElement | null;
  private previewContainer!: HTMLElement | null;
  private hiddenInput!: HTMLInputElement | null;
  private termId!: number;

  /**
   * Initialize the pattern admin
   */
  constructor() {
    this.init();
  }

  /**
   * Initialize event listeners and UI
   */
  private init(): void {
    const container = document.querySelector(
      '.aggressive-apparel-color-pattern-admin'
    ) as HTMLElement;
    if (!container) {
      return;
    }

    this.termId = parseInt(container.dataset.termId || '0', 10) || 0;

    this.uploadButton = container.querySelector(
      '.aggressive-apparel-color-pattern-admin__upload-button'
    );
    this.changeButton = container.querySelector(
      '.aggressive-apparel-color-pattern-admin__change-button'
    );
    this.removeButton = container.querySelector(
      '.aggressive-apparel-color-pattern-admin__remove-button'
    );
    this.previewContainer = container.querySelector(
      '.aggressive-apparel-color-pattern-admin__preview'
    );
    this.hiddenInput = container.querySelector(
      'input[name="color_pattern_id"]'
    );

    this.bindEvents();
  }

  /**
   * Bind event listeners
   */
  private bindEvents(): void {
    this.uploadButton?.addEventListener('click', e => {
      e.preventDefault();
      this.openMediaUploader();
    });

    this.changeButton?.addEventListener('click', e => {
      e.preventDefault();
      this.openMediaUploader();
    });

    this.removeButton?.addEventListener('click', e => {
      e.preventDefault();
      this.confirmRemovePattern();
    });
  }

  /**
   * Open WordPress media uploader
   */
  private openMediaUploader(): void {
    // If media frame already exists, open it
    if (this.mediaFrame) {
      this.mediaFrame.open();
      return;
    }

    // Create media frame
    this.mediaFrame = (window as any).wp.media({
      title: (window as any).aggressiveApparelPattern.strings.selectImage,
      button: {
        text: (window as any).aggressiveApparelPattern.strings.useThisImage,
      },
      multiple: false,
      library: {
        type: 'image',
      },
    });

    // Handle selection
    this.mediaFrame.on('select', () => {
      const attachment = this.mediaFrame
        .state()
        .get('selection')
        .first()
        .toJSON();
      this.handleImageSelection(attachment);
    });

    this.mediaFrame.open();
  }

  /**
   * Handle image selection from media library
   */
  private handleImageSelection(attachment: any): void {
    const formData = new FormData();
    formData.append('action', 'upload_color_pattern');
    formData.append('nonce', (window as any).aggressiveApparelPattern.nonce);
    formData.append('term_id', this.termId.toString());
    formData.append('attachment_id', attachment.id);

    // Show loading state
    this.showLoadingState();

    // Upload via XMLHttpRequest
    const xhr = new XMLHttpRequest();
    xhr.open('POST', (window as any).aggressiveApparelPattern.ajaxUrl);

    xhr.onload = () => {
      if (xhr.status === 200) {
        try {
          const response = JSON.parse(xhr.responseText);
          if (response.success) {
            this.updatePreview(response.data);
            this.showSuccessMessage('Pattern uploaded successfully');
          } else {
            this.showErrorMessage(
              response.data?.message ||
                (window as any).aggressiveApparelPattern.strings.uploadError
            );
          }
        } catch {
          this.showErrorMessage(
            (window as any).aggressiveApparelPattern.strings.uploadError
          );
        }
      } else {
        this.showErrorMessage(
          (window as any).aggressiveApparelPattern.strings.uploadError
        );
      }
      this.hideLoadingState();
    };
    xhr.onerror = () => {
      this.showErrorMessage(
        (window as any).aggressiveApparelPattern.strings.uploadError
      );
      this.hideLoadingState();
    };

    xhr.send(formData);
  }

  /**
   * Confirm pattern removal
   */
  private confirmRemovePattern(): void {
    if (
      !window.confirm(
        'Are you sure you want to remove this pattern? This action cannot be undone.'
      )
    ) {
      return;
    }

    this.removePattern();
  }

  /**
   * Remove pattern via XMLHttpRequest
   */
  private removePattern(): void {
    const formData = new FormData();
    formData.append('action', 'delete_color_pattern');
    formData.append('nonce', (window as any).aggressiveApparelPattern.nonce);
    formData.append('term_id', this.termId.toString());

    // Show loading state
    this.showLoadingState();

    const xhr = new XMLHttpRequest();
    xhr.open('POST', (window as any).aggressiveApparelPattern.ajaxUrl);
    xhr.onload = () => {
      if (xhr.status === 200) {
        try {
          const response = JSON.parse(xhr.responseText);
          if (response.success) {
            this.clearPreview();
            this.showSuccessMessage('Pattern removed successfully');
          } else {
            this.showErrorMessage(
              response.data?.message || 'Failed to remove pattern'
            );
          }
        } catch {
          this.showErrorMessage('Failed to remove pattern');
        }
      } else {
        this.showErrorMessage('Failed to remove pattern');
      }
      this.hideLoadingState();
    };
    xhr.onerror = () => {
      this.showErrorMessage('Failed to remove pattern');
      this.hideLoadingState();
    };
    xhr.send(formData);
  }

  /**
   * Update preview with new pattern
   */
  private updatePreview(data: any): void {
    if (!this.previewContainer) {
      return;
    }

    // Clear existing content
    this.previewContainer.innerHTML = '';

    // Create image element
    const img = document.createElement('img');
    img.src = data.thumbnail_url;
    img.alt = 'Pattern preview';
    img.className = 'aggressive-apparel-color-pattern-admin__thumbnail';
    this.previewContainer.appendChild(img);

    // Create actions container
    const actionsDiv = document.createElement('div');
    actionsDiv.className = 'aggressive-apparel-color-pattern-admin__actions';

    // Create change button
    const changeButton = document.createElement('button');
    changeButton.type = 'button';
    changeButton.className =
      'button aggressive-apparel-color-pattern-admin__change-button';
    changeButton.textContent = (
      window as any
    ).aggressiveApparelPattern.strings.changePattern;
    actionsDiv.appendChild(changeButton);

    // Create remove button
    const removeButton = document.createElement('button');
    removeButton.type = 'button';
    removeButton.className =
      'button aggressive-apparel-color-pattern-admin__remove-button';
    removeButton.textContent = (
      window as any
    ).aggressiveApparelPattern.strings.removePattern;
    actionsDiv.appendChild(removeButton);

    this.previewContainer.appendChild(actionsDiv);

    if (this.hiddenInput) {
      this.hiddenInput.value = data.attachment_id;
    }

    // Update button references and re-bind events
    this.changeButton = changeButton;
    this.removeButton = removeButton;
    this.bindEvents();
  }

  /**
   * Clear preview (show upload button)
   */
  private clearPreview(): void {
    if (!this.previewContainer) {
      return;
    }

    // Clear existing content
    this.previewContainer.innerHTML = '';

    // Create no pattern container
    const noPatternDiv = document.createElement('div');
    noPatternDiv.className =
      'aggressive-apparel-color-pattern-admin__no-pattern';

    // Create paragraph
    const paragraph = document.createElement('p');
    paragraph.textContent = 'No pattern uploaded.';
    noPatternDiv.appendChild(paragraph);

    // Create upload button
    const uploadButton = document.createElement('button');
    uploadButton.type = 'button';
    uploadButton.className =
      'button aggressive-apparel-color-pattern-admin__upload-button';
    uploadButton.textContent = (
      window as any
    ).aggressiveApparelPattern.strings.uploadPattern;
    noPatternDiv.appendChild(uploadButton);

    this.previewContainer.appendChild(noPatternDiv);

    if (this.hiddenInput) {
      this.hiddenInput.value = '';
    }

    // Update button reference and re-bind events
    this.uploadButton = uploadButton;
    this.bindEvents();
  }

  /**
   * Show loading state
   */
  private showLoadingState(): void {
    const container = this.previewContainer?.closest(
      '.aggressive-apparel-color-pattern-admin'
    ) as HTMLElement;
    container?.classList.add('aggressive-apparel-color-pattern-admin--loading');

    if (this.uploadButton) this.uploadButton.disabled = true;
    if (this.changeButton) this.changeButton.disabled = true;
    if (this.removeButton) this.removeButton.disabled = true;
  }

  /**
   * Hide loading state
   */
  private hideLoadingState(): void {
    const container = this.previewContainer?.closest(
      '.aggressive-apparel-color-pattern-admin'
    ) as HTMLElement;
    container?.classList.remove(
      'aggressive-apparel-color-pattern-admin--loading'
    );

    if (this.uploadButton) this.uploadButton.disabled = false;
    if (this.changeButton) this.changeButton.disabled = false;
    if (this.removeButton) this.removeButton.disabled = false;
  }

  /**
   * Show success message
   */
  private showSuccessMessage(message: string): void {
    this.showAdminNotice(message, 'success');
  }

  /**
   * Show error message
   */
  private showErrorMessage(message: string): void {
    this.showAdminNotice(message, 'error');
  }

  /**
   * Show admin notice
   */
  private showAdminNotice(
    message: string,
    type: 'success' | 'error' = 'success'
  ): void {
    const notice = document.createElement('div');
    notice.className = `notice notice-${type} is-dismissible`;

    const paragraph = document.createElement('p');
    paragraph.textContent = message;
    notice.appendChild(paragraph);

    const wrap = document.querySelector('.wrap');
    if (wrap) {
      wrap.insertBefore(notice, wrap.firstChild);
    }

    // Auto-dismiss after 5 seconds
    setTimeout(() => {
      notice.style.display = 'none';
      notice.remove();
    }, 5000);
  }
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
  new AggressiveApparelColorPatternAdmin();
});
