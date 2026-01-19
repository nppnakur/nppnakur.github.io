// session.php फाइल में ये कोड जोड़ें
session_start();
error_log("Session ID: ".session_id());
error_log("User ID in session: ".($_SESSION['user_id'] ?? 'Not set'));