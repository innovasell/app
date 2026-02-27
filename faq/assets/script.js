// Preenche modal de edição
document.getElementById('editFaqModal')?.addEventListener('show.bs.modal', e => {
  const b = e.relatedTarget;
  document.getElementById('edit-faq-id').value = b.getAttribute('data-id') || '';
  document.getElementById('edit-question').value = b.getAttribute('data-question') || '';
  document.getElementById('edit-answer').value = b.getAttribute('data-answer') || '';
  document.getElementById('edit-product').value = b.getAttribute('data-product') || '';
  document.getElementById('edit-supplier').value = b.getAttribute('data-supplier') || '';
});
// Preenche modal de exclusão
document.getElementById('deleteFaqModal')?.addEventListener('show.bs.modal', e => {
  const b = e.relatedTarget;
  document.getElementById('delete-faq-id').value = b.getAttribute('data-id') || '';
  document.getElementById('delete-faq-title').textContent = b.getAttribute('data-question') || '';
});
