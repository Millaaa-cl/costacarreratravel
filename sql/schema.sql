-- ============================================================
-- travelsanrafael.cl · Esquema MySQL / MariaDB
-- Importar en phpMyAdmin (cPanel) sobre una base de datos vacía.
-- ============================================================
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS departures (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  date         DATE NOT NULL,
  capacity     SMALLINT UNSIGNED NOT NULL DEFAULT 20,
  booked       SMALLINT UNSIGNED NOT NULL DEFAULT 0,   -- incluye reservas pagadas + pendientes de pago
  price_adult  INT UNSIGNED NOT NULL,                  -- CLP
  price_child  INT UNSIGNED NOT NULL,                  -- CLP
  status       ENUM('open','closed') NOT NULL DEFAULT 'open',
  notes        VARCHAR(255) NULL,
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_date (date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bookings (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code           VARCHAR(16) NOT NULL,
  departure_id   INT UNSIGNED NOT NULL,
  adults         TINYINT UNSIGNED NOT NULL,
  children       TINYINT UNSIGNED NOT NULL DEFAULT 0,
  amount         INT UNSIGNED NOT NULL,
  name           VARCHAR(120) NOT NULL,
  email          VARCHAR(160) NOT NULL,
  phone          VARCHAR(30) NOT NULL,
  country        VARCHAR(60) NULL,
  lodging        VARCHAR(160) NULL,
  notes          TEXT NULL,
  lang           CHAR(2) NOT NULL DEFAULT 'es',
  status         ENUM('pending','paid','rejected','cancelled','expired','refunded') NOT NULL DEFAULT 'pending',
  flow_token     VARCHAR(64) NULL,
  flow_order     BIGINT UNSIGNED NULL,
  payment_media  VARCHAR(60) NULL,
  ip             VARCHAR(45) NULL,
  created_at     DATETIME NOT NULL,
  expires_at     DATETIME NOT NULL,
  paid_at        DATETIME NULL,
  admin_note     VARCHAR(255) NULL,
  UNIQUE KEY uq_code (code),
  KEY idx_status_exp (status, expires_at),
  KEY idx_token (flow_token),
  KEY idx_departure (departure_id),
  CONSTRAINT fk_booking_departure FOREIGN KEY (departure_id) REFERENCES departures(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admins (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username       VARCHAR(60) NOT NULL,
  password_hash  VARCHAR(255) NOT NULL,
  created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
