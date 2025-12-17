/*Entity tables for Hospital Management System*/

-- Create tables in correct dependency order

create table person(
    person_id INT,
    name VARCHAR(50) NOT NULL,
    gender CHAR(1) NOT NULL,
    age INT,
    phone VARCHAR(20) UNIQUE,
    password VARCHAR(255) NOT NULL,
    PRIMARY KEY (person_id),
    CONSTRAINT gender_check CHECK(gender IN ('M', 'F', 'O')),
    CONSTRAINT age_check CHECK(age >= 0)
);

create table department(
    department_id INT,
    department_name VARCHAR(50) UNIQUE,
    contact VARCHAR(20) NOT NULL,
    location VARCHAR(100),
    PRIMARY KEY (department_id)
);

create table patient(
    patient_id INT,
    person_id INT NOT NULL,
    height_cm INT,
    weight_kg INT,
    blood_type VARCHAR(3),
    address VARCHAR(100),
    PRIMARY KEY (patient_id),
    FOREIGN KEY (person_id) REFERENCES person(person_id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT blood_type_check CHECK(blood_type IN('A', 'B', 'AB', 'O')),
    CONSTRAINT height_check CHECK(height_cm > 0),
    CONSTRAINT weight_check CHECK(weight_kg > 0)
);

create table doctor(
    doctor_id INT,
    person_id INT NOT NULL,
    specialty VARCHAR(50) NOT NULL,
    year_experience INT,
    salary INT,
    department_id INT,
    PRIMARY KEY (doctor_id),
    FOREIGN KEY (person_id) REFERENCES person(person_id) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (department_id) REFERENCES department(department_id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT experience_check CHECK(year_experience >= 0),
    CONSTRAINT salary_check CHECK(salary >= 0)
);

create table admin(
    admin_id INT,
    person_id INT NOT NULL,
    PRIMARY KEY (admin_id),
    FOREIGN KEY (person_id) REFERENCES person(person_id) ON DELETE CASCADE ON UPDATE CASCADE
);

create table nurse(
    nurse_id INT,
    person_id INT NOT NULL,
    title VARCHAR(50) NOT NULL,
    schedule VARCHAR(50),
    department_id INT,
    PRIMARY KEY (nurse_id),
    FOREIGN KEY (person_id) REFERENCES person(person_id) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (department_id) REFERENCES department(department_id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT title_check CHECK(title IN('Junior', 'Senior', 'Head Nurse'))
);

create table ward(
    ward_id INT,
    ward_number VARCHAR(10) UNIQUE,
    ward_type VARCHAR(50),
    bed_count INT NOT NULL,
    current_occupancy INT DEFAULT 0,
    PRIMARY KEY (ward_id),
    CONSTRAINT bed_count_check CHECK(bed_count > 0),
    CONSTRAINT occupancy_check CHECK(current_occupancy >= 0 AND current_occupancy <= bed_count)
);

create table disease(
    disease_id INT,
    disease_name VARCHAR(100) UNIQUE NOT NULL,
    category VARCHAR(50),
    description TEXT,
    common_treatments TEXT,
    PRIMARY KEY (disease_id),
    CONSTRAINT category_check CHECK(category IN('Infectious', 'Chronic', 'Acute', 'Other'))
);

create table prescription(
    prescription_id INT,
    patient_id INT NOT NULL,
    doctor_id INT NOT NULL,
    p_date DATE NOT NULL,
    
    validity_period INT,
    PRIMARY KEY (prescription_id),
    FOREIGN KEY (patient_id) REFERENCES patient(patient_id) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (doctor_id) REFERENCES doctor(doctor_id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT validity_check CHECK(validity_period > 0)
);

create table medicine(
    medicine_id INT,
    medicine_name VARCHAR(100) UNIQUE NOT NULL,
    type VARCHAR(50),
    form VARCHAR(50),
    price float DEFAULT 0.0,
    manufacturer VARCHAR(100) NOT NULL,
    stock INT DEFAULT 0,
    PRIMARY KEY (medicine_id),
    CONSTRAINT price_check CHECK(price >= 0),
    CONSTRAINT stock_check CHECK(stock >= 0)
);

create table appointment(
    appointment_id INT,
    patient_id INT NOT NULL,
    doctor_id INT NOT NULL,
    appointment_date DATE NOT NULL,
    time TIME NOT NULL,
    symptoms TEXT,
    status VARCHAR(20) NOT NULL,
    PRIMARY KEY (appointment_id),
    FOREIGN KEY (patient_id) REFERENCES patient(patient_id) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (doctor_id) REFERENCES doctor(doctor_id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT status_check CHECK(status IN('Scheduled', 'Completed', 'Cancelled'))
);

create table medical_record(
    record_id INT,
    patient_id INT NOT NULL,
    doctor_id INT NOT NULL,
    record_date DATE NOT NULL,
    diagnosis TEXT NOT NULL,
    treatment_plan TEXT NOT NULL,
    vital_signs TEXT,
    medical_images BLOB,
    PRIMARY KEY (record_id),
    FOREIGN KEY (patient_id) REFERENCES patient(patient_id) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (doctor_id) REFERENCES doctor(doctor_id) ON DELETE CASCADE ON UPDATE CASCADE
);

/*Relationship tables for Hospital Management System*/

create table patient_ward(
    patient_id INT,
    ward_id INT,
    PRIMARY KEY (patient_id, ward_id),
    FOREIGN KEY (patient_id) REFERENCES patient(patient_id) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (ward_id) REFERENCES ward(ward_id) ON DELETE CASCADE ON UPDATE CASCADE
);

create table patient_disease(
    patient_id INT,
    disease_id INT,
    PRIMARY KEY (patient_id, disease_id),
    FOREIGN KEY (patient_id) REFERENCES patient(patient_id) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (disease_id) REFERENCES disease(disease_id) ON DELETE CASCADE ON UPDATE CASCADE
);

create table prescription_medicine(
    prescription_id INT,
    medicine_id INT,
    dosage VARCHAR(100) NOT NULL,
    usage_statement TEXT,
    PRIMARY KEY (prescription_id, medicine_id),
    FOREIGN KEY (prescription_id) REFERENCES prescription(prescription_id) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (medicine_id) REFERENCES medicine(medicine_id) ON DELETE CASCADE ON UPDATE CASCADE
);



create table execution(
    prescription_id INT,
    nurse_id INT,
    PRIMARY KEY (prescription_id, nurse_id),
    FOREIGN KEY (prescription_id) REFERENCES prescription(prescription_id) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (nurse_id) REFERENCES nurse(nurse_id) ON DELETE CASCADE ON UPDATE CASCADE
);

create table diagnosis(
    doctor_id INT,
    disease_id INT,
    PRIMARY KEY (doctor_id, disease_id),
    FOREIGN KEY (doctor_id) REFERENCES doctor(doctor_id) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (disease_id) REFERENCES disease(disease_id) ON DELETE CASCADE ON UPDATE CASCADE
);

DELIMITER |

CREATE TRIGGER check_patient_disjoint
AFTER INSERT ON patient
FOR EACH ROW
BEGIN
    IF NEW.person_id IN (SELECT person_id FROM doctor) THEN
        DELETE FROM patient WHERE patient.person_id = NEW.person_id;
    END IF;
END;|

CREATE TRIGGER check_doctor_disjoint
AFTER INSERT ON doctor
FOR EACH ROW
BEGIN
    IF NEW.person_id IN (SELECT person_id FROM patient) THEN
        DELETE FROM doctor WHERE doctor.person_id = NEW.person_id;
    END IF;
END;|

CREATE TRIGGER check_nurse_disjoint
AFTER INSERT ON nurse
FOR EACH ROW
BEGIN
    IF NEW.person_id IN (SELECT person_id FROM doctor) THEN
        DELETE FROM nurse WHERE nurse.person_id = NEW.person_id;
    END IF;
END;|

CREATE TRIGGER check_doctor_disjoint_nurse
AFTER INSERT ON doctor
FOR EACH ROW
BEGIN
    IF NEW.person_id IN (SELECT person_id FROM nurse) THEN
        DELETE FROM doctor WHERE doctor.person_id = NEW.person_id;
    END IF;
END;|

CREATE TRIGGER check_admin_disjoint_doctor
AFTER INSERT ON admin
FOR EACH ROW
BEGIN
    IF NEW.person_id IN (SELECT person_id FROM doctor) THEN
        DELETE FROM admin WHERE admin.person_id = NEW.person_id;
    END IF;
END;|

CREATE TRIGGER check_admin_disjoint_patient
AFTER INSERT ON admin
FOR EACH ROW
BEGIN
    IF NEW.person_id IN (SELECT person_id FROM patient) THEN
        DELETE FROM admin WHERE admin.person_id = NEW.person_id;
    END IF;
END;|

DELIMITER ;