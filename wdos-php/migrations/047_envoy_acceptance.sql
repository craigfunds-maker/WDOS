-- v109.45: the WODDI Envoy Appointment Acceptance & Commitment Form, filled and accepted in WDOS (Azeez, 22 Nov 2026).
-- One row per envoy. Parts A, D and E as she gave them, Parts B and C as ticked, and Part F when HQ acknowledges.
CREATE TABLE IF NOT EXISTS envoy_acceptances (
  user_id BIGINT NOT NULL PRIMARY KEY,
  full_name VARCHAR(190) NOT NULL DEFAULT '',
  country VARCHAR(120) NOT NULL DEFAULT '',
  profession VARCHAR(190) NOT NULL DEFAULT '',
  organisation VARCHAR(190) NOT NULL DEFAULT '',
  email VARCHAR(190) NOT NULL DEFAULT '',
  phone VARCHAR(40) NOT NULL DEFAULT '',
  network VARCHAR(24) NOT NULL DEFAULT '',
  effective_date DATE NULL,
  focus VARCHAR(1000) NOT NULL DEFAULT '',
  first_30_days VARCHAR(1000) NOT NULL DEFAULT '',
  support_needed VARCHAR(1000) NOT NULL DEFAULT '',
  part_b VARCHAR(40) NOT NULL DEFAULT '',
  part_c VARCHAR(80) NOT NULL DEFAULT '',
  signature MEDIUMTEXT NULL,
  signed_name VARCHAR(190) NOT NULL DEFAULT '',
  preferred_contact VARCHAR(40) NOT NULL DEFAULT '',
  accepted_at DATETIME NOT NULL,
  ip VARCHAR(64) NOT NULL DEFAULT '',
  ack_by BIGINT NULL,
  ack_name VARCHAR(190) NOT NULL DEFAULT '',
  ack_title VARCHAR(190) NOT NULL DEFAULT '',
  ack_ref VARCHAR(80) NOT NULL DEFAULT '',
  ack_at DATETIME NULL
) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS envoy_accept_nudges (
  user_id BIGINT NOT NULL,
  n INT NOT NULL,
  sent_at DATETIME NOT NULL,
  PRIMARY KEY (user_id, n)
) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
