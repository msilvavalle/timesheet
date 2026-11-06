<?php
// Sección "Tarifa" de las fichas de cliente y de consultor.
// Espera: $tipoTarifa ('cliente' | 'consultor'), $historialTarifas,
// $errorTarifa, $urlFicha (closure), $valorTarifa y $desdeTarifa (lo escrito).
$inicioMesTarifa = mejrAhora()->format('Y-m-01');
$hoyTarifa = mejrAhora()->format('Y-m-d');
$vigenteTarifa = null;

foreach ($historialTarifas as $filaTarifa) {
    if ($filaTarifa['vigente_desde'] <= $hoyTarifa) {
        $vigenteTarifa = (int) $filaTarifa['centavos_hora'];
        break;
    }
}
?>
        <section id="tarifa" class="bg-white border rounded p-4" aria-labelledby="titulo-tarifa">
          <h2 id="titulo-tarifa" class="h5"><?= texto($tipoTarifa === 'cliente' ? 'Tarifa de cobro' : 'Tarifa de pago') ?></h2>
          <p class="text-secondary">
            <?= texto($tipoTarifa === 'cliente'
                ? 'Lo que se cobra al cliente por cada hora registrada en sus proyectos.'
                : 'Lo que se paga al consultor por cada hora que registra.') ?>
          </p>

          <p class="mb-3">
            <?= texto('Vigente hoy') ?>:
            <?php if ($vigenteTarifa === null): ?>
              <span class="badge text-bg-warning"><?= texto('Sin tarifa') ?></span>
            <?php else: ?>
              <strong><?= escapar(mejrUsd($vigenteTarifa)) ?></strong> / h
            <?php endif; ?>
          </p>

          <?php if ($errorTarifa !== ''): ?>
            <div class="alert alert-danger" role="alert"><?= texto($errorTarifa) ?></div>
          <?php endif; ?>

          <form method="post" action="<?= escapar($urlFicha('tarifa')) ?>" class="row g-2 align-items-end mb-3">
            <input type="hidden" name="csrf" value="<?= escapar($_SESSION['csrf']) ?>">
            <input type="hidden" name="accion" value="tarifa">
            <div class="col-sm-4 col-lg-3">
              <label for="valor-tarifa" class="form-label"><?= texto('US$ por hora') ?></label>
              <input id="valor-tarifa" name="valor" type="text" inputmode="decimal" class="form-control"
                maxlength="9" pattern="[0-9]{1,6}([.,][0-9]{1,2})?" value="<?= escapar($valorTarifa) ?>" required>
            </div>
            <div class="col-sm-4 col-lg-3">
              <label for="desde-tarifa" class="form-label"><?= texto('Rige desde') ?></label>
              <input id="desde-tarifa" name="desde" type="date" class="form-control"
                value="<?= escapar($desdeTarifa !== '' ? $desdeTarifa : $inicioMesTarifa) ?>" required>
            </div>
            <div class="col-sm-4 col-lg-3">
              <button type="submit" class="btn btn-dark"><?= texto('Guardar tarifa') ?></button>
            </div>
          </form>

          <p class="small text-secondary">
            <?= texto('Las tarifas se aplican a cada hora según su fecha. Para corregir una tarifa de este mes o futura, bórrala y vuelve a cargarla.') ?>
          </p>

          <?php if (!$historialTarifas): ?>
            <p class="text-secondary mb-0"><?= texto('Sin tarifas cargadas. Las horas de este período quedan sin valorizar hasta que agregues una.') ?></p>
          <?php else: ?>
            <h3 class="h6 mt-3"><?= texto('Historial') ?></h3>
            <table class="table table-sm align-middle mb-0">
              <thead>
                <tr>
                  <th scope="col"><?= texto('Desde') ?></th>
                  <th scope="col" class="mejr-numero"><?= texto('US$ por hora') ?></th>
                  <th scope="col"><?= texto('Cargada por') ?></th>
                  <th scope="col" class="text-end"><?= texto('Acciones') ?></th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($historialTarifas as $filaTarifa): ?>
                  <tr>
                    <td><?= escapar(mejrFecha($filaTarifa['vigente_desde'])) ?></td>
                    <td class="mejr-numero"><?= escapar(mejrUsd((int) $filaTarifa['centavos_hora'])) ?></td>
                    <td class="small"><?= escapar((string) $filaTarifa['autor']) ?></td>
                    <td class="text-end">
                      <?php if ($filaTarifa['vigente_desde'] >= $inicioMesTarifa): ?>
                        <form method="post" action="<?= escapar($urlFicha('tarifa')) ?>" data-confirmar="<?= texto('¿Borrar esta tarifa?') ?>">
                          <input type="hidden" name="csrf" value="<?= escapar($_SESSION['csrf']) ?>">
                          <input type="hidden" name="accion" value="tarifa_borrar">
                          <input type="hidden" name="desde" value="<?= escapar($filaTarifa['vigente_desde']) ?>">
                          <button type="submit" class="btn btn-sm btn-outline-danger"><?= texto('Borrar') ?></button>
                        </form>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          <?php endif; ?>
        </section>
