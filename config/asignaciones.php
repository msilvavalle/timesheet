<?php
// Asignaciones de consultores a proyectos, compartidas por consultor.php
// (lado 'consultor': se eligen proyectos) y proyecto.php (lado 'proyecto':
// se eligen consultores). Marcar activa o reactiva la fila de
// proyecto_consultor; desmarcar la desactiva. Nunca se borra.
//
// Solo se tocan las asignaciones cuyo otro lado está disponible (proyecto
// activo de un cliente activo, o consultor activo): una asignación a un
// proyecto desactivado se conserva tal cual para cuando se reactive.

function mejrOpcionesAsignables(PDO $pdo, string $lado): array
{
    if ($lado === 'consultor') {
        return $pdo->query(
            'SELECT p.id, c.nombre AS grupo, p.nombre
             FROM proyectos p
             INNER JOIN clientes c ON c.id = p.cliente_id
             WHERE p.activo = 1 AND c.activo = 1
             ORDER BY c.nombre, p.nombre, p.id'
        )->fetchAll();
    }

    return $pdo->query(
        "SELECT id, especialidad AS grupo, CONCAT(apellidos, ', ', nombres) AS nombre
         FROM usuarios
         WHERE rol = 'consultor' AND activo = 1
         ORDER BY apellidos, nombres, id"
    )->fetchAll();
}

// Ids del otro lado con asignación activa.
function mejrAsignacionesActivas(PDO $pdo, string $lado, int $id): array
{
    [$fijo, $otro] = $lado === 'consultor'
        ? ['consultor_id', 'proyecto_id']
        : ['proyecto_id', 'consultor_id'];

    $consulta = $pdo->prepare(
        "SELECT $otro FROM proyecto_consultor WHERE $fijo = :id AND activo = 1"
    );
    $consulta->execute(['id' => $id]);

    return array_map('intval', $consulta->fetchAll(PDO::FETCH_COLUMN));
}

/**
 * Deja activas exactamente las asignaciones marcadas entre las disponibles.
 * Devuelve [cantidad activada, cantidad desactivada].
 */
function mejrGuardarAsignaciones(PDO $pdo, string $lado, int $id, array $marcados): array
{
    [$fijo, $otro] = $lado === 'consultor'
        ? ['consultor_id', 'proyecto_id']
        : ['proyecto_id', 'consultor_id'];

    $marcados = array_values(array_unique(array_map('intval', $marcados)));

    $pdo->beginTransaction();

    try {
        // Bloquea la fila del lado fijo para serializar guardados simultáneos.
        $bloqueo = $lado === 'consultor'
            ? "SELECT id FROM usuarios WHERE id = :id AND rol = 'consultor' FOR UPDATE"
            : 'SELECT id FROM proyectos WHERE id = :id FOR UPDATE';
        $consulta = $pdo->prepare($bloqueo);
        $consulta->execute(['id' => $id]);

        if (!$consulta->fetch()) {
            throw new DomainException('No se encontró el registro.');
        }

        $disponibles = array_map('intval', array_column(mejrOpcionesAsignables($pdo, $lado), 'id'));

        $actuales = $pdo->prepare(
            "SELECT $otro AS otro, activo FROM proyecto_consultor WHERE $fijo = :id FOR UPDATE"
        );
        $actuales->execute(['id' => $id]);
        $estado = [];

        foreach ($actuales->fetchAll() as $fila) {
            $estado[(int) $fila['otro']] = (int) $fila['activo'];
        }

        $activar = $pdo->prepare(
            "UPDATE proyecto_consultor SET activo = 1 WHERE $fijo = :id AND $otro = :otro"
        );
        $insertar = $pdo->prepare(
            "INSERT INTO proyecto_consultor ($fijo, $otro, activo) VALUES (:id, :otro, 1)"
        );
        $desactivar = $pdo->prepare(
            "UPDATE proyecto_consultor SET activo = 0 WHERE $fijo = :id AND $otro = :otro"
        );

        $activadas = 0;
        $desactivadas = 0;

        foreach ($disponibles as $opcion) {
            $marcado = in_array($opcion, $marcados, true);
            $actual = $estado[$opcion] ?? null;

            if ($marcado && $actual === null) {
                $insertar->execute(['id' => $id, 'otro' => $opcion]);
                $activadas++;
            } elseif ($marcado && $actual === 0) {
                $activar->execute(['id' => $id, 'otro' => $opcion]);
                $activadas++;
            } elseif (!$marcado && $actual === 1) {
                $desactivar->execute(['id' => $id, 'otro' => $opcion]);
                $desactivadas++;
            }
        }

        $pdo->commit();
    } catch (Throwable $excepcion) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $excepcion;
    }

    return [$activadas, $desactivadas];
}
