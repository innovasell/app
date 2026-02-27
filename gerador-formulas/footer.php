</main> <footer class="footer mt-auto py-3 bg-dark text-white-50">
    <div class="container text-center">
        <span>Versão <?php echo APP_VERSION; ?> | Arquivo atualizado em: <?php echo $timestamp_versao; ?></span>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="script.js?v=<?php echo filemtime('script.js'); ?>"></script>

</body>
</html>