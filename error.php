<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CHECKON - Ошибка</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <?php include 'navbar.php'; ?>
    
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-6 text-center">
                <div class="card">
                    <div class="card-body">
                        <i class="fas fa-exclamation-triangle" style="font-size: 64px; color: #dc3545;"></i>
                        <h2 class="mt-3">Ошибка 404</h2>
                        <p class="text-muted">Запрашиваемая страница не найдена</p>
                        <a href="index.php" class="btn btn-primary">
                            <i class="fas fa-home"></i> Вернуться на главную
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>