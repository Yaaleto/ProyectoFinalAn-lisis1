import './stimulus_bootstrap.js';
/*
 * Welcome to your app's main JavaScript file!
 *
 * This file will be included onto the page via the importmap() Twig function,
 * which should already be in your base.html.twig.
 */
import './styles/app.css';

// Al entrar a un campo con error se quita su borde rojo: queda solo el contorno de foco
// y el mensaje bajo el campo sigue visible hasta el siguiente envío.
document.addEventListener('focusin', (event) => {
    if (event.target instanceof Element && event.target.getAttribute('aria-invalid') === 'true') {
        event.target.removeAttribute('aria-invalid');
    }
});
