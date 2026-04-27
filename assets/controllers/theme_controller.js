import { Controller } from '@hotwired/stimulus';

const STORAGE_KEY = 'mykanban_theme';

export default class extends Controller {
    static targets = ['icon'];

    connect() {
        this.applyStoredOrPreferredTheme();
        this.render();
    }

    toggle() {
        const nextTheme = this.currentTheme() === 'dark' ? 'light' : 'dark';
        this.applyTheme(nextTheme, true);
    }

    applyStoredOrPreferredTheme() {
        let stored = null;

        try {
            stored = localStorage.getItem(STORAGE_KEY);
        } catch (e) {
            stored = null;
        }

        if (stored === 'dark' || stored === 'light') {
            this.applyTheme(stored, false);
            return;
        }

        if (!document.documentElement.dataset.theme) {
            const preferred = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
            this.applyTheme(preferred, false);
        }
    }

    applyTheme(theme, persist) {
        document.documentElement.dataset.theme = theme;

        if (persist) {
            try {
                localStorage.setItem(STORAGE_KEY, theme);
            } catch (e) {
                // Ignore storage errors (private mode, blocked storage, etc.).
            }
        }

        this.render();
    }

    currentTheme() {
        return document.documentElement.dataset.theme === 'dark' ? 'dark' : 'light';
    }

    render() {
        const darkModeActive = this.currentTheme() === 'dark';

        if (this.hasIconTarget) {
            this.iconTarget.textContent = darkModeActive ? '☀' : '◐';
        }

        const nextThemeLabel = darkModeActive ? 'clair' : 'sombre';
        this.element.setAttribute('aria-pressed', darkModeActive ? 'true' : 'false');
        this.element.setAttribute('title', `Activer le theme ${nextThemeLabel}`);
        this.element.setAttribute('aria-label', `Activer le theme ${nextThemeLabel}`);
    }
}