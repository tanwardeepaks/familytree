CREATE DATABASE IF NOT EXISTS core_familytree CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE core_familytree;

CREATE TABLE IF NOT EXISTS family_members (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  gender ENUM('male','female') NOT NULL,
  father_id INT DEFAULT NULL,
  mother_id INT DEFAULT NULL,
  spouse_id INT DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX(father_id),
  INDEX(mother_id),
  INDEX(spouse_id),
  FOREIGN KEY (father_id) REFERENCES family_members(id) ON DELETE SET NULL,
  FOREIGN KEY (mother_id) REFERENCES family_members(id) ON DELETE SET NULL,
  FOREIGN KEY (spouse_id) REFERENCES family_members(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
