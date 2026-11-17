-- v109.5: large files arrive in small pieces. The live server refuses any single request over about 1.5 MB (HTTP 413),
-- so the browser sends a file in parts of 600 KB; the parts are joined when the file is saved, then deleted.
CREATE TABLE IF NOT EXISTS upload_parts (
  upload_id VARCHAR(40) NOT NULL,
  user_id INT NOT NULL,
  seq INT NOT NULL,
  total INT NOT NULL,
  bytes LONGBLOB NOT NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (upload_id, seq),
  KEY upload_parts_created (created_at)
);
