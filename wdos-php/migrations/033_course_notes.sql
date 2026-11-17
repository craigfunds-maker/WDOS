-- v109.8: nothing in the courses lives only on one phone. Lesson answers (notes), quiz answers not yet sent, and the
-- place she stopped (course, lesson, second of the narration) are kept with her account, so any device continues.
CREATE TABLE IF NOT EXISTS course_notes (
  user_id INT NOT NULL, course_no INT NOT NULL, module_no INT NOT NULL, body TEXT NOT NULL, updated_at DATETIME NOT NULL,
  PRIMARY KEY (user_id, course_no, module_no)
);
CREATE TABLE IF NOT EXISTS course_drafts (
  user_id INT NOT NULL, course_no INT NOT NULL, answers VARCHAR(20) NOT NULL, updated_at DATETIME NOT NULL,
  PRIMARY KEY (user_id, course_no)
);
CREATE TABLE IF NOT EXISTS course_place (
  user_id INT NOT NULL PRIMARY KEY, course_no INT NOT NULL, part VARCHAR(8) NOT NULL, seconds INT NOT NULL DEFAULT 0, updated_at DATETIME NOT NULL
);
