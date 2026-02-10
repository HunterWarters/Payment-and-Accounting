
A new **Billings Management** table has been added to the database schema to handle general billing and miscellaneous fees separate from student assessments.

#### Table Structure
```sql
CREATE TABLE `billings` (
  `billing_id` INT(11) PRIMARY KEY AUTO_INCREMENT,
  `billing_description` VARCHAR(255) NOT NULL,
  `amount` DECIMAL(10,2) NOT NULL,
  `due_date` DATE NOT NULL,
  `created_date` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP(),
  `remarks` TEXT DEFAULT NULL,
  `status` ENUM('Active','Completed','Cancelled') DEFAULT 'Active',
  `student_id` INT(11) DEFAULT NULL,
  FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE SET NULL
);
```

#### Purpose
This table allows the system to:
- Create general billing records for various fees (miscellaneous, laboratory, events, etc.)
- Track billing status (Active, Completed, Cancelled)
- Set due dates for payments
- Optionally link billings to specific students
- Add remarks or notes for each billing entry



#### Sample Usage
```sql
-- Example: Creating a new miscellaneous fee billing
INSERT INTO billings (billing_description, amount, due_date, status) 
VALUES ('miscellaneous-fee', 5000.00, '2026-03-15', 'Active');

-- Example: Creating a student-specific laboratory fee
INSERT INTO billings (billing_description, amount, due_date, student_id, status) 
VALUES ('laboratory-fee', 3000.00, '2026-03-20', 1, 'Active');
```

---



- All other tables remain unchanged
- Existing functionality is not affected
- The billings table is optional and independent from student_assessments
- No data migration required for existing records
