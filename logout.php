
// ✅ logout.php – session destroy + redirect
<?php
session_start();
session_destroy();
header("Location: index.php");
exit();
?>


