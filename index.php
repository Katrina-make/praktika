<?php 
header('Content-Type: text/html; charset=UTF-8');
mb_internal_encoding('UTF-8');

// Подключение к базе данных
$host = 'sql206.infinityfree.com';
$dbname = 'if0_39293979_okonsib';
$username = 'if0_39293979';
$password = 'naaQtGe63XoVz8';
$port = 3306;

try {
    $dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";
    $pdo = new PDO($dsn, $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("SET NAMES 'utf8mb4'");
} catch (PDOException $e) {
    die("Ошибка подключения: " . $e->getMessage());
}

// Создание таблиц при первом запуске
$pdo->exec("
    CREATE TABLE IF NOT EXISTS services (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        description TEXT NOT NULL,
        image VARCHAR(255) NOT NULL
    ) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
    
    CREATE TABLE IF NOT EXISTS projects (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        description TEXT NOT NULL,
        image VARCHAR(255) NOT NULL
    ) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
    
    CREATE TABLE IF NOT EXISTS requests (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        phone VARCHAR(20) NOT NULL,
        email VARCHAR(100) NOT NULL,
        message TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
    
    CREATE TABLE IF NOT EXISTS admins (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) NOT NULL,
        password VARCHAR(255) NOT NULL
    ) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
");

// Проверка существования администратора по умолчанию
$stmt = $pdo->query("SELECT COUNT(*) FROM admins");
if ($stmt->fetchColumn() == 0) {
    $passwordHash = password_hash('admin123', PASSWORD_DEFAULT);
    $pdo->exec("INSERT INTO admins (username, password) VALUES ('admin', '$passwordHash')");
}

// Обработка формы заявки
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_request'])) {
    $name = htmlspecialchars($_POST['name'], ENT_QUOTES, 'UTF-8');
    $phone = htmlspecialchars($_POST['phone'], ENT_QUOTES, 'UTF-8');
    $email = htmlspecialchars($_POST['email'], ENT_QUOTES, 'UTF-8');
    $message = htmlspecialchars($_POST['message'], ENT_QUOTES, 'UTF-8');
    
    $stmt = $pdo->prepare("INSERT INTO requests (name, phone, email, message) VALUES (?, ?, ?, ?)");
    $stmt->execute([$name, $phone, $email, $message]);
    
    $successMessage = "Ваша заявка успешно отправлена! Мы свяжемся с вами в ближайшее время.";
}

// Аутентификация администратора
$isAdmin = false;
session_start();

if (isset($_POST['login'])) {
    $username = $_POST['username'];
    $password = $_POST['password'];
    
    $stmt = $pdo->prepare("SELECT * FROM admins WHERE username = ?");
    $stmt->execute([$username]);
    $admin = $stmt->fetch();
    
    if ($admin && password_verify($password, $admin['password'])) {
        $_SESSION['admin'] = true;
        $isAdmin = true;
    } else {
        $loginError = "Неверное имя пользователя или пароль";
    }
}

if (isset($_SESSION['admin']) && $_SESSION['admin']) {
    $isAdmin = true;
}

if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: " . strtok($_SERVER['REQUEST_URI'], '?'));
    exit;
}

// Обработка действий в админ-панели
$editServiceId = null;
$editProjectId = null;

// Добавление/обновление услуги
if ($isAdmin && isset($_POST['save_service'])) {
    $title = htmlspecialchars($_POST['service_title'], ENT_QUOTES, 'UTF-8');
    $description = htmlspecialchars($_POST['service_description'], ENT_QUOTES, 'UTF-8');
    $image = htmlspecialchars($_POST['service_image'], ENT_QUOTES, 'UTF-8');
    
    if (!empty($_POST['service_id'])) {
        // Обновление существующей услуги
        $serviceId = (int)$_POST['service_id'];
        $stmt = $pdo->prepare("UPDATE services SET title = ?, description = ?, image = ? WHERE id = ?");
        $stmt->execute([$title, $description, $image, $serviceId]);
        $successMessage = "Услуга успешно обновлена!";
    } else {
        // Добавление новой услуги
        $stmt = $pdo->prepare("INSERT INTO services (title, description, image) VALUES (?, ?, ?)");
        $stmt->execute([$title, $description, $image]);
        $successMessage = "Услуга успешно добавлена!";
    }
}

