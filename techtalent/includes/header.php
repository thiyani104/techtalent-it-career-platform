<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title><?php echo htmlspecialchars($page_title ?? 'TechTalent Solutions', ENT_QUOTES, 'UTF-8'); ?></title>
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Bootstrap 5.3 CDN CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="icon" type="image/png" href="../assets/images/logo.png">
    <!-- Bootstrap Icons CDN -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    
    <!-- Custom Stylesheet -->
    <link rel="stylesheet" href="../assets/css/style.css">
    
    <!-- Embedded Tech Custom Styling & Modern Aesthetics -->
    <style>
        :root {
            --primary-navy: #0f2137;
            --primary-navy-dark: #0a1728;
            --accent-blue: #007bff;
            --accent-blue-hover: #0056b3;
            --bg-light: #f8f9fa;
            --text-dark: #1e293b;
            --text-muted: #64748b;
        }

        body {
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            color: var(--text-dark);
            background-color: #fcfcfd;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }

        /* Navbar Custom Styles */
        .navbar-custom {
            background-color: var(--primary-navy);
            box-shadow: 0 4px 20px rgba(15, 33, 55, 0.18);
            padding: 0.85rem 1rem;
        }

        .navbar-custom .navbar-brand {
            font-weight: 800;
            font-size: 1.45rem;
            color: #ffffff;
            letter-spacing: -0.5px;
        }

        .navbar-custom .navbar-brand span {
            color: var(--accent-blue);
        }

        .navbar-custom .nav-link {
            color: rgba(255, 255, 255, 0.85);
            font-weight: 500;
            font-size: 0.95rem;
            margin: 0 0.25rem;
            transition: all 0.25s ease;
        }

        .navbar-custom .nav-link:hover,
        .navbar-custom .nav-link.active {
            color: #ffffff;
            transform: translateY(-1px);
        }

        .btn-accent {
            background-color: var(--accent-blue);
            color: #ffffff;
            font-weight: 600;
            border-radius: 8px;
            border: none;
            padding: 0.55rem 1.35rem;
            transition: all 0.3s ease;
        }

        .btn-accent:hover {
            background-color: var(--accent-blue-hover);
            color: #ffffff;
            box-shadow: 0 4px 14px rgba(0, 123, 255, 0.4);
            transform: translateY(-2px);
        }

        .btn-outline-navy {
            border: 2px solid rgba(255, 255, 255, 0.35);
            color: #ffffff;
            font-weight: 600;
            border-radius: 8px;
            padding: 0.5rem 1.25rem;
            transition: all 0.3s ease;
        }

        .btn-outline-navy:hover {
            background-color: rgba(255, 255, 255, 0.15);
            color: #ffffff;
            border-color: #ffffff;
        }

        /* Hero Section */
        .hero-section {
            background: linear-gradient(135deg, var(--primary-navy) 0%, #1a365d 100%);
            color: #ffffff;
            padding: 5rem 0 6rem;
            position: relative;
            overflow: hidden;
        }

        .hero-section::before {
            content: '';
            position: absolute;
            top: -20%;
            right: -10%;
            width: 500px;
            height: 500px;
            background: radial-gradient(circle, rgba(0, 123, 255, 0.18) 0%, rgba(0,0,0,0) 70%);
            border-radius: 50%;
            pointer-events: none;
        }

        .hero-title {
            font-weight: 800;
            font-size: 3rem;
            line-height: 1.2;
            letter-spacing: -1px;
        }

        .hero-subtitle {
            font-size: 1.15rem;
            color: rgba(255, 255, 255, 0.85);
            font-weight: 300;
        }

        .search-card {
            background: #ffffff;
            border-radius: 14px;
            padding: 1.25rem;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.25);
        }

        .search-card .form-control,
        .search-card .form-select {
            border: 1px solid #e2e8f0;
            padding: 0.75rem 1rem;
            font-size: 0.95rem;
            border-radius: 8px;
        }

        .search-card .form-control:focus,
        .search-card .form-select:focus {
            border-color: var(--accent-blue);
            box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.15);
        }

        /* Category Card */
        .category-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            padding: 1.75rem 1.5rem;
            transition: all 0.3s cubic-bezier(0.165, 0.84, 0.44, 1);
            height: 100%;
            text-decoration: none;
            color: inherit;
            display: block;
        }

        .category-card:hover {
            transform: translateY(-6px);
            box-shadow: 0 14px 28px rgba(15, 33, 55, 0.1);
            border-color: rgba(0, 123, 255, 0.35);
        }

        .category-icon-wrapper {
            width: 58px;
            height: 58px;
            border-radius: 12px;
            background: rgba(0, 123, 255, 0.08);
            color: var(--accent-blue);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.6rem;
            margin-bottom: 1.25rem;
            transition: all 0.3s ease;
        }

        .category-card:hover .category-icon-wrapper {
            background: var(--accent-blue);
            color: #ffffff;
        }

        .category-title {
            font-weight: 700;
            font-size: 1.1rem;
            color: var(--primary-navy);
            margin-bottom: 0.5rem;
        }

        .category-desc {
            font-size: 0.875rem;
            color: var(--text-muted);
            margin-bottom: 0;
            line-height: 1.55;
        }

        /* CTA Banner */
        .cta-banner {
            background: linear-gradient(135deg, #102a45 0%, var(--primary-navy) 100%);
            border-radius: 16px;
            padding: 3.5rem 2.5rem;
            color: #ffffff;
            box-shadow: 0 15px 30px rgba(15, 33, 55, 0.15);
        }

        /* Footer Custom Styles */
        .footer-custom {
            background-color: var(--primary-navy-dark);
            color: rgba(255, 255, 255, 0.75);
            font-size: 0.925rem;
            margin-top: auto;
        }

        .footer-custom h5 {
            color: #ffffff;
            font-weight: 700;
            font-size: 1.1rem;
            margin-bottom: 1.25rem;
            position: relative;
        }

        .footer-custom h5::after {
            content: '';
            display: block;
            width: 30px;
            height: 2px;
            background-color: var(--accent-blue);
            margin-top: 0.5rem;
        }

        .footer-custom a {
            color: rgba(255, 255, 255, 0.75);
            text-decoration: none;
            transition: color 0.25s ease, padding-left 0.25s ease;
        }

        .footer-custom a:hover {
            color: var(--accent-blue);
            padding-left: 4px;
        }

        .footer-bottom {
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            padding: 1.5rem 0;
        }
    </style>
    <!-- Design-only overrides; application logic remains unchanged. -->
    <link rel="stylesheet" href="../assets/css/techtalent-design.css">
    <?php foreach ($extra_css ?? [] as $css_href): ?>
    <link rel="stylesheet" href="<?php echo htmlspecialchars($css_href, ENT_QUOTES, 'UTF-8'); ?>">
    <?php endforeach; ?>
</head>
<body>
