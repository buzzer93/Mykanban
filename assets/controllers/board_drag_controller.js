import { Controller } from '@hotwired/stimulus';
import Sortable from 'sortablejs';
import { generateCsrfHeaders, generateCsrfToken, removeCsrfToken } from './csrf_protection_controller.js';

const MOBILE_MEDIA_QUERY = '(max-width: 1023px), (pointer: coarse) and (max-width: 1279px)';

export default class extends Controller {
    static targets = ['list'];

    static values = {
        url: String,
        token: String,
    };

    connect() {
        this.sortables = [];
        this.mobileDragDisabled = false;

        this.boundResize = () => this.syncSortableMode();

        window.addEventListener('resize', this.boundResize);

        this.syncSortableMode();
    }

    disconnect() {
        window.removeEventListener('resize', this.boundResize);
        this.destroySortables();
    }

    syncSortableMode() {
        const mobile = this.isMobile();
        if (mobile && !this.mobileDragDisabled) {
            this.mobileDragDisabled = true;
            this.destroySortables();
            return;
        }

        if (!mobile && this.mobileDragDisabled) {
            this.mobileDragDisabled = false;
        }

        if (!mobile && this.sortables.length === 0) {
            this.createSortables();
        }
    }

    createSortables() {
        this.listTargets.forEach((list) => {
            const sortable = Sortable.create(list, {
                group: 'kanban-tasks',
                animation: 150,
                scroll: false,
                filter: 'a, button, input, textarea, select, label',
                preventOnFilter: false,
                onStart: (event) => this.onDragStart(event),
                onEnd: (event) => this.onDragEnd(event),
            });

            this.sortables.push(sortable);
        });
    }

    destroySortables() {
        this.sortables.forEach((sortable) => sortable.destroy());
        this.sortables = [];
    }

    onDragStart() {
    }

    onDragEnd(event) {
        return this.persistMove(event);
    }

    isMobile() {
        return window.matchMedia(MOBILE_MEDIA_QUERY).matches;
    }

    async moveTaskToPreviousColumn(event) {
        const taskId = Number.parseInt(event.currentTarget?.dataset?.taskId ?? '', 10);
        if (Number.isNaN(taskId)) {
            return;
        }

        await this.moveTaskByDirection(taskId, -1);
    }

    async moveTaskToNextColumn(event) {
        const taskId = Number.parseInt(event.currentTarget?.dataset?.taskId ?? '', 10);
        if (Number.isNaN(taskId)) {
            return;
        }

        await this.moveTaskByDirection(taskId, 1);
    }

    async moveTaskByDirection(taskId, direction) {
        if (direction !== -1 && direction !== 1) {
            return;
        }

        const taskElement = this.element.querySelector(`[data-task-id="${taskId}"]`);
        const sourceList = taskElement?.closest('[data-column-id]');
        if (!(sourceList instanceof HTMLElement)) {
            return;
        }

        const sourceIndex = this.listTargets.findIndex((list) => list === sourceList);
        const targetList = this.listTargets[sourceIndex + direction];

        if (!(targetList instanceof HTMLElement)) {
            return;
        }

        targetList.appendChild(taskElement);

        const newIndex = targetList.children.length - 1;

        await this.persistMove({
            item: taskElement,
            to: targetList,
            newIndex,
        });

        const targetColumnId = Number.parseInt(targetList.dataset.columnId, 10);
        if (!Number.isNaN(targetColumnId)) {
            this.element.dispatchEvent(new CustomEvent('board:task-moved-by-button', {
                bubbles: true,
                detail: {
                    taskId,
                    targetColumnId,
                    direction,
                },
            }));
        }
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
