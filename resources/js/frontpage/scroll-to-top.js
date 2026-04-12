document.addEventListener('DOMContentLoaded', () => {
    const btn = document.getElementById('stt');
    if (!btn) return;

    let ticking = false;

    window.addEventListener('scroll', () => {
        if (!ticking) {
            window.requestAnimationFrame(() => {
                btn.classList.toggle('show', window.scrollY > 400);
                ticking = false;
            });
            ticking = true;
        }
    });

    btn.addEventListener('click', () => {
        window.scrollTo({ top: 0, behavior: 'smooth' });
    });
});
