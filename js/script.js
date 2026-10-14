// Client-side enhancement only; all validation and authorization remain server-side.
document.addEventListener('submit', function (event) {
    const form = event.target;
    const submit = form.querySelector('button[type="submit"]');
    if (!submit || form.dataset.submitting === 'true') return;
    form.dataset.submitting = 'true';
    submit.disabled = true;
    submit.dataset.originalText = submit.textContent;
    submit.textContent = 'Working…';
});
