
<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// If the user confirms logout, destroy the session
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_logout'])) {

    // Unset all session variables
    $_SESSION = array();

    // Destroy the session cookie if cookies are enabled
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();

        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }

    // Destroy the session
    session_destroy();

    // Redirect to the guest landing page
    header('Location: ../guest/index.php');
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Confirm Logout | TechTalent</title>

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            font-family: "Segoe UI", Arial, sans-serif;
            background:
                radial-gradient(
                    circle at 15% 15%,
                    rgba(59, 130, 246, 0.22),
                    transparent 35%
                ),
                radial-gradient(
                    circle at 90% 85%,
                    rgba(37, 99, 235, 0.20),
                    transparent 35%
                ),
                linear-gradient(135deg, #061126, #0b1d3b 55%, #12376a);
        }

        .logout-card {
            width: 100%;
            max-width: 440px;
            padding: 42px 35px;
            text-align: center;
            background: #ffffff;
            border: 1px solid rgba(255, 255, 255, 0.4);
            border-radius: 24px;
            box-shadow: 0 25px 70px rgba(0, 0, 0, 0.30);
            animation: appear 0.3s ease-out;
        }

        @keyframes appear {
            from {
                opacity: 0;
                transform: translateY(15px) scale(0.98);
            }

            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        .logout-icon {
            width: 82px;
            height: 82px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 24px;
            color: #2563eb;
            background: #eff6ff;
            border: 1px solid #dbeafe;
            border-radius: 24px;
            font-size: 35px;
        }

        .brand {
            margin-bottom: 12px;
            color: #2563eb;
            font-size: 12px;
            font-weight: 800;
            letter-spacing: 2px;
            text-transform: uppercase;
        }

        h1 {
            margin-bottom: 14px;
            color: #07152e;
            font-size: 28px;
            font-weight: 800;
        }

        .description {
            margin-bottom: 30px;
            color: #64748b;
            font-size: 15px;
            line-height: 1.8;
        }

        .logout-actions {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .btn {
            width: 100%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 9px;
            min-height: 49px;
            padding: 13px 20px;
            border: 1px solid transparent;
            border-radius: 11px;
            font-family: inherit;
            font-size: 14px;
            font-weight: 750;
            text-decoration: none;
            cursor: pointer;
            transition:
                background 0.2s ease,
                transform 0.2s ease,
                box-shadow 0.2s ease;
        }

        .btn-logout {
            color: #ffffff;
            background: #2563eb;
            border-color: #2563eb;
            box-shadow: 0 7px 16px rgba(37, 99, 235, 0.20);
        }

        .btn-logout:hover {
            background: #1d4ed8;
            box-shadow: 0 10px 22px rgba(37, 99, 235, 0.27);
            transform: translateY(-2px);
        }

        .btn-cancel {
            color: #334155;
            background: #ffffff;
            border-color: #dbe3ef;
        }

        .btn-cancel:hover {
            color: #07152e;
            background: #f1f5f9;
            transform: translateY(-2px);
        }

        .security-note {
            margin-top: 25px;
            padding-top: 18px;
            color: #94a3b8;
            border-top: 1px solid #edf1f7;
            font-size: 12px;
            line-height: 1.7;
        }

        @media (max-width: 480px) {
            .logout-card {
                padding: 32px 23px;
                border-radius: 20px;
            }

            h1 {
                font-size: 25px;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            *,
            *::before,
            *::after {
                animation: none !important;
                transition: none !important;
            }
        }
    </style>
</head>

<body>

    <main class="logout-card">

        <div class="logout-icon" aria-hidden="true">
            &#10162;
        </div>

        <div class="brand">TechTalent Solutions</div>

        <h1>Ready to leave?</h1>

        <p class="description">
            Are you sure you want to log out of your TechTalent account?
            Your current session will end, and you will need to log in
            again to access your account.
        </p>

        <div class="logout-actions">

            <form method="POST" action="">
                <button
                    type="submit"
                    name="confirm_logout"
                    value="1"
                    class="btn btn-logout"
                >
                    Yes, Log Out
                    <span aria-hidden="true">&#10132;</span>
                </button>
            </form>

            <button
                type="button"
                class="btn btn-cancel"
                onclick="goBack()"
            >
                Cancel
            </button>

        </div>

        <div class="security-note">
            &#128274; Your account security matters to us.
        </div>

    </main>

    <script>
        function goBack() {
            if (window.history.length > 1) {
                window.history.back();
            } else {
                window.location.href = '../guest/index.php';
            }
        }
    </script>

</body>
</html>
