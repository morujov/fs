{{--
    Контекстный фон витрины. Декоративный, aria-hidden, кликов не ловит.
    Мотив: «телефонная сетка» + бледные цифры-лесенка (escalera) и их зеркало
    (capicúa) — тема «красивого номера», не крича. Inline-SVG (а не картинка):
    ноль внешних запросов (инвариант №11), цвета из токенов через var(),
    тёмную тему подхватит сам, когда её введём. Стили слоя — .bg-figure в
    app.css (fixed, z-10 вниз, маска-фейд к низу).
--}}
<div class="bg-figure" aria-hidden="true">
    <svg xmlns="http://www.w3.org/2000/svg" preserveAspectRatio="xMidYMin slice">
        <defs>
            <pattern id="num-grid" width="88" height="88" patternUnits="userSpaceOnUse">
                <path d="M0 44H88M44 0V88" fill="none" stroke="var(--c-line)" stroke-width="1"/>
                <g fill="var(--c-accent)" opacity="0.05"
                   font-family="ui-monospace, monospace" font-size="13" font-weight="700" text-anchor="middle">
                    <text x="22" y="27">1</text><text x="66" y="27">2</text>
                    <text x="22" y="71">3</text><text x="66" y="71">2</text>
                </g>
            </pattern>
        </defs>
        <rect width="100%" height="100%" fill="url(#num-grid)"/>
    </svg>
</div>
