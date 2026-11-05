-- Ingresos fallidos, para limitar los intentos en login.php
-- (config/limite-ingreso.php). Ejecutar una vez en la base del sistema.
-- Las filas de más de un día se borran solas al registrar nuevos fallos.
-- Para revertir: DROP TABLE intentos_login;

CREATE TABLE IF NOT EXISTS intentos_login (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  ip VARCHAR(45) NOT NULL,
  identificador VARCHAR(254) NOT NULL,
  creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_intentos_ip_fecha (ip, creado_en),
  KEY idx_intentos_identificador_fecha (identificador, creado_en)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
