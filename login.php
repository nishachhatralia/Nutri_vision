<?php
session_start();

include 'db.php';
include 'includes/icon.php';

$login_message = '';

if(isset($_POST['login']))
{
    $email = $_POST['email'];
    $password = $_POST['password'];

    $query = "SELECT * FROM users
              WHERE email='$email'
              AND password='$password'";

    $result = mysqli_query($conn, $query);

    if(mysqli_num_rows($result) > 0)
    {
        $user = mysqli_fetch_assoc($result);

        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['full_name'];

        header("Location: dashboard.php");
    }
    else
    {
        $login_message = "Invalid Email or Password";
    }
}
?>

<!DOCTYPE html>
<html>

<head>

    <title>Login - NutriVision AI</title>

    <link rel="stylesheet" href="style.css">

</head>

<body>

<div class="navbar">
    <h2><?php echo nv_icon('logo', 24); ?><span class="brand-text">NutriVision AI</span></h2>
</div>

<div class="container" style="max-width: 460px;">

<h1>Login</h1>

<?php if($login_message): ?>
<div class="alert alert-danger">❌ <?php echo htmlspecialchars($login_message); ?></div>
<?php endif; ?>

<form method="POST">

<label>Email</label>
<input type="email"
       name="email"
       placeholder="Enter Email"
       required>

<label>Password</label>
<input type="password"
       name="password"
       placeholder="Enter Password"
       required>

<button type="submit"
        name="login"
        class="btn btn-block">

    Login

</button>

</form>

<p style="margin-top: 16px; color: var(--gray);">New here? <a href="register.php" style="color: var(--primary); font-weight: 600;">Create an account</a></p>

</div>

</body>
</html>