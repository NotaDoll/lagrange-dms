import * as bootstrap from 'bootstrap';
window.bootstrap = bootstrap;

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.toggle-password').forEach((btn) => {
        btn.addEventListener('click', () => {
            const input = document.getElementById(btn.dataset.target);
            const showing = input.type === 'text';

            input.type = showing ? 'password' : 'text';
            btn.querySelector('.eye-open').classList.toggle('d-none', !showing);
            btn.querySelector('.eye-closed').classList.toggle('d-none', showing);
        });
    });
});