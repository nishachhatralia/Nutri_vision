<?php
include 'db.php';
include 'includes/icon.php';

$register_message = '';
$register_success = false;

if(isset($_POST['register']))
{
    $full_name = $_POST['full_name'];
    $email = $_POST['email'];
    $password = $_POST['password'];
    $age = $_POST['age'];
    $gender = $_POST['gender'];
    $weight = $_POST['weight'];
    $height = $_POST['height'];
    $goal = $_POST['goal'];
    $activity_level = $_POST['activity_level'];

    $query = "INSERT INTO users
    (full_name, email, password, age, gender, weight, height, goal, activity_level)

    VALUES

    ('$full_name','$email','$password','$age','$gender','$weight','$height','$goal','$activity_level')";

    $result = mysqli_query($conn, $query);

    if($result)
    {
        $register_message = "Registration Successful";
        $register_success = true;
    }
    else
    {
        $register_message = "Registration Failed";
    }
}
?>

<!DOCTYPE html>
<html>

<head>

    <title>Register - NutriVision AI</title>

    <link rel="stylesheet" href="style.css">

</head>

<body>

<div class="navbar">
    <h2><?php echo nv_icon('logo', 24); ?><span class="brand-text">NutriVision AI</span></h2>
</div>

<div class="container" style="max-width: 520px;">

<h1>Register</h1>

<?php if($register_message): ?>
<div class="alert <?php echo $register_success ? 'alert-success' : 'alert-danger'; ?>">
    <?php echo $register_success ? '✅' : '❌'; ?> <?php echo htmlspecialchars($register_message); ?>
</div>
<?php endif; ?>

<form method="POST">

<label>Full Name</label>
<input type="text"
       name="full_name"
       placeholder="Full Name"
       required>

<label>Email</label>
<input type="email"
       name="email"
       placeholder="Email"
       required>

<label>Password</label>
<input type="password"
       name="password"
       placeholder="Password"
       required>

<label>Age</label>
<input type="number"
       name="age"
       placeholder="Age"
       required>

<label>Gender</label>
<select name="gender">

    <option>Male</option>
    <option>Female</option>

</select>

<label>Weight (kg)</label>
<input type="number"
       step="0.1"
       name="weight"
       placeholder="Weight (kg)"
       required>

<label>Height (cm)</label>
<input type="number"
       step="0.1"
       name="height"
       placeholder="Height (cm)"
       required>

<label>Goal</label>
<select name="goal">

    <option>Weight Loss</option>
    <option>Muscle Gain</option>
    <option>Maintain Weight</option>

</select>

<label>Activity Level</label>
<select name="activity_level">

    <option>Beginner</option>
    <option>Intermediate</option>
    <option>Advanced</option>

</select>

<button type="submit"
        name="register"
        class="btn btn-lg btn-block">

    Register

</button>

</form>

<p style="margin-top: 16px; color: var(--gray);">Already have an account? <a href="login.php" style="color: var(--primary); font-weight: 600;">Log in</a></p>

</div>

</body>
</html>