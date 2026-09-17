<?php
// ============================================
// coordenador_pedagogico/templates/header.php
// ============================================
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Coordenador Pedagógico</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="assets/css/dashboard.css">
    <style>
        :root {
            --primary-color: #c9a84c;
            --primary-dark: #b8973a;
            --secondary-color: #1a2332;
            --text-color: #2d3748;
            --text-muted: #718096;
            --border-color: #e2e8f0;
            --bg-light: #f8fafc;
            --shadow-md: 0 4px 20px rgba(0,0,0,0.08);
            --shadow-lg: 0 10px 40px rgba(0,0,0,0.12);
            --radius: 16px;
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        body { background: #f0f2f5; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .dashboard-container { display: flex; min-height: 100vh; }
        .main-content { flex: 1; margin-left: 260px; min-height: 100vh; background: #f0f2f5; }
        .content-area { padding: 25px 30px; }
        .btn-toggle-sidebar { display: none; background: none; border: none; font-size: 28px; cursor: pointer; color: var(--secondary-color); }
        
        @media (max-width: 992px) {
            .main-content { margin-left: 0; }
            .btn-toggle-sidebar { display: block; }
        }
        @media (max-width: 768px) {
            .content-area { padding: 15px; }
        }
    </style>
</head>
<body>