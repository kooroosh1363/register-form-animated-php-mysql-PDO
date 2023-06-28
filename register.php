<?php

include "./database/pdo_connection.php";

$error = "";

if (
    isset($_POST['username']) && $_POST['username'] !== ''
    && isset($_POST['email']) && $_POST['email'] !== ''
    &&  isset($_POST['password']) && $_POST['password'] !== ''
    &&  isset($_POST['confirm']) && $_POST['confirm'] !== ''
) {
    if ($_POST['password'] === $_POST['confirm']) {
        if (strlen($_POST['password']) > 4) {
            $sql = "SELECT * FROM users WHERE email=?";
            $statement = $conn->prepare($sql);
            $statement->execute([$_POST['email']]);
            $user = $statement->fetch();
            if ($user === false) {
                if (isset($_POST['sub'])) {
                    $username = $_POST['username'];
                    $email = $_POST['email'];
                    $password = $_POST['password'];
                    $result = $conn->prepare("INSERT INTO users SET username=? ,email=? ,password=?");
                    $result->bindValue(1, $username);
                    $result->bindValue(2, $email);
                    $result->bindValue(3, $password);

                    $result->execute();
                }
            } else {
                $error = "The email is duplicate!";
            }
        } else {
            $error = "Password is too short! Must be at least five characters!";
        }
    } else {
        $error = "passwords do not match!";
    }
} else {
    if (!empty($_POST)) {
        $error = "You must fill in all the fields";
    }
}

?>



<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="./assets/style.css?v=<?php echo time(); ?>">
    <title>register-form</title>
</head>

<body>
    <video autoplay muted loop plays-inline class="video">
        <source src="./assets/video/ww(2160p).mp4" type="video/mp4">
    </video>
    <div class="container">

        <div class="img">
            <div class="box-form">
                <form class="form" action="#" method="Post">
                    <h2>Register</h2>
                    <div class="box-input">
                        <input type="text" name="username" id="name" required>
                        <label for="name">User Name</label>
                    </div>
                    <div class="box-input">
                        <input type="email" name="email" id="email" required>
                        <label for="email">Email</label>
                    </div>
                    <div class="box-input">
                        <input type="password" name="password" id="pass" required>
                        <label for="pass">Password</label>
                    </div>
                    <div class="box-input">
                        <input type="password" name="confirm" id="conf" required>
                        <label for="conf">RePassword</label>
                    </div>
                    <div class="group">
                        <a href="#" style="color:#cc979e;">Forget Password?</a>
                        <a href="./login.php">Login</a>
                    </div>
                    <button name="sub" class="btn">Sign In</button>
                    <section class="sec">
                        <?php
                        if ($error !== "") echo $error;
                        ?>
                    </section>
                </form>


            </div>

        </div>
    </div>
</body>

</html>