/**
 * Modern Vanilla JavaScript Lightbox
 * No jQuery dependencies
 */

interface LightboxOptions {
    animationSpeed: number;
    bgOpacity: number;
    showCounter: boolean;
    showArrows: boolean;
    showCloseButton: boolean;
}

class VanillaLightbox {
    private container: HTMLElement | null = null;
    private overlay: HTMLElement | null = null;
    private content: HTMLElement | null = null;
    private currentImage: HTMLImageElement | null = null;
    private counter: HTMLElement | null = null;
    private closeButton: HTMLElement | null = null;
    private prevButton: HTMLElement | null = null;
    private nextButton: HTMLElement | null = null;
    private currentIndex: number = 0;
    private images: HTMLElement[] = [];
    private options: LightboxOptions = {
        animationSpeed: 300,
        bgOpacity: 0.8,
        showCounter: true,
        showArrows: true,
        showCloseButton: true
    };

    constructor(selector: string, options: Partial<LightboxOptions> = {}) {
        // Merge options
        this.options = { ...this.options, ...options };

        // Find all gallery images
        this.images = Array.from(document.querySelectorAll(selector));

        if (this.images.length === 0) return;

        // Create lightbox elements
        this.createLightbox();

        // Add click event to images
        this.images.forEach((image, index) => {
            image.addEventListener('click', (e) => {
                e.preventDefault();
                this.openLightbox(index);
            });
        });

        // Add keyboard navigation
        document.addEventListener('keydown', (e) => {
            if (!this.container || this.container.style.display === 'none') return;

            switch (e.key) {
                case 'Escape':
                    this.closeLightbox();
                    break;
                case 'ArrowLeft':
                    this.prevImage();
                    break;
                case 'ArrowRight':
                    this.nextImage();
                    break;
            }
        });
    }

    private createLightbox(): void {
        // Create container
        this.container = document.createElement('div');
        this.container.className = 'vanilla-lightbox';
        this.container.style.display = 'none';
        this.container.setAttribute('aria-hidden', 'true');
        this.container.setAttribute('role', 'dialog');

        // Create overlay
        this.overlay = document.createElement('div');
        this.overlay.className = 'lightbox-overlay';
        this.overlay.addEventListener('click', () => this.closeLightbox());

        // Create content area
        this.content = document.createElement('div');
        this.content.className = 'lightbox-content';

        // Create image container
        this.currentImage = document.createElement('img');
        this.currentImage.className = 'lightbox-image';

        // Create counter
        if (this.options.showCounter) {
            this.counter = document.createElement('div');
            this.counter.className = 'lightbox-counter';
        }

        // Create navigation buttons
        if (this.options.showArrows) {
            this.prevButton = document.createElement('button');
            this.prevButton.className = 'lightbox-prev';
            this.prevButton.innerHTML = '&#10094;';
            this.prevButton.setAttribute('aria-label', 'Previous image');
            this.prevButton.addEventListener('click', (e) => {
                e.stopPropagation();
                this.prevImage();
            });

            this.nextButton = document.createElement('button');
            this.nextButton.className = 'lightbox-next';
            this.nextButton.innerHTML = '&#10095;';
            this.nextButton.setAttribute('aria-label', 'Next image');
            this.nextButton.addEventListener('click', (e) => {
                e.stopPropagation();
                this.nextImage();
            });
        }

        // Create close button
        if (this.options.showCloseButton) {
            this.closeButton = document.createElement('button');
            this.closeButton.className = 'lightbox-close';
            this.closeButton.innerHTML = '&times;';
            this.closeButton.setAttribute('aria-label', 'Close lightbox');
            this.closeButton.addEventListener('click', (e) => {
                e.stopPropagation();
                this.closeLightbox();
            });
        }

        // Assemble the lightbox
        this.content.appendChild(this.currentImage);

        if (this.counter) this.content.appendChild(this.counter);
        if (this.prevButton) this.content.appendChild(this.prevButton);
        if (this.nextButton) this.content.appendChild(this.nextButton);
        if (this.closeButton) this.content.appendChild(this.closeButton);

        this.container.appendChild(this.overlay);
        this.container.appendChild(this.content);

        // Add to document
        document.body.appendChild(this.container);

        // Add styles
        this.addStyles();
    }

