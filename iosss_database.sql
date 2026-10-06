CREATE DATABASE iosss_db2;
USE iosss_db2;

CREATE TABLE schools (
school_id INT AUTO_INCREMENT PRIMARY KEY,
school_name VARCHAR(50) NOT NULL UNIQUE,
gender ENUM('male','female') NOT NULL,
capacity INT NOT NULL DEFAULT 10
);

 
INSERT INTO schools (school_name, gender, capacity) VALUES
    ('Alliance High School',        'Male', 10),
    ("Mang'u High School",          'Male', 10),
    ('Starehe Boys Centre',         'Male', 10),
    ('Nairobi School',              'Male', 10),
    ('Lenana School',               'Male', 10),
    ('Kagumo High School',          'Male', 10),
    ('Maranda High School',         'Male', 10),
    ('Kapsabet Boys High School',   'Male', 10),
    ('Kabaa High School',           'Male', 10),
    ('Nakuru Boys High School',     'Male', 10),
    ('Alliance Girls High School',           'Female', 10),
    ('Kenya High School',                    'Female', 10),
    ("Moi Girls High School Nairobi",        'Female', 10),
    ('Pangani Girls High School',            'Female', 10),
    ('Precious Blood Riruta',                'Female', 10),
    ('Loreto High School Limuru',            'Female', 10),
    ('Butere Girls High School',             'Female', 10),
    ('Moi Girls High School Eldoret',        'Female', 10),
    ("Ng'iya Girls High School",             'Female', 10),
    ('Bishop Gatimu Ngandu Girls High School','Female', 10
    );
    
    CREATE TABLE IF NOT EXISTS applicants (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    indexno        VARCHAR(11) NOT NULL UNIQUE,
    name           VARCHAR(100) NOT NULL,
    sex            ENUM('Male','Female') NOT NULL,
    dob            DATE NOT NULL,
    pschool        VARCHAR(100) NOT NULL,
 
    english        INT NOT NULL,
    kiswahili      INT NOT NULL,
    maths          INT NOT NULL,
    science        INT NOT NULL,
    sst            INT NOT NULL,
    aggregate      INT NOT NULL,
 
    choice1        VARCHAR(50) NOT NULL,
    choice2        VARCHAR(50) NOT NULL,
    choice3        VARCHAR(50) NOT NULL,
 
    selected_school VARCHAR(50) DEFAULT NULL,
    choice_number   TINYINT     DEFAULT NULL,
 
    FOREIGN KEY (choice1) REFERENCES schools(school_name),
    FOREIGN KEY (choice2) REFERENCES schools(school_name),
    FOREIGN KEY (choice3) REFERENCES schools(school_name)
);
    
 