import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static values = {
        delay: { type: Number, default: 4000 },
    };

    connect() {
        this.hideTimeout = window.setTimeout(() => this.hide(), this.delayValue);
    }

    disconnect() {
        this.clearTimeout();
    }

    hide() {
        this.clearTimeout();
        this.element.remove();
    }

    clearTimeout() {
        if (this.hideTimeout) {
            window.clearTimeout(this.hideTimeout);
            this.hideTimeout = null;
        }
    }
}
