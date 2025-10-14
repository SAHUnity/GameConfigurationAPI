<nav id="sidebarMenu" class="col-md-3 col-lg-2 d-md-block bg-light sidebar collapse">
    <div class="position-sticky pt-3">
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'index.php' ? 'active' : ''; ?>" 
                   href="./index.php">
                    <i class="fas fa-home"></i>
                    Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'games.php' ? 'active' : ''; ?>" 
                   href="./games.php">
                    <i class="fas fa-gamepad"></i>
                    Manage Games
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'configs.php' ? 'active' : ''; ?>" 
                   href="./configs.php">
                    <i class="fas fa-cog"></i>
                    Manage Configs
                </a>
            </li>
        </ul>
    </div>
</nav>