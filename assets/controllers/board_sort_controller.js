import { Controller } from '@hotwired/stimulus';

const STORAGE_KEY = 'mykanban.board.sort';
const ALLOWED_SORTS = ['manual', 'smart', 'urgency', 'importance', 'deadline'];

export default class extends Controller {
    static targets = ['select'];

    connect() {
        const params = new URLSearchParams(window.location.search);
        const currentSort = params.get('sort');

        if (currentSort && ALLOWED_SORTS.includes(currentSort)) {
            localStorage.setItem(STORAGE_KEY, currentSort);

            return;
        }

        const storedSort = localStorage.getItem(STORAGE_KEY);
        if (storedSort && ALLOWED_SORTS.includes(storedSort)) {
            if (document.querySelector('[role="alert"]')) {
                return;
            }

            params.set('sort', storedSort);
            window.location.search = params.toString();
        }
    }

    submit(event) {
        const value = event.target.value;
        if (ALLOWED_SORTS.includes(value)) {
            localStorage.setItem(STORAGE_KEY, value);
        }

        this.element.requestSubmit();
    }
}
