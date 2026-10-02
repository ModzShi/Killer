<section class="sr-banners" aria-label="Banners demonstrativos do jogo">
    <div class="sr-banners__stage" data-banner-stage>
        <a class="sr-banners__slide is-active" href="<?= app_escape(app_url('cadastrar/')) ?>" aria-label="Crie sua conta no Subway Run" aria-hidden="false">
            <img src="<?= app_escape(app_url('arquivos/har-art/corrida-cidade.jpg')) ?>" alt="Arte demonstrativa de corrida em trilhos com moedas">
        </a>
        <a class="sr-banners__slide" href="<?= app_escape(app_url('cadastrar/')) ?>" aria-label="Crie sua conta no Subway Run" aria-hidden="true" tabindex="-1">
            <img src="<?= app_escape(app_url('arquivos/har-art/corrida-premios.png')) ?>" alt="Arte demonstrativa de personagem e corrida">
        </a>
        <a class="sr-banners__slide" href="<?= app_escape(app_url('cadastrar/')) ?>" aria-label="Crie sua conta no Subway Run" aria-hidden="true" tabindex="-1">
            <img src="<?= app_escape(app_url('arquivos/har-art/personagens-corrida.png')) ?>" alt="Arte demonstrativa de personagem e corrida">
        </a>
    </div>
    <div class="sr-banners__controls">
        <span>SUBWAY RUN <small>• BANNERS DEMONSTRATIVOS</small></span>
        <div class="sr-banners__dots" role="group" aria-label="Selecionar banner">
            <button type="button" class="is-active" aria-label="Banner 1" aria-current="true"></button>
            <button type="button" aria-label="Banner 2"></button>
            <button type="button" aria-label="Banner 3"></button>
        </div>
    </div>
    <p class="sr-banners__notice">Estas artes são apenas demonstração; os valores e chamadas impressos não constituem oferta ou promessa de pagamento.</p>
</section>
