<?php
session_start();
require_once 'config/database.php';

$erro = '';
$perfis_disponiveis = [];
$mostrar_selecao = false;
$usuario_temp = null;
$login_sucesso = false;
$nome_sucesso = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $login = trim($_POST['login'] ?? '');
    $senha = $_POST['senha'] ?? '';
    $perfil_escolhido = $_POST['perfil_escolhido'] ?? '';

    if (empty($login) || empty($senha)) {
        $erro = 'Preencha todos os campos!';
    } else {
        try {
            $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE email = ? OR telefone = ?");
            $stmt->execute([$login, $login]);
            $usuario = $stmt->fetch();

            if ($usuario) {
                if (password_verify($senha, $usuario['senha'])) {
                    if ($usuario['status'] == 'ativo') {

                        $perfil_principal = strtolower($usuario['perfil'] ?? 'usuario');
                        $multi_perfil = $usuario['multi_perfil'] ?? '';
                        $multi_perfil_array = array_map('trim', explode(',', $multi_perfil));

                        $perfis_disponiveis = [];
                        if (!empty($perfil_principal) && $perfil_principal != 'usuario') {
                            $perfis_disponiveis[] = $perfil_principal;
                        }
                        foreach ($multi_perfil_array as $p) {
                            if (!empty($p) && !in_array($p, $perfis_disponiveis)) {
                                $perfis_disponiveis[] = $p;
                            }
                        }
                        if (empty($perfis_disponiveis)) {
                            $perfis_disponiveis[] = 'usuario';
                        }

                        // Função auxiliar de redirect
                        $calcularRedirect = function($perfil_nome) {
                            switch ($perfil_nome) {
                                case 'admin': case 'administrador': return 'index.php';
                                case 'diretor':                     return 'diretor/dashboard.php';
                                case 'vice_diretor':                return 'vice_diretor/dashboard.php';
                                case 'coordenador': case 'pedagogico': return 'coordenador/dashboard.php';
                                case 'coordenador_pedagogico':      return 'coordenador_pedagogico/dashboard.php';
                                case 'supervisor': case 'supervisor_escolar': return 'supervisor/dashboard.php';
                                case 'orientador': case 'orientador_educacional': return 'orientador/dashboard.php';
                                case 'pedagogo':                    return 'pedagogo/dashboard.php';
                                case 'psicopedagogo':               return 'psicopedagogo/dashboard.php';
                                case 'professor': case 'docente':   return 'professor/dashboard.php';
                                case 'instrutor':                   return 'instrutor/dashboard.php';
                                case 'monitor':                     return 'monitor/dashboard.php';
                                case 'tutor':                       return 'tutor/dashboard.php';
                                case 'auxiliar_docente':            return 'auxiliar_docente/dashboard.php';
                                case 'aluno': case 'estudante':     return 'aluno/dashboard.php';
                                case 'bolsista':                    return 'bolsista/dashboard.php';
                                case 'estagiario':                  return 'estagiario/dashboard.php';
                                case 'secretario': case 'secretaria': return 'secretaria/dashboard.php';
                                case 'financeiro':                  return 'financeiro/dashboard.php';
                                case 'contador':                    return 'contador/dashboard.php';
                                case 'rh':                          return 'rh/dashboard.php';
                                case 'atendimento':                 return 'atendimento/dashboard.php';
                                case 'recepcionista':               return 'recepcionista/dashboard.php';
                                case 'vigilante':                   return 'vigilante/dashboard.php';
                                case 'motorista':                   return 'motorista/dashboard.php';
                                case 'aux_limpeza':                 return 'aux_limpeza/dashboard.php';
                                case 'jardineiro':                  return 'jardineiro/dashboard.php';
                                case 'manutencao':                  return 'manutencao/dashboard.php';
                                case 'porteiro':                    return 'porteiro/dashboard.php';
                                case 'merendeira':                  return 'merendeira/dashboard.php';
                                case 'aux_servicos':                return 'aux_servicos/dashboard.php';
                                case 'encarregado':                 return 'encarregado/dashboard.php';
                                case 'responsavel':                 return 'responsavel/dashboard.php';
                                case 'pai':                         return 'pai/dashboard.php';
                                case 'mae':                         return 'mae/dashboard.php';
                                case 'tutor_legal':                 return 'tutor_legal/dashboard.php';
                                case 'gerente':                     return 'gerente/dashboard.php';
                                case 'gestor':                      return 'gestor/dashboard.php';
                                default:                            return 'index.php';
                            }
                        };

                        // Se escolheu perfil válido
                        if (!empty($perfil_escolhido) && in_array($perfil_escolhido, $perfis_disponiveis)) {
                            $_SESSION['usuario_id'] = $usuario['id'];
                            $_SESSION['usuario_nome'] = $usuario['nome'];
                            $_SESSION['usuario_perfil'] = $perfil_escolhido;
                            $_SESSION['usuario_perfil_principal'] = $perfil_principal;
                            $_SESSION['usuario_multi_perfil'] = $multi_perfil;
                            $_SESSION['usuario_email'] = $usuario['email'];
                            $_SESSION['usuario_telefone'] = $usuario['telefone'] ?? '';
                            $_SESSION['usuario_perfis_disponiveis'] = $perfis_disponiveis;

                            $stmt = $pdo->prepare("UPDATE usuarios SET ultimo_acesso = NOW() WHERE id = ?");
                            $stmt->execute([$usuario['id']]);

                            $redirect = $calcularRedirect($perfil_escolhido);
                            $_SESSION['login_sucesso'] = true;
                            $_SESSION['login_nome'] = $usuario['nome'];
                            $_SESSION['login_redirect'] = $redirect;

                            header("Location: " . $redirect);
                            exit;
                        } elseif (count($perfis_disponiveis) > 1) {
                            $mostrar_selecao = true;
                            $usuario_temp = $usuario;
                            $perfis_disponiveis = array_unique($perfis_disponiveis);

                            $_SESSION['temp_usuario'] = [
                                'id' => $usuario['id'],
                                'nome' => $usuario['nome'],
                                'email' => $usuario['email'],
                                'telefone' => $usuario['telefone'] ?? '',
                                'perfis' => $perfis_disponiveis
                            ];
                        } else {
                            $perfil_nome = $perfis_disponiveis[0];
                            $_SESSION['usuario_id'] = $usuario['id'];
                            $_SESSION['usuario_nome'] = $usuario['nome'];
                            $_SESSION['usuario_perfil'] = $perfil_nome;
                            $_SESSION['usuario_perfil_principal'] = $perfil_principal;
                            $_SESSION['usuario_multi_perfil'] = $multi_perfil;
                            $_SESSION['usuario_email'] = $usuario['email'];
                            $_SESSION['usuario_telefone'] = $usuario['telefone'] ?? '';
                            $_SESSION['usuario_perfis_disponiveis'] = $perfis_disponiveis;

                            $stmt = $pdo->prepare("UPDATE usuarios SET ultimo_acesso = NOW() WHERE id = ?");
                            $stmt->execute([$usuario['id']]);

                            $redirect = $calcularRedirect($perfil_nome);
                            $_SESSION['login_sucesso'] = true;
                            $_SESSION['login_nome'] = $usuario['nome'];
                            $_SESSION['login_redirect'] = $redirect;

                            header("Location: " . $redirect);
                            exit;
                        }
                    } else {
                        $erro = '❌ Usuário ' . $usuario['status'] . '. Entre em contato com o administrador.';
                    }
                } else {
                    $erro = '❌ Senha incorreta!';
                }
            } else {
                $erro = '❌ Usuário não encontrado!';
            }
        } catch (Exception $e) {
            $erro = 'Erro no sistema: ' . $e->getMessage();
        }
    }
}

