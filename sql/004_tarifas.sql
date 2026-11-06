-- Tarifas por hora en dólares, con vigencia (config/tarifas.php).
-- tarifas_cliente: lo que se cobra al cliente. tarifas_consultor: lo que se
-- paga al consultor. Una tarifa rige desde vigente_desde hasta que empieza la
-- siguiente; cada hora se valora con la tarifa vigente en la fecha del registro.
-- Montos en centavos de dólar (12050 = US$ 120,50).
--
-- ANTES DE EJECUTAR: los tipos de cliente_id, consultor_id y creado_por deben
-- ser idénticos a clientes.id y usuarios.id. Revisar con:
--   SHOW CREATE TABLE clientes;  SHOW CREATE TABLE usuarios;
-- y ajustar "INT UNSIGNED" si allí dice otra cosa.
--
-- Para revertir: DROP TABLE tarifas_cliente, tarifas_consultor;

CREATE TABLE IF NOT EXISTS tarifas_cliente (
  cliente_id INT UNSIGNED NOT NULL,
  vigente_desde DATE NOT NULL,
  centavos_hora INT UNSIGNED NOT NULL,
  creado_por INT UNSIGNED NOT NULL,
  creado_en DATETIME NOT NULL,
  PRIMARY KEY (cliente_id, vigente_desde),
  CONSTRAINT fk_tarifa_cliente FOREIGN KEY (cliente_id) REFERENCES clientes (id),
  CONSTRAINT fk_tarifa_cliente_autor FOREIGN KEY (creado_por) REFERENCES usuarios (id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS tarifas_consultor (
  consultor_id INT UNSIGNED NOT NULL,
  vigente_desde DATE NOT NULL,
  centavos_hora INT UNSIGNED NOT NULL,
  creado_por INT UNSIGNED NOT NULL,
  creado_en DATETIME NOT NULL,
  PRIMARY KEY (consultor_id, vigente_desde),
  CONSTRAINT fk_tarifa_consultor FOREIGN KEY (consultor_id) REFERENCES usuarios (id),
  CONSTRAINT fk_tarifa_consultor_autor FOREIGN KEY (creado_por) REFERENCES usuarios (id)
) ENGINE=InnoDB;
