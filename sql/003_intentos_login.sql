-- Intentos de ingreso, para limitar la fuerza bruta en login.php
-- (config/limite-ingreso.php). Ejecutar una vez en la base del sistema.
-- clave = SHA-256 de 'cuenta:<id>' o del texto escrito si la cuenta no
-- existe: no se guardan correos ni usuarios. Fechas en UTC.
-- Las filas de más de un día se borran solas.
-- Para revertir: DROP TABLE intentos_login;

CREATE TABLE IF NOT EXISTS intentos_login (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  clave CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  ip VARCHAR(45) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  creado_en DATETIME NOT NULL,
  KEY idx_intentos_clave_fecha (clave, creado_en),
  KEY idx_intentos_ip_fecha (ip, creado_en),
  KEY idx_intentos_fecha (creado_en)
) ENGINE=InnoDB;
