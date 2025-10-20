-- Skapa schema för tipspromenadapp

CREATE TABLE IF NOT EXISTS users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  email VARCHAR(190) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS quizzes (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  title VARCHAR(200) NOT NULL,
  description TEXT,
  join_code VARCHAR(32) NOT NULL UNIQUE,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_quiz_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS questions (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  quiz_id INT UNSIGNED NOT NULL,
  q_order INT UNSIGNED NOT NULL,
  text TEXT NOT NULL,
  image_path VARCHAR(255) DEFAULT NULL,
  type ENUM('mcq','tiebreaker') NOT NULL DEFAULT 'mcq',
  correct_option TINYINT UNSIGNED DEFAULT NULL,
  tiebreaker_answer DOUBLE DEFAULT NULL,
  CONSTRAINT fk_question_quiz FOREIGN KEY (quiz_id) REFERENCES quizzes(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS options (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  question_id INT UNSIGNED NOT NULL,
  opt_order TINYINT UNSIGNED NOT NULL,
  text VARCHAR(500) NOT NULL,
  CONSTRAINT fk_option_question FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS submissions (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  quiz_id INT UNSIGNED NOT NULL,
  participant_name VARCHAR(120) NOT NULL,
  contact_info VARCHAR(190) DEFAULT NULL,
  score INT NOT NULL DEFAULT 0,
  tiebreaker_value DOUBLE DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_submission_quiz FOREIGN KEY (quiz_id) REFERENCES quizzes(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS submission_answers (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  submission_id INT UNSIGNED NOT NULL,
  question_id INT UNSIGNED NOT NULL,
  selected_option_id INT UNSIGNED DEFAULT NULL,
  text_answer TEXT DEFAULT NULL,
  is_correct TINYINT(1) DEFAULT NULL,
  CONSTRAINT fk_sa_submission FOREIGN KEY (submission_id) REFERENCES submissions(id) ON DELETE CASCADE,
  CONSTRAINT fk_sa_question FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE CASCADE,
  CONSTRAINT fk_sa_option FOREIGN KEY (selected_option_id) REFERENCES options(id) ON DELETE SET NULL
);

-- Hjälpindex
CREATE INDEX idx_questions_quiz ON questions(quiz_id, q_order);
CREATE INDEX idx_options_question ON options(question_id);
CREATE INDEX idx_submissions_quiz ON submissions(quiz_id, score, created_at);

