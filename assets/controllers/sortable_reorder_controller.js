import { Controller } from '@hotwired/stimulus';
import Sortable from 'sortablejs';
import { generateCsrfHeaders, generateCsrfToken, removeCsrfToken } from './csrf_protection_controller.js';

export default class extends Controller {
    static values = {
        url: String,
        token: String,
    };

    connect() {
        this.sortable = Sortable.create(this.element, {
            animation: 150,
            handle: '.drag-handle',
            onEnd: () => this.persistOrder(),
        });
    }

    disconnect() {
        if (this.sortable) {
            this.sortable.destroy();
        }
    }

    async persistOrder() {
        const orderedIds = Array.from(this.element.querySelectorAll('[data-id]'))
            .map((node) => Number.parseInt(node.dataset.id, 10))
            .filter((id) => !Number.isNaN(id));

        try {
            const csrfForm = document.createElement('form');
            const csrfField = document.createElement('input');
            csrfField.type = 'hidden';
            csrfField.name = '_csrf_token';
            csrfField.value = this.tokenValue;
            csrfForm.appendChild(csrfField);

            generateCsrfToken(csrfForm);
            const csrfHeaders = generateCsrfHeaders(csrfForm);
            const csrfToken = csrfField.value;

            const response = await fetch(this.urlValue, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    ...csrfHeaders,
                },
                credentials: 'same-origin',
                body: JSON.stringify({
                    orderedIds,
                    _token: csrfToken,
                }),
            });

            removeCsrfToken(csrfForm);

            if (!response.ok) {
                let errorMessage = 'Reorder HTTP ' + response.status;

                const contentType = response.headers.get('Content-Type') ?? '';
                if (contentType.includes('application/json')) {
                    const payload = await response.json();
                    if (typeof payload?.error === 'string' && payload.error !== '') {
                        errorMessage += ': ' + payload.error;
                    }
                } else {
                    const payload = await response.text();
                    if (payload.trim() !== '') {
                        errorMessage += ': ' + payload.trim();
                    }
                }

                throw new Error(errorMessage);
            }
        } catch (e) {
            console.error('[sortable-reorder] Unable to persist reordered list', e);
            window.location.reload();
        }
    }
}
