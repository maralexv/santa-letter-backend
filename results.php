<!DOCTYPE html>
<html lang="en">
<head> 
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Santa Letter - All Wishes</title>

    <?php require 'header.php';?>

</head>

<body>
    <div style="text-align: center;"><h1>All Christmas wishes for this year:</h1></div>
    <div id="wishes" style="display: block;">
        <?php
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

            $currentyear = date("Y");

            $sql = "SELECT users.UserName, notes.NoteText, notes.Time, notes.NoteID
                    FROM notes 
                    INNER JOIN users ON notes.User_id=users.UserID 
                    WHERE YEAR(notes.Time)=$currentyear  
                    ORDER BY users.UserName 
                    ASC;";
            $result = $conn->query($sql);

            // loop through the result  
            if ($result->num_rows > 0) {
                // print out (display) the table of wishes fior each user
                echo "<table><tr><th>Authors' emails</th><th>Their wishes</th></tr>";
                while ($row = $result->fetch_assoc()) {
                    // display users and notes
                    echo "<tr><td>" . $row["UserName"]. "</td><td>" . $row["NoteText"]. "</td></tr>";
                }
                echo "</table>";

            } else {
                echo "<p style='text-align: center;'>Nothing on record yet...<p>";
            };

        ?>

    </div>

    <footer>
        <a href="index.php"><button>back</button></a><br><br>
        Copyright scarvesfamily&copy; <?php echo date("Y");?>
    </footer><br>

</body>
</html>
