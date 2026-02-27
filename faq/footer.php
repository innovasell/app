<?php 
// footer.php
if (!defined('APP_VERSION')) exit; 
?>
<footer class="border-top py-4 mt-auto bg-white">
  <div class="container text-center text-muted small">
    <p class="mb-0">Innova Wiki &copy; <?= date('Y') ?></p>
  </div>
</footer>

<div class="modal fade" id="loginModal" tabindex="-1"><div class="modal-dialog"><form class="modal-content" method="post" action="<?=h($_SERVER['PHP_SELF'])?>"><input type="hidden" name="action" value="login"><input type="hidden" name="csrf" value="<?=h(csrf_token())?>"><div class="modal-header"><h5 class="modal-title">Login</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><input type="email" name="email" class="form-control mb-2" placeholder="E-mail" required><input type="password" name="password" class="form-control" placeholder="Senha" required></div><div class="modal-footer"><button class="btn btn-primary w-100">Entrar</button></div></form></div></div>

<?php if (is_admin()): ?>
<div class="modal fade" id="newFaqModal" tabindex="-1"><div class="modal-dialog modal-lg"><form class="modal-content" method="post" action="<?=h($_SERVER['PHP_SELF'])?>"><input type="hidden" name="action" value="create_faq"><input type="hidden" name="csrf" value="<?=h(csrf_token())?>"><div class="modal-header"><h5 class="modal-title">Nova FAQ</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><input class="form-control mb-2" name="question" placeholder="Pergunta" required maxlength="255"><textarea class="form-control mb-2" name="answer" placeholder="Resposta" rows="5" required></textarea><div class="row"><div class="col"><input class="form-control" name="product" placeholder="Produto"></div><div class="col"><input class="form-control" name="supplier" placeholder="Fornecedor"></div></div></div><div class="modal-footer"><button class="btn btn-success">Salvar</button></div></form></div></div>

<div class="modal fade" id="editFaqModal" tabindex="-1"><div class="modal-dialog modal-lg"><form class="modal-content" method="post" action="<?=h($_SERVER['PHP_SELF'])?>"><input type="hidden" name="action" value="update_faq"><input type="hidden" name="csrf" value="<?=h(csrf_token())?>"><input type="hidden" name="faq_id" id="edit-faq-id"><div class="modal-header"><h5 class="modal-title">Editar</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><input class="form-control mb-2" name="question" id="edit-question" required maxlength="255"><textarea class="form-control mb-2" name="answer" id="edit-answer" rows="5" required></textarea><div class="row"><div class="col"><input class="form-control" name="product" id="edit-product"></div><div class="col"><input class="form-control" name="supplier" id="edit-supplier"></div></div></div><div class="modal-footer"><button class="btn btn-primary">Atualizar</button></div></form></div></div>

<div class="modal fade" id="deleteFaqModal" tabindex="-1"><div class="modal-dialog"><form class="modal-content" method="post" action="<?=h($_SERVER['PHP_SELF'])?>"><input type="hidden" name="action" value="delete_faq"><input type="hidden" name="csrf" value="<?=h(csrf_token())?>"><input type="hidden" name="faq_id" id="delete-faq-id"><div class="modal-header"><h5 class="modal-title">Excluir?</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body">Tem certeza que deseja excluir esta pergunta?</div><div class="modal-footer"><button class="btn btn-danger">Excluir</button></div></form></div></div>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const editM = document.getElementById('editFaqModal');
    if(editM) editM.addEventListener('show.bs.modal', e => {
        const b = e.relatedTarget;
        document.getElementById('edit-faq-id').value = b.getAttribute('data-id')||'';
        document.getElementById('edit-question').value = b.getAttribute('data-question')||'';
        document.getElementById('edit-answer').value = b.getAttribute('data-answer')||'';
        document.getElementById('edit-product').value = b.getAttribute('data-product')||'';
        document.getElementById('edit-supplier').value = b.getAttribute('data-supplier')||'';
    });
    const delM = document.getElementById('deleteFaqModal');
    if(delM) delM.addEventListener('show.bs.modal', e => {
        const b = e.relatedTarget;
        document.getElementById('delete-faq-id').value = b.getAttribute('data-id')||'';
    });
});
</script>
</body>
</html>