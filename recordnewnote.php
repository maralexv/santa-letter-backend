<?php
// Get parameter form the URL
$q = json_decode($_REQUEST["q"]);

// echo $q . " > note: " . $q[0] . ", userID: " . $q[1];

if ($q !== '') {
    $note = test_input($q[0]);
    $uid = test_input($q[1]);

    // Connect to the database
    $dbhost = "localhost";
    $dbuser = "alex";
    $dbpass = "easter2000";
    $db = "notes";

    // Create connection to MariaDB server
    $conn = new mysqli($dbhost, $dbuser, $dbpass, $db);

    // Check connection
    if($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    // Insert new note of identified user into db
    $sql = "INSERT INTO notes (NoteText, User_id) VALUES ('$note', '$uid')";

    // Check whther the task was carried out successfully and report
    if ($conn->query($sql) === TRUE) {
        echo "Record inserted successfully";
    } else {
        echo "Error: " . $sql . "<br>" . $conn->error;
    }

    $conn->close();
}

function test_input ($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

?> 