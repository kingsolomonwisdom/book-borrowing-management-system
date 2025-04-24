
<?php
	require("connection.php");


	$sql = "CREATE TABLE tbl_User (
	    UserID INT AUTO_INCREMENT PRIMARY KEY,
	    UserName VARCHAR(100) NOT NULL,
	    LastName VARCHAR(50) NOT NULL,
	    Email VARCHAR(100) NOT NULL UNIQUE,
	    IDNumber VARCHAR(50) NOT NULL,
	    RegistrationDate DATE NOT NULL
	) ENGINE=InnoDB;";

	$query = mysqli_query($con, $sql);
	echo $query ? "User table created successfully.<br>" : "User table creation failed: " . mysqli_error($con) . "<br>";

	$sql = "CREATE TABLE tbl_Librarian (
	    LibrarianID INT AUTO_INCREMENT PRIMARY KEY,
	    FirstName VARCHAR(50) NOT NULL,
	    LastName VARCHAR(50) NOT NULL,
	    Email VARCHAR(100) NOT NULL UNIQUE,
	    PhoneNumber VARCHAR(15),
	    HireDate DATE NOT NULL
	) ENGINE=InnoDB;";

	$query = mysqli_query($con, $sql);
	echo $query ? "Librarian table created successfully.<br>" : "Librarian table creation failed: " . mysqli_error($con) . "<br>";


	$sql = "CREATE TABLE tbl_Books (
	    BookID INT AUTO_INCREMENT PRIMARY KEY,
	    Title VARCHAR(100) NOT NULL,
	    Author VARCHAR(100) NOT NULL,
	    PublishedDate DATE,
	    Genre VARCHAR(50),
	    Quantity INT NOT NULL
	) ENGINE=InnoDB;";

	$query = mysqli_query($con, $sql);
	echo $query ? "Books table created successfully.<br>" : "Books table creation failed: " . mysqli_error($con) . "<br>";


	$sql = "CREATE TABLE tbl_fines (
	    FineID INT AUTO_INCREMENT PRIMARY KEY,
	    UserID INT NOT NULL,
	    FineAmount DECIMAL(10, 2) NOT NULL,
	    FineDate DATE NOT NULL,
	    FOREIGN KEY (UserID) REFERENCES tbl_User(UserID) ON DELETE CASCADE
	) ENGINE=InnoDB;";

	$query = mysqli_query($con, $sql);
	echo $query ? "Fines table created successfully.<br>" : "Fines table creation failed: " . mysqli_error($con) . "<br>";


	$sql = "CREATE TABLE tbl_Borrowing (
	    BorrowID INT AUTO_INCREMENT PRIMARY KEY,
	    BorrowerName VARCHAR(100),
	    BookTitle VARCHAR(255),
	    BookID VARCHAR(50),
	    BorrowDate DATE,
	    ReturnDate DATE,
	    UserID INT,
	    BookImage VARCHAR(255)
	) ENGINE=InnoDB;";

	$query = mysqli_query($con, $sql);
	echo $query ? "Borrow table created successfully.<br>" : "Borrow table creation failed: " . mysqli_error($con) . "<br>";


	$sql = "CREATE TABLE uploaded_images (
	    id INT AUTO_INCREMENT PRIMARY KEY,
	    image_name VARCHAR(255) NOT NULL,
	    image_type VARCHAR(100) NOT NULL,
	    image_data VARCHAR(255) NOT NULL,
	    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
	) ENGINE=InnoDB;";

	$query = mysqli_query($con, $sql);
	echo $query ? "Uploaded images table created successfully.<br>" : "Uploaded images table creation failed: " . mysqli_error($con) . "<br>";
?>