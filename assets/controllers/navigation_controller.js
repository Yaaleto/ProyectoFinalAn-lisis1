import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['drawer', 'backdrop', 'openButton'];

    connect() {
        this.closeOnEscape = this.closeOnEscape.bind(this);
        document.addEventListener('keydown', this.closeOnEscape);
    }

    disconnect() {
        document.removeEventListener('keydown', this.closeOnEscape);
    }

    open() {
        this.drawerTarget.classList.remove('hidden');
        this.backdropTarget.classList.remove('hidden');
        this.openButtonTarget.setAttribute('aria-expanded', 'true');
        document.body.classList.add('overflow-hidden');
        this.drawerTarget.querySelector('button')?.focus();
    }

    close() {
        this.drawerTarget.classList.add('hidden');
        this.backdropTarget.classList.add('hidden');
        this.openButtonTarget.setAttribute('aria-expanded', 'false');
        document.body.classList.remove('overflow-hidden');
        this.openButtonTarget.focus();
    }

    closeOnEscape(event) {
        if (event.key === 'Escape' && this.openButtonTarget.getAttribute('aria-expanded') === 'true') {
            this.close();
        }
    }
}
