<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<title>الوصول مرفوض</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
<style>body{font-family:'Cairo','Segoe UI',sans-serif;}</style>
</head>
<body class="bg-light d-flex align-items-center justify-content-center" style="height:100vh;">
<div class="text-center p-4">
  <h1 class="display-4 text-danger"><i class="fa-solid fa-lock"></i> 403</h1>
  <p class="lead">ليس لديك صلاحية للوصول إلى هذا المورد.</p>
  <a href="<?= e(base_path('index.php')) ?>" class="btn btn-primary">العودة إلى لوحة التحكم</a>
</div>
</body>
</html>
