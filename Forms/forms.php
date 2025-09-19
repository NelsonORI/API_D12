<?php
class forms{
    private function submit_button($value){
        echo "<input type='submit' value='{$value}'>";
    }

    public function signup(){
        ?>
        <h2>Signup Form</h2>
        <form action='mail.php' method='post'>
            <label for='username'>Username:</label>
            <input type='text' id='username' name='username' required><br><br>
            <label for='email'>Email:</label>
            <input type='email' id='email' name='email' required><br><br>
            <label for='password'>Password:</label>
            <input type='password' id='password' name='password' required><br><br>
            <?php $this->submit_button('Sign Up'); ?> <a href="login.php">Already have an account? Log in</a>
        </form>
        <?php
    }

   
public function login(){
    // Display error message if authentication failed
    $error = '';
    if (isset($_GET['error']) && $_GET['error'] == 1) {
        $error = '<div>Invalid username or password.</div>';
    }
    ?>
        <div>
            <h2>Login Form</h2>
            <?php echo $error; ?>
            <form action='authenticate.php' method='post'>
                <div>
                    <label for='username'>Username:</label>
                    <input type='text' id='username' name='username' required>
                </div>
                <div>
                    <label for='password'>Password:</label>
                    <input type='password' id='password' name='password' required>
                </div>
                <?php $this->submit_button('Log In'); ?>
                <a href="index.php">Don't have an account? Sign up</a>
            </form>
        </div>
    <?php
}
}