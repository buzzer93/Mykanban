import { Controller } from '@hotwired/stimulus';
import Sortable from 'sortablejs';
import { generateCsrfHeaders, generateCsrfToken, removeCsrfToken } from './csrf_protection_controller.js';

export default class extends Controller {
    static targets = ['list'];

    static values = {
        url: String,
        token: String,
    };

    connect() {
        this.sortables = [];
        this.boundMoveNextColumn = (event) => this.moveTaskToNextColumn(event);
        this.element.addEventListener('board:move-next-column', this.boundMoveNextColumn);

        this.listTargets.forEach((list) => {
            const sortable = Sortable.create(list, {
                group: 'kanban-tasks',
                animation: 150,
                scroll: false,
                filter: 'a, button, input, textarea, select, label',
                preventOnFilter: false,
                onEnd: (event) => this.persistMove(event),
            });

            this.sortables.push(sortable);
        });
    }

    disconnect() {
        this.element.removeEventListener('board:move-next-column', this.boundMoveNextColumn);
        this.sortables.forEach((sortable) => sortable.destroy());
        this.sortables = [];
    }

    async moveTaskToNextColumn(event) {
        const taskId = Number.parseInt(event.detail?.taskId ?? '', 10);
        if (Number.isNaN(taskId)) {
            return;
        }

        const taskElement = this.element.querySelector(`[data-task-id="${taskId}"]`);
        const sourceList = taskElement?.closest('[data-column-id]');
        if (!(sourceList instanceof HTMLElement)) {
            return;
        }

        const sourceIndex = this.listTargets.findIndex((list) => list === sourceList);
        const targetList = this.listTargets[sourceIndex + 1];

        if (!(targetList instanceof HTMLElement)) {
            return;
        }

        targetList.appendChild(taskElement);

        await this.persistMove({
            item: taskElement,
            to: targetList,
            newIndex: targetList.children.length - 1,
        });
    }

    async persistMove(event) {
        const taskId = Number.parseInt(event.item.dataset.taskId, 10);
        const targetColumnId = Number.parseInt(event.to.dataset.columnId, 10);
        const newPosition = event.newIndex;

        if (Number.isNaN(taskId) || Number.isNaN(targetColumnId) || newPosition < 0) {
            window.location.reload();

            return;
        }

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
                    taskId,
                    targetColumnId,
                    newPosition,
                    _token: csrfToken,
                }),
            });

            removeCsrfToken(csrfForm);

            if (!response.ok) {
                let errorMessage = 'Move HTTP ' + response.status;

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
            // Keep a clear browser-side trace when move persistence fails.
            console.error('[board-drag] Unable to persist task move', e);
            window.location.reload();
        }
    }
}
