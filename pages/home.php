<?php
session_start();
require_once __DIR__ . '/../config.php';

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}
?>
<!DOCTYPE html>
<html lang="pt-PT">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=0.9, maximum-scale=5.0">
    <title>LabInSmile - Próteses Dentárias</title>

    <?php require_once __DIR__ . '/../inc/site_head.php'; ?>

    <style>
        .whatsapp-float{
            position: fixed;
            width: 60px;
            height: 60px;
            bottom: 25px;
            right: 25px;
            z-index: 9999;

            display: flex;
            align-items: center;
            justify-content: center;

            background: #25D366;
            border-radius: 50%;

            box-shadow: 0 4px 12px rgba(0,0,0,0.25);

            transition: 0.3s;
        }

        .whatsapp-float:hover{
            transform: scale(1.08);
        }

        .whatsapp-float img{
            width: 34px;
            height: 34px;
        }

        @media(max-width:768px){
            .whatsapp-float{
                width: 55px;
                height: 55px;
                bottom: 20px;
                right: 20px;
            }

            .whatsapp-float img{
                width: 30px;
                height: 30px;
            }
        }
    </style>
</head>

<body class="has-bg">

<?php require_once __DIR__ . '/../inc/site_header.php'; ?>

<main>

    <section class="why-choose-us">
        <div class="container">

            <div class="why-box">

                <h2>Porquê Escolher a LabInSmile</h2>

                <div class="expertise-grid">

                    <div class="expertise-card">
                        <p class="description">
                            Experiência de anos na indústria de próteses dentárias
                        </p>
                    </div>

                    <div class="expertise-card">
                        <p class="description">
                            Equipamento de última geração e atualizado regularmente
                        </p>
                    </div>

                    <div class="expertise-card">
                        <p class="description">
                            Equipa de profissionais altamente qualificados
                        </p>
                    </div>

                    <div class="expertise-card">
                        <p class="description">
                            Atenção ao detalhe em cada projeto
                        </p>
                    </div>

                    <div class="expertise-card">
                        <p class="description">
                            Prazos de entrega justos e confiáveis
                        </p>
                    </div>

                    <div class="expertise-card">
                        <p class="description">
                            Suporte total ao cliente durante o processo
                        </p>
                    </div>

                    <div class="expertise-card">
                        <p class="description">
                            Materiais premium de fornecedores conhecidos
                        </p>
                    </div>

                    <div class="expertise-card">
                        <p class="description">
                            Garantia de satisfação nos trabalhos realizados
                        </p>
                    </div>

                </div>

            </div>

        </div>
    </section>

    <section class="cta-section">
        <div class="container">

            <div class="cta-box">

                <h2>Próteses Dentárias Personalizadas</h2>

                <p>
                    Qualidade, precisão e acabamento artesanal com tecnologia avançada
                </p>

                <a href="contacto.php" class="btn-orcamento">
                    Pedir Orçamento
                </a>

            </div>

        </div>
    </section>

</main>

<footer>
    <div class="container">

        <strong>LabInSmile - Próteses Dentárias</strong>

        <p>Telefone: +351 967 544 606</p>

        <p>Email: labinsmile@gmail.com</p>

        <p>
            Morada: Avenida da República, Nº 74 1.º Andar Sala 1 Paredes
        </p>

        <p class="copyright">
            © 2026 LabInSmile. Todos os direitos reservados.
        </p>

    </div>
</footer>

<!-- BOTÃO FLUTUANTE WHATSAPP -->
<a
    href="https://wa.me/351967544606?text=Olá,%20gostaria%20de%20obter%20mais%20informações."
    class="whatsapp-float"
    target="_blank"
>

    <img
        src="/LabInSmile/images/whatsapp.png"
        alt="WhatsApp"
    >

</a>

</body>
</html>