// Добавление/обновление проекта
if ($isAdmin && isset($_POST['save_project'])) {
    $title = htmlspecialchars($_POST['project_title'], ENT_QUOTES, 'UTF-8');
    $description = htmlspecialchars($_POST['project_description'], ENT_QUOTES, 'UTF-8');
    $image = htmlspecialchars($_POST['project_image'], ENT_QUOTES, 'UTF-8');
    
    if (!empty($_POST['project_id'])) {
        // Обновление существующего проекта
        $projectId = (int)$_POST['project_id'];
        $stmt = $pdo->prepare("UPDATE projects SET title = ?, description = ?, image = ? WHERE id = ?");
        $stmt->execute([$title, $description, $image, $projectId]);
        $successMessage = "Проект успешно обновлен!";
    } else {
        // Добавление нового проекта
        $stmt = $pdo->prepare("INSERT INTO projects (title, description, image) VALUES (?, ?, ?)");
        $stmt->execute([$title, $description, $image]);
        $successMessage = "Проект успешно добавлен!";
    }
}

// Удаление услуги
if ($isAdmin && isset($_GET['delete_service'])) {
    $serviceId = (int)$_GET['delete_service'];
    $stmt = $pdo->prepare("DELETE FROM services WHERE id = ?");
    $stmt->execute([$serviceId]);
    $successMessage = "Услуга успешно удалена!";
}

// Удаление проекта
if ($isAdmin && isset($_GET['delete_project'])) {
    $projectId = (int)$_GET['delete_project'];
    $stmt = $pdo->prepare("DELETE FROM projects WHERE id = ?");
    $stmt->execute([$projectId]);
    $successMessage = "Проект успешно удален!";
}

// Редактирование услуги
if ($isAdmin && isset($_GET['edit_service'])) {
    $editServiceId = (int)$_GET['edit_service'];
    $stmt = $pdo->prepare("SELECT * FROM services WHERE id = ?");
    $stmt->execute([$editServiceId]);
    $editService = $stmt->fetch();
}

// Редактирование проекта
if ($isAdmin && isset($_GET['edit_project'])) {
    $editProjectId = (int)$_GET['edit_project'];
    $stmt = $pdo->prepare("SELECT * FROM projects WHERE id = ?");
    $stmt->execute([$editProjectId]);
    $editProject = $stmt->fetch();
}

