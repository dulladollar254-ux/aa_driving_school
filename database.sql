-- AA Driving School Management System (Mombasa)
-- Import in phpMyAdmin or: mysql -u root < database.sql
CREATE DATABASE IF NOT EXISTS aa_driving_school CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE aa_driving_school;

DROP TABLE IF EXISTS test_results, payments, lessons, vehicles, students, instructors, users;

CREATE TABLE users (
  user_id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(50) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  full_name VARCHAR(100) NOT NULL,
  role ENUM('Administrator','Receptionist','Instructor','Student') NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE instructors (
  instructor_id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NULL,
  full_name VARCHAR(100) NOT NULL,
  phone VARCHAR(20) NOT NULL,
  email VARCHAR(100),
  license_no VARCHAR(50) NOT NULL UNIQUE,
  FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE students (
  student_id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NULL,
  full_name VARCHAR(100) NOT NULL,
  gender ENUM('Male','Female','Other') NOT NULL,
  id_number VARCHAR(30),
  phone VARCHAR(20) NOT NULL,
  email VARCHAR(100),
  address VARCHAR(150),
  course VARCHAR(60) NOT NULL DEFAULT 'Class B (Light vehicle)',
  course_fee DECIMAL(10,2) NOT NULL DEFAULT 0,
  reg_date DATE NOT NULL,
  FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE vehicles (
  vehicle_id INT AUTO_INCREMENT PRIMARY KEY,
  reg_no VARCHAR(20) NOT NULL UNIQUE,
  model VARCHAR(60) NOT NULL,
  category VARCHAR(30) NOT NULL DEFAULT 'Class B',
  status ENUM('Available','Maintenance') NOT NULL DEFAULT 'Available'
) ENGINE=InnoDB;

CREATE TABLE lessons (
  schedule_id INT AUTO_INCREMENT PRIMARY KEY,
  student_id INT NOT NULL,
  instructor_id INT NOT NULL,
  vehicle_id INT NULL,
  lesson_date DATE NOT NULL,
  lesson_time TIME NOT NULL,
  topic VARCHAR(100),
  status ENUM('Scheduled','Completed','Cancelled') NOT NULL DEFAULT 'Scheduled',
  notes VARCHAR(255),
  FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE,
  FOREIGN KEY (instructor_id) REFERENCES instructors(instructor_id) ON DELETE RESTRICT,
  FOREIGN KEY (vehicle_id) REFERENCES vehicles(vehicle_id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE payments (
  payment_id INT AUTO_INCREMENT PRIMARY KEY,
  student_id INT NOT NULL,
  amount DECIMAL(10,2) NOT NULL,
  payment_date DATE NOT NULL,
  payment_method ENUM('Cash','M-Pesa','Bank') NOT NULL,
  reference VARCHAR(50),
  received_by INT NULL,
  FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE,
  FOREIGN KEY (received_by) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE test_results (
  result_id INT AUTO_INCREMENT PRIMARY KEY,
  student_id INT NOT NULL,
  test_type ENUM('Theory','Practical') NOT NULL,
  test_date DATE NOT NULL,
  score INT NOT NULL,
  remarks VARCHAR(255),
  FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Demo accounts: admin/admin123 ; others use pass1234
INSERT INTO users (user_id, username, password, full_name, role) VALUES
(1,'admin','$2y$10$AdUz4wA/g5adUP7Mhp0ebuZqb7J02jSq8T2Bj7SUNdG42b9B5K.Z6','System Administrator','Administrator'),
(2,'reception','$2y$10$CjPmrtefaYF9Yd5O3BLQduIffAz5wpMRX3k4mML1Mq94J6KzCR3Nq','Front Desk','Receptionist'),
(3,'instructor1','$2y$10$CjPmrtefaYF9Yd5O3BLQduIffAz5wpMRX3k4mML1Mq94J6KzCR3Nq','John Mwangi','Instructor'),
(4,'student1','$2y$10$CjPmrtefaYF9Yd5O3BLQduIffAz5wpMRX3k4mML1Mq94J6KzCR3Nq','Amina Hassan','Student');

INSERT INTO instructors (instructor_id,user_id,full_name,phone,email,license_no) VALUES
(1,3,'John Mwangi','0711000111','john@aadriving.co.ke','INS-0001'),
(2,NULL,'Fatuma Ali','0722000222','fatuma@aadriving.co.ke','INS-0002');

INSERT INTO vehicles (reg_no,model,category,status) VALUES
('KDA 101A','Toyota Axio','Class B','Available'),
('KDB 202B','Suzuki Swift','Class B','Available'),
('KMF 303C','Honda CB150','Class A','Maintenance');

INSERT INTO students (student_id,user_id,full_name,gender,id_number,phone,email,address,course,course_fee,reg_date) VALUES
(1,4,'Amina Hassan','Female','31234567','0733111222','amina@example.com','Nyali, Mombasa','Class B (Light vehicle)',15000,CURDATE()),
(2,NULL,'Brian Otieno','Male','29876543','0744333444','brian@example.com','Likoni, Mombasa','Class B (Light vehicle)',15000,CURDATE());

INSERT INTO payments (student_id,amount,payment_date,payment_method,reference,received_by) VALUES
(1,8000,CURDATE(),'M-Pesa','QHX12ABC34',2),
(2,5000,CURDATE(),'Cash',NULL,2);

INSERT INTO lessons (student_id,instructor_id,vehicle_id,lesson_date,lesson_time,topic,status) VALUES
(1,1,1,DATE_ADD(CURDATE(),INTERVAL 1 DAY),'09:00:00','Vehicle controls & moving off','Scheduled'),
(2,2,2,DATE_ADD(CURDATE(),INTERVAL 1 DAY),'10:00:00','Parking basics','Scheduled');

INSERT INTO test_results (student_id,test_type,test_date,score,remarks) VALUES
(1,'Theory',CURDATE(),78,'Good understanding of road signs');
