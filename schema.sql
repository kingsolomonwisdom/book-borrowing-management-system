-- Create the database if it doesn't exist
CREATE DATABASE IF NOT EXISTS bbms;
USE bbms;

-- Drop existing tables if they exist
DROP TABLE IF EXISTS tbl_Fines;
DROP TABLE IF EXISTS tbl_Borrowing;
DROP TABLE IF EXISTS tbl_Books;
DROP TABLE IF EXISTS uploaded_images;
DROP TABLE IF EXISTS tbl_Librarian;
DROP TABLE IF EXISTS tbl_User;

-- Create User table
CREATE TABLE tbl_User (
    UserID INT AUTO_INCREMENT PRIMARY KEY,
    Name VARCHAR(100) NOT NULL,
    LastName VARCHAR(50) NOT NULL,
    Email VARCHAR(100) NOT NULL UNIQUE,
    Password VARCHAR(255) NOT NULL,
    IDNumber VARCHAR(50) NOT NULL,
    RegistrationDate DATE NOT NULL,
    Role ENUM('user', 'admin') DEFAULT 'user',
    CreatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UpdatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Create Librarian table
CREATE TABLE tbl_Librarian (
    LibrarianID INT AUTO_INCREMENT PRIMARY KEY,
    FirstName VARCHAR(50) NOT NULL,
    LastName VARCHAR(50) NOT NULL,
    Email VARCHAR(100) NOT NULL UNIQUE,
    PhoneNumber VARCHAR(15),
    HireDate DATE NOT NULL,
    CreatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UpdatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Create Books table with updated fields
CREATE TABLE tbl_Books (
    BookID INT AUTO_INCREMENT PRIMARY KEY,
    ISBN VARCHAR(13) UNIQUE,
    Title VARCHAR(255) NOT NULL,
    Author VARCHAR(255) NOT NULL,
    Publisher VARCHAR(255),
    PublishedDate DATE,
    Genre VARCHAR(100),
    Description TEXT,
    PageCount INT,
    TotalCopies INT DEFAULT 1,
    AvailableCopies INT DEFAULT 1,
    CoverImageURL VARCHAR(1024),
    Categories VARCHAR(255),
    PreviewLink VARCHAR(1024),
    CreatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UpdatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CHECK (AvailableCopies >= 0 AND AvailableCopies <= TotalCopies)
) ENGINE=InnoDB;

-- Create Borrowing table with improved status tracking
CREATE TABLE tbl_Borrowing (
    BorrowID INT AUTO_INCREMENT PRIMARY KEY,
    UserID INT NOT NULL,
    BookID INT NOT NULL,
    BorrowerName VARCHAR(255),
    BookTitle VARCHAR(255),
    BorrowDate DATE NOT NULL,
    DueDate DATE NOT NULL,
    ReturnDate DATE,
    Status ENUM('Borrowed', 'Returned', 'Overdue') DEFAULT 'Borrowed',
    CreatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UpdatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (UserID) REFERENCES tbl_User(UserID) ON DELETE RESTRICT,
    FOREIGN KEY (BookID) REFERENCES tbl_Books(BookID) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- Create Fines table with improved tracking
CREATE TABLE tbl_Fines (
    FineID INT AUTO_INCREMENT PRIMARY KEY,
    BorrowID INT NOT NULL,
    UserID INT NOT NULL,
    Amount DECIMAL(10,2) NOT NULL,
    FineDate DATE NOT NULL,
    Status ENUM('Pending', 'Paid') DEFAULT 'Pending',
    PaymentDate DATE,
    CreatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UpdatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (BorrowID) REFERENCES tbl_Borrowing(BorrowID) ON DELETE RESTRICT,
    FOREIGN KEY (UserID) REFERENCES tbl_User(UserID) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- Create uploaded_images table
CREATE TABLE uploaded_images (
    id INT AUTO_INCREMENT PRIMARY KEY,
    image_name VARCHAR(255) NOT NULL,
    image_type VARCHAR(100) NOT NULL,
    image_data VARCHAR(255) NOT NULL,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Create indexes for better performance
CREATE INDEX idx_book_isbn ON tbl_Books(ISBN);
CREATE INDEX idx_book_title ON tbl_Books(Title);
CREATE INDEX idx_book_author ON tbl_Books(Author);
CREATE INDEX idx_borrowing_status ON tbl_Borrowing(Status);
CREATE INDEX idx_borrowing_dates ON tbl_Borrowing(BorrowDate, DueDate, ReturnDate);
CREATE INDEX idx_user_borrowings ON tbl_Borrowing(UserID, Status);
CREATE INDEX idx_book_borrowings ON tbl_Borrowing(BookID, Status);
CREATE INDEX idx_fines_status ON tbl_Fines(Status);
CREATE INDEX idx_user_fines ON tbl_Fines(UserID);

-- Create stored procedures
DELIMITER //

-- Procedure for borrowing books with improved validation
CREATE PROCEDURE sp_BorrowBook(
    IN p_user_id INT,
    IN p_book_id INT,
    IN p_due_date DATE
)
BEGIN
    DECLARE v_available_copies INT;
    DECLARE v_borrower_name VARCHAR(255);
    DECLARE v_book_title VARCHAR(255);
    DECLARE v_active_borrows INT;
    
    -- Check if user exists
    IF NOT EXISTS (SELECT 1 FROM tbl_User WHERE UserID = p_user_id) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Invalid user ID';
    END IF;
    
    -- Check if book exists
    IF NOT EXISTS (SELECT 1 FROM tbl_Books WHERE BookID = p_book_id) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Invalid book ID';
    END IF;
    
    -- Check if user already has this book
    SELECT COUNT(*) INTO v_active_borrows
    FROM tbl_Borrowing
    WHERE UserID = p_user_id AND BookID = p_book_id AND Status = 'Borrowed';
    
    IF v_active_borrows > 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'User already has this book borrowed';
    END IF;
    
    -- Get available copies and book title
    SELECT AvailableCopies, Title INTO v_available_copies, v_book_title
    FROM tbl_Books WHERE BookID = p_book_id FOR UPDATE;
    
    -- Get borrower name
    SELECT CONCAT(Name, ' ', LastName) INTO v_borrower_name
    FROM tbl_User WHERE UserID = p_user_id;
    
    IF v_available_copies > 0 THEN
        START TRANSACTION;
        
        -- Update available copies
        UPDATE tbl_Books 
        SET AvailableCopies = AvailableCopies - 1
        WHERE BookID = p_book_id;
        
        -- Create borrow record
        INSERT INTO tbl_Borrowing (
            UserID, BookID, BorrowerName, BookTitle, 
            BorrowDate, DueDate, Status
        )
        VALUES (
            p_user_id, p_book_id, v_borrower_name, v_book_title,
            CURRENT_DATE, p_due_date, 'Borrowed'
        );
        
        COMMIT;
        
        SELECT 'Book borrowed successfully' AS message;
    ELSE
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Book not available for borrowing';
    END IF;
END //

-- Procedure for returning books with improved fine calculation
CREATE PROCEDURE sp_ReturnBook(
    IN p_borrow_id INT
)
BEGIN
    DECLARE v_book_id INT;
    DECLARE v_user_id INT;
    DECLARE v_days_overdue INT;
    DECLARE v_fine_amount DECIMAL(10,2);
    DECLARE v_borrow_status VARCHAR(20);
    
    -- Get the borrow record details
    SELECT BookID, UserID, Status, DATEDIFF(CURRENT_DATE, DueDate)
    INTO v_book_id, v_user_id, v_borrow_status, v_days_overdue
    FROM tbl_Borrowing
    WHERE BorrowID = p_borrow_id;
    
    -- Validate borrow record
    IF v_borrow_status IS NULL THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Invalid borrow record';
    END IF;
    
    IF v_borrow_status = 'Returned' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Book already returned';
    END IF;
    
    START TRANSACTION;
    
    -- Calculate fine if overdue (₱50 per day)
    IF v_days_overdue > 0 THEN
        SET v_fine_amount = v_days_overdue * 50.00;
        
        -- Create fine record
        INSERT INTO tbl_Fines (BorrowID, UserID, Amount, FineDate, Status)
        VALUES (p_borrow_id, v_user_id, v_fine_amount, CURRENT_DATE, 'Pending');
    END IF;
    
    -- Update borrow record
    UPDATE tbl_Borrowing
    SET ReturnDate = CURRENT_DATE,
        Status = 'Returned'
    WHERE BorrowID = p_borrow_id;
    
    -- Update book available copies
    UPDATE tbl_Books
    SET AvailableCopies = AvailableCopies + 1
    WHERE BookID = v_book_id;
    
    COMMIT;
    
    SELECT 'Book returned successfully' AS message,
           COALESCE(v_fine_amount, 0.00) AS fine_amount;
END //

-- Trigger to update book status to Overdue
CREATE TRIGGER trg_check_overdue_books
BEFORE UPDATE ON tbl_Borrowing
FOR EACH ROW
BEGIN
    IF NEW.ReturnDate IS NULL AND CURRENT_DATE > NEW.DueDate 
       AND NEW.Status != 'Overdue' THEN
        SET NEW.Status = 'Overdue';
    END IF;
END //

-- Daily maintenance trigger to update overdue status
CREATE EVENT evt_update_overdue_books
ON SCHEDULE EVERY 1 DAY
STARTS CURRENT_TIMESTAMP
DO
BEGIN
    UPDATE tbl_Borrowing
    SET Status = 'Overdue'
    WHERE ReturnDate IS NULL 
    AND CURRENT_DATE > DueDate 
    AND Status = 'Borrowed';
END //

DELIMITER ;

-- Insert sample admin user (password: admin123)
INSERT INTO tbl_User (Name, LastName, Email, Password, IDNumber, RegistrationDate, Role)
VALUES ('System', 'Administrator', 'admin@bbms.com', '$2y$10$8KzQ.ROCxR7ZYz1g1FH3ZOxVh3rUoY9xO9vYzAJ9PvFEYIZwpwn6O', 'ADMIN001', CURRENT_DATE, 'admin');

-- Insert sample books
INSERT INTO tbl_Books (ISBN, Title, Author, Publisher, PublishedDate, Genre, Description, PageCount, TotalCopies, AvailableCopies, Categories)
VALUES 
('9780439708180', 'Harry Potter and the Sorcerer''s Stone', 'J.K. Rowling', 'Scholastic', '1998-09-01', 'Fantasy', 'The first book in the Harry Potter series', 309, 2, 2, 'Fantasy,Young Adult'),
('9780439064873', 'Harry Potter and the Chamber of Secrets', 'J.K. Rowling', 'Scholastic', '1999-06-02', 'Fantasy', 'The second book in the Harry Potter series', 341, 1, 1, 'Fantasy,Young Adult'),
('9780439136365', 'Harry Potter and the Prisoner of Azkaban', 'J.K. Rowling', 'Scholastic', '1999-09-08', 'Fantasy', 'The third book in the Harry Potter series', 435, 1, 1, 'Fantasy,Young Adult'); 