<?php

class layouts {
    public function heading($conf) {
        echo "Welcome to our Ticket Platform!";
    }
    public function welcome($conf) {
        echo "<p>Sign up to buy tickets for upcoming events.</p>";
    }
    public function footer($conf) {
        echo "<footer>
        Copyright &copy; " . date("Y") . " Tickyfii
        <br>Contact us at <a href='mailto:info@tickyfii.com'>info@tickyfii.com</a></footer>";
    }
}