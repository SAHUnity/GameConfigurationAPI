<nav class="navbar navbar-expand navbar-dark bg-dark static-top">
    <a class="navbar-brand me-4" href="./index.php"><?php echo APP_NAME; ?></a>
    
    <button id="sidebarToggle" class="btn btn-link btn-sm rounded-circle me-3">
        <i class="fas fa-bars text-white"></i>
    </button>
    
    <ul class="navbar-nav ms-auto">
        <li class="nav-item dropdown no-arrow">
            <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown">
                <span class="me-2 d-none d-lg-inline text-gray-300 small">Admin</span>
            </a>
            <div class="dropdown-menu dropdown-menu-end">
                <a class="dropdown-item" href="./logout.php">
                    <i class="fas fa-sign-out-alt fa-sm fa-fw mr-2 text-gray-400"></i>
                    Logout
                </a>
            </div>
        </li>
    </ul>
</nav>