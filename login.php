<?php 

session_start();

include "./database/pdo_connection.php";

if(isset($_SESSION['user'])){
    unset($_SESSION['user']);
}


$error="";

if (
    isset($_POST['email']) && $_POST['email'] !== ''
    && isset($_POST['password']) && $_POST['password'] !== ''
    ) {
        if(isset($_POST['sub'])){
            $email=$_POST['email'];
            $password=$_POST['password'];
            $result=$conn->prepare("SELECT * FROM users WHERE email=? AND password=?");
            $result->bindValue(1,$email);
            $result->bindValue(2,$password);
            $result->execute();
            if($result->rowCount()>=1){
                $_SESSION['user']=$_POST['email'];
                // print_r($_SESSION);
                header('location:register.php');
            }else{
                $error='email or password is incorrect!';
            }

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
    <div class="container">
        <div class="img">
            <div class="box-form">
                <form class="form" action="#" method="POST">
                    <h2>Login</h2>
                    <div class="box-input">
                        <input type="email" name="email" id="email" required>
                        <label for="email">Email</label>
                    </div>
                    <div class="box-input">
                        <input type="password" name="password" id="pass" required>
                        <label for="pass">Password</label>
                    </div>
                    <div class="group">
                        <a href="#" style="color:aquamarine;">Forget Password?</a>
                        <a href="./register.php">Register Now</a>
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