// Recupera dados temporários da sessão
if ($mostrar_selecao && isset($_SESSION['temp_usuario'])) {
    $temp = $_SESSION['temp_usuario'];
    $perfis_disponiveis = $temp['perfis'];
    $nome_usuario = $temp['nome'];
    $email_usuario = $temp['email'];
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - SoftGest Web</title>

    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Segoe UI', sans-serif;
            background: linear-gradient(135deg, #1a2332 0%, #2d3748 50%, #1a2332 100%);
            background-size: 400% 400%;
            animation: bgShift 20s ease infinite;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            overflow: hidden;
            position: relative;
        }
        @keyframes bgShift {
            0%,100% { background-position: 0% 50%; }
            50%     { background-position: 100% 50%; }
        }

        /* ============================================================
         * FUNDO ANIMADO — ESTRELAS
         * ============================================================ */
        .stars-bg { position: fixed; inset: 0; overflow: hidden; z-index: 0; pointer-events: none; }
        .stars-bg span {
            position: absolute; background: #fff; border-radius: 50%;
            opacity: 0.5; animation: starFloat linear infinite;
            box-shadow: 0 0 6px rgba(255,255,255,0.6);
        }
        @keyframes starFloat {
            0%   { transform: translateY(0) scale(1); opacity: 0.3; }
            50%  { opacity: 0.9; }
            100% { transform: translateY(-100vh) scale(0.5); opacity: 0; }
        }

        /* ============================================================
         * ORBES DE LUZ
         * ============================================================ */
        .orb { position: fixed; border-radius: 50%; filter: blur(80px); opacity: 0.35; pointer-events: none; z-index: 0; }
        .orb.o1 { width: 400px; height: 400px; background: radial-gradient(circle, #c9a84c, transparent 70%); top: -100px; left: -100px; animation: orbMove1 18s ease-in-out infinite; }
        .orb.o2 { width: 350px; height: 350px; background: radial-gradient(circle, #3498db, transparent 70%); bottom: -100px; right: -100px; animation: orbMove2 22s ease-in-out infinite; }
        .orb.o3 { width: 300px; height: 300px; background: radial-gradient(circle, #e74c3c, transparent 70%); top: 50%; left: 50%; animation: orbMove3 25s ease-in-out infinite; }
        @keyframes orbMove1 { 0%,100% { transform: translate(0, 0); } 50% { transform: translate(200px, 100px); } }
        @keyframes orbMove2 { 0%,100% { transform: translate(0, 0); } 50% { transform: translate(-200px, -80px); } }
        @keyframes orbMove3 { 0%,100% { transform: translate(-50%, -50%) scale(1); } 50% { transform: translate(-30%, -60%) scale(1.3); } }

        /* ============================================================
         * CONTAINER LOGIN
         * ============================================================ */
        .login-container {
            background: rgba(255,255,255,0.98);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 50px 40px;
            width: 100%;
            max-width: 520px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            position: relative;
            z-index: 2;
            animation: loginBoxEntrance 0.7s cubic-bezier(0.34, 1.56, 0.64, 1) both;
            border: 1px solid rgba(201,168,76,0.2);
        }
        @keyframes loginBoxEntrance {
            0%   { opacity: 0; transform: translateY(60px) scale(0.9); }
            100% { opacity: 1; transform: translateY(0) scale(1); }
        }

        /* LOGO */
        .logo { text-align: center; margin-bottom: 30px; }
        .logo h1 {
            color: #1a2332; font-size: 28px; font-weight: 700;
            animation: logoGlow 3s ease-in-out infinite alternate;
            display: inline-block;
        }
        @keyframes logoGlow {
            0%   { text-shadow: 0 0 0 rgba(201,168,76,0); transform: scale(1); }
            100% { text-shadow: 0 0 20px rgba(201,168,76,0.6), 0 0 40px rgba(201,168,76,0.3); transform: scale(1.02); }
        }
        .logo span { color: #c9a84c; }
        .logo p { color: #94a3b8; font-size: 14px; margin-top: 5px; animation: fadeSlideIn 0.8s ease 0.3s both; }
        @keyframes fadeSlideIn {
            0%   { opacity: 0; transform: translateY(10px); }
            100% { opacity: 1; transform: translateY(0); }
        }

        /* FORM */
        .form-group { margin-bottom: 20px; position: relative; animation: fieldSlideIn 0.5s ease both; }
        .form-group:nth-child(1) { animation-delay: 0.05s; }
        .form-group:nth-child(2) { animation-delay: 0.15s; }
        @keyframes fieldSlideIn {
            0%   { opacity: 0; transform: translateX(-20px); }
            100% { opacity: 1; transform: translateX(0); }
        }
        .form-group label { display: block; font-weight: 600; color: #1a2332; margin-bottom: 6px; font-size: 14px; }
        .form-group input, .form-group select {
            width: 100%; padding: 12px 15px;
            border: 2px solid #e2e8f0; border-radius: 10px;
            font-size: 15px;
            transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
            background: white;
        }
        .form-group input:focus, .form-group select:focus {
            outline: none; border-color: #c9a84c;
            box-shadow: 0 0 0 4px rgba(201, 168, 76, 0.15);
            transform: translateY(-2px) scale(1.01);
        }
        .input-group { position: relative; }
        .input-group input { padding-right: 45px; }
        .input-group .toggle-password {
            position: absolute; right: 12px; top: 50%;
            transform: translateY(-50%); cursor: pointer;
            color: #94a3b8; font-size: 18px;
            background: none; border: none;
            transition: all 0.3s;
        }
        .input-group .toggle-password:hover {
            transform: translateY(-50%) scale(1.2) rotate(15deg);
            color: #c9a84c;
        }

        /* BOTÃO LOGIN */
        .btn-login {
            width: 100%; padding: 14px;
            background: linear-gradient(135deg, #c9a84c, #b8973a, #c9a84c);
            background-size: 200% 200%;
            color: #1a2332; border: none; border-radius: 10px;
            font-size: 16px; font-weight: 700; cursor: pointer;
            transition: all 0.3s; position: relative; overflow: hidden;
            animation: btnGradient 4s ease infinite, fieldSlideIn 0.5s ease 0.25s both;
        }
        @keyframes btnGradient {
            0%,100% { background-position: 0% 50%; }
            50%     { background-position: 100% 50%; }
        }
        .btn-login:hover { transform: translateY(-3px) scale(1.02); box-shadow: 0 12px 30px rgba(201, 168, 76, 0.5); }
        .btn-login:active { transform: translateY(-1px) scale(0.98); }
        .btn-login::before {
            content: ''; position: absolute;
            top: 0; left: -100%; width: 100%; height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.5), transparent);
            transition: left 0.6s;
        }
        .btn-login:hover::before { left: 100%; }

        /* ALERTAS */
        .alert {
            padding: 12px 15px; border-radius: 10px;
            margin-bottom: 20px; font-size: 14px;
            border: 1px solid;
            animation: alertShake 0.5s ease;
        }
        @keyframes alertShake {
            0%,100% { transform: translateX(0); }
            20%     { transform: translateX(-8px); }
            40%     { transform: translateX(8px); }
            60%     { transform: translateX(-5px); }
            80%     { transform: translateX(5px); }
        }
        .alert-error { background: #fee2e2; color: #991b1b; border-color: #fecaca; }
        .alert-success { background: #d1fae5; color: #065f46; border-color: #a7f3d0; }

        /* ============================================================
         * SELEÇÃO DE PERFIL
         * ============================================================ */
        .perfil-selection {
            background: linear-gradient(135deg, #fef9e7 0%, #fdf4d8 100%);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            border: 2px dashed #c9a84c;
            animation: perfilSlideIn 0.5s ease both, glowPulse 3s ease-in-out infinite;
        }
        @keyframes perfilSlideIn {
            0%   { opacity: 0; transform: translateY(20px); }
            100% { opacity: 1; transform: translateY(0); }
        }
        @keyframes glowPulse {
            0%,100% { box-shadow: 0 0 0 0 rgba(201,168,76,0.15); }
            50%     { box-shadow: 0 0 15px 3px rgba(201,168,76,0.25); }
        }
        .perfil-selection .user-info { text-align: center; margin-bottom: 15px; }
        .perfil-selection .user-info .name { font-size: 16px; font-weight: 700; color: #8b6914; }
        .perfil-selection .user-info .email { font-size: 13px; color: #a68b3e; }

        .perfil-grid {
            display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 10px;
            margin: 15px 0; max-height: 300px; overflow-y: auto; padding: 5px;
        }
        .perfil-grid::-webkit-scrollbar { width: 6px; }
        .perfil-grid::-webkit-scrollbar-thumb { background: #c9a84c; border-radius: 10px; }

        .perfil-option {
            padding: 12px 10px;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            cursor: pointer; text-align: center;
            transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
            background: white;
        }
        .perfil-option:hover {
            border-color: #c9a84c;
            transform: translateY(-4px) scale(1.03);
            box-shadow: 0 8px 20px rgba(201, 168, 76, 0.25);
        }
        .perfil-option input[type="radio"] { display: none; }
        .perfil-option.selected {
            border-color: #c9a84c;
            background: linear-gradient(135deg, #f5edd6, #faf4e3);
            box-shadow: 0 0 0 3px rgba(201, 168, 76, 0.25);
            transform: translateY(-2px);
        }
        .perfil-option.selected .icon { animation: perfilIconBounce 0.5s ease; }
        @keyframes perfilIconBounce {
            0%,100% { transform: scale(1); }
            50%     { transform: scale(1.3) rotate(10deg); }
        }
        .perfil-option .icon { font-size: 28px; display: block; margin-bottom: 3px; }
        .perfil-option .label { font-size: 11px; font-weight: 600; color: #1a2332; line-height: 1.2; }

        .btn-selecionar {
            width: 100%; padding: 12px;
            background: linear-gradient(135deg, #c9a84c, #b8973a);
            color: #1a2332; border: none; border-radius: 10px;
            font-size: 15px; font-weight: 700; cursor: pointer;
            transition: all 0.3s; margin-top: 10px;
        }
        .btn-selecionar:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(201, 168, 76, 0.4);
        }
        .btn-selecionar:disabled { opacity: 0.5; cursor: not-allowed; }

        /* LINKS */
        .register-link { text-align: center; margin-top: 20px; color: #94a3b8; font-size: 14px; animation: fadeSlideIn 0.8s ease 0.5s both; }
        .register-link a { color: #c9a84c; text-decoration: none; font-weight: 600; transition: all 0.3s; display: inline-block; }
        .register-link a:hover { transform: translateX(6px); text-shadow: 0 0 10px rgba(201,168,76,0.6); }
        .version { text-align: center; margin-top: 20px; font-size: 12px; color: #94a3b8; animation: fadeSlideIn 0.8s ease 0.6s both; }
        .hint { font-size: 12px; color: #94a3b8; margin-top: 4px; }

        /* STATUS */
        .connection-status {
            text-align: center; margin-top: 15px;
            padding: 8px; border-radius: 8px;
            font-size: 12px; font-weight: 600;
            display: none;
        }
        .connection-status.online { display: block; background: #d1fae5; color: #065f46; animation: statusPulse 2s ease infinite; }
        .connection-status.offline { display: block; background: #fee2e2; color: #991b1b; }
        @keyframes statusPulse {
            0%,100% { box-shadow: 0 0 0 0 rgba(6,95,70,0.4); }
            50%     { box-shadow: 0 0 0 8px rgba(6,95,70,0); }
        }

        /* ============================================================
         * 💥 EXPLOSÃO DE ESTRELAS
         * ============================================================ */
        .boom-overlay {
            position: fixed; inset: 0;
            background: radial-gradient(circle, rgba(201,168,76,0.2) 0%, rgba(0,0,0,0.9) 70%);
            z-index: 9999; display: none;
            align-items: center; justify-content: center;
            flex-direction: column;
            animation: overlayFadeIn 0.4s ease both;
        }
        .boom-overlay.active { display: flex; }
        @keyframes overlayFadeIn { from { opacity: 0; } to { opacity: 1; } }

        .boom-msg {
            color: #fff; font-size: 2rem; font-weight: 800;
            text-align: center; z-index: 2;
            animation: boomMsgAppear 1s cubic-bezier(0.34, 1.56, 0.64, 1) both;
        }
        @keyframes boomMsgAppear {
            0%   { opacity: 0; transform: scale(0.3) translateY(30px); }
            60%  { opacity: 1; transform: scale(1.15); }
            100% { opacity: 1; transform: scale(1); }
        }
        .boom-msg .emoji {
            display: block; font-size: 4.5rem; margin-bottom: 15px;
            animation: boomEmoji 1.2s ease infinite;
        }
        @keyframes boomEmoji {
            0%,100% { transform: scale(1) rotate(0deg); }
            25%     { transform: scale(1.15) rotate(-12deg); }
            75%     { transform: scale(1.15) rotate(12deg); }
        }
        .boom-msg .name {
            display: block; font-size: 1.7rem; color: #c9a84c;
            margin-top: 10px;
            text-shadow: 0 0 20px rgba(201,168,76,0.9);
            animation: nameShine 2s ease infinite;
        }
        @keyframes nameShine {
            0%,100% { text-shadow: 0 0 20px rgba(201,168,76,0.9); }
            50%     { text-shadow: 0 0 40px rgba(201,168,76,1), 0 0 60px rgba(201,168,76,0.6); }
        }
        .boom-msg .sub {
            display: block; font-size: 1rem;
            color: #cbd5e0; margin-top: 15px; font-weight: 400;
        }
        .boom-msg .loader {
            display: inline-block; width: 40px; height: 40px;
            border: 4px solid rgba(201,168,76,0.3);
            border-top-color: #c9a84c;
            border-radius: 50%; margin-top: 25px;
            animation: loaderSpin 0.8s linear infinite;
        }
        @keyframes loaderSpin { to { transform: rotate(360deg); } }

        .star-particle {
            position: fixed; font-size: 2rem;
            pointer-events: none; z-index: 10000;
            animation: starBoom 2.2s ease-out forwards;
            filter: drop-shadow(0 0 10px currentColor);
        }
        @keyframes starBoom {
            0% { transform: translate(0, 0) scale(0) rotate(0deg); opacity: 0; }
            20% { transform: translate(var(--tx), var(--ty)) scale(1.5) rotate(180deg); opacity: 1; }
            80% { opacity: 1; }
            100% { transform: translate(calc(var(--tx) * 2), calc(var(--ty) * 2 + 100px)) scale(0.3) rotate(720deg); opacity: 0; }
        }

        .particle {
            position: fixed; width: 8px; height: 8px;
            border-radius: 50%; pointer-events: none; z-index: 9999;
            animation: particleBoom 1.5s ease-out forwards;
            box-shadow: 0 0 12px currentColor;
        }
        @keyframes particleBoom {
            0%   { transform: translate(0, 0) scale(1); opacity: 1; }
            100% { transform: translate(var(--tx), var(--ty)) scale(0); opacity: 0; }
        }

        .confetti {
            position: fixed; width: 12px; height: 12px;
            pointer-events: none; z-index: 9999;
            animation: confettiFall 3s linear forwards;
        }
        @keyframes confettiFall {
            0%   { transform: translateY(-20px) rotate(0deg); opacity: 1; }
            100% { transform: translateY(100vh) rotate(720deg); opacity: 0; }
        }

        @media (max-width: 480px) {
            .login-container { padding: 30px 20px; margin: 10px; }
            .perfil-grid { grid-template-columns: 1fr 1fr; max-height: 250px; }
            .perfil-option .icon { font-size: 24px; }
            .perfil-option .label { font-size: 10px; }
            .boom-msg { font-size: 1.4rem; }
            .boom-msg .emoji { font-size: 3rem; }
            .boom-msg .name { font-size: 1.2rem; }
        }
    </style>
</head>
<body>

<!-- FUNDO -->
<div class="stars-bg" id="starsBg"></div>
<div class="orb o1"></div>
<div class="orb o2"></div>
<div class="orb o3"></div>

<!-- OVERLAY DE BOOM -->
<div class="boom-overlay" id="boomOverlay">
    <div class="boom-msg">
        <span class="emoji">🎉</span>
        <span>Bem-vindo(a)!</span>
        <span class="name" id="boomName"></span>
        <span class="sub">A redirecionar para o teu painel...</span>
        <div class="loader"></div>
    </div>
</div>

<div class="login-container">
    <div class="logo">
        <h1>SoftGest <span>Web</span></h1>
        <p>Sistema de Gestão Escolar</p>
    </div>

    <?php if ($erro): ?>
        <div class="alert alert-error"><?= htmlspecialchars($erro) ?></div>
    <?php endif; ?>

    <?php if (isset($_GET['registrado'])): ?>
        <div class="alert alert-success">✅ Conta criada! Faça login para continuar.</div>
    <?php endif; ?>

    <?php if (isset($_GET['saiu'])): ?>
        <div class="alert alert-success">👋 Você saiu do sistema. Até logo!</div>
    <?php endif; ?>

    <form method="POST" id="formLogin">
        <div class="form-group">
            <label>📧📱 Email ou Telefone *</label>
            <input type="text" name="login" placeholder="admin@softgest.com ou 999999999"
                   value="<?= htmlspecialchars($_POST['login'] ?? '') ?>" required>
            <div class="hint">Digite seu email ou telefone cadastrado</div>
        </div>

        <div class="form-group">
            <label>🔒 Senha *</label>
            <div class="input-group">
                <input type="password" name="senha" id="senha" placeholder="••••••••" required>
                <button type="button" class="toggle-password" onclick="toggleSenha()">🙈</button>
            </div>
            <div class="hint">Senha padrão: admin123</div>
        </div>

        <?php if ($mostrar_selecao && isset($perfis_disponiveis) && count($perfis_disponiveis) > 1): ?>
        <div class="perfil-selection">
            <div class="user-info">
                <div class="name">👤 <?= htmlspecialchars($nome_usuario ?? '') ?></div>
                <div class="email">📧 <?= htmlspecialchars($email_usuario ?? '') ?></div>
                <div style="margin-top:5px;font-size:12px;color:#a68b3e;">
                    Selecione o perfil que deseja usar
                </div>
            </div>

            <div class="perfil-grid">
                <?php
                $perfil_icons = [
                    'admin'=>'👑','administrador'=>'👑','diretor'=>'🎯','vice_diretor'=>'🎯',
                    'coordenador'=>'📋','coordenador_pedagogico'=>'📋','supervisor'=>'👀',
                    'supervisor_escolar'=>'👀','orientador'=>'🧭','orientador_educacional'=>'🧭',
                    'pedagogo'=>'📚','psicopedagogo'=>'🧠','professor'=>'👨‍🏫','docente'=>'👩‍🏫',
                    'instrutor'=>'🎓','monitor'=>'📊','tutor'=>'📖','auxiliar_docente'=>'👩‍🏫',
                    'aluno'=>'🎒','estudante'=>'📖','bolsista'=>'💰','estagiario'=>'💼',
                    'secretario'=>'📄','secretaria'=>'📝','financeiro'=>'💰','contador'=>'🧮',
                    'rh'=>'👥','atendimento'=>'📞','recepcionista'=>'🛎️','vigilante'=>'🚨',
                    'motorista'=>'🚗','aux_limpeza'=>'🧹','jardineiro'=>'🌿','manutencao'=>'🔧',
                    'porteiro'=>'🚪','merendeira'=>'🍳','aux_servicos'=>'🧹','encarregado'=>'👨‍👧',
                    'responsavel'=>'👩‍👦','pai'=>'👨','mae'=>'👩','tutor_legal'=>'⚖️',
                    'gerente'=>'📊','gestor'=>'⚙️','usuario'=>'👤','visitante'=>'👋','convidado'=>'🎫'
                ];
                $perfil_labels = [
                    'admin'=>'Admin','administrador'=>'Admin','diretor'=>'Diretor','vice_diretor'=>'Vice-Dir.',
                    'coordenador'=>'Coordenador','coordenador_pedagogico'=>'Coord. Ped.','supervisor'=>'Supervisor',
                    'supervisor_escolar'=>'Superv. Esc.','orientador'=>'Orientador','orientador_educacional'=>'Orient. Ed.',
                    'pedagogo'=>'Pedagogo','psicopedagogo'=>'Psicoped.','professor'=>'Professor','docente'=>'Docente',
                    'instrutor'=>'Instrutor','monitor'=>'Monitor','tutor'=>'Tutor','auxiliar_docente'=>'Aux. Doc.',
                    'aluno'=>'Aluno','estudante'=>'Estudante','bolsista'=>'Bolsista','estagiario'=>'Estagiário',
                    'secretario'=>'Secretário','secretaria'=>'Secretaria','financeiro'=>'Financeiro','contador'=>'Contador',
                    'rh'=>'RH','atendimento'=>'Atendimento','recepcionista'=>'Rececionista','vigilante'=>'Vigilante',
                    'motorista'=>'Motorista','aux_limpeza'=>'Aux. Limp.','jardineiro'=>'Jardineiro','manutencao'=>'Manutenção',
                    'porteiro'=>'Porteiro','merendeira'=>'Merendeira','aux_servicos'=>'Aux. Serv.','encarregado'=>'Encarregado',
                    'responsavel'=>'Responsável','pai'=>'Pai','mae'=>'Mãe','tutor_legal'=>'Tutor Legal',
                    'gerente'=>'Gerente','gestor'=>'Gestor','usuario'=>'Usuário','visitante'=>'Visitante','convidado'=>'Convidado'
                ];

                foreach ($perfis_disponiveis as $perfil):
                    $icon = $perfil_icons[$perfil] ?? '📌';
                    $label = $perfil_labels[$perfil] ?? ucfirst(str_replace('_', ' ', $perfil));
                ?>
                    <label class="perfil-option" onclick="selectPerfil(this)">
                        <input type="radio" name="perfil_escolhido" value="<?= htmlspecialchars($perfil) ?>">
                        <span class="icon"><?= $icon ?></span>
                        <span class="label"><?= htmlspecialchars($label) ?></span>
                    </label>
                <?php endforeach; ?>
            </div>

            <button type="submit" class="btn-selecionar" id="btnSelecionar" disabled>
                🚀 Acessar com este perfil
            </button>
        </div>
        <?php endif; ?>

        <?php if (!$mostrar_selecao): ?>
        <button type="submit" class="btn-login" id="btnLogin">🔐 Entrar</button>
        <?php endif; ?>
    </form>

    <div id="connectionStatus" class="connection-status">
        <span id="statusIcon">🟢</span>
        <span id="statusText">Conectado</span>
    </div>

    <div class="register-link">
        Não tem conta? <a href="registrar.php">Registre-se</a>
    </div>

    <div class="version">v4.0 — Sistema de Gestão Escolar</div>
</div>

<script>
/* ============================================================
 * FUNDO DE ESTRELAS
 * ============================================================ */
(function() {
    const bg = document.getElementById('starsBg');
    for (let i = 0; i < 60; i++) {
        const s = document.createElement('span');
        const size = Math.random() * 3 + 1;
        s.style.width = size + 'px';
        s.style.height = size + 'px';
        s.style.left = Math.random() * 100 + '%';
        s.style.top = (Math.random() * 100 + 100) + '%';
        s.style.animationDuration = (Math.random() * 15 + 15) + 's';
        s.style.animationDelay = (Math.random() * 10) + 's';
        bg.appendChild(s);
    }
})();

/* TOGGLE SENHA */
function toggleSenha() {
    const input = document.getElementById('senha');
    const btn = document.querySelector('.toggle-password');
    if (input.type === 'password') {
        input.type = 'text';
        btn.textContent = '🙉';
    } else {
        input.type = 'password';
        btn.textContent = '🙈';
    }
}

/* AUTO-FOCUS */
document.addEventListener('DOMContentLoaded', function() {
    document.querySelector('input[name="login"]')?.focus();
});

/* SELEÇÃO DE PERFIL */
let selectedPerfil = null;

function selectPerfil(element) {
    document.querySelectorAll('.perfil-option').forEach(el => el.classList.remove('selected'));
    element.classList.add('selected');
    const radio = element.querySelector('input[type="radio"]');
    radio.checked = true;
    selectedPerfil = radio.value;

    const btn = document.getElementById('btnSelecionar');
    if (btn) {
        btn.disabled = false;
        const label = element.querySelector('.label').textContent;
        btn.innerHTML = '🚀 Acessar como ' + label;
    }
}

document.addEventListener('DOMContentLoaded', function() {
    const firstOption = document.querySelector('.perfil-option');
    if (firstOption) selectPerfil(firstOption);
});

/* ============================================================
 * 💥 EXPLOSÃO DE ESTRELAS
 * ============================================================ */
function boomEstrelas() {
    const emojis = ['⭐','🌟','✨','💫','🌠','🎇','🎆','⭐','🌟','💥','🎉','🎊'];
    const cores = ['#FFD700','#FFA500','#FFF8DC','#FFE4B5','#FFEFD5','#3498db','#e74c3c','#27ae60'];
    const cx = window.innerWidth / 2;
    const cy = window.innerHeight / 2;

    for (let i = 0; i < 50; i++) {
        const star = document.createElement('div');
        star.className = 'star-particle';
        star.textContent = emojis[Math.floor(Math.random() * emojis.length)];
        star.style.left = cx + 'px';
        star.style.top = cy + 'px';
        star.style.color = cores[Math.floor(Math.random() * cores.length)];

        const angulo = (Math.PI * 2 * i) / 50 + (Math.random() * 0.6);
        const distancia = 200 + Math.random() * 600;
        star.style.setProperty('--tx', Math.cos(angulo) * distancia + 'px');
        star.style.setProperty('--ty', Math.sin(angulo) * distancia + 'px');
        star.style.animationDelay = (Math.random() * 0.3) + 's';
        star.style.fontSize = (1 + Math.random() * 2.2) + 'rem';

        document.body.appendChild(star);
        setTimeout(() => star.remove(), 2600);
    }

    for (let i = 0; i < 100; i++) {
        const p = document.createElement('div');
        p.className = 'particle';
        p.style.left = cx + 'px';
        p.style.top = cy + 'px';
        p.style.background = cores[Math.floor(Math.random() * cores.length)];
        p.style.color = p.style.background;

        const angulo = Math.random() * Math.PI * 2;
        const distancia = 100 + Math.random() * 800;
        p.style.setProperty('--tx', Math.cos(angulo) * distancia + 'px');
        p.style.setProperty('--ty', Math.sin(angulo) * distancia + 'px');
        p.style.animationDelay = (Math.random() * 0.4) + 's';
        p.style.width = p.style.height = (4 + Math.random() * 10) + 'px';

        document.body.appendChild(p);
        setTimeout(() => p.remove(), 2200);
    }

    const coresConfete = ['#FFD700','#FF6B6B','#4ECDC4','#95E1D3','#F38181','#AA96DA','#FCBAD3','#FFFFD2','#c9a84c'];
    for (let i = 0; i < 120; i++) {
        const c = document.createElement('div');
        c.className = 'confetti';
        c.style.left = Math.random() * 100 + 'vw';
        c.style.top = '-20px';
        c.style.background = coresConfete[Math.floor(Math.random() * coresConfete.length)];
        c.style.animationDelay = (Math.random() * 1.5) + 's';
        c.style.animationDuration = (2 + Math.random() * 2) + 's';
        c.style.transform = 'rotate(' + (Math.random() * 360) + 'deg)';
        if (Math.random() > 0.5) c.style.borderRadius = '50%';
        document.body.appendChild(c);
        setTimeout(() => c.remove(), 5200);
    }

    const flash = document.createElement('div');
    flash.style.cssText = 'position:fixed;inset:0;background:radial-gradient(circle,#fff 0%,transparent 60%);z-index:9998;pointer-events:none;animation:overlayFadeIn 0.7s ease reverse both;';
    document.body.appendChild(flash);
    setTimeout(() => flash.remove(), 800);
}

function mostrarBoom(nome) {
    const overlay = document.getElementById('boomOverlay');
    document.getElementById('boomName').textContent = nome ? '👋 ' + nome : '';
    overlay.classList.add('active');

    boomEstrelas();
    setTimeout(boomEstrelas, 400);
    setTimeout(boomEstrelas, 900);

    if (navigator.vibrate) navigator.vibrate([100, 50, 100, 50, 200, 50, 300]);
}

/* MOSTRA SE VIER DO REGISTRO */
<?php if (isset($_GET['registrado']) || isset($_GET['saiu'])): ?>
document.addEventListener('DOMContentLoaded', function() {
    boomEstrelas();
    setTimeout(boomEstrelas, 400);
});
<?php endif; ?>

/* ANIMAÇÃO AO SUBMETER */
document.getElementById('formLogin')?.addEventListener('submit', function() {
    const btn = this.querySelector('button[type="submit"]');
    if (btn && !btn.disabled) {
        btn.innerHTML = '⏳ A entrar...';
        btn.style.opacity = '0.8';
    }
});

/* STATUS DE CONEXÃO */
const statusDiv = document.getElementById('connectionStatus');
const statusIcon = document.getElementById('statusIcon');
const statusText = document.getElementById('statusText');

function updateConnectionStatus(online) {
    if (online) {
        statusDiv.className = 'connection-status online';
        statusIcon.textContent = '🟢';
        statusText.textContent = 'Conectado';
        statusDiv.style.display = 'block';
    } else {
        statusDiv.className = 'connection-status offline';
        statusIcon.textContent = '🔴';
        statusText.textContent = 'Offline - Verifique sua conexão';
        statusDiv.style.display = 'block';
    }
}

updateConnectionStatus(navigator.onLine);
window.addEventListener('online', () => updateConnectionStatus(true));
window.addEventListener('offline', () => updateConnectionStatus(false));

setInterval(function() {
    fetch('/softgest_web/api/sync.php?acao=ping', { method: 'GET', headers: { 'Cache-Control': 'no-cache' } })
    .then(r => updateConnectionStatus(r.ok))
    .catch(() => updateConnectionStatus(false));
}, 30000);

console.log('✨ Login — animações ativas!');
</script>
</body>
</html>
