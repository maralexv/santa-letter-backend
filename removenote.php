<?php
// Get parameter form the URL
$q = $_REQUEST["q"];
// $q = test_input($_REQUEST["q"]);

if ($q !== "") {

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
    // echo "Connected successfully";

    $sql = "DELETE FROM notes WHERE NoteID=$q;";
    $result = $conn->query($sql);

    $result->free_result();
    // Close database connection
    $conn->close();
}

function test_input ($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

?>