    private addStyles(): void {
        const style = document.createElement('style');
        style.textContent = `
            .vanilla-lightbox {
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                z-index: 1000;
                display: flex;
                align-items: center;
                justify-content: center;
            }
            .lightbox-overlay {
                position: absolute;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background-color: rgba(0, 0, 0, ${this.options.bgOpacity});
            }
            .lightbox-content {
                position: relative;
                max-width: 90%;
                max-height: 90%;
                z-index: 1001;
            }
            .lightbox-image {
                display: block;
                max-width: 100%;
                max-height: 90vh;
                box-shadow: 0 0 20px rgba(0, 0, 0, 0.5);
            }
            .lightbox-counter {
                position: absolute;
                bottom: -30px;
                left: 0;
                right: 0;
                text-align: center;
                color: white;
                font-size: 14px;
            }
            .lightbox-prev, .lightbox-next {
                position: absolute;
                top: 50%;
                transform: translateY(-50%);
                background: rgba(0, 0, 0, 0.5);
                color: white;
                border: none;
                border-radius: 50%;
                width: 40px;
                height: 40px;
                font-size: 18px;
                cursor: pointer;
                display: flex;
                align-items: center;
                justify-content: center;
                transition: background 0.3s;
            }
            .lightbox-prev:hover, .lightbox-next:hover {
                background: rgba(0, 0, 0, 0.8);
            }
            .lightbox-prev {
                left: 10px;
            }
            .lightbox-next {
                right: 10px;
            }
            .lightbox-close {
                position: absolute;
                top: -40px;
                right: 0;
                background: none;
                border: none;
                color: white;
                font-size: 30px;
                cursor: pointer;
            }
        `;
        document.head.appendChild(style);
    }

    private openLightbox(index: number): void {
        if (!this.container || !this.currentImage) return;

        this.currentIndex = index;
        this.updateImage();

        // Show lightbox
        this.container.style.display = 'flex';
        this.container.setAttribute('aria-hidden', 'false');

        // Disable page scrolling
        document.body.style.overflow = 'hidden';
    }

    private updateImage(): void {
        if (!this.currentImage || !this.images[this.currentIndex]) return;

        const image = this.images[this.currentIndex] as HTMLAnchorElement;
        const imageUrl = image.href || image.getAttribute('data-full-img') || '';
        const title = image.getAttribute('data-title') || '';

        // Update image
        this.currentImage.src = imageUrl;
        this.currentImage.alt = title;

        // Update counter
        if (this.counter) {
            this.counter.textContent = `${this.currentIndex + 1} / ${this.images.length}`;
        }

        // Update navigation visibility
        if (this.prevButton) {
            this.prevButton.style.display = this.currentIndex > 0 ? 'flex' : 'none';
        }

        if (this.nextButton) {
            this.nextButton.style.display = this.currentIndex < this.images.length - 1 ? 'flex' : 'none';
        }
    }

    private closeLightbox(): void {
        if (!this.container) return;

        this.container.style.display = 'none';
        this.container.setAttribute('aria-hidden', 'true');

        // Re-enable page scrolling
        document.body.style.overflow = '';
    }

    private prevImage(): void {
        if (this.currentIndex > 0) {
            this.currentIndex--;
            this.updateImage();
        }
    }

    private nextImage(): void {
        if (this.currentIndex < this.images.length - 1) {
            this.currentIndex++;
            this.updateImage();
        }
    }
}

// Initialize lightbox when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    new VanillaLightbox('#screenshots-gallery a', {
        animationSpeed: 300,
        bgOpacity: 0.9,
        showCounter: true,
        showArrows: true,
        showCloseButton: true
    });
});
