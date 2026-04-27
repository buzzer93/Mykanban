import { Controller } from '@hotwired/stimulus';
import Sortable from 'sortablejs';
import { generateCsrfHeaders, generateCsrfToken, removeCsrfToken } from './csrf_protection_controller.js';

const EDGE_ZONE_PX = 60;
const EDGE_HOLD_MS = 400;

export default class extends Controller {
    static targets = ['list'];

    static values = {
        url: String,
        token: String,
    };

    connect() {
        this.sortables = [];
        this.dragging = false;
        this.edgeTimer = null;
        this.lastEdgeSide = null;

        this.boundMoveNextColumn = (event) => this.moveTaskToNextColumn(event);
        this.boundDocumentTouchMove = (event) => this.onDocumentTouchMove(event);
        this.element.addEventListener('board:move-next-column', this.boundMoveNextColumn);

        this.listTargets.forEach((list) => {
            const sortable = Sortable.create(list, {
                group: 'kanban-tasks',
                animation: 150,
                scroll: false,
                filter: 'a, button, input, textarea, select, label',
                preventOnFilter: false,
                delay: 250,
                delayOnTouchOnly: true,
                touchStartThreshold: 5,
                onStart: () => this.onDragStart(),
                onEnd: (event) => this.onDragEnd(event),
            });

            this.sortables.push(sortable);
        });
    }

    disconnect() {
        this.element.removeEventListener('board:move-next-column', this.boundMoveNextColumn);
        document.removeEventListener('touchmove', this.boundDocumentTouchMove);
        this.clearEdgeTimer();
        this.sortables.forEach((sortable) => sortable.destroy());
        this.sortables = [];
    }

    onDragStart() {
        this.dragging = true;
        if (this.isMobile()) {
            document.addEventListener('touchmove', this.boundDocumentTouchMove, { passive: true });
        }
    }

    onDragEnd(event) {
        this.dragging = false;
        document.removeEventListener('touchmove', this.boundDocumentTouchMove);
        this.clearEdgeTimer();
        return this.persistMove(event);
    }

    onDocumentTouchMove(event) {
        if (!this.dragging) {
            return;
        }

        const touch = event.changedTouches[0] ?? event.touches[0];
        if (!touch) {
            return;
        }

        const x = touch.clientX;
        const width = window.innerWidth;

        let side = null;
        if (x <= EDGE_ZONE_PX) {
            side = 'prev';
        } else if (x >= width - EDGE_ZONE_PX) {
            side = 'next';
        }

        if (side === null) {
            this.clearEdgeTimer();
            return;
        }

        if (this.lastEdgeSide === side && this.edgeTimer !== null) {
            return;
        }

        this.clearEdgeTimer();
        this.lastEdgeSide = side;
        this.edgeTimer = window.setTimeout(() => {
            this.element.dispatchEvent(new CustomEvent('board:column-advance', {
                bubbles: true,
                detail: { direction: side },
            }));
            this.edgeTimer = null;
            this.lastEdgeSide = null;
        }, EDGE_HOLD_MS);
    }

    clearEdgeTimer() {
        if (this.edgeTimer !== null) {
            clearTimeout(this.edgeTimer);
            this.edgeTimer = null;
        }
        this.lastEdgeSide = null;
    }

    isMobile() {
        return window.matchMedia('(max-width: 1023px), (pointer: coarse) and (max-width: 1279px)').matches;
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
