import { startStimulusApp } from '@symfony/stimulus-bundle';
import BoardDragController from './controllers/board_drag_controller.js';
import BoardSortController from './controllers/board_sort_controller.js';
import CsrfProtectionController from './controllers/csrf_protection_controller.js';
import HelloController from './controllers/hello_controller.js';
import SortableReorderController from './controllers/sortable_reorder_controller.js';
import ThemeController from './controllers/theme_controller.js';

const app = startStimulusApp();
app.register('board-drag', BoardDragController);
app.register('board-sort', BoardSortController);
app.register('csrf-protection', CsrfProtectionController);
app.register('hello', HelloController);
app.register('sortable-reorder', SortableReorderController);
app.register('theme', ThemeController);
