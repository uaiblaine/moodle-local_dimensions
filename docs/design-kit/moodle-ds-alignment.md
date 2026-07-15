# Alinhamento ao Moodle Design System (Camada 3)

Captura das boas práticas e tokens do Moodle Design System (MDS) e como replicá-las no
nosso kit, **apontando onde divergem** da nossa interpretação anterior (estética Anthropic/CDS).
Régua: **Bootstrap/Boost (Mustache) hoje → componentes React do MDS quando o Moodle 5.3 LTS sair**.

## Fontes

- `github.com/moodlehq/design-system` — tokens em `tokens/css/*.css` (Style Dictionary, origem ZeroHeight).
- Component Library `componentlibrary.moodle.com` — referência Bootstrap/Boost para Mustache.
- `design.moodle.com` (Penpot) — **não extraível como dado** (só-JS); coberto pelos dois acima.

## Arquitetura do MDS (a boa prática estrutural #1)

Modelo em **duas camadas**, que devemos espelhar para o port React ser um *rename*, não um redesenho:

1. **Primitivos** — `--mds-color-{hue}-{50..900}`, `--mds-scale-{0..1800}`, `--mds-typography-*`. Valores crus.
2. **Semânticos** — o que os componentes consomem. Nunca consumir primitivo direto.

Eixos semânticos do MDS:

- `--mds-bg-surface-{default,subtle,strong}` · `--mds-text-{default,muted,subtle,emphasis,inverse}`
- `--mds-bg-interactive-{primary,secondary,danger}-{default,hover,active,disabled,default-light}` — **fills sólidos com estados**.
- `--mds-bg-feedback-{primary,info,success,warning,danger,secondary}-{default,light,subtle}` — **tints de status**.
- `--mds-border-{default,subtle,feedback-*,interactive-*}` · `--mds-focus-{default,danger}`
- `--mds-border-radius-{xs..xxl,pill}` · `--mds-spacing-{xxs..xxl}` · `--mds-stroke-weight-{sm..xxl}`
- Tipografia: `--mds-font-size-{headings-1..6,paragraph-default/lead/small}` · `--mds-font-weight-*` · `--mds-line-height-*`
- Sombras compostas: `--mds-{color,blur,offset}-{sm,md,lg}` · ícones `--mds-icons-{xxs..xxxl}`
- Cor por tipo de atividade: `assessment`=pink, `collaboration`=indigo, `communication`=orange, `file/resource`=cyan, `interactive`=red.

## Valores concretos (light)

| Eixo | Token semântico | Valor (primitivo) |
| --- | --- | --- |
| Superfície base | `bg-surface-default` | `#ffffff` |
| Superfície sutil | `bg-surface-subtle` | gray-100 `#f8f9fa` |
| Superfície forte | `bg-surface-strong` | gray-200 `#e9ecef` |
| Borda padrão | `border-default` | gray-300 `#dee2e6` |
| Texto padrão | `text-default` | gray-900 `#1d2125` |
| Texto muted | `text-muted` | gray-600 `#6a737b` |
| **Primary** | `bg-interactive-primary-default` | blue-500 `#0f6cbf` (hover blue-600 `#0c5699`, active blue-700 `#094173`) |
| Info | `bg-feedback-info-default` | cyan-600 `#006778` (tint cyan-100 `#cce6ea`) |
| Success | `bg-feedback-success-default` | green-500 `#357a32` |
| Warning | `bg-feedback-warning-default` | yellow-500 `#f0ad4e` |
| Danger | `bg-interactive-danger-default` | red-500 `#ca3120` |
| Foco | `focus-default` | = primary blue |

Escala (`--mds-scale-*`): 100=4px, 200=6px, 300=8px, 400=12px, 500=14px, 600=16px, 700=20px,
800=24px, 1000=32px, 1200=48px, 1800=50rem (pill).

