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
</head>

<body class="has-bg">

<?php require_once __DIR__ . '/../inc/site_header.php'; ?>

<main>

    <section class="home-hero">
        <div class="container home-hero-inner">
            <div class="home-hero-image">
                <img src="/LabInSmile/images/fundo estatico.jpeg" alt="LabInSmile">
            </div>

            <div class="home-hero-text">
                <p>Na LabInSmile acreditamos que cada detalhe faz a diferença.</p>
                <p>Os nossos trabalhos são desenvolvidos maioritariamente de forma artesanal, com precisão, dedicação e atenção ao detalhe em cada etapa.</p>
                <p>Valorizamos a estética, o conforto e a naturalidade para garantir resultados de elevada qualidade.</p>
                <p>O nosso compromisso é entregar soluções fiáveis e acabamentos à altura de cada sorriso.</p>
            </div>
        </div>
    </section>

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
