<?php
// Cierre común. La página puede listar sus scripts de js/ en $scriptsPagina
// (por ejemplo ['confirmar.js?v=1']). No se permite JavaScript en línea (CSP).
?>
  </main>

  <?php foreach ($scriptsPagina ?? [] as $script): ?>
    <script src="js/<?= escapar($script) ?>" defer></script>
  <?php endforeach; ?>
</body>
</html>