Radius: xs=4px, sm=6px, **md=8px**, lg=12px, xl=16px, xxl=32px, pill=50rem.
Stroke: sm=**1px**, md=2px, lg=3px.
Tipografia: **Noto Sans** / Menlo; h1=2.5rem … h6=1rem; parágrafo 1rem (lead 1.25, small 0.875);
pesos light 300 / regular 400 / medium 500 / semibold 600 / bold 700; margem heading=8px, parágrafo=16px.
Sombras: cor sm/md/lg = preto 8%/15%/17%; md ≈ `0 8px 16px rgba(0,0,0,.15)`.

## Mapeamento: MDS → Boost/Bootstrap (hoje) → React do MDS (Moodle 5.3 LTS)

| MDS semântico | Boost/Bootstrap 5 (Mustache hoje) | React do MDS (Moodle 5.3 LTS) |
| --- | --- | --- |
| `bg-interactive-primary-*` | `$primary` / `.btn-primary` | `--mds-bg-interactive-primary-*` |
| `bg-interactive-secondary-*` | `$secondary` / `.btn-secondary` | idem |
| `bg-interactive-danger-*` | `$danger` / `.btn-outline-danger` | idem |
| `bg-feedback-{info,success,warning,danger}` | `.alert-{info,success,warning,danger}`, `.badge` | `--mds-bg-feedback-*` |
| `bg-surface-{default,subtle,strong}` | `$body-bg` / `$gray-100` / `$gray-200` | `--mds-bg-surface-*` |
| `text-{default,muted}` | `$body-color` / `.text-muted` | `--mds-text-*` |
| `border-default` | `$border-color` (gray-300) | `--mds-border-default` |
| radius `md` | `$border-radius` (.375rem Boost ≈ 6px) | `--mds-border-radius-md` |
| focus | `$focus-ring-*` / `:focus-visible` | `--mds-focus-default` |

> As grays do MDS **são** as grays do Bootstrap — então em Boost dá pra apoiar em `$gray-*`/`$primary`
> nativos; não inventar CSS vars novas no tema. Os componentes Mustache usam classes Bootstrap (`.btn`,
> `.alert`, `.badge`, `.card`, `.nav-tabs`, `.form-*`) — ver Component Library.

## Divergências da nossa interpretação anterior

| Aspecto | Nossa interpretação (CDS/Anthropic) | Moodle DS | Recomendação |
| --- | --- | --- | --- |
| Superfícies | neutros **quentes** (`#f7f6f3`, `#f0eee9`) | grays **frios** Bootstrap | adotar grays Bootstrap |
| Primary/accent | `#185fa5` (accent único) | blue-500 `#0f6cbf` + estados | adotar azul Moodle + hover/active |
| Info | **fundido** no accent (azul) | **cyan** separado | separar info (cyan) do primary |
| Success | `#0f6e56` (teal) | green `#357a32` | adotar verde Moodle |
| Warning / Danger | `#854f0b` / `#a32d2d` | yellow `#f0ad4e` / red `#ca3120` | adotar os do Moodle |
| Borda | hairline **0.5px** | **1px** (`stroke-sm`) | usar 1px (0.5px era estética Anthropic) |
| Radius | **8px único** | escala xs..xxl + pill | adotar a escala (md=8px já bate) |
| Estados | só tints (`bg-accent`) | **default/hover/active/disabled** sólidos | adicionar fills interativos + estados |
| feedback vs interactive | **conflados** | **separados** | adotar a separação |
| Elevação | nenhuma (flat) | sombras compostas sm/md/lg | adicionar tokens de elevação |
| Foco | **ausente** | `focus-default`/`focus-danger` | adicionar anel de foco (WCAG 2.2 AA) |
| Fonte | Anthropic Sans | **Noto Sans** | usar Noto Sans / stack do Boost |
| Nomenclatura | flat (`--surface-2`) | semântica `--mds-*` (primitive→semantic) | espelhar a taxonomia → React = rename |

## Boas práticas capturadas (além do visual)

1. **Token em duas camadas**; componentes só consomem semântico. Migração React = trocar implementação.
2. **interactive (sólido + estados) × feedback (tint)** — não usar um pelo outro.
3. **Cobertura de estados**: default/hover/active/disabled **+ foco** para todo interativo.
4. **T-shirt sizing** (xxs..xxxl) sobre escala numérica para espaço/raio/ícone.
5. **WCAG 2.2 AA** — três pilares do Component Library: *links*, *contraste de cor*, *acesso por teclado*.
   Texto sobre tint usa o stop **800** da mesma família; texto sobre fill sólido usa branco.