// Получение данных для сайта
$services = $pdo->query("SELECT * FROM services")->fetchAll(PDO::FETCH_ASSOC);
$projects = $pdo->query("SELECT * FROM projects")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="style/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="style/favicon-16x16.png">
    <link rel="manifest" href="/site.webmanifest">
    <title>Консиб | Пластиковые окна</title>
     <style>
        /* Глобальные стили */
        :root {
            --primary: #2c6e3c;
            --secondary: #d4a017;
            --light: #f8f9fa;
            --dark: #343a40;
            --gray: #6c757d;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Montserrat', sans-serif;
        }
        
        body {
            line-height: 1.6;
            color: var(--dark);
            overflow-x: hidden;
        }
        
        .container {
            width: 100%;
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 15px;
        }
        
        .btn {
            display: inline-block;
            background: var(--secondary);
            color: white;
            padding: 12px 30px;
            border-radius: 4px;
            text-decoration: none;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
        }
        
        .btn:hover {
            background: #b8860b;
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        section {
            padding: 80px 0;
        }
        
        .section-title {
            text-align: center;
            margin-bottom: 50px;
            position: relative;
        }
        
        .section-title h2 {
            font-size: 2.5rem;
            color: var(--primary);
            display: inline-block;
            margin-bottom: 15px;
        }
        
        .section-title h2::after {
            content: '';
            position: absolute;
            width: 80px;
            height: 3px;
            background: var(--secondary);
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
        }
        
        /* Шапка */
        header {
            background-color: white;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            position: fixed;
            width: 100%;
            top: 0;
            z-index: 1000;
        }
        
        .header-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 0;
        }
        
        .logo {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--primary);
            text-decoration: none;
        }
        
        .logo span {
            color: var(--secondary);
        }
        
        nav ul {
            display: flex;
            list-style: none;
        }
        
        nav ul li {
            margin-left: 30px;
        }
        
        nav ul li a {
            text-decoration: none;
            color: var(--dark);
            font-weight: 500;
            transition: color 0.3s ease;
        }
        
        nav ul li a:hover {
            color: var(--primary);
        }
        
        .contact-phone {
            display: flex;
            align-items: center;
        }
        
        .contact-phone a {
            font-weight: 600;
            color: var(--dark);
            text-decoration: none;
            margin-left: 10px;
        }
        
        .mobile-menu-btn {
            display: none;
            font-size: 1.5rem;
            cursor: pointer;
        }
        
        /* Герой-секция */
        .hero {
            background: linear-gradient(rgba(0,0,0,0.7), rgba(0,0,0,0.7)), url('https://images.unsplash.com/photo-1504307651254-35680f356dfd?ixlib=rb-4.0.3&auto=format&fit=crop&w=1950&q=80');
            background-size: cover;
            background-position: center;
            height: 100vh;
            display: flex;
            align-items: center;
            color: white;
            text-align: center;
            padding-top: 80px;
        }
        
        .hero-content {
            max-width: 800px;
            margin: 0 auto;
        }
        
        .hero h1 {
            font-size: 3.5rem;
            margin-bottom: 20px;
            text-transform: uppercase;
        }
        
        .hero p {
            font-size: 1.2rem;
            margin-bottom: 30px;
        }
        
        /* Услуги */
        .services {
            background-color: var(--light);
        }
        
        .services-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
        }
        
        .service-card {
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            transition: transform 0.3s ease;
        }
        
        .service-card:hover {
            transform: translateY(-10px);
        }
        
        .service-img {
         height: 200px;
        background-repeat: no-repeat;
        margin-left: 20px;
        margin-top: 30px;
        border-radius: 20px
        }
        
        .service-content {
            padding: 25px;
        }
        
        .service-content h3 {
            color: var(--primary);
            margin-bottom: 15px;
        }
        
        /* О компании */
        .about {
            display: flex;
            align-items: center;
            gap: 50px;
        }
        
        .about-img {
            flex: 1;
            height: 500px;
            background: url('https://images.unsplash.com/photo-1513694203232-719a280e022f?ixlib=rb-4.0.3&auto=format&fit=crop&w=1949&q=80');
            background-size: cover;
            background-position: center;
            border-radius: 8px;
        }
        
        .about-content {
            flex: 1;
        }
        
        .about-content h3 {
            color: var(--primary);
            font-size: 2rem;
            margin-bottom: 20px;
        }
        
        .about-content p {
            margin-bottom: 20px;
        }
        
        /* Проекты */
        .projects-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 30px;
        }
        
        .project-card {
            position: relative;
            height: 300px;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .project-img {
            width: 100%;
            height: 100%;
            background-size: cover;
            background-position: center;
            transition: transform 0.5s ease;
        }
        
        .project-card:hover .project-img {
            transform: scale(1.1);
        }
        
        .project-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(44, 110, 60, 0.85);
            color: white;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            opacity: 0;
            transition: opacity 0.3s ease;
            padding: 20px;
            text-align: center;
        }
        
        .project-card:hover .project-overlay {
            opacity: 1;
        }
        
        /* Преимущества */
        .advantages {
            background-color: var(--primary);
            color: white;
        }
        
        .advantages .section-title h2 {
            color: white;
        }
        
        .advantages-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 30px;
        }
        
        .advantage-card {
            text-align: center;
            padding: 30px;
            background: rgba(255,255,255,0.1);
            border-radius: 8px;
        }
        
        .advantage-card i {
            font-size: 3rem;
            color: var(--secondary);
            margin-bottom: 20px;
        }
        
        .advantage-card h3 {
            margin-bottom: 15px;
        }
        
        /* Контакты */
        .contact-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 50px;
        }
        
        .contact-info h3 {
            color: var(--primary);
            margin-bottom: 20px;
        }
        
        .contact-item {
            display: flex;
            margin-bottom: 20px;
        }
        
        .contact-item i {
            color: var(--secondary);
            font-size: 1.2rem;
            margin-right: 15px;
            min-width: 30px;
        }
        
        .contact-form .form-group {
            margin-bottom: 20px;
        }
        
        .contact-form input,
        .contact-form textarea {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 1rem;
        }
        
        .contact-form textarea {
            height: 150px;
            resize: vertical;
        }
        
        /* Футер */
        footer {
            background-color: var(--dark);
            color: white;
            padding: 50px 0 20px;
        }
        
        .footer-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 30px;
            margin-bottom: 40px;
        }
        
        .footer-col h4 {
            color: var(--secondary);
            margin-bottom: 20px;
            position: relative;
            padding-bottom: 10px;
        }
        
        .footer-col h4::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 50px;
            height: 2px;
            background: var(--secondary);
        }
        
        .footer-col ul {
            list-style: none;
        }
        
        .footer-col ul li {
            margin-bottom: 10px;
        }
        
        .footer-col ul li a {
            color: #bbb;
            text-decoration: none;
            transition: color 0.3s ease;
        }
        
        .footer-col ul li a:hover {
            color: var(--secondary);
        }
        
        .social-links {
            display: flex;
            gap: 15px;
            margin-top: 20px;
        }
        
        .social-links a {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            background: rgba(255,255,255,0.1);
            border-radius: 50%;
            color: white;
            transition: all 0.3s ease;
        }
        
        .social-links a:hover {
            background: var(--secondary);
            transform: translateY(-3px);
        }
        
        .copyright {
            text-align: center;
            padding-top: 20px;
            border-top: 1px solid rgba(255,255,255,0.1);
            color: #bbb;
            font-size: 0.9rem;
        }
        
        /* Адаптивность */
        @media (max-width: 992px) {
            .about {
                flex-direction: column;
            }
            
            .about-img {
                width: 100%;
            }
            
            .hero h1 {
                font-size: 2.8rem;
            }
        }
        
        @media (max-width: 768px) {
            .mobile-menu-btn {
                display: block;
            }
            
            nav ul {
                position: fixed;
                top: 70px;
                left: -100%;
                background: white;
                width: 100%;
                flex-direction: column;
                box-shadow: 0 10px 10px rgba(0,0,0,0.1);
                transition: all 0.4s ease;
                padding: 20px 0;
            }
            
            nav ul.active {
                left: 0;
            }
            
            nav ul li {
                margin: 0;
                text-align: center;
            }
            
            nav ul li a {
                display: block;
                padding: 15px 0;
                border-bottom: 1px solid #eee;
            }
            
            .hero h1 {
                font-size: 2.2rem;
            }
            
            section {
                padding: 60px 0;
            }
        }
        
        /* Стили для админ-панели */
        .admin-panel {
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            max-width: 1200px;
            margin: 40px auto;
        }
        
        .admin-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #ddd;
        }
        
        .admin-section {
            margin-bottom: 30px;
        }
        
        .admin-section h3 {
            margin-bottom: 15px;
            color: var(--primary);
        }
        
        .admin-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        
        .admin-table th, .admin-table td {
            padding: 12px 15px;
            text-align: left;
            border: 1px solid #ddd;
        }
        
        .admin-table th {
            background-color: var(--primary);
            color: white;
        }
        
        .admin-table tr:nth-child(even) {
            background-color: #f2f2f2;
        }
        
        .admin-form {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        .admin-form .form-group {
            margin-bottom: 15px;
        }
        
        .admin-form label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
        }
        
        .admin-form input, .admin-form textarea, .admin-form select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        
        .admin-form textarea {
            height: 100px;
        }
        
        .form-full-width {
            grid-column: span 2;
        }
        
        .login-container {
            max-width: 400px;
            margin: 100px auto;
            padding: 30px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }
        
        .login-container h2 {
            text-align: center;
            margin-bottom: 20px;
            color: var(--primary);
        }
        
        .login-form .form-group {
            margin-bottom: 20px;
        }
        
        .login-form label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
        }
        
        .login-form input {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        
        .error-message {
            color: #dc3545;
            margin-bottom: 15px;
        }
        
        .success-message {
            color: #28a745;
            margin-bottom: 15px;
            background: #d4edda;
            padding: 10px;
            border-radius: 4px;
        }
        
        .admin-actions {
            display: flex;
            gap: 10px;
        }
        
        .btn-edit {
            background: #17a2b8;
        }
        
        .btn-delete {
            background: #dc3545;
        }
        
        .btn-edit:hover {
            background: #138496;
        }
        
        .btn-delete:hover {
            background: #bd2130;
        }
    </style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <?php if (isset($_GET['admin']) && !$isAdmin): ?> 
        <!-- Форма входа для администратора -->
        <div class="login-container">
            <h2>Вход в админ-панель</h2>
            <?php if (isset($loginError)): ?>
                <div class="error-message"><?php echo $loginError; ?></div>
            <?php endif; ?>
            <form class="login-form" method="POST">
                <div class="form-group">
                    <label for="username">Имя пользователя:</label>
                    <input type="text" id="username" name="username" required>
                </div>
                <div class="form-group">
                    <label for="password">Пароль:</label>
                    <input type="password" id="password" name="password" required>
                </div>
                <button type="submit" name="login" class="btn">Войти</button>
            </form>
        </div>
    <?php elseif ($isAdmin && isset($_GET['admin'])): ?>
        <!-- Админ-панель -->
        <div class="admin-panel">
            <div class="admin-header">
                <h2>Админ-панель Консиб</h2>
                <a href="?logout" class="btn">Выйти</a>
            </div>
            
            <?php if (isset($successMessage)): ?>
                <div class="success-message"><?php echo $successMessage; ?></div>
            <?php endif; ?>
            
            <!-- Управление услугами -->
            <div class="admin-section">
                <h3>Управление услугами</h3>
                
                <!-- Форма добавления/редактирования услуги -->
                <form method="POST" class="admin-form">
                    <?php if ($editServiceId): ?>
                        <input type="hidden" name="service_id" value="<?php echo $editServiceId; ?>">
                    <?php endif; ?>
                    
                    <div class="form-group form-full-width">
                        <label for="service_title">Название услуги:</label>
                        <input type="text" id="service_title" name="service_title" 
                               value="<?php echo $editService['title'] ?? ''; ?>" required>
                    </div>
                    <div class="form-group form-full-width">
                        <label for="service_description">Описание:</label>
                        <textarea id="service_description" name="service_description" required><?php 
                            echo $editService['description'] ?? ''; 
                        ?></textarea>
                    </div>
                    <div class="form-group">
                        <label for="service_image">URL изображения:</label>
                        <input type="text" id="service_image" name="service_image" 
                               value="<?php echo $editService['image'] ?? ''; ?>" required>
                    </div>
                    <div class="form-group form-full-width">
                        <button type="submit" name="save_service" class="btn">
                            <?php echo $editServiceId ? 'Обновить услугу' : 'Добавить услугу'; ?>
                        </button>
                        <?php if ($editServiceId): ?>
                            <a href="?admin" class="btn" style="background: #6c757d;">Отмена</a>
                        <?php endif; ?>
                    </div>
                </form>
                
                <!-- Таблица услуг -->
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Название</th>
                            <th>Описание</th>
                            <th>Изображение</th>
                            <th>Действия</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($services as $service): ?>
                        <tr>
                            <td><?php echo $service['id']; ?></td>
                            <td><?php echo htmlspecialchars($service['title'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php 
                                $desc = htmlspecialchars($service['description'], ENT_QUOTES, 'UTF-8');
                                echo mb_strlen($desc) > 100 ? mb_substr($desc, 0, 100) . '...' : $desc; 
                            ?></td>
                            <td><a href="<?php echo htmlspecialchars($service['image'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank">Просмотр</a></td>
                            <td class="admin-actions">
                                <a href="?admin&edit_service=<?php echo $service['id']; ?>" class="btn btn-edit">Редактировать</a>
                                <a href="?admin&delete_service=<?php echo $service['id']; ?>" 
                                   class="btn btn-delete" 
                                   onclick="return confirm('Вы уверены, что хотите удалить эту услугу?')">Удалить</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Управление проектами -->
            <div class="admin-section">
                <h3>Управление проектами</h3>
                
                <!-- Форма добавления/редактирования проекта -->
                <form method="POST" class="admin-form">
                    <?php if ($editProjectId): ?>
                        <input type="hidden" name="project_id" value="<?php echo $editProjectId; ?>">
                    <?php endif; ?>
                    
                    <div class="form-group form-full-width">
                        <label for="project_title">Название проекта:</label>
                        <input type="text" id="project_title" name="project_title" 
                               value="<?php echo $editProject['title'] ?? ''; ?>" required>
                    </div>
                    <div class="form-group form-full-width">
                        <label for="project_description">Описание:</label>
                        <textarea id="project_description" name="project_description" required><?php 
                            echo $editProject['description'] ?? ''; 
                        ?></textarea>
                    </div>
                    <div class="form-group">
                        <label for="project_image">URL изображения:</label>
                        <input type="text" id="project_image" name="project_image" 
                               value="<?php echo $editProject['image'] ?? ''; ?>" required>
                    </div>
                    <div class="form-group form-full-width">
                        <button type="submit" name="save_project" class="btn">
                            <?php echo $editProjectId ? 'Обновить проект' : 'Добавить проект'; ?>
                        </button>
                        <?php if ($editProjectId): ?>
                            <a href="?admin" class="btn" style="background: #6c757d;">Отмена</a>
                        <?php endif; ?>
                    </div>
                </form>
                
                <!-- Таблица проектов -->
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Название</th>
                            <th>Описание</th>
                            <th>Изображение</th>
                            <th>Действия</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($projects as $project): ?>
                        <tr>
                            <td><?php echo $project['id']; ?></td>
                            <td><?php echo htmlspecialchars($project['title'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php 
                                $desc = htmlspecialchars($project['description'], ENT_QUOTES, 'UTF-8');
                                echo mb_strlen($desc) > 100 ? mb_substr($desc, 0, 100) . '...' : $desc; 
                            ?></td>
                            <td><a href="<?php echo htmlspecialchars($project['image'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank">Просмотр</a></td>
                            <td class="admin-actions">
                                <a href="?admin&edit_project=<?php echo $project['id']; ?>" class="btn btn-edit">Редактировать</a>
                                <a href="?admin&delete_project=<?php echo $project['id']; ?>" 
                                   class="btn btn-delete" 
                                   onclick="return confirm('Вы уверены, что хотите удалить этот проект?')">Удалить</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Управление заявками -->
            <div class="admin-section">
                <h3>Заявки от клиентов</h3>
                
                <!-- Таблица заявок -->
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Имя</th>
                            <th>Телефон</th>
                            <th>Email</th>
                            <th>Дата</th>
                            <th>Сообщение</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $requests = $pdo->query("SELECT * FROM requests ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
                        foreach ($requests as $request): 
                        ?>
                        <tr>
                            <td><?php echo $request['id']; ?></td>
                            <td><?php echo htmlspecialchars($request['name'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars($request['phone'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars($request['email'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo date('d.m.Y H:i', strtotime($request['created_at'])); ?></td>
                            <td><?php echo htmlspecialchars($request['message'], ENT_QUOTES, 'UTF-8'); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php else: ?>
        <!-- Основной сайт -->
        <!-- Шапка сайта -->
        <header>
            <div class="container header-container">
                <a href="#" class="logo">Кон<span>Сиб</span></a>
                
                <div class="mobile-menu-btn">
                    <i class="fas fa-bars"></i>
                </div>
                
                <nav>
                    <ul>
                        <li><a href="#home">Главная</a></li>
                        <li><a href="#services">Услуги</a></li>
                        <li><a href="#about">О компании</a></li>
                        <li><a href="#projects">Проекты</a></li>
                        <li><a href="#advantages">Преимущества</a></li>
                        <li><a href="#contact">Контакты</a></li>
                        <li><a href="?admin">Админ</a></li>
                    </ul>
                </nav>
                
                <div class="contact-phone">
                    <i class="fas fa-phone"></i>
                    <a href="tel:+78001234567">8 (800) 123-45-67</a>
                </div>
            </div>
        </header>

        <!-- Герой-секция -->
        <section class="hero" id="home">
            <div class="container hero-content">
                <h1>Профессиональная установка окон</h1>
                <p>НЕСТАНДАРТНЫЕ ОКНА | ДВЕРИ | ГАРАЖНЫЕ ВОРОТА </p>
                <a href="#contact" class="btn">Заказать консультацию</a>
            </div>
        </section>

        <!-- Услуги -->
        
        <section class="services" id="services">
            <div class="container">
                <div class="section-title">
                    <h2>Наши услуги</h2>
                </div>
                
                <div class="services-grid">
                    <?php foreach ($services as $service): ?>
                    <div class="service-card">
                        <div class="service-img" style="background-image: url('<?php echo $service['image']; ?>');"></div>
                        <div class="service-content">
                            <h3><?php echo $service['title']; ?></h3>
                            <p><?php echo $service['description']; ?></p>
                            <a href="#" class="btn" style="margin-top: 15px;">Подробнее</a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>

        <!-- О компании -->
        <section class="about" id="about">
            <div class="container">
                <div class="about-img"></div>
                <div class="about-content">
                    <h3>О нашей компании</h3>
                    <p>Сегодня ГК "Консиб" - крупная, успешная, динамично развивающаяся организация.</p>
                    <p>Успех компании во многом зависит от усилий всех сотрудников, направленных на достижение общей цели.</p>
                    <p>Компания стремится быть лидером по эффективности ведения бизнеса, по темпам роста, качеству продукции и применяемым технологиям.</p>
                    
                    <div style="display: flex; gap: 15px; margin-top: 30px;">
                        <div style="text-align: center;">
                            <h3 style="color: var(--secondary); font-size: 2.5rem;">30+</h3>
                            <p>Лет опыта</p>
                        </div>
                        <div style="text-align: center;">
                            <h3 style="color: var(--secondary); font-size: 2.5rem;">2000+</h3>
                            <p>Завершенных проектов</p>
                        </div>
                        <div style="text-align: center;">
                            <h3 style="color: var(--secondary); font-size: 2.5rem;">500+</h3>
                            <p>Профессионалов</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Проекты -->
        <section class="projects" id="projects">
            <div class="container">
                <div class="section-title">
                    <h2>Наши проекты</h2>
                </div>
                
                <div class="projects-grid">
                    <?php foreach ($projects as $project): ?>
                    <div class="project-card">
                        <div class="project-img" style="background-image: url('<?php echo $project['image']; ?>');"></div>
                        <div class="project-overlay">
                            <h3><?php echo $project['title']; ?></h3>
                            <p><?php echo $project['description']; ?></p>
                            <a href="#" class="btn" style="margin-top: 15px;">Подробнее</a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>

        <!-- Преимущества -->
        <section class="advantages" id="advantages">
            <div class="container">
                <div class="section-title">
                    <h2>Наши преимущества</h2>
                </div>
                
                <div class="advantages-grid">
                    <div class="advantage-card">
                        <i class="fas fa-medal"></i>
                        <h3>Качество</h3>
                        <p>Использование качественных материалов. В производстве применяются профили VEKA и WHS, которые отличаются долговечностью и хорошей тепло- и звукоизоляцией.</p>
                    </div>
                    
                    <div class="advantage-card">
                        <i class="fas fa-calendar-check"></i>
                        <h3>Изготовление нестандартных конструкций.</h3>
                        <p>Компания предлагает окна любых конструкций: арочные, круглые, треугольные, овальные, трапециевидные, многоугольные, комбинированные, портальные, штульповые.</p>
                    </div>
                    
                    <div class="advantage-card">
                        <i class="fas fa-ruble-sign"></i>
                        <h3>Отлаженная логистика</h3>
                        <p> Исключает путаницу и неполную комплектацию при отгрузке.</p>
                    </div>
                    
                    <div class="advantage-card">
                        <i class="fas fa-hard-hat"></i>
                        <h3>Опытная команда</h3>
                        <p>Наши специалисты имеют большой опыт в установке и ремонте окон.</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Контакты -->
        <section class="contact" id="contact">
            <div class="container">
                <div class="section-title">
                    <h2>Контакты</h2>
                </div>
                
                <?php if (isset($successMessage)): ?>
                    <div class="success-message"><?php echo $successMessage; ?></div>
                <?php endif; ?>
                
                <div class="contact-grid">
                    <div class="contact-info">
                        <h3>Наши контакты</h3>
                        
                        <div class="contact-item">
                            <i class="fas fa-map-marker-alt"></i>
                            <div>
                                <h4>Адрес:</h4>
                                <p>г. Барнаул, ул. Чеглецова , д. 37, офис 8</p>
                            </div>
                        </div>
                        
                        <div class="contact-item">
                            <i class="fas fa-phone"></i>
                            <div>
                                <h4>Телефон:</h4>
                                <p><a href="tel:+78001234567">8 (800) 123-45-67</a></p>
                            </div>
                        </div>
                        
                        <div class="contact-item">
                            <i class="fas fa-envelope"></i>
                            <div>
                                <h4>Email:</h4>
                                <p><a href="mailto:info@stroygrad.ru">info@konsib.ru</a></p>
                            </div>
                        </div>
                        
                        <div class="contact-item">
                            <i class="fas fa-clock"></i>
                            <div>
                                <h4>Режим работы:</h4>
                                <p>Пн-Пт: 9:00 - 18:00</p>
                            </div>
                        </div>
                        
                        <div style="margin-top: 30px;">
                            <iframe src="https://yandex.ru/map-widget/v1/?um=constructor%3Ae4b1c8d3b2b2b2b2b2b2b2b2b2b2b2b2b2&amp;source=constructor" width="100%" height="300" frameborder="0"></iframe>
                        </div>
                    </div>
                    
                    <div class="contact-form">
                        <h3>Оставьте заявку</h3>
                        <p>Наш менеджер свяжется с вами в течение 15 минут</p>
                        
                        <form method="POST">
                            <div class="form-group">
                                <input type="text" name="name" placeholder="Ваше имя" required>
                            </div>
                            
                            <div class="form-group">
                                <input type="tel" name="phone" placeholder="Ваш телефон" required>
                            </div>
                            
                            <div class="form-group">
                                <input type="email" name="email" placeholder="Ваш email">
                            </div>
                            
                            <div class="form-group">
                                <textarea name="message" placeholder="Сообщение"></textarea>
                            </div>
                            
                            <button type="submit" name="submit_request" class="btn">Отправить</button>
                        </form>
                    </div>
                </div>
            </div>
        </section>

        <!-- Футер -->
        <footer>
            <div class="container">
                <div class="footer-grid">
                    <div class="footer-col">
                        <a href="#" class="logo" style="color: white; font-size: 1.8rem; margin-bottom: 15px; display: block;">Кон<span>Сиб</span></a>
                        <p>Качественная установка окон. Профессиональный подход и соблюдение сроков.</p>
                        
                        <div class="social-links">
                            <a href="#"><i class="fab fa-vk"></i></a>
                            <a href="#"><i class="fab fa-telegram"></i></a>
                            <a href="#"><i class="fab fa-instagram"></i></a>
                            <a href="#"><i class="fab fa-youtube"></i></a>
                        </div>
                    </div>
                    
                    <div class="footer-col">
                        <h4>Навигация</h4>
                        <ul>
                            <li><a href="#home">Главная</a></li>
                            <li><a href="#services">Услуги</a></li>
                            <li><a href="#about">О компании</a></li>
                            <li><a href="#projects">Проекты</a></li>
                            <li><a href="#advantages">Преимущества</a></li>
                            <li><a href="#contact">Контакты</a></li>
                        </ul>
                    </div>
                    
                    <div class="footer-col">
                        <h4>Услуги</h4>
                        <ul>
                            <li><a href="#">Установка нестандартных окон.</a></li>
                            <li><a href="#">Герметизация и утепление монтажных швов.</a></li>
                            <li><a href="#">Остекление веранд.</a></li>
                            <li><a href="#">Регулировка фурнитуры.</a></li>
                            <li><a href="#">Изготовление стеклопакетов любых размеров и типов.</a></li>
                        </ul>
                    </div>
                    
                    <div class="footer-col">
                        <h4>Контакты</h4>
                        <ul>
                            <li><i class="fas fa-map-marker-alt"></i> г. Барнаул, ул. Чеглецова, 37</li>
                            <li><i class="fas fa-phone"></i> 8 (800) 123-45-67</li>
                            <li><i class="fas fa-envelope"></i> info@konsib.ru</li>
                            <li><i class="fas fa-clock"></i> Пн-Пт: 9:00 - 18:00</li>
                        </ul>
                    </div>
                </div>
                
                <div class="copyright">
                    <p>&copy; 2025 "КонСиб". Все права защищены.</p>
                </div>
            </div>
        </footer>
    <?php endif; ?>


       <script>
        // Мобильное меню
        const mobileMenuBtn = document.querySelector('.mobile-menu-btn');
        const navMenu = document.querySelector('nav ul');
        
        mobileMenuBtn.addEventListener('click', () => {
            navMenu.classList.toggle('active');
        });
        
        // Плавная прокрутка
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                
                if (navMenu) navMenu.classList.remove('active');
                
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth'
                    });
                }
            });
        });
        
        // Фиксированная шапка при прокрутке
        window.addEventListener('scroll', () => {
            const header = document.querySelector('header');
            if (header) {
                header.classList.toggle('sticky', window.scrollY > 0);
            }
        });
        
        // Закрытие меню при клике на пункт
        if (navMenu) {
            navMenu.querySelectorAll('a').forEach(link => {
                link.addEventListener('click', () => {
                    navMenu.classList.remove('active');
                });
            });
        }
    </script>
</body>
</html>