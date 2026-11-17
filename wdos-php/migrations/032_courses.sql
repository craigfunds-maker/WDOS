-- v109.5: the Volunteer Leadership Personal Development Courses (12 courses, 60 lessons, a 10-question quiz each,
-- pass mark 7 of 10) and certificates that anyone can verify with the QR code printed on them.
CREATE TABLE IF NOT EXISTS course_progress (
  user_id INT NOT NULL, course_no INT NOT NULL, module_no INT NOT NULL, completed_at DATETIME NOT NULL,
  PRIMARY KEY (user_id, course_no, module_no)
);
CREATE TABLE IF NOT EXISTS course_attempts (
  id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL, course_no INT NOT NULL, score INT NOT NULL, passed TINYINT NOT NULL,
  answers VARCHAR(40) NOT NULL, bank_version VARCHAR(20) NOT NULL, created_at DATETIME NOT NULL,
  KEY course_attempts_user (user_id, course_no)
);
CREATE TABLE IF NOT EXISTS certificates (
  id INT AUTO_INCREMENT PRIMARY KEY, code VARCHAR(24) NOT NULL, user_id INT NOT NULL, course_no INT NOT NULL,
  holder_name VARCHAR(190) NOT NULL, network VARCHAR(10) NOT NULL DEFAULT '', score INT NOT NULL DEFAULT 0,
  issued_at DATETIME NOT NULL, revoked_at DATETIME NULL, revoked_reason VARCHAR(255) NULL,
  UNIQUE KEY certificates_code (code), UNIQUE KEY certificates_one (user_id, course_no)
);