6. **Bootstrap/Boost é o substrato** hoje; Component Library é a referência canônica do Mustache.
7. **Cor por tipo de atividade** (assessment/collaboration/communication/file/interactive) — aproveitável
   nas tags de framework/atividade do `master-detail` e do `MOD.LINKS`.

## Componentes — guidance Boost capturada (Component Library)

> O Penpot (design.moodle.com) é só-JS e **não foi navegável**; estas regras vêm da Component Library
> (`componentlibrary.moodle.com`), que documenta o mesmo em termos Bootstrap/Boost — o substrato do Mustache.

**Botões** (`moodle/components/buttons`)
- Hierarquia: **um único `.btn-primary` por componente/tela**; `.btn-secondary` p/ cancelar/controles persistentes;
  `.btn-danger` destrutivo; `.btn-outline-secondary` p/ filtros/toggles; `.btn-subtle-*` (intermediário); `.btn-icon` só-ícone.
- **Ação perigosa:** estilizar o **Cancelar como primário** p/ encorajar o default seguro → reflete no `MOD.DELPLANS`.
- Rótulo específico ("Salvar", "Excluir"), nunca "OK/Sim". Tamanhos `.btn-sm`/`.btn-lg`. Renderer PHP `single_button()`.

**Ícones** (`moodle/components/moodle-icons`)
- **FontAwesome 6.7.2**; em Mustache use `{{#pix}} i/edit, core {{/pix}}` (mapeado em `icon_system_fontawesome.php`), não `<i class="fa">` cru.
- Decorativo → `aria-hidden="true"`; significativo → `aria-label`/texto `visually-hidden`. `fa-fw` p/ largura fixa.

**Activity icons** (`moodle/components/activityicons`) — cor por **propósito**, útil nas tags de framework/atividade do `master-detail`/`MOD.LINKS`:
- administration `#da58ef` · assessment `#f90086` · collaboration `#5b40ff` · communication `#eb6200` · interactivecontent `#8d3d1b` · content `#0099ad`.
- Classe `activity_icon` + classe de propósito; vars `$activity-icon-*-bg`; `set_colourize(false)` desliga; customizável no SCSS do Boost.

**Nav pills** = **nosso seletor de contexto** (`BAR-CTX`). Tokens `--mds-bg-nav-pill-{hover,pressed,selected}` = **gray-200/300/200**:
- o toggle Sistema/Categoria é **nav-pill com selecionado em cinza**, **não** um `.btn-primary` azul. (Corrigido na `hierarchy-nav`.)
- `role="tablist"`, `.nav-link.active`, `aria-selected`; alcançável por teclado.

## Restrições de plataforma

Antes de qualquer escolha estética, duas coisas decidem o que **dá pra construir** aqui: o que o
stylelint do CI aceita e o que o Bootstrap 4 do Moodle 4.5 entende. Registradas uma vez neste
documento em vez de redescobertas a cada superfície — as duas já custaram retrabalho.

**Boundary do stylelint do CI**

O CI roda o config do **core** (`.stylelintrc` na raiz do Moodle), não o `.stylelintrc.json` do
plugin — que não carrega nenhuma das regras abaixo. Daí a impressão de boundary invisível: o
`npx stylelint` local passa e o CI falha. É reproduzível, apontando o stylelint pro config do core
(da raiz do Moodle):

```sh
npx stylelint --config .stylelintrc public/local/dimensions/styles.css
```

