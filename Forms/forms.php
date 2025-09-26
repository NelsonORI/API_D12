<?php
class forms {
    private function submit_button($value, $class = 'btn-primary') {
        echo "<button type='submit' class='btn $class btn-lg w-100'>$value</button>";
    }

    public function signup() {
        ?>
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="form-container">
                    <h2 class="text-center mb-4">Create Your Account</h2>
                    <form action='mail.php' method='post'>
                        <div class="mb-3">
                            <label for='username' class="form-label">Username:</label>
                            <input type='text' class="form-control form-control-lg" id='username' name='username' required>
                        </div>
                        <div class="mb-3">
                            <label for='email' class="form-label">Email:</label>
                            <input type='email' class="form-control form-control-lg" id='email' name='email' required>
                        </div>
                        <div class="mb-3">
                            <label for='password' class="form-label">Password:</label>
                            <input type='password' class="form-control form-control-lg" id='password' name='password' required>
                        </div>
                        <div class="mb-3">
                            <?php $this->submit_button('Sign Up'); ?>
                        </div>
                        <div class="text-center mt-3">
                            <a href="login.php">Already have an account? Log in</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <?php
    }

    public function login() {
        // Display error message if authentication failed
        $error = '';
        if (isset($_GET['error']) && $_GET['error'] == 1) {
            $error = '<div class="alert alert-danger">Invalid username or password.</div>';
        }
        ?>
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="form-container">
                    <h2 class="text-center mb-4">Login to Your Account</h2>
                    <?php echo $error; ?>
                    <form action='authenticate.php' method='post'>
                        <div class="mb-3">
                            <label for='username' class="form-label">Username:</label>
                            <input type='text' class="form-control form-control-lg" id='username' name='username' required>
                        </div>
                        <div class="mb-3">
                            <label for='password' class="form-label">Password:</label>
                            <input type='password' class="form-control form-control-lg" id='password' name='password' required>
                        </div>
                        <div class="mb-3">
                            <?php $this->submit_button('Log In'); ?>
                        </div>
                        <div class="text-center mt-3">
                            <a href="index.php">Don't have an account? Sign up</a><br>
                            <a href="forgot_password.php" class="text-muted">Forgot your password?</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <?php
    }

    public function forgot_password() {
        $message = '';
        if (isset($_GET['message'])) {
            $messageType = isset($_GET['success']) ? 'success' : 'danger';
            $messageText = htmlspecialchars(urldecode($_GET['message']));
            $message = "<div class='alert alert-$messageType'>$messageText</div>";
        }
        ?>
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="form-container">
                    <h2 class="text-center mb-4">Reset Your Password</h2>
                    <?php echo $message; ?>
                    <form action='process_forgot_password.php' method='post'>
                        <div class="mb-3">
                            <label for='email' class="form-label">Enter your email address:</label>
                            <input type='email' class="form-control form-control-lg" id='email' name='email' required>
                        </div>
                        <div class="mb-3">
                            <?php $this->submit_button('Send Reset Link', 'btn-warning'); ?>
                        </div>
                        <div class="text-center mt-3">
                            <a href="login.php">Back to Login</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <?php
    }

    public function reset_password($token) {
        $error = '';
        if (isset($_GET['error'])) {
            $error = '<div class="alert alert-danger">' . htmlspecialchars(urldecode($_GET['error'])) . '</div>';
        }
        ?>
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="form-container">
                    <h2 class="text-center mb-4">Create New Password</h2>
                    <?php echo $error; ?>
                    <form action='process_reset_password.php' method='post'>
                        <input type='hidden' name='token' value='<?php echo htmlspecialchars($token); ?>'>
                        <div class="mb-3">
                            <label for='password' class="form-label">New Password:</label>
                            <input type='password' class="form-control form-control-lg" id='password' name='password' required minlength="6">
                        </div>
                        <div class="mb-3">
                            <label for='confirm_password' class="form-label">Confirm New Password:</label>
                            <input type='password' class="form-control form-control-lg" id='confirm_password' name='confirm_password' required minlength="6">
                        </div>
                        <div class="mb-3">
                            <?php $this->submit_button('Reset Password', 'btn-success'); ?>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <?php
    }
}
?>