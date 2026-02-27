/* script.js */
document.addEventListener('DOMContentLoaded', () => {
    // Preenche modal de edição
    const editModal = document.getElementById('editFaqModal');
    if (editModal) {
        editModal.addEventListener('show.bs.modal', e => {
            const button = e.relatedTarget;
            document.getElementById('edit-faq-id').value = button.getAttribute('data-id') || '';
            document.getElementById('edit-question').value = button.getAttribute('data-question') || '';
            document.getElementById('edit-answer').value = button.getAttribute('data-answer') || '';
            document.getElementById('edit-product').value = button.getAttribute('data-product') || '';
            document.getElementById('edit-supplier').value = button.getAttribute('data-supplier') || '';
        });
    }

    // Preenche modal de exclusão
    const deleteModal = document.getElementById('deleteFaqModal');
    if (deleteModal) {
        deleteModal.addEventListener('show.bs.modal', e => {
            const button = e.relatedTarget;
            document.getElementById('delete-faq-id').value = button.getAttribute('data-id') || '';
            document.getElementById('delete-faq-title').textContent = button.getAttribute('data-question') || '';
        });
    }
});