<?php
class layouts {
    public function heading($conf) {
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title><?php echo $conf['site_name']; ?> - Ticket Platform</title>
            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
            <style>
                :root {
                    --primary: #6f42c1;
                    --secondary: #fd7e14;
                    --light-bg: #f8f9fa;
                }
                
                body {
                    background-color: var(--light-bg);
                    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                    min-height: 100vh;
                    display: flex;
                    flex-direction: column;
                }
                
                .navbar-brand {
                    font-weight: 700;
                    color: var(--primary) !important;
                }
                
                .hero-section {
                    background: linear-gradient(rgba(0, 0, 0, 0.7), rgba(0, 0, 0, 0.7)), url('https://images.unsplash.com/photo-1533174072545-7a4b6ad7a6c3?ixlib=rb-4.0.3&auto=format&fit=crop&w=1350&q=80');
                    background-size: cover;
                    background-position: center;
                    color: white;
                    padding: 5rem 0;
                    margin-bottom: 2rem;
                }
                
                .btn-primary {
                    background-color: var(--primary);
                    border-color: var(--primary);
                }
                
                .btn-primary:hover {
                    background-color: #5a32a3;
                    border-color: #5a32a3;
                }
                
                .btn-outline-primary {
                    color: var(--primary);
                    border-color: var(--primary);
                }
                
                .btn-outline-primary:hover {
                    background-color: var(--primary);
                    color: white;
                }
                
                .form-container {
                    background: white;
                    border-radius: 10px;
                    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
                    padding: 2rem;
                    margin-bottom: 2rem;
                }
                
                .footer {
                    background-color: #343a40;
                    color: white;
                    padding: 2rem 0;
                    margin-top: auto;
                }
                
                .feature-icon {
                    font-size: 2.5rem;
                    color: var(--primary);
                    margin-bottom: 1rem;
                }
            </style>
        </head>
        <body>
            <!-- Navigation -->
            <nav class="navbar navbar-expand-lg navbar-dark bg-dark sticky-top">
                <div class="container">
                    <a class="navbar-brand" href="index.php">
                        <i class="fas fa-ticket-alt me-2"></i><?php echo $conf['site_name']; ?>
                    </a>
                    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                        <span class="navbar-toggler-icon"></span>
                    </button>
                    <div class="collapse navbar-collapse" id="navbarNav">
                        <ul class="navbar-nav me-auto">
                            <li class="nav-item">
                                <a class="nav-link active" href="index.php">Home</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="#">Events</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="#">Venues</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="#">Support</a>
                            </li>
                        </ul>
                        <div class="d-flex">
                            <a href="login.php" class="btn btn-outline-light me-2">Login</a>
                            <a href="index.php" class="btn btn-primary">Sign Up</a>
                        </div>
                    </div>
                </div>
            </nav>

            <!-- Hero Section -->
            <div class="hero-section">
                <div class="container text-center">
                    <h1 class="display-4 fw-bold">Find Your Next Experience</h1>
                    <p class="lead">Discover events, concerts, and shows happening near you</p>
                    <a href="#events" class="btn btn-primary btn-lg mt-3">Explore Events</a>
                </div>
            </div>

            <div class="container flex-grow-1">
        <?php
    }

    public function welcome($conf) {
        ?>
        <!-- Welcome Message -->
        <div class="row mb-5">
            <div class="col-12 text-center">
                <h2 class="mb-3">Welcome to our Ticket Platform!</h2>
                <p class="lead">Sign up to buy tickets for upcoming events.</p>
            </div>
        </div>
        <?php
    }

    public function footer($conf) {
        ?>
            </div> <!-- Close container -->

            <!-- Footer -->
            <footer class="footer">
                <div class="container">
                    <div class="row">
                        <div class="col-md-4 mb-4">
                            <h5><i class="fas fa-ticket-alt me-2"></i><?php echo $conf['site_name']; ?></h5>
                            <p>Your trusted platform for event tickets and experiences.</p>
                        </div>
                        <div class="col-md-4 mb-4">
                            <h5>Quick Links</h5>
                            <ul class="list-unstyled">
                                <li><a href="#" class="text-white">Upcoming Events</a></li>
                                <li><a href="#" class="text-white">Popular Venues</a></li>
                                <li><a href="#" class="text-white">Gift Cards</a></li>
                            </ul>
                        </div>
                        <div class="col-md-4 mb-4">
                            <h5>Contact Us</h5>
                            <p>Email: <a href="mailto:<?php echo $conf['site_email']; ?>" class="text-white"><?php echo $conf['site_email']; ?></a></p>
                            <div class="d-flex">
                                <a href="#" class="text-white me-3"><i class="fab fa-facebook-f"></i></a>
                                <a href="#" class="text-white me-3"><i class="fab fa-twitter"></i></a>
                                <a href="#" class="text-white me-3"><i class="fab fa-instagram"></i></a>
                            </div>
                        </div>
                    </div>
                    <hr class="bg-light">
                    <div class="text-center">
                        Copyright &copy; <?php echo date("Y"); ?> <?php echo $conf['site_name']; ?>. All rights reserved.
                    </div>
                </div>
            </footer>

            <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
        </body>
        </html>
        <?php
    }
}
?>