<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_FILES['testFile'])) {
        $uploadDir = '/opt/bitnami/php/tmp/';
        $uploadFile = $uploadDir . basename($_FILES['testFile']['name']);
        if (move_uploaded_file($_FILES['testFile']['tmp_name'], $uploadFile)) {
            echo "File is valid, and was successfully uploaded.\n";
        } else {
            echo "Possible file upload attack!\n";
        }
    } else {
        echo "No file uploaded.\n";
    }
} else {
    ?>
<form enctype="multipart/form-data" action="test_upload.php" method="POST">
    <input type="file" name="testFile" />
    <input type="submit" value="Upload File" />
</form>
<?php
}
?>