| Regra (core `.stylelintrc`) | Rejeita | Saída |
| --- | --- | --- |
| `declaration-no-important` | qualquer `!important` — e `keyframe-declaration-no-important` fecha o mesmo dentro de `@keyframes` | quando um utilitário Bootstrap no markup (`.d-flex`, `.d-block`, ambos `!important`) briga com uma propriedade que precisamos alternar, **tirar o utilitário do template** e assumir a propriedade numa classe do plugin |
| `csstree/validator` (`stylelint-csstree-validator` 3.x) | `clamp()`/`min()`/`max()` **onde se espera um comprimento** — a gramática é antiga e não os conhece. Verificado falhando em `height`, `min-height`, `max-height`, `width`, `max-width`, `font-size`, `padding`, `margin`, `gap`, `flex-basis` → *"Invalid value"* | `calc()` **passa** (e `minmax()` em grid também); no lugar do `clamp()`, par `height` + `min-height`/`max-height` |
| `time-min-milliseconds: 100` | qualquer duração `< 100ms` | é o **piso da escala de motion** — `--mds-motion-fast` (150ms) já está acima; não descer abaixo de 100ms atrás de "mais snappy" |

> As três são **erro**, não warning. E o `csstree/validator` não é só de `height`: pega qualquer
> propriedade de comprimento — formular como "só height-like" subestima o alcance.

**Bootstrap 4 (Moodle 4.5) × Bootstrap 5 (5.x)**

O plugin suporta 4.5 → 5.2, e **4.5 é Bootstrap 4**. As *classes* do BS5 têm ponte no 4.5
(`form-select` etc.), mas os **data attributes do JS não**: o data-API do BS4 escuta `data-toggle`,
o do BS5 escuta `data-bs-toggle`. Componente ligado por markup (dropdown etc.) precisa dos **dois**
lado a lado, e das duas classes de alinhamento (`dropdown-menu-right dropdown-menu-end`) — como em
`participants_manager.mustache` e `plans.mustache`. Seletor de JS idem: casar os dois.

| Fato (verificado em `v4.5.12` × checkout 5.1) | Consequência de desenho |
| --- | --- |
| 4.5 **não define nenhuma custom property `--bs-*` de modal** (`--bs-modal-width`, `--bs-modal-margin`…) — seu `_modal.scss` é só variável SCSS | nunca dimensionar modal por var do BS5. Usar as classes do próprio Bootstrap — `modal-xl` é **idêntico em 4 e 5** (800px no `lg`, 1140px no `xl`) — ou dar fallback: `var(--bs-modal-margin, 1.75rem)` (= `$modal-dialog-margin-y-sm-up` do 4.5) |
| BS5 (`EventHandler.trigger`) dispara **os dois** eventos, jQuery e nativo; BS4 dispara **só jQuery** | um listener **jQuery** cobre os dois ramos; `addEventListener` cobre só o 5.x |
| `lib/amd/src/first.js` faz `window.jQuery = $`, então o BS5 **sempre** pega seu caminho jQuery | o bind por jQuery não é gambiarra de compatibilidade: é o caminho que o core garante nos dois |

O custo de ignorar isso já foi pago: dois defeitos **silenciosos** no 4.5, corrigidos em `f84d30a`.
O `context.js` casava as abas só por `data-bs-toggle` e escutava evento nativo — no 4.5 o seletor
não casava nada e o evento não chegava, então o contador da contextbar nunca seguia a aba, o
`saveNav` nunca rodava e o restore da aba salva nunca disparava. E o modal de participantes
dimensionava a si mesmo com `--bs-modal-width`: indefinida no 4.5, ele encolhia pro `$modal-md`
(**500px**) com quatro abas e grids dentro. Nenhum dos dois quebra visivelmente no 5.x — só somem
no branch mais antigo, que é exatamente onde ninguém olha.

## Reflexo no kit

- `tokens.html` reescrito para o modelo MDS (semântico, valores Moodle, estados, foco, elevação, escalas) — feito.
- Novo card `states.html` (estados interativos + foco) — feito.
- Os **8 componentes** migrados para os tokens MDS (grays Bootstrap, **primary azul sólido**, Noto Sans, 1px,
  **nav-pill cinza** no seletor de contexto, **info=cyan / success=green** na cohort-assign), com os nomes legados
  mantidos como **aliases deprecados → `--mds-*`** para migração incremental — feito.
- Os `screens/` re-skinados: painel **as-is intocado**, painel **to-be em tokens MDS** (override escopado em
  `.screens > .panel:last-child`, claro/escuro) — feito. **Camada 3 completa**; resta a sua revisão e o port quando o Moodle 5.3 LTS sair.
