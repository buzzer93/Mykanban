import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['column', 'counter', 'hint', 'navBar', 'prevButton', 'nextButton', 'track'];

    connect() {
        this.activeIndex = 0;

        this.boundColumnAdvance = (event) => this.onColumnAdvance(event);
        this.boundResize = () => this.applyLayout();

        this.element.addEventListener('board:column-advance', this.boundColumnAdvance);
        window.addEventListener('resize', this.boundResize);

        this.applyLayout({ animate: false });
    }

    disconnect() {
        this.element.removeEventListener('board:column-advance', this.boundColumnAdvance);
        window.removeEventListener('resize', this.boundResize);
    }

    previous() {
        this.goTo(this.activeIndex - 1);
    }

    next() {
        this.goTo(this.activeIndex + 1);
    }

    onColumnAdvance(event) {
        if (!this.isMobile()) {
            return;
        }
        const direction = event.detail?.direction;
        if (direction === 'next') {
            this.next();
        } else if (direction === 'prev') {
            this.previous();
        }
    }

    goTo(index) {
        const bounded = Math.max(0, Math.min(index, this.columnTargets.length - 1));
        if (bounded === this.activeIndex) {
            return;
        }
        this.activeIndex = bounded;
        this.applyLayout({ animate: true });

        this.element.dispatchEvent(new CustomEvent('board:column-changed', {
            bubbles: true,
            detail: { index: bounded },
        }));
    }

    applyLayout(options = {}) {
        const animate = options.animate ?? true;
        const mobile = this.isMobile();

        this.element.classList.toggle('board-mobile-mode', mobile);

        if (this.hasTrackTarget) {
            if (mobile) {
                if (!animate) {
                    this.trackTarget.classList.add('board-mobile-no-transition');
                    this.trackTarget.style.transform = `translateX(-${this.activeIndex * 100}%)`;
                    // Force reflow so the next mutation re-enables the transition cleanly.
                    void this.trackTarget.offsetWidth;
                    this.trackTarget.classList.remove('board-mobile-no-transition');
                } else {
                    this.trackTarget.style.transform = `translateX(-${this.activeIndex * 100}%)`;
                }
            } else {
                this.trackTarget.style.transform = '';
            }
        }

        if (this.hasCounterTarget) {
            this.counterTarget.textContent = `${this.activeIndex + 1}/${this.columnTargets.length}`;
        }

        if (this.hasPrevButtonTarget) {
            this.prevButtonTarget.disabled = this.activeIndex <= 0;
        }
        if (this.hasNextButtonTarget) {
            this.nextButtonTarget.disabled = this.activeIndex >= this.columnTargets.length - 1;
        }

        if (this.hasHintTarget) {
            this.hintTarget.classList.toggle('hidden', !mobile);
        }
        if (this.hasNavBarTarget) {
            this.navBarTarget.classList.toggle('hidden', !mobile);
        }
    }

    isMobile() {
        return window.matchMedia('(max-width: 1023px), (pointer: coarse) and (max-width: 1279px)').matches;
    